# Création manuelle d'écritures ERPNext (Journal Entry) — Design Spec

## Contexte et objectif

Suite au sous-projet 3 (consultation des rapports comptables ERPNext), l'utilisateur a fait remarquer que l'espace de test ne permet que de **consulter** — pas de **créer** une écriture comptable manuelle dans ERPNext (l'équivalent du "Journal Entry" natif, ou de "Gestion des écritures" qui existe déjà localement dans PME360). Ce sous-projet ajoute cette capacité, dans le même espace de test admin.

**Ce qui existe déjà et reste inchangé** : `AccountingController::storeEntry()` (local, `app/Http/Controllers/AccountingController.php:105-189`) continue de fonctionner exactement comme aujourd'hui — création d'une `AccountingEntry` locale, indépendante d'ERPNext.

## Champs répliqués depuis l'écran ERPNext réel (vérifié en direct)

Inspection de `https://.../app/journal-entry/new` :
- **Company** (auto-résolu depuis la PME sélectionnée, pas un champ du formulaire PME360)
- **Series** (numérotation automatique ERPNext, pas un champ du formulaire)
- **Entry Type** — menu déroulant (Journal Entry, Bank Entry, Cash Entry, Contra Entry, Opening Entry, Depreciation Entry...)
- **Posting Date**
- **Accounting Entries** — tableau dynamique de lignes, chacune : Account, Debit, Credit (Party Type/Party laissés hors scope — non pertinents pour ce cas d'usage)
- **Total Debit / Total Credit** — calculés automatiquement par ERPNext
- **Reference Number / Reference Date**

## Décisions verrouillées

- Nombre de lignes **illimité** (pas figé à 2 comme un simple débit/crédit) — ajout dynamique de lignes, comme dans ERPNext.
- Sélection du compte via **menu déroulant alimenté en direct** depuis le Plan comptable ERPNext de la PME (`ErpNextClient::getChartOfAccountsForCompany()`, déjà existant depuis le sous-projet 3) — pas de texte libre.
- Total Débit/Crédit calculés **côté PME360 en JavaScript** pour un retour visuel immédiat, sachant qu'ERPNext refusera de toute façon une écriture déséquilibrée côté serveur (double vérification, pas de confiance aveugle au JS).
- L'écriture est **soumise immédiatement** (`docstatus: 1`) après création — sinon elle n'apparaît dans aucun rapport (même principe que les factures).
- Reste dans l'espace de test admin (`/admin/erpnext-accounting-test`) — pas encore dans le vrai module comptable PME.

## Architecture

### Nouvelle méthode `ErpNextClient::createJournalEntryForPme()`

```php
/**
 * @param  array<int, array{account_number: string, debit: float, credit: float}>  $lines
 * @return array<string, mixed>
 */
public function createJournalEntryForPme(
    User $pme,
    string $voucherType,
    string $postingDate,
    array $lines,
    ?string $referenceNumber = null,
    ?string $referenceDate = null
): array
```

- Pour chaque ligne, résout le compte via `findAccountByNumber($pme->erpnext_company_name, $line['account_number'])` (méthode privée déjà existante, réutilisée telle quelle).
- Construit `accounts[]` : `[{'account' => <nom résolu>, 'debit_in_account_currency' => $line['debit'], 'credit_in_account_currency' => $line['credit']}, ...]`.
- `POST /api/resource/Journal Entry` avec `company`, `voucher_type`, `posting_date`, `accounts`, et si renseignés `cheque_no`/`cheque_date` (noms de champs internes historiques d'ERPNext pour ce que l'écran affiche comme "Reference Number"/"Reference Date" sur un Journal Entry).
- Soumet via `PUT .../docstatus: 1` (même pattern que `createAndSubmitSalesInvoiceForPme`).
- Lève `ErpNextApiException` si la réponse ne contient pas de nom de document (même convention que le reste du client), ou si ERPNext refuse (ex: déséquilibre débit/crédit — message d'erreur remonté tel quel à l'utilisateur).

### Nouveaux endpoints (dans le contrôleur existant `ErpNextAccountingTestController`)

```php
Route::get('/create-entry', [ErpNextAccountingTestController::class, 'createEntry'])->name('create-entry');
Route::post('/create-entry', [ErpNextAccountingTestController::class, 'storeEntry'])->name('store-entry');
```

- `createEntry()` : formulaire — liste des PME provisionnées, et pour la PME présélectionnée (ou via un second appel AJAX/rechargement), la liste des comptes de son Plan comptable ERPNext (menu déroulant).
- `storeEntry()` : valide (`user_id`, `voucher_type`, `posting_date`, `lines` tableau avec `account_number`/`debit`/`credit`, total débit = total crédit), appelle `createJournalEntryForPme()`, affiche le résultat (numéro d'écriture ERPNext créée, ou message d'erreur clair).

### Vue

`resources/views/admin/erpnext-accounting-test/create-entry.blade.php` — formulaire avec lignes dynamiques en JS (pattern déjà établi dans `admin/erpnext-test/create.blade.php` pour les lignes de facture, à adapter pour un sélecteur de compte au lieu d'un texte libre).

Un lien "Nouvelle écriture" ajouté en haut de `admin/erpnext-accounting-test/index.blade.php`.

## Tests (vérification manuelle)

1. Créer une écriture équilibrée à 2 lignes (ex: débit 50 000 sur un compte de charge, crédit 50 000 sur un compte de trésorerie) pour "NotifyMails" → vérifier la création + soumission réussie dans ERPNext, et que la Balance générale/Grand livre du sous-projet 3 la reflète immédiatement après.
2. Tenter une écriture déséquilibrée (débit ≠ crédit) → vérifier qu'ERPNext refuse avec un message clair, affiché tel quel dans PME360.
3. Créer une écriture à 3 lignes ou plus → vérifier que ça fonctionne (pas de limite à 2 lignes).

## Fichiers concernés

- `app/Services/ErpNextClient.php` (modifié — 1 nouvelle méthode publique, réutilise `findAccountByNumber()` existant)
- `app/Http/Controllers/ErpNextAccountingTestController.php` (modifié — 2 nouvelles méthodes)
- `routes/web.php` (modifié — 2 routes ajoutées)
- `resources/views/admin/erpnext-accounting-test/create-entry.blade.php` (nouveau)
- `resources/views/admin/erpnext-accounting-test/index.blade.php` (modifié — 1 lien ajouté)

Ne pas toucher : `AccountingController::storeEntry()` et "Gestion des écritures" locale (inchangés).
