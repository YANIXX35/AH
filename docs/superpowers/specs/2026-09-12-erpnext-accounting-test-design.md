# Espace de test "Comptabilité ERPNext" — Design Spec

## Contexte et objectif

Suite aux sous-projets 1 (provisionnement automatique) et 2 (synchronisation Facturation), l'objectif est de vérifier, dans un espace de test admin isolé (même principe que `/admin/erpnext-test`), que les rapports comptables réels d'ERPNext (Plan comptable, Grand livre, Balance générale, Bilan, Compte de résultat) sont correctement accessibles et exploitables pour une PME provisionnée — avant d'envisager de les afficher un jour dans le vrai dashboard PME.

**Rien dans l'existant n'est modifié** : les rapports locaux PME360 (`AccountingController::report()`, `BceaoLiasseService`, routes `accounting.report.*`) restent strictement inchangés.

## Découverte technique vérifiée empiriquement

Contrairement à tous les appels ERPNext utilisés jusqu'ici (Company, Customer, Item, Sales Invoice, Payment Entry — tous de simples ressources REST via `/api/resource/...`), le **Bilan** et le **Compte de résultat** ne sont pas des ressources : ce sont des **rapports calculés côté serveur**, exécutés via un mécanisme différent :

```
POST /api/method/frappe.desk.query_report.run
Content-Type: application/x-www-form-urlencoded  (PAS du JSON, contrairement au reste)

report_name=Balance Sheet  (ou "Profit and Loss Statement")
filters={"company":"...","filter_based_on":"Date Range","period_start_date":"2026-01-01","period_end_date":"2026-12-31","periodicity":"Yearly"}  (JSON encodé en string)
```

Point piège vérifié en direct : envoyer `from_date`/`to_date` (noms de champs intuitifs) échoue avec `"From Date and To Date are mandatory"` — les vrais noms attendus sont `period_start_date`/`period_end_date`, et il faut impérativement ajouter `filter_based_on: "Date Range"` pour que ces champs soient pris en compte (sinon ERPNext s'attend à `from_fiscal_year`/`to_fiscal_year` à la place). Vérifié en direct sur la société "NotifyMails #69" : Bilan → Total Actif 1 344 000 XOF ; Compte de résultat → Total Produits 1 344 000 XOF, cohérents avec les factures déjà créées.

Le **Plan comptable** et le **Grand livre** restent de simples ressources REST classiques (`/api/resource/Account`, `/api/resource/GL Entry`), déjà maîtrisées. La **Balance générale** n'a pas d'endpoint dédié fiable pour un usage API simple — elle sera calculée côté PME360 en agrégeant les lignes du Grand livre par compte (somme débit/crédit), pas via un appel API supplémentaire.

## Architecture

### Extension de `ErpNextClient`

Quatre nouvelles méthodes publiques :

```php
public function getChartOfAccountsForCompany(string $company): array
```
`GET /api/resource/Account` filtré sur `company`, retourne la liste complète (nom, intitulé, type, groupe/feuille).

```php
public function getGeneralLedgerForCompany(string $company, string $fromDate, string $toDate): array
```
`GET /api/resource/GL Entry` filtré sur `company` + `posting_date` entre les deux dates, champs : `account`, `posting_date`, `debit`, `credit`, `voucher_type`, `voucher_no`, `remarks`.

```php
public function getTrialBalanceForCompany(string $company, string $fromDate, string $toDate): array
```
Appelle `getGeneralLedgerForCompany()` puis agrège en PHP : pour chaque compte, somme des débits et des crédits, solde net. Pas d'appel API supplémentaire.

```php
public function getFinancialStatementForCompany(string $company, string $reportName, string $fromDate, string $toDate): array
```
Méthode générique pour Bilan/Compte de résultat, implémentant le mécanisme `query_report.run` découvert ci-dessus. `$reportName` vaut `"Balance Sheet"` ou `"Profit and Loss Statement"`. Retourne le tableau `result` (lignes hiérarchiques compte/montant) tel que renvoyé par ERPNext.

**Remarque technique** : ces quatre méthodes utilisent `Http::asForm()` pour le dernier cas (report), alors que le reste du client utilise du JSON — nécessite d'ajouter une variante du helper privé `post()` existant (ou un paramètre optionnel) qui envoie en `application/x-www-form-urlencoded` au lieu de JSON.

### Nouveau contrôleur

`app/Http/Controllers/Admin/ErpNextAccountingTestController.php` (ou à plat comme les autres, suivant la convention déjà établie dans ce projet — contrôleurs admin à la racine de `app/Http/Controllers/`, pas de sous-dossier) :

- `index()` : formulaire de sélection d'une PME provisionnée (`User::whereNotNull('erpnext_company_name')`) + sélection de la période (par défaut : année en cours, 1er janvier → aujourd'hui).
- `show(Request $request)` : reçoit `user_id` + dates en query string, appelle les 4 méthodes d'`ErpNextClient`, affiche les 5 onglets (Plan comptable, Grand livre, Balance, Bilan, Compte de résultat) dans une seule vue avec navigation par onglets (pas de rechargement de page nécessaire — même contenu déjà chargé, juste caché/affiché en JS, comme pattern simple).

### Routes

```php
Route::prefix('erpnext-accounting-test')->name('erpnext-accounting-test.')->group(function () {
    Route::get('/', [ErpNextAccountingTestController::class, 'index'])->name('index');
    Route::get('/show', [ErpNextAccountingTestController::class, 'show'])->name('show');
});
```
Ajoutées dans le même groupe `platform.admin` que `erpnext-test.*`.

### Vue

`resources/views/admin/erpnext-accounting-test/index.blade.php` (formulaire) et `show.blade.php` (les 5 onglets). Lien "Comptabilité ERPNext Test" ajouté dans la sidebar admin, juste après "ERPNext Test".

### Gestion d'erreur

Si un rapport échoue (ex: PME non provisionnée, société sans données), afficher un message clair par onglet plutôt qu'une page blanche — chaque appel `ErpNextClient` est encapsulé dans son propre `try/catch` au niveau du contrôleur, un échec sur un rapport n'empêche pas d'afficher les autres.

## Tests (vérification manuelle)

1. Sélectionner "NotifyMails" (déjà provisionnée, données réelles) → vérifier que les 5 onglets affichent des données cohérentes entre eux (ex: le total de la Balance générale doit correspondre aux masses du Bilan).
2. Vérifier que le Bilan affiche bien un Total Actif = Total Passif (équilibre comptable de base).
3. Sélectionner une PME non provisionnée → vérifier un message d'erreur clair par onglet, pas de plantage.

## Fichiers concernés

- `app/Services/ErpNextClient.php` (modifié — 4 nouvelles méthodes + variante form-encoded du helper `post`)
- `app/Http/Controllers/ErpNextAccountingTestController.php` (nouveau)
- `resources/views/admin/erpnext-accounting-test/{index,show}.blade.php` (nouveaux)
- `routes/web.php` (modifié — 2 routes ajoutées)
- `resources/views/layouts/partials/sidebar.blade.php` (modifié — 1 lien ajouté)

Ne pas toucher : `AccountingController`, `BceaoLiasseService`, toutes les routes `accounting.report.*` et `accounting.liasse-bceao*` (rapports locaux PME360, inchangés), le dashboard `admin/erpnext-test/*` (facturation, inchangé).
