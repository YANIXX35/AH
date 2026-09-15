# Stock — ERPNext comme moteur (avec repli local)

## Contexte et objectif

Jusqu'ici, chaque sous-projet ERPNext suivait le même sens : PME360 reste la source de vérité, ERPNext n'est qu'un miroir comptable silencieux synchronisé en arrière-plan (`SyncStockMovementToErpNext`, dispatché après coup, sans jamais bloquer l'utilisateur). Ce sous-projet **inverse ce sens pour le module Stock**, en pilote : ERPNext devient la source de vérité du calcul (quantité, valorisation CUMP), PME360 se contente d'afficher ce résultat — dans le but d'éliminer le risque de divergence entre deux calculs indépendants (un en Laravel, un en ERPNext).

**Contrepartie assumée et discutée avec l'utilisateur** : comme l'ERPNext trial actuel a déjà montré des indisponibilités/lenteurs pendant cette session (timeouts, MySQL local tombé, etc.), on ne bascule pas en tout-ou-rien. Design retenu : **ERPNext synchrone en premier essai, repli sur l'ancien calcul CUMP local si ERPNext échoue**, avec re-synchronisation différée via le job déjà existant.

## Décisions verrouillées

- **Champ d'application de ce sous-projet : uniquement `StockService::recordMovement()`.** La création de produit (`createProduct()`) reste 100% locale — un produit sans mouvement n'a ni quantité ni valorisation à faire diverger, donc rien à gagner à la faire dépendre d'ERPNext.
- **Séquence de `recordMovement()`** :
  1. Si la PME n'est pas provisionnée sur ERPNext (`erpnext_company_name`/`erpnext_warehouse` manquants) ou qu'ERPNext est désactivé → aller directement à l'étape 4 (repli local), sans tenter d'appel API.
  2. Sinon, appeler ERPNext **de façon synchrone** : `createStockMovementForPme()` (entree/sortie) ou `adjustStockForPme()` (ajustement) — méthodes déjà existantes et déjà testées en production.
  3. Si l'appel réussit → interroger `Bin` pour récupérer la quantité et la valorisation réelles (`actual_qty`, `valuation_rate`) de cet article sur l'entrepôt de la PME, puis écrire le `StockMovement`/`StockProduct` local avec **ces valeurs ERPNext**, pas un calcul local. Marquer la synchronisation `synced` immédiatement (pas besoin de redispatcher le job existant).
  4. Si l'appel échoue (exception, PME non provisionnée, ERPNext désactivé) → utiliser l'**ancien calcul CUMP local** (celui qui existe déjà dans `StockService`, inchangé), écrire le mouvement normalement, puis dispatcher `SyncStockMovementToErpNext` comme filet de rattrapage (déjà construit, déjà testé) pour retenter plus tard en arrière-plan.
- **Limite assumée** : si le repli local est utilisé puis que la resynchronisation en arrière-plan réussit plus tard, on ne corrige pas rétroactivement les quantités locales déjà enregistrées (la synchro de secours ne fait que marquer `synced`, comme aujourd'hui) — un écart local/ERPNext temporaire est possible dans ce cas de figure rare (panne ERPNext au moment précis d'un mouvement), accepté comme compromis pragmatique plutôt que de reconstruire toute la chaîne de réconciliation.
- **Coût de latence assumé** : en cas de succès ERPNext (chemin normal), l'utilisateur attend la réponse réelle d'ERPNext avant que "Enregistrer" ne se termine — 2 à 3 appels API en série (création + soumission + lecture Bin), donc potentiellement plusieurs secondes. Compromis explicitement accepté par l'utilisateur en échange de l'élimination du risque de double calcul.

## Découverte technique à vérifier

- `ErpNextClient::getStockLevelsForCompany()` existe déjà mais ne renvoie que `actual_qty`, pas `valuation_rate` — nécessite une nouvelle méthode ciblée sur un seul article, avec ce champ en plus. À vérifier empiriquement que `Bin.valuation_rate` est bien renseigné par ERPNext pour les articles déjà mouvementés.

## Architecture

### Nouvelle méthode `ErpNextClient`

```php
public function getBinForItem(User $pme, string $itemCode): ?array
```
Retourne `{actual_qty: float, valuation_rate: float}` ou `null` si aucune ligne `Bin` trouvée (article jamais mouvementé sur cet entrepôt), en filtrant sur `item_code` + `warehouse: $pme->erpnext_warehouse`.

### Modification de `StockService::recordMovement()`

Restructuration de la méthode pour suivre la séquence à 4 étapes décrite ci-dessus. Le calcul CUMP local existant est conservé tel quel (pas réécrit), seulement utilisé conditionnellement (chemin de repli) plutôt que systématiquement.

### `SyncStockMovementToErpNext`

Inchangé dans son fonctionnement interne — seul son point de déclenchement change : dispatché uniquement dans le chemin de repli (échec ERPNext synchrone), plus jamais après un succès synchrone (déjà `synced`, inutile de le redispatcher).

## Tests (vérification manuelle)

1. ERPNext disponible, PME provisionnée : créer une entrée → vérifier que le `StockMovement` local contient exactement la quantité/valorisation renvoyées par `Bin` sur ERPNext (pas un calcul local), et que la ligne de sync est `synced` immédiatement, sans job en attente.
2. Simuler une panne ERPNext (mauvaise clé API temporaire) : créer une entrée → vérifier que le mouvement est bien enregistré localement (ancien calcul CUMP), que l'utilisateur n'est jamais bloqué, et que la ligne de sync est `failed`/`pending` avec un job de rattrapage dispatché.
3. PME non provisionnée sur ERPNext : créer un mouvement → vérifier repli local immédiat, sans tentative d'appel API inutile.
4. Mesurer le temps de réponse réel du chemin synchrone (succès) pour valider que la latence reste acceptable en pratique.

## Fichiers concernés

- `app/Services/ErpNextClient.php` (modifié — nouvelle méthode `getBinForItem()`)
- `app/Domain/Inventory/StockService.php` (modifié — restructuration de `recordMovement()`)

Ne pas toucher : `StockController`, les vues `stock.*`, `createProduct()`/`updateProduct()`/`deleteProduct()`, le dashboard de test admin `/admin/erpnext-stock-test`, `SyncStockMovementToErpNext` (job lui-même inchangé).
