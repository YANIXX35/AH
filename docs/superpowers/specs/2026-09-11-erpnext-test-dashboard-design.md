# ERPNext Test Dashboard — Design Spec

## Contexte et objectif

Sitiame Capital (PME360) évalue une dépendance à ERPNext pour la facturation/comptabilité. Avant toute intégration dans l'application existante, l'utilisateur veut un espace **isolé et réservé aux admins** pour valider en conditions réelles le mécanisme de base : créer une facture depuis PME360, la faire créer réellement côté ERPNext via son API REST, et en garder une copie structurée dans la base de données de PME360.

**Contrainte stricte** : aucune modification du code existant (dashboards, modèles, routes actuels restent intacts). Tout le nouveau code vit dans des fichiers/tables dédiés à cette fonctionnalité.

**Ce que ce test doit prouver** :
1. PME360 peut créer/retrouver un "Customer" ERPNext correspondant à une PME déjà enregistrée dans PME360.
2. PME360 peut créer une "Sales Invoice" ERPNext via API à partir de données saisies dans PME360.
3. La réponse ERPNext (numéro de facture, montants calculés, statut) peut être enregistrée de façon structurée dans une table PME360 dédiée.
4. Les échecs (API indisponible, erreur de validation) sont visibles et n'entraînent aucun enregistrement partiel/silencieux.

**Hors périmètre pour cette v1** (décisions prises en brainstorming) :
- Pas de gestion de la TVA/taxes (pas de "Sales Taxes and Charges Template").
- Pas de catalogue d'articles réutilisable — lignes de facture en texte libre (désignation, quantité, prix unitaire).
- Pas de synchronisation retour (webhook ERPNext → PME360) — ce test ne couvre que le sens PME360 → ERPNext.
- Pas d'accès pour d'autres rôles que l'admin plateforme.

## Architecture

### Accès et routes

Nouveau groupe de routes admin, sur le modèle de l'existant (`routes/web.php:444`, middleware `platform.admin`, préfixe `admin`) :

```php
Route::middleware('platform.admin')->prefix('admin')->name('admin.')->group(function () {
    // ... routes existantes inchangées ...

    Route::prefix('erpnext-test')->name('erpnext-test.')->group(function () {
        Route::get('/', [ErpNextTestController::class, 'index'])->name('index');
        Route::get('/create', [ErpNextTestController::class, 'create'])->name('create');
        Route::post('/', [ErpNextTestController::class, 'store'])->name('store');
        Route::get('/{erpNextTestInvoice}', [ErpNextTestController::class, 'show'])->name('show');
    });
});
```

Un seul lien est ajouté dans le menu admin existant (`resources/views/layouts/partials/sidebar.blade.php`, section admin), pointant vers `admin.erpnext-test.index`. Aucun autre lien de navigation n'est modifié.

### Configuration

Nouvelle sous-clé dans `config/services.php`, suivant le pattern déjà utilisé par `services.cinetpay`/`services.ocr_space` :

```php
'erpnext' => [
    'base_url' => env('ERPNEXT_BASE_URL'),
    'api_key' => env('ERPNEXT_API_KEY'),
    'api_secret' => env('ERPNEXT_API_SECRET'),
    'timeout' => env('ERPNEXT_TIMEOUT', 15),
],
```

Variables ajoutées à `.env` (jamais commitées) et `.env.example` (avec valeurs vides) :
```
ERPNEXT_BASE_URL=https://sitiame-erp-essai.z.frappe.cloud
ERPNEXT_API_KEY=
ERPNEXT_API_SECRET=
ERPNEXT_TIMEOUT=15
```

### Service : `ErpNextClient`

Nouveau fichier `app/Services/ErpNextClient.php`, suivant exactement le pattern de `OcrService.php`/`CinetPayService.php` (config-driven, `Http` facade, `try/catch (\Throwable $e)`, méthode `enabled()` qui vérifie que `base_url`/`api_key`/`api_secret` sont bien renseignés avant tout appel).

Deux méthodes publiques, chacune retournant soit les données décodées, soit lève une `ErpNextApiException` (nouvelle exception dédiée, `app/Exceptions/ErpNextApiException.php`) portant le message d'erreur brut renvoyé par ERPNext :

```php
public function findOrCreateCustomer(User $pme): array
public function createSalesInvoice(User $pme, string $erpNextCustomerName, array $items): array
```

`findOrCreateCustomer` :
- Si `$pme->erpnext_customer_id` est déjà renseigné, tente un `GET /api/resource/Customer/{id}` pour confirmer qu'il existe toujours côté ERPNext ; s'il n'existe plus, retombe sur la création.
- Sinon, `POST /api/resource/Customer` avec `customer_name = $pme->company_name` (ou `$pme->name` si `company_name` est vide), `customer_group` et `territory` sur des valeurs par défaut fixes ("All Customer Groups", "All Territories" — valeurs standard ERPNext).
- Enregistre l'identifiant retourné dans `$pme->erpnext_customer_id` (nouvelle colonne, voir Données).

`createSalesInvoice` :
- `POST /api/resource/Sales Invoice` avec `customer`, `items[]` (chaque ligne : `item_code` généré à la volée en texte libre via le champ `item_name`/description, `qty`, `rate`) — ERPNext accepte des lignes de facture sans `Item` préexistant tant que `item_name` et `rate` sont fournis avec un `item_code` par défaut réutilisable (ex: constante `"Prestation libre"`, créée une fois manuellement dans ERPNext avant le premier test — étape manuelle documentée dans le plan, pas automatisée en v1).

### Données

**Nouvelle colonne** sur `users` : `erpnext_customer_id` (`string`, nullable) — migration dédiée, n'affecte aucune donnée existante.

**Nouvelles tables**, préfixées `erpnext_test_` pour bien marquer leur caractère isolé/expérimental :

```
erpnext_test_invoices
- id
- user_id (FK -> users, la PME facturée)
- erpnext_invoice_name (string, ex: "ACC-SINV-2026-00001")
- status (enum: 'pending', 'synced', 'failed')
- error_message (text, nullable — rempli si status='failed')
- grand_total (decimal 15,2, nullable — renvoyé par ERPNext)
- raw_response (json, nullable — réponse brute complète, pour debug)
- created_by_user_id (FK -> users, l'admin qui a créé le test)
- timestamps

erpnext_test_invoice_items
- id
- erpnext_test_invoice_id (FK -> erpnext_test_invoices)
- description (string)
- quantity (decimal 10,2)
- unit_price (decimal 15,2)
- amount (decimal 15,2) — quantity * unit_price, calculé côté PME360
- timestamps
```

Deux nouveaux modèles Eloquent : `ErpNextTestInvoice` (avec relations `user()`, `createdBy()`, `items()`) et `ErpNextTestInvoiceItem` (relation `invoice()`).

### Flux applicatif

1. `GET /admin/erpnext-test` (`index`) : liste des `ErpNextTestInvoice` existantes, triées par date décroissante, avec badge de statut (pending/synced/failed) et lien vers le détail.
2. `GET /admin/erpnext-test/create` (`create`) : formulaire — select des PME via le scope existant `User::clients()` (`app/Models/User.php:181`), lignes dynamiques (désignation/quantité/prix ajoutées en JS, pattern à réutiliser depuis un formulaire admin existant avec lignes répétables lors de l'implémentation).
3. `POST /admin/erpnext-test` (`store`) :
   a. Valide les données (PME sélectionnée, au moins une ligne avec désignation/quantité/prix > 0).
   b. Crée un enregistrement `ErpNextTestInvoice` en statut `pending` (+ ses `items`) **avant** l'appel API, pour garder une trace même en cas d'échec réseau total.
   c. Appelle `ErpNextClient::findOrCreateCustomer()` puis `createSalesInvoice()`.
   d. En cas de succès : met à jour l'enregistrement en `synced`, avec `erpnext_invoice_name`, `grand_total`, `raw_response`.
   e. En cas d'échec (exception `ErpNextApiException` ou `\Throwable`) : met à jour en `failed` avec `error_message` — **redirige quand même vers le détail** (pas de perte d'information, l'échec est visible).
4. `GET /admin/erpnext-test/{invoice}` (`show`) : détail — informations PME360 (lignes saisies) + bloc "Réponse ERPNext" affichant le JSON brut si présent, ou le message d'erreur si échec.

### Gestion d'erreur

- Timeout ou erreur réseau → `ErpNextApiException` avec message générique ("ERPNext injoignable") + détail technique en `error_message`.
- Erreur de validation ERPNext (ex: 4xx avec message JSON) → le message d'erreur exact renvoyé par ERPNext est capturé dans `error_message`, pour diagnostiquer précisément (ex: template de taxe manquant, champ requis absent).
- Aucun appel n'est retenté automatiquement en v1 (pas de file de jobs) — un échec nécessite de recréer une nouvelle facture test manuellement.

## Tests

Étant donné les contraintes déjà rencontrées dans cette session (PHPUnit non exécutable localement, PHP 8.2 installé vs 8.4 requis), la vérification se fera manuellement :
1. Créer une PME de test si aucune n'existe, saisir une facture avec 2 lignes → vérifier `synced`, `erpnext_invoice_name` rempli, et que la facture apparaît bien dans ERPNext (`Accounting → Invoicing → Sales Invoice`).
2. Couper temporairement `ERPNEXT_API_KEY` (config invalide) → vérifier que le statut passe à `failed` avec un message d'erreur clair, et qu'aucune facture fantôme n'apparaît côté ERPNext.
3. Refaire un test avec la même PME → vérifier que `erpnext_customer_id` est réutilisé (pas de doublon de Customer côté ERPNext).

## Fichiers concernés

- `config/services.php` (modifié — ajout clé `erpnext`)
- `.env.example` (modifié — 4 nouvelles variables)
- `app/Services/ErpNextClient.php` (nouveau)
- `app/Exceptions/ErpNextApiException.php` (nouveau)
- `app/Http/Controllers/Admin/ErpNextTestController.php` (nouveau)
- `app/Models/ErpNextTestInvoice.php` (nouveau)
- `app/Models/ErpNextTestInvoiceItem.php` (nouveau)
- `app/Models/User.php` (modifié — ajout `erpnext_customer_id` au fillable + relation `erpNextTestInvoices()`)
- `database/migrations/xxxx_add_erpnext_customer_id_to_users_table.php` (nouveau)
- `database/migrations/xxxx_create_erpnext_test_invoices_table.php` (nouveau)
- `database/migrations/xxxx_create_erpnext_test_invoice_items_table.php` (nouveau)
- `resources/views/admin/erpnext-test/{index,create,show}.blade.php` (nouveaux)
- `routes/web.php` (modifié — ajout du sous-groupe `erpnext-test` dans le groupe admin existant)
- `resources/views/layouts/partials/sidebar.blade.php` (modifié — un seul lien ajouté)

Ne pas toucher : tout dashboard/route/modèle existant en dehors des ajouts listés ci-dessus.
