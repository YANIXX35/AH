# Comptabilité — ERPNext comme point de saisie unique pour les écritures manuelles

## Contexte et objectif

Troisième module basculé sur le patron "ERPNext = point de saisie, PME360 = affichage", après le Stock et la Facturation. Périmètre confirmé avec l'utilisateur, après exploration du code réel :

- **Cible** : la saisie manuelle d'écriture comptable (`AccountingController::storeEntry()`, bouton "Nouvelle écriture" sur `/accounting`) — un débit/crédit simple, un seul montant.
- **Hors scope, explicitement exclu** : le pipeline OCR (`AccountingDocumentController::storeValidation()` → `createEntryFromDocument()`) reste **entièrement local** — c'est la vraie valeur ajoutée de PME360, aucun rapport avec ERPNext.
- **Hors scope, découvert en explorant** : le "Rapprochement bancaire" de production (`AccountingController::bankReconciliation()`) n'est **pas** un formulaire de création — c'est un rapport de cohérence en lecture seule (comparaison Trésorerie vs écritures classe 5), rien à inverser.

## Découverte technique clé

`AccountingEntry` (le modèle local) est structurellement un **débit/crédit à 2 comptes, montant unique** (`debit_account`, `credit_account`, `amount`) — pas une écriture multi-lignes. Ça correspond exactement à une `Journal Entry` ERPNext **à 2 lignes** (une ligne débit, une ligne crédit), déjà testée avec succès dans le dashboard admin. Aucune réécriture de modèle nécessaire.

## Risque majeur identifié et sa parade

**Ce module réutilise le doctype `Journal Entry`, déjà utilisé par 2 mécanismes existants** :
1. `SyncPayrollToErpNext` crée 2 `Journal Entry` par lot de paie synchronisé (charge 6611/422, paiement 422/5711).
2. Le dashboard admin `/admin/erpnext-accounting-test/create-entry` peut créer des `Journal Entry` de test.

Si on écoute **tous** les `on_submit` de `Journal Entry` sans distinction, ces écritures déjà créées par PME360 lui-même seraient réinjectées comme si elles venaient d'ERPNext → doublons dans les rapports comptables (Bilan, Compte de résultat faussés).

**Parade retenue** : `ErpNextClient::createJournalEntryForPme()` (méthode partagée par les deux mécanismes ci-dessus) est modifiée pour toujours poser un marqueur `user_remark: 'PME360_SYNC'` sur toute écriture qu'elle crée. Le nouveau webhook ignore silencieusement toute `Journal Entry` reçue dont `user_remark` commence par `PME360_SYNC` — seules les écritures créées à la main par un humain directement sur ERPNext (sans ce marqueur) sont ingérées.

## Décisions verrouillées

- **Un Webhook ERPNext** : `Journal Entry`, `on_submit` → nouvelle route `POST /webhooks/erpnext/accounting-entry`, même patron d'authentification (jeton partagé déjà existant) que Stock/Facturation.
- **Filtre "2 lignes exactement"** : le document relu doit avoir exactement 2 lignes dans `accounts`, une avec `debit_in_account_currency > 0` (et `credit = 0`), l'autre l'inverse, montants égaux — sinon ignoré silencieusement (log d'avertissement, `200`). Le modèle local ne peut pas représenter une écriture à 3 lignes ou plus — limite assumée, pas un bug.
- **Filtre marqueur PME360** : voir ci-dessus, `user_remark` commençant par `PME360_SYNC` → ignoré.
- **Résolution des comptes locaux** : le nom de compte ERPNext (ex: `"411-Clients - NOT69"`) est retraduit vers son numéro local via le même procédé déjà utilisé dans le dashboard admin (`Str::before($account, '-')`), puis vérifié directement contre `PlanComptableAccount` de la PME (nouvelle vérification directe dans le contrôleur webhook, pas une réutilisation du trait `ValidatesPlanComptableAccount` — celui-ci dépend d'un contexte de session utilisateur connecté qui n'existe pas dans un webhook). Si le compte n'a pas de correspondance locale, l'écriture est ignorée avec un message clair en log — jamais d'erreur qui casse le webhook.
- **Champs de l'`AccountingEntry` créée** : `document_type: 'ecriture_erpnext'` (nouvelle valeur distinctive), `document_reference` = nom de la Journal Entry ERPNext, `description` = `user_remark` ERPNext s'il existe, sinon un texte généré ; `date` = `posting_date`.
- **Retrait du formulaire "Nouvelle écriture"** côté PME360 (`AccountingController::storeEntry()` et sa route `accounting.entries.store`, le bloc de formulaire correspondant dans `accounting.blade.php`) — remplacé par un message informatif, même patron que Stock/Facturation.
- **Ce qui reste strictement inchangé** : `editEntry()`/`updateEntry()`, `destroyEntry()`, tous les endpoints OCR (`retryEntryOcr`, `autoCorrectEntryFromOcr`, `storeManualOcrValidation`), `storeEntryPayment()`, les actions groupées — ils s'appliquent à toutes les écritures quelle que soit leur origine (OCR locale ou webhook ERPNext), et continuent de fonctionner sans modification.
- **Idempotence** : garde `AccountingEntry::where('document_type', 'ecriture_erpnext')->where('document_reference', $docname)->exists()` avant création, pour éviter tout doublon si le webhook est rejoué.

## Tests (vérification manuelle)

1. Créer et soumettre une `Journal Entry` à 2 lignes directement sur ERPNext pour NotifyMails, comptes réels (ex: 6011000/401) → vérifier qu'une `AccountingEntry` apparaît sur `/accounting` avec les bons comptes/montant.
2. Créer une `Journal Entry` à 3 lignes ou plus → vérifier qu'elle est bien ignorée (pas de crash, pas d'entrée locale créée).
3. Déclencher une synchronisation de Paie (`SyncPayrollToErpNext`) → vérifier que les 2 `Journal Entry` qu'elle crée (marquées `PME360_SYNC`) ne créent **pas** de nouvelles `AccountingEntry` en double.
4. Vérifier que `/accounting` n'affiche plus le bouton/formulaire "Nouvelle écriture".
5. Vérifier que l'édition/suppression d'une écriture existante (créée via OCR ou via ce nouveau webhook) fonctionne toujours normalement.

## Fichiers concernés

- `app/Services/ErpNextClient.php` (modifié — marqueur `user_remark` ajouté dans `createJournalEntryForPme()`)
- `app/Http/Controllers/ErpNextAccountingEntryWebhookController.php` (nouveau)
- `routes/web.php` (nouvelle route hors `auth`, suppression de `accounting.entries.store`)
- `bootstrap/app.php` (nouvelle exemption CSRF)
- `app/Http/Controllers/AccountingController.php` (modifié — suppression de `storeEntry()`)
- `resources/views/accounting.blade.php` (modifié — formulaire "Nouvelle écriture" retiré)

Ne pas toucher : `AccountingEntry` (modèle), `AccountingDocumentController`/`createEntryFromDocument()` (pipeline OCR, intégralement local), `editEntry`/`updateEntry`/`destroyEntry`/`storeEntryPayment`/tous les endpoints OCR existants, `bankReconciliation()` (rapport lecture seule, non concerné), `SyncPayrollToErpNext` (continue de fonctionner à l'identique, simplement ses écritures portent désormais un marqueur).
