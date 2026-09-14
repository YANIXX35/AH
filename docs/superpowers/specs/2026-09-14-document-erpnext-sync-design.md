# Documents (KYC + OCR) — Synchronisation ERPNext en production

## Contexte et objectif

Contrairement aux sous-projets précédents (Facturation, Stock, Paie), le module **Gestion des documents** de PME360 n'a **aucun équivalent fonctionnel** côté ERPNext (pas de OCR natif, pas de workflow de conformité KYC). Le but ici n'est donc pas de reproduire une logique métier équivalente, mais d'utiliser le mécanisme générique de **pièces jointes** d'ERPNext (`File` doctype) pour y **miroir/archiver** les documents déjà traités par PME360, afin qu'un comptable consultant ERPNext directement y retrouve aussi les justificatifs.

**Deux sources locales distinctes**, toutes deux gardées inchangées :
- `KycDocument` (`app/Models/KycDocument.php`) — attestation DFE/NIF, Registre de commerce, etc., approuvées via `AdminComplianceKycController::approve()`.
- `AccountingDocument` (`app/Models/AccountingDocument.php`) — factures/reçus scannés, validés (avec génération d'une `AccountingEntry` locale) via `AccountingDocumentController::storeValidation()`.

## Découverte technique vérifiée empiriquement

- ERPNext expose un endpoint générique d'upload : `POST /api/method/upload_file` (multipart/form-data, pas JSON), champs `file` (binaire), `doctype`, `docname`, `is_private`. Testé en direct : un fichier attaché à la Company de test (`Test Inscription E2E 1789166589 #15`) crée bien un document `File` (`is_private: 1`, `attached_to_doctype: "Company"`, `attached_to_name: "..."`, `file_url: "/private/files/..."`).
- Cet endpoint est structurellement différent des autres appels `ErpNextClient` existants (`get`/`post`/`postForm`/`put`, tous JSON ou form-urlencoded) — nécessite une nouvelle méthode privée dédiée au multipart.
- Les deux modèles locaux stockent déjà leurs fichiers sur le disque `public` (`Storage::disk('public')`) : `KycDocument` via `AdminComplianceKycController.php:68` (`$file->store('kyc-documents', 'public')`), `AccountingDocument` via `AccountingController.php:2731`/`2982` (`$file->store('accounting-documents', 'public')`) — le contenu binaire est donc lisible via `Storage::disk('public')->get($storedPath)`.

## Décisions verrouillées

- **Simplification volontaire de la cible d'attachement** : les deux types de documents sont attachés à la **Company** de la PME (`doctype: 'Company', docname: $pme->erpnext_company_name`), jamais à un document transactionnel spécifique (Sales Invoice, Journal Entry) — même repli pragmatique que le compte de trésorerie fixe `5711` déjà utilisé pour la Facturation/Paie. Raison : rien ne garantit qu'une `AccountingEntry` générée depuis un `AccountingDocument` ait elle-même un miroir ERPNext (les écritures comptables générales ne sont **pas encore synchronisées** — seules Facturation/Stock/Paie le sont), donc cibler un document transactionnel précis ajouterait une dépendance fragile hors scope.
- **Déclenchement KYC** : dans `AdminComplianceKycController::approve()`, juste après le bulk `KycDocument::query()->where('user_id', $user->id)->update([...])`, dispatch `SyncKycDocumentsToErpNext::dispatch($user)` — un seul job par PME qui parcourt tous ses `KycDocument` avec `status: 'approved'`.
- **Déclenchement OCR** : dans `AccountingDocumentController::storeValidation()`, juste après `$this->createEntryFromDocument($document);`, dispatch `SyncAccountingDocumentToErpNext::dispatch($document)`.
- **Traçabilité** : deux nouvelles tables, une par source, copie conforme des tables de sync précédentes :
  - `kyc_document_erpnext_syncs` (`kyc_document_id` FK unique, `status`, `erpnext_file_name`, `last_error`, `last_synced_at`, `raw_response`).
  - `accounting_document_erpnext_syncs` (`accounting_document_id` FK unique, `status`, `erpnext_file_name`, `last_error`, `last_synced_at`, `raw_response`).
- **Idempotence** : le job KYC traite **tous** les documents approuvés du user à chaque déclenchement (pas seulement ceux de l'approbation en cours, puisque le bulk update ne dit pas lesquels sont "nouveaux") ; chaque document individuel garde son propre statut de sync (`firstOrCreate` + `if ($sync->status === 'synced') return;` par document, à l'intérieur d'une boucle) — évite tout doublon d'upload en cas de ré-approbation.
- **Résilience** : catch systématique de toute `\Throwable`, jamais de blocage de l'approbation KYC ou de la validation OCR — garde identique aux jobs précédents (indispensable, `QUEUE_CONNECTION=sync` en production). Garde aussi : PME non provisionnée (`erpnext_company_name` vide) → `failed` immédiat sans appel API.
- **Hors scope explicite** :
  - Pas de lecture/traitement du fichier côté ERPNext (juste un stockage, pas un pipeline OCR).
  - Pas de synchronisation des `AccountingEntry` elles-mêmes vers ERPNext dans ce sous-projet (gap déjà identifié séparément, pas traité ici).
  - Pas de sens inverse (ERPNext → PME360).
  - Pas d'interface admin de suivi dédiée (comme pour les sous-projets précédents).

## Architecture

### Nouvelle méthode `ErpNextClient`

```php
public function uploadFileForPme(User $pme, string $localDisk, string $storedPath, string $originalName): array
```
Lit le contenu via `Storage::disk($localDisk)->get($storedPath)`, appelle une nouvelle méthode privée `uploadFile()` (multipart, distincte de `post()`/`postForm()`), avec `doctype: 'Company'`, `docname: $pme->erpnext_company_name`, `is_private: 1`. Retourne le document `File` créé (`{name, file_name, file_url, ...}`).

### Deux nouvelles tables + modèles

`KycDocumentErpNextSync` / `AccountingDocumentErpNextSync`, structure calquée sur `PayrollErpNextSync` (avec `protected $table` explicite dans les deux cas).

### Deux nouveaux Jobs

`SyncKycDocumentsToErpNext` (reçoit un `User`, boucle sur ses `KycDocument` approuvés) et `SyncAccountingDocumentToErpNext` (reçoit un `AccountingDocument`), tous deux appellent `uploadFileForPme()`.

### Modifications de contrôleurs

`AdminComplianceKycController::approve()` et `AccountingDocumentController::storeValidation()` — un seul dispatch ajouté chacun, aucune autre ligne modifiée.

## Tests (vérification manuelle, pas de suite PHPUnit exécutable localement — PHP 8.2 vs 8.4 requis)

1. Sur une PME provisionnée, soumettre un document KYC (ex: Registre de commerce), l'approuver via le dashboard admin conformité → vérifier sur ERPNext qu'un `File` privé apparaît, attaché à la Company de cette PME. Vérifier `kyc_document_erpnext_syncs` marque `synced`.
2. Importer et valider un document comptable (OCR) → vérifier qu'un `File` apparaît aussi sur ERPNext pour la même Company. Vérifier `accounting_document_erpnext_syncs` marque `synced`.
3. Ré-approuver les mêmes documents KYC une seconde fois (si le flux le permet) → vérifier qu'aucun doublon de `File` n'est créé (le job saute les documents déjà `synced`).
4. Sur une PME **non provisionnée**, approuver un KYC / valider un document OCR → vérifier que le flux local continue de fonctionner normalement, et que la ligne de sync correspondante marque `failed` avec un message clair.

## Fichiers concernés

- `app/Services/ErpNextClient.php` (modifié — nouvelle méthode privée `uploadFile()` + méthode publique `uploadFileForPme()`)
- `database/migrations/YYYY_MM_DD_HHMMSS_create_kyc_document_erpnext_syncs_table.php` (nouveau)
- `database/migrations/YYYY_MM_DD_HHMMSS_create_accounting_document_erpnext_syncs_table.php` (nouveau)
- `app/Models/KycDocumentErpNextSync.php` (nouveau)
- `app/Models/AccountingDocumentErpNextSync.php` (nouveau)
- `app/Jobs/SyncKycDocumentsToErpNext.php` (nouveau)
- `app/Jobs/SyncAccountingDocumentToErpNext.php` (nouveau)
- `app/Http/Controllers/AdminComplianceKycController.php` (modifié — dispatch dans `approve()`)
- `app/Http/Controllers/AccountingDocumentController.php` (modifié — dispatch dans `storeValidation()`)

Ne pas toucher : `KycDocument`, `AccountingDocument`, `AccountingEntry`, le pipeline OCR (`OcrPipelineService`/`OcrService`), les vues existantes.
