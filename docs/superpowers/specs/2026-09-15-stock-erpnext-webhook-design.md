# Stock — ERPNext comme point de saisie unique (webhook entrant)

## Contexte et objectif

Correction de trajectoire par rapport au sous-projet précédent ("ERPNext comme moteur, avec repli local") : ce n'était pas ce que l'utilisateur demandait. Le vrai besoin : **la saisie de mouvements de stock se fait désormais exclusivement dans le formulaire natif ERPNext** (Stock Entry / Stock Reconciliation), et PME360 doit détecter automatiquement ces créations pour les refléter dans son propre affichage (`StockProduct`/`StockMovement` locaux, utilisés par `/stock`).

**Sens du flux — inversion complète par rapport à tous les sous-projets précédents** : jusqu'ici, PME360 → ERPNext (création locale, synchronisation silencieuse vers ERPNext). Ici : ERPNext → PME360 (création native ERPNext, notification vers PME360 via webhook, PME360 met à jour son affichage local).

**Conséquence assumée et confirmée par l'utilisateur** : le formulaire "Nouveau mouvement" de `/stock` (PME360) devient obsolète — la saisie de mouvements ne doit plus s'y faire. `StockController`/vues `stock.*` sont donc bien concernés par ce sous-projet, contrairement à la règle "ne pas toucher" des sous-projets Stock précédents.

## Découverte technique vérifiée empiriquement

- Le doctype `Webhook` est accessible et utilisable via l'API REST standard (`GET`/`POST /api/resource/Webhook`) — vérifié en listant 3 webhooks système déjà présents sur l'instance (mécanisme interne de synchronisation Frappe Cloud, sans rapport avec ce projet, à ne pas toucher).
- Structure d'un `Webhook` : `webhook_doctype` (doctype surveillé), `webhook_docevent` (ex: `on_submit`), `request_url`, `request_method: "POST"`, `request_structure: "JSON"`, `webhook_json` (gabarit Jinja2, ex: `{{ doc.name }}`), `webhook_headers` (table enfant de paires clé/valeur — sert à porter un jeton d'authentification partagé), `enable_security` (signature HMAC, non utilisée ici — on préfère un jeton statique dans l'en-tête, même patron que `OPCACHE_RESET_TOKEN` déjà utilisé dans ce projet pour l'endpoint `/internal/opcache-reset`).
- Un `Stock Entry`/`Stock Reconciliation` créé et soumis dans l'UI native ERPNext déclenche un `on_submit` — c'est le bon événement (un brouillon non soumis n'a pas d'effet réel sur le stock, donc rien à notifier avant soumission).
- Le payload JSON envoyé par le webhook reste volontairement minimal (`doctype`, `name`, `company`) — **PME360 relit ensuite le document complet via l'API existante** (pas de dépendance à une reconstruction JSON complexe côté Jinja pour les lignes d'articles, qui sont une table enfant).

## Décisions verrouillées

- **Deux Webhooks ERPNext créés via l'API** (pas manuellement dans l'UI, pour rester scriptable/reproductible) :
  1. `webhook_doctype: "Stock Entry"`, `webhook_docevent: "on_submit"`.
  2. `webhook_doctype: "Stock Reconciliation"`, `webhook_docevent: "on_submit"`.
  Tous deux pointent vers la même route PME360 `POST /webhooks/erpnext/stock-movement`, avec `webhook_json: '{"doctype": "{{ doc.doctype }}", "name": "{{ doc.name }}", "company": "{{ doc.company }}"}'` et un en-tête `X-PME360-Webhook-Token` (jeton statique partagé, nouvelle variable d'env `ERPNEXT_WEBHOOK_TOKEN`).
- **Résolution de la PME** : `User::where('erpnext_company_name', $payload['company'])->first()` — si aucune PME ne correspond, retourner une réponse 200 (pour qu'ERPNext ne retente pas indéfiniment) mais ne rien traiter, et logger un avertissement.
- **Résolution de l'article local** : chaque ligne (`items`) du document ERPNext relu porte déjà `item_name` (le libellé d'origine, ex: "Produit test stock") — on retrouve/crée le `StockProduct` local correspondant par ce nom exact pour cette PME (`StockProduct::firstOrCreate(['user_id' => $pme->id, 'name' => $itemName], [...])`), symétrique de la résolution `findOrCreateItem($product->name)` déjà utilisée dans l'autre sens.
- **Quantité/valorisation appliquées localement** : relues depuis `Bin` (déjà `ErpNextClient::getBinForItem()`, réutilisée telle quelle) — jamais recalculées côté PME360.
- **Type de mouvement local créé** : `stock_entry_type: "Material Receipt"` → `entree` ; `"Material Issue"` → `sortie` ; `Stock Reconciliation` → `ajustement`. Le nouveau `StockMovement` est marqué `reason: "Créé depuis ERPNext (" . $docname . ")"` pour rester traçable/distinguable dans l'historique.
- **Le formulaire de création locale est retiré** de `/stock` (vue produit) — remplacé par un message indiquant que la saisie se fait désormais sur ERPNext. La route `stock.movements.store` et `StockController::storeMovement()` sont supprimées (plus `StockService::recordMovement()` n'est plus appelée par aucun contrôleur — la méthode elle-même reste en place dans `StockService`, non supprimée, au cas où elle resservirait, mais n'est plus câblée nulle part).
- **Sécurité de l'endpoint entrant** : vérification stricte du jeton partagé (`X-PME360-Webhook-Token`) avant tout traitement — 403 sinon. Endpoint hors groupe `auth`/CSRF (comme un vrai webhook), avec `throttle` dédié.
- **Idempotence** : chaque appel webhook relit l'état réel (`Bin`) plutôt que d'appliquer un delta — rejouer deux fois la même notification (ex: retry réseau d'ERPNext) donne le même résultat final, pas de doublon de quantité. Un nouveau `StockMovement` sera quand même créé à chaque appel réel (représentant un vrai événement ERPNext) — pas de déduplication par `docname` dans cette v1 (chaque Stock Entry ERPNext ne déclenche qu'un seul `on_submit`, donc un seul appel attendu en usage normal).

## Architecture

### Nouvelle route + contrôleur

`POST /webhooks/erpnext/stock-movement` → `ErpNextStockWebhookController::handle()`, hors middleware `auth`/`web` CSRF, avec `throttle:60,1`.

### Nouvelles méthodes `ErpNextClient`

```php
public function getDocument(string $doctype, string $name): array
```
Wrapper générique public autour du `get()` privé existant, pour relire n'importe quel document par doctype+nom (Stock Entry, Stock Reconciliation) — première méthode générique de ce type dans `ErpNextClient` (jusqu'ici chaque lecture était spécifique à un besoin métier).

### Modification de `StockController`/vues

Suppression du formulaire de création de mouvement dans `stock.show` (remplacé par un message), suppression de la route/action `storeMovement`.

## Tests (vérification manuelle)

1. Créer les 2 Webhooks via l'API sur l'instance de test, vérifier leur présence (`GET /api/resource/Webhook`).
2. Dans l'UI ERPNext, créer et soumettre un `Stock Entry` (Material Receipt) pour la Company de test → vérifier que PME360 reçoit l'appel webhook et crée/actualise le bon `StockProduct` local avec la quantité exacte lue depuis `Bin`.
3. Créer et soumettre un `Stock Reconciliation` → vérifier que le `StockMovement` local est marqué `ajustement` avec la quantité absolue correcte.
4. Vérifier que `/stock` ne propose plus de formulaire de création, uniquement l'affichage.
5. Tester le rejet d'un appel webhook sans le bon jeton → 403.
6. Tester un appel webhook pour une Company qui ne correspond à aucune PME locale → réponse 200 silencieuse, rien de cassé.

## Fichiers concernés

- `app/Services/ErpNextClient.php` (modifié — nouvelle méthode `getDocument()`)
- `app/Http/Controllers/ErpNextStockWebhookController.php` (nouveau)
- `routes/web.php` (nouvelle route, hors groupe `auth`)
- `resources/views/stock/show.blade.php` (modifié — formulaire de création retiré)
- `app/Http/Controllers/StockController.php` (modifié — suppression de `storeMovement()`)
- `.env` / `config/services.php` (nouvelle variable `ERPNEXT_WEBHOOK_TOKEN`)

Ne pas toucher : `StockService::recordMovement()` (conservée telle quelle, simplement plus appelée), `createProduct()`/`updateProduct()`/`deleteProduct()`, le dashboard de test admin `/admin/erpnext-stock-test`, le sous-projet "ERPNext comme moteur" livré juste avant (son code reste en place, simplement plus sollicité une fois le formulaire retiré).
