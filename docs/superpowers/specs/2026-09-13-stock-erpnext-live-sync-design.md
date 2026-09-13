# Stock — Synchronisation ERPNext en production (intégration réelle)

## Contexte et objectif

Le sous-projet précédent ("Stock ERPNext Test") a prouvé, dans un dashboard admin isolé, que les mécanismes ERPNext (`Item`, `Stock Entry`, `Stock Reconciliation`, `Bin`) fonctionnent correctement avec le plan SYSCOHADA. Ce sous-projet connecte maintenant le **vrai** module Stock utilisé par les PME (`/stock`, `StockController`/`StockService`) à ERPNext, en arrière-plan et sans rien changer pour l'utilisateur — exactement le même saut qu'on a fait pour la Facturation (sous-projet 2 : `InvoiceService` → `SyncInvoiceToErpNext`/`SyncInvoicePaymentToErpNext`/`SyncInvoiceCancellationToErpNext`).

**Ce qui existe côté PME360 et reste inchangé** : `StockController`, les vues, `StockProduct`/`StockMovement`, et le calcul CUMP (coût unitaire moyen pondéré) dans `StockService::recordMovement()` (`app/Domain/Inventory/StockService.php:110-191`). Aucune de ces règles métier locales n'est modifiée.

## Découverte technique vérifiée empiriquement (sous-projet précédent)

- `ErpNextClient::createStockMovementForPme(User $pme, string $itemDescription, float $quantity, float $unitRate, string $direction): array` — déjà implémentée et testée en production (`app/Services/ErpNextClient.php`), crée+soumet un `Stock Entry` (Material Receipt/Issue).
- `ErpNextClient::adjustStockForPme(User $pme, string $itemDescription, float $newQuantity): array` — déjà implémentée et testée, crée+soumet un `Stock Reconciliation` avec une quantité absolue.
- `findOrCreateItem(string $description): string` — déjà utilisée par la Facturation pour ses lignes ; déterministe via `Str::slug()`, donc idempotente sans table de correspondance dédiée.
- Prérequis déjà en place pour toute PME provisionnée : `stock_adjustment_account` sur la Company (ajouté dans `provisionCompanyForPme()`), warehouse "Magasin principal" avec compte comptable lié.

**Point important sur la sémantique locale de `ajustement`** : contrairement à ce que le nom suggère, `StockService::recordMovement()` traite `ajustement` comme un **delta signé** ajouté au stock courant (positif ou négatif), pas comme une valeur absolue (`app/Domain/Inventory/StockService.php:130-134`). Cependant, chaque `StockMovement` stocke `quantity_after`, le stock résultant déjà calculé localement — c'est cette valeur absolue qu'on synchronisera vers ERPNext via `Stock Reconciliation`, peu importe le signe du delta d'origine. Pas besoin de retraduire le calcul, juste de lire le résultat déjà connu.

## Décisions verrouillées

- **Point d'accroche** : `StockService::recordMovement()` dispatche un nouveau job `SyncStockMovementToErpNext::dispatch($movement)` juste après le `DB::transaction(...)` (donc après que le mouvement local, le nouveau CUMP et l'écriture d'audit soient déjà validés) — même position exacte que `SyncInvoiceToErpNext::dispatch($invoice)` dans `InvoiceService::createInvoice()`.
- **Mapping type → opération ERPNext** :
  - `entree` → `createStockMovementForPme(..., direction: 'in')`, quantité = `abs($movement->quantity)`.
  - `sortie` → `createStockMovementForPme(..., direction: 'out')`, quantité = `abs($movement->quantity)`.
  - `ajustement` → `adjustStockForPme(..., newQuantity: $movement->quantity_after)` (valeur absolue, jamais le delta).
- **Coût unitaire pour `entree`** : `$movement->unit_cost ?? $movement->average_cost_after ?? 0` (le champ `unit_cost` peut rester `null` localement si l'utilisateur ne le renseigne pas sur une entrée — cas déjà permis par `StockService`).
- **Traçabilité** : nouvelle table `stock_movement_erpnext_syncs`, une ligne par `StockMovement`, copie conforme de `invoice_erpnext_syncs` : `stock_movement_id` (FK unique), `status` (pending/synced/failed), `erpnext_document_type` (`Stock Entry` ou `Stock Reconciliation`), `erpnext_document_name`, `last_error`, `last_synced_at`, `raw_response` (JSON).
- **Résilience** : le job attrape systématiquement toute `\Throwable` et marque `failed` avec le message — jamais de propagation vers l'appelant, indispensable car la production tourne en `QUEUE_CONNECTION=sync` (un job qui laisse fuiter une exception casserait l'enregistrement du mouvement de stock réel de l'utilisateur). Garde aussi identique à `SyncInvoiceToErpNext` : si la PME n'a pas de `erpnext_company_name`/`erpnext_warehouse`, marquer `failed` avec un message clair sans tenter l'appel.
- **Hors scope explicite** :
  - Pas de synchronisation à la création du produit lui-même (`StockService::createProduct()`) — l'Item ERPNext se crée automatiquement au premier mouvement via `findOrCreateItem()`, comme pour les lignes de facture.
  - Pas d'interface admin de suivi des synchronisations pour l'instant (la Facturation n'en a pas non plus).
  - Pas de sens inverse (ERPNext → PME360) — comme pour tous les sous-projets précédents, PME360 reste la source de vérité pour la saisie, ERPNext un miroir comptable.
  - Pas de re-synchronisation rétroactive des mouvements déjà existants avant ce déploiement (comportement identique à l'activation de la sync Facturation : seuls les nouveaux mouvements sont synchronisés).

## Architecture

### Nouvelle table + modèle

`stock_movement_erpnext_syncs` (migration) + `StockMovementErpNextSync` (modèle Eloquent), structure calquée sur `InvoiceErpNextSync`.

### Nouveau Job

`App\Jobs\SyncStockMovementToErpNext` (implémente `ShouldQueue`), reçoit un `StockMovement`, résout son produit et sa PME (`$movement->product->user`), applique le mapping ci-dessus, catch `\Throwable`.

### Modification de `StockService::recordMovement()`

Après la fermeture du `DB::transaction`, dispatcher le job avec le `$movement` retourné, avant de le renvoyer à l'appelant (`StockController::storeMovement()` reste inchangé).

## Tests (vérification manuelle, pas de suite PHPUnit exécutable localement — PHP 8.2 vs 8.4 requis)

1. Sur une PME provisionnée, créer un produit dans `/stock`, puis enregistrer une **entrée** de 10 unités à 5000 XOF → vérifier en ERPNext qu'un `Stock Entry` (Material Receipt) est bien soumis, avec le bon article/quantité/valeur, et que `stock_movement_erpnext_syncs` marque `synced`.
2. Enregistrer une **sortie** de 3 unités → vérifier le `Stock Entry` (Material Issue) correspondant et le nouveau `Bin.actual_qty` (doit refléter 7).
3. Enregistrer un **ajustement** à la baisse de -2 (résultat local `quantity_after` = 5) → vérifier qu'un `Stock Reconciliation` est soumis avec `qty: 5` (pas -2 ni un delta).
4. Enregistrer un mouvement sur une PME **non provisionnée** sur ERPNext → vérifier que le mouvement local est créé normalement (aucun blocage) et que la ligne de sync est `failed` avec un message clair.
5. Simuler une panne ERPNext (mauvaise clé API) → vérifier que l'enregistrement du mouvement local n'est jamais affecté (comportement déjà vérifié pour la Facturation avec la même méthode).

## Fichiers concernés

- `database/migrations/YYYY_MM_DD_HHMMSS_create_stock_movement_erpnext_syncs_table.php` (nouveau)
- `app/Models/StockMovementErpNextSync.php` (nouveau)
- `app/Jobs/SyncStockMovementToErpNext.php` (nouveau)
- `app/Domain/Inventory/StockService.php` (modifié — dispatch dans `recordMovement()`)

Ne pas toucher : `StockController`, les vues `stock.*`, `StockProduct`, le calcul CUMP existant, `ErpNextClient` (les méthodes nécessaires existent déjà).
