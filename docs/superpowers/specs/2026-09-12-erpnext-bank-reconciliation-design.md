# Rapprochement bancaire ERPNext (v1) — Design Spec

## Contexte et objectif

Suite aux sous-projets 1 à 4 (provisionnement, Facturation, rapports comptables, écritures manuelles), ce sous-projet ajoute le **Rapprochement bancaire** à la liste des fonctionnalités communes PME360/ERPNext connectées par API, dans le même espace de test admin.

**Ce qui existe déjà côté PME360 et reste inchangé** : `AccountingController::bankReconciliation()` (`accounting.bank-reconciliation`) — un tableau de comparaison en lecture seule (somme `TreasuryTransaction` vs somme `AccountingEntry` classe 5, écart indicatif), sans mécanisme d'import ni de pointage réel. Rien n'y est modifié.

## Découverte technique vérifiée empiriquement

Le Rapprochement bancaire d'ERPNext repose sur 3 objets, dans cet ordre de dépendance :
1. **`Bank`** — l'établissement bancaire (juste un nom, ex: "Caisse Interne").
2. **`Bank Account`** — rattaché à un `Bank` ET à un vrai compte du plan comptable (`account`, ex: `5711-Caisse en monnaie nationale - NOT69`).
3. **`Bank Transaction`** — une ligne de relevé (date, montant en `deposit`/`withdrawal`, description, référence), rattachée à un `Bank Account`.

Aucun de ces objets n'existe par défaut pour une société nouvellement provisionnée (contrairement au plan comptable, généré automatiquement à la création de la Company) — il faut les créer explicitement, au moins une fois par compte de trésorerie utilisé.

Vérifié en direct sur "NotifyMails #69" : création réussie d'un `Bank` ("Caisse Interne"), d'un `Bank Account` ("Caisse principale - Caisse Interne", lié à 5711), et d'une `Bank Transaction` de 15 000 XOF — retournée avec `status: "Pending"` et `unallocated_amount: 15000`, confirmant qu'ERPNext suit nativement un vrai statut de pointage (une ligne reste "Pending" tant qu'elle n'est associée à aucun paiement/écriture ; `unallocated_amount` diminue au fur et à mesure des associations).

## Décisions verrouillées

- **v1 = création de lignes de relevé uniquement**. L'action de pointage réelle (associer une `Bank Transaction` à un `Payment Entry`/`Journal Entry` existant) est explicitement **hors scope** — traitée comme un futur sous-projet séparé.
- **Auto-création du `Bank`/`Bank Account`** (find-or-create), jamais un pré-requis manuel côté utilisateur — même philosophie que le provisionnement de Company (sous-projet 1).
- Reste dans l'espace de test admin `/admin/erpnext-accounting-test` — `AccountingController::bankReconciliation()` local non touché.

## Architecture

### Nouvelle méthode `ErpNextClient::createBankTransactionForPme()`

```php
public function createBankTransactionForPme(
    User $pme,
    string $treasuryAccountNumber,
    string $date,
    float $amount,
    string $direction,   // 'deposit' ou 'withdrawal'
    string $description,
    ?string $referenceNumber = null
): array
```

- Résout le compte de trésorerie via `findAccountByNumber()` (déjà existant, réutilisé).
- `findOrCreateBankAccountForPme()` (nouvelle méthode privée) : cherche un `Bank Account` existant pour ce compte + cette Company (`GET /api/resource/Bank Account` filtré sur `company`+`account`) ; sinon crée d'abord un `Bank` générique ("Banque interne" — nom fixe, un seul établissement générique par PME pour cette v1, pas de gestion multi-banques) puis le `Bank Account`.
- `POST /api/resource/Bank Transaction` avec `date`, `bank_account`, `deposit`/`withdrawal` (l'un des deux = montant, l'autre = 0 selon `$direction`), `description`, `reference_number`, `currency: 'XOF'`.
- **Pas de soumission** (`docstatus` reste à 0/Draft) — contrairement aux factures/écritures, une `Bank Transaction` n'a pas besoin d'être "soumise" pour exister et afficher un statut `Pending` ; c'est son propre cycle de vie natif (`Pending` → `Reconciled`), vérifié en direct.

### Nouvel onglet et formulaire

Dans `/admin/erpnext-accounting-test` :
- 6ᵉ onglet "Rapprochement bancaire" dans `show.blade.php` — liste des `Bank Transaction` de la PME, via `ErpNextClient::getBankTransactionsForCompany(string $company): array` (nouvelle méthode, `GET /api/resource/Bank Transaction` filtré directement sur `company` — vérifié en direct, ce filtre fonctionne tel quel).
- Nouveau formulaire `create-bank-transaction.blade.php`, même structure que `create-entry.blade.php` : PME, compte de trésorerie (menu déroulant vivant), date, montant, sens (dépôt/retrait), description, référence.

### Contrôleur

Deux nouvelles méthodes sur `ErpNextAccountingTestController` (`createBankTransaction()`/`storeBankTransaction()`), même schéma que `createEntry()`/`storeEntry()`.

## Tests (vérification manuelle)

1. Créer une ligne de relevé (dépôt, 20 000 XOF) pour une PME n'ayant encore aucun `Bank Account` → vérifier la création automatique du `Bank`/`Bank Account`, puis de la `Bank Transaction` avec `status: Pending`.
2. Créer une deuxième ligne pour la même PME/même compte → vérifier qu'aucun nouveau `Bank`/`Bank Account` n'est recréé (réutilisation).
3. Consulter l'onglet "Rapprochement bancaire" → vérifier que les lignes créées apparaissent avec leur statut.

## Fichiers concernés

- `app/Services/ErpNextClient.php` (modifié — 3 nouvelles méthodes : `createBankTransactionForPme()` et `getBankTransactionsForCompany()` publiques, `findOrCreateBankAccountForPme()` privée)
- `app/Http/Controllers/ErpNextAccountingTestController.php` (modifié — 2 nouvelles méthodes, 1 onglet ajouté à `show()`)
- `routes/web.php` (modifié — 2 routes ajoutées)
- `resources/views/admin/erpnext-accounting-test/create-bank-transaction.blade.php` (nouveau)
- `resources/views/admin/erpnext-accounting-test/show.blade.php` (modifié — 1 onglet ajouté)
- `resources/views/admin/erpnext-accounting-test/index.blade.php` (modifié — 1 lien ajouté)

Ne pas toucher : `AccountingController::bankReconciliation()` local, `TreasuryTransaction`, le mécanisme de rapprochement Mobile Money existant.
