# Stock ERPNext Test — Design Spec

## Contexte et objectif

Après les 5 sous-projets comptables (provisionnement, Facturation, rapports comptables, écritures manuelles, rapprochement bancaire), ce sous-projet couvre le module **Stock** de PME360, dans un nouvel espace de test admin isolé.

**Ce qui existe côté PME360 et reste inchangé** : `StockController`/`StockService` (`app/Domain/Inventory/StockService.php`) gèrent `StockProduct` (article, quantité globale, coût moyen pondéré CUMP) et `StockMovement` (`entree`/`sortie`/`ajustement`). Module mono-entrepôt (pas de notion d'entrepôts multiples localement), totalement découplé de la Facturation (créer une facture ne décrémente pas le stock). Rien de tout cela n'est modifié.

## Découverte technique vérifiée empiriquement

Trois objets ERPNext, réutilisant en partie l'existant :
- **`Item`** — déjà géré via `ErpNextClient::findOrCreateItem()` (existant, réutilisé tel quel).
- **`Stock Entry`** — mouvement de stock, `stock_entry_type` = `"Material Receipt"` (entrée) ou `"Material Issue"` (sortie), une ligne par article (`item_code`, `qty`, `basic_rate`, `t_warehouse` pour une entrée / `s_warehouse` pour une sortie), **doit être soumis** (`docstatus: 1`) pour affecter réellement le stock.
- **`Bin`** — pas un objet qu'on crée, mais la table native ERPNext qui reflète le niveau de stock réel par article/entrepôt (`GET /api/resource/Bin` filtré sur `item_code`, champ `actual_qty`).

**Piège découvert (même famille que les précédents)** : soumettre un `Stock Entry` échoue si la Company n'a pas de **"Stock Adjustment Account"** configuré (`Please set Account in Warehouse... or set default Stock Adjustment Account`) — même schéma que le "Round Off Account" du sous-projet 1. Un compte SYSCOHADA adapté existe (classe 603x, "Variations des stocks..."), à configurer via `PUT /api/resource/Company/{name}` avec `stock_adjustment_account`.

**Deuxième piège découvert** : les 4 entrepôts **créés automatiquement par défaut par ERPNext** à la création de la Company ("Stores", "Finished Goods", "Work In Progress", "Goods In Transit") **n'ont pas de compte comptable lié**, contrairement à l'entrepôt "Magasin principal" qu'on crée nous-mêmes dans `provisionCompanyForPme()` (qui, lui, a bien un `account` défini). Toute opération de stock doit donc utiliser l'entrepôt **"Magasin principal"**, pas les entrepôts par défaut d'ERPNext, sous peine d'échec à la soumission (`Please set Account in Warehouse Stores...`). Vérifié en direct : succès avec "Magasin principal - NOT69", échec avec "Stores - NOT69".

## Décisions verrouillées

- **`ajustement` → `Stock Reconciliation`** (pas `Stock Entry`), car c'est le mécanisme natif ERPNext conçu pour fixer une quantité absolue (correspond exactement à la sémantique d'un ajustement d'inventaire), plutôt que `Stock Entry` qui raisonne en mouvement relatif.
- Amélioration du provisionnement existant (sous-projet 1) : `provisionCompanyForPme()` doit désormais **aussi configurer `stock_adjustment_account`** sur la Company, en plus de `round_off_account` déjà fait — pour que les futures PME n'aient pas ce blocage à la première opération de stock.
- Toutes les opérations de stock de cette v1 utilisent l'entrepôt **"Magasin principal"** de la PME (celui déjà créé/connu via `erpnext_warehouse` sur `User`) — pas de sélection d'entrepôt dans le formulaire, cohérent avec le mono-entrepôt de PME360 local.
- Reste dans un nouvel espace de test admin dédié `/admin/erpnext-stock-test` — aucune modification de `StockController`/`StockService` local.

## Architecture

### Extension de `provisionCompanyForPme()` (sous-projet 1)

Ajout d'une résolution de compte classe 603x (`findAccountByNumber($company, '6031')`) et d'un `PUT` sur la Company pour `stock_adjustment_account`, au même endroit que `round_off_account` est déjà fixé.

### Nouvelles méthodes `ErpNextClient`

```php
public function createStockMovementForPme(User $pme, string $itemDescription, float $quantity, float $unitRate, string $direction): array
```
- `$direction` = `'in'` (Material Receipt, utilise `t_warehouse`) ou `'out'` (Material Issue, utilise `s_warehouse`).
- Résout/crée l'article via `findOrCreateItem()` existant.
- Crée puis soumet le `Stock Entry` sur l'entrepôt `$pme->erpnext_warehouse`.

```php
public function adjustStockForPme(User $pme, string $itemDescription, float $newQuantity): array
```
- `POST /api/resource/Stock Reconciliation` avec une ligne (`item_code`, `warehouse`, `qty` = nouvelle quantité absolue), soumis immédiatement.

```php
public function getStockLevelsForCompany(string $company): array
```
- `GET /api/resource/Bin` filtré sur les entrepôts de la Company (ou plus simplement sans filtre company si le champ n'existe pas sur Bin — à vérifier lors de l'implémentation, avec repli sur un filtre par `warehouse` contenant l'abréviation de la Company).

### Nouveau dashboard

`/admin/erpnext-stock-test` : formulaire de sélection de PME, puis un onglet "Articles en stock" (liste des `Bin` avec quantité) et un formulaire "Nouveau mouvement" (article texte libre, quantité, prix, sens entrée/sortie/ajustement).

## Tests (vérification manuelle)

1. Créer un mouvement d'entrée (10 unités, 5000 XOF) pour un nouvel article → vérifier `Stock Entry` soumis, `Bin.actual_qty = 10`.
2. Créer un mouvement de sortie (3 unités) sur le même article → vérifier `actual_qty = 7`.
3. Faire un ajustement à 20 → vérifier `actual_qty = 20` exactement (pas un cumul).
4. Provisionner une PME de test toute neuve → vérifier que `stock_adjustment_account` est bien fixé automatiquement, sans blocage au premier mouvement.

## Fichiers concernés

- `app/Services/ErpNextClient.php` (modifié — extension de `provisionCompanyForPme()` + 3 nouvelles méthodes)
- `app/Http/Controllers/ErpNextStockTestController.php` (nouveau)
- `resources/views/admin/erpnext-stock-test/{index,show,create-movement}.blade.php` (nouveaux)
- `routes/web.php` (modifié)
- `resources/views/layouts/partials/sidebar.blade.php` (modifié — 1 lien ajouté)

Ne pas toucher : `StockController`, `StockService`, `StockProduct`, `StockMovement` locaux.
