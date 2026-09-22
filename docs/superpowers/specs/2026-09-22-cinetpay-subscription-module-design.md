# Module Abonnement CinetPay (ERPNext) — Design

## Contexte

Deux systèmes connectés : **PME360** (Laravel 13, LWS, `sitiame-capital.com`) et **ERPNext** auto-hébergé (VPS Docker, app custom `sitiame_core`). Ils communiquent déjà via des webhooks HTTP authentifiés par un jeton partagé (header `X-PME360-Webhook-Token`) : comptabilité, facturation, stock, inscription PME, dossiers de financement sont déjà en place selon ce patron.

PME360 gère un modèle premium/freemium sur son modèle `User` : `is_premium`, `premium_status`, `premium_ends_at`. Jusqu'ici rien n'automatise le paiement de cet abonnement — c'est le sujet de ce module.

**Objectif** : depuis ERPNext, un admin/comptable génère un lien de paiement CinetPay pour l'abonnement premium d'une PME (tarif fixe : 15 000 F CFA / 1 mois). Une fois le paiement confirmé auprès de CinetPay, ERPNext notifie PME360 pour activer/prolonger le premium de la PME concernée.

## API CinetPay Business utilisée

- `baseUrl` = `https://api.cinetpay.net`
- Auth : `POST {baseUrl}/v1/oauth/login` avec `{"api_key": ..., "api_password": ...}` → `access_token` bearer, `expires_in` secondes.
- Init paiement : `POST {baseUrl}/v1/payment` → répond `{code, status, merchant_transaction_id, notify_token, transaction_id, payment_token, payment_url, details}`.
- Statut : `GET {baseUrl}/v1/payment/{merchant_transaction_id}` → `{code, status, merchant_transaction_id, transaction_id, user, payment_method}`. `status` ∈ SUCCESS / ERROR / INITIATED (+ WAITING, FAILED observés en usage réel).

**Règle de sécurité imposée par la doc CinetPay elle-même** : ne jamais faire confiance au `status` reçu dans le webhook (`notify_url`) — n'importe qui peut appeler cette URL publique avec un faux payload. La seule source de vérité est le `GET /v1/payment/{merchant_transaction_id}`, à appeler systématiquement à réception d'une notification, avant toute mise à jour d'état.

## Architecture

Tout le nouveau code vit dans `sitiame_core` (ERPNext), sur le même patron que les modules Scoring/Financement déjà en place (Desktop Icon + Workspace Sidebar + doctype custom + contrôleur webhook Python). Un webhook symétrique est ajouté côté PME360 pour recevoir la confirmation, sur le patron déjà établi par `ErpNextPmeRegistrationWebhookController`/`ErpNextFinancingDossierWebhookController`.

```
Admin ERPNext                CinetPay                    PME360
     |                          |                            |
     | 1. crée Subscription     |                            |
     |    Payment (company,     |                            |
     |    amount=15000,         |                            |
     |    duration=1)           |                            |
     |                          |                            |
     | 2. clique "Générer       |                            |
     |    le lien"              |                            |
     |------- POST /v1/payment ->                            |
     |<---- payment_url, transaction_id, notify_token ------- |
     |                          |                            |
     | 3. admin partage         |                            |
     |    payment_url à la PME  |                            |
     |    (hors périmètre)      |                            |
     |                          |                            |
     |                     4. PME paie                        |
     |                          |                            |
     |<---- POST notify_url (status non fiable) --------------|
     | 5. vérifie notify_token,                                |
     |    puis GET /v1/payment/                                |
     |    {merchant_transaction_id}                            |
     |------- GET status ------>|                            |
     |<---- statut réel --------|                            |
     |                          |                            |
     | 6. si SUCCESS: doc =     |                            |
     |    "Payé", paid_at       |                            |
     |------- POST /webhooks/erpnext/subscription-paid ------->|
     |                          |          7. is_premium=true, |
     |                          |             premium_ends_at  |
     |<-------------------------------------- 200 OK -----------|
```

## Modèle de données — doctype `Subscription Payment` (sitiame_core)

| Champ | Type | Détail |
|---|---|---|
| `company` | Link → Company | PME concernée (obligatoire) |
| `amount` | Currency | Défaut `15000`, modifiable avant génération du lien |
| `duration_months` | Int | Défaut `1`, modifiable avant génération du lien |
| `status` | Select : `En attente` / `Payé` / `Échoué` | Défaut `En attente` |
| `transaction_id` | Data, read-only | Renseigné après génération du lien |
| `notify_token` | Data, read-only, hidden | Renseigné après génération du lien, utilisé pour authentifier le webhook entrant |
| `payment_url` | Data, read-only | Lien à partager manuellement à la PME |
| `paid_at` | Datetime, read-only | Renseigné à la confirmation du paiement |
| `pme360_notified_at` | Datetime, read-only | Renseigné après appel réussi du webhook vers PME360 |

Une fois `transaction_id` renseigné (lien généré), `company`/`amount`/`duration_months` deviennent non modifiables (évite de changer les termes d'un paiement déjà initié côté CinetPay).

Naming series : `SUB-.YYYY.-` (cohérent avec `FD-.YYYY.-` de Financing Dossier).

## Flux détaillé

### 1. Génération du lien (bouton custom sur le doctype)

- Appelle `sitiame_core.api.cinetpay_client` (nouveau module) :
  - Récupère/rafraîchit le token OAuth CinetPay (mis en cache via `frappe.cache`, invalidé après `expires_in`).
  - `POST /v1/payment` avec `merchant_transaction_id` = nom du document (`SUB-2026-00001`), `amount`, `currency=XOF`, `notify_url` = `https://erp.sitiame-capital.com/api/method/sitiame_core.api.cinetpay_subscription_webhook`, `success_url`/`failed_url` = pages ERPNext génériques de confirmation, `designation` = "Abonnement premium Sitiame — {company}".
  - Sauvegarde `transaction_id`, `notify_token`, `payment_url` sur le document via `db_set`.
- Si l'appel échoue (HTTP non-2xx ou `status != "OK"`) : `frappe.throw` avec le message d'erreur CinetPay, le document reste "En attente" sans `transaction_id`.

### 2. Réception du webhook (`sitiame_core.api.cinetpay_subscription_webhook`, whitelisted, `allow_guest=True`)

1. Lit `merchant_transaction_id` et `notify_token` du payload POST.
2. Retrouve le `Subscription Payment` par `transaction_id`/nom. Si introuvable → 200 (idempotence, ignore silencieusement — évite de fuiter l'existence d'un ID).
3. Compare `notify_token` reçu à celui stocké. Si différent → HTTP 403, `frappe.log_error`, ne touche pas le document.
4. Si le document est déjà `Payé` → 200 immédiat (idempotence, ne rejoue rien).
5. Appelle `GET /v1/payment/{merchant_transaction_id}` pour obtenir le statut réel.
6. Si statut réel = `SUCCESS` : `status = "Payé"`, `paid_at = now()`, puis déclenche l'étape 3 (notification PME360).
7. Si statut réel = `FAILED` : `status = "Échoué"`.
8. Sinon (WAITING, INITIATED...) : aucune transition, réponse 200 quand même.
9. Toute la fonction répond HTTP 200 en moins de 10s (pas d'appel bloquant long ; l'appel à PME360 en étape 3 est synchrone mais avec un timeout court de 5s — si PME360 ne répond pas, ça ne bloque pas la réponse à CinetPay au-delà de ce délai).

### 3. Notification vers PME360

- Nouvel appel HTTP `POST https://sitiame-capital.com/webhooks/erpnext/subscription-paid`, header `X-PME360-Webhook-Token` (même secret partagé existant), payload `{"company": "<nom Company ERPNext>", "duration_months": 1, "paid_at": "<iso datetime>"}`.
- Si l'appel réussit (200) : `pme360_notified_at = now()`.
- Si l'appel échoue : rien n'est modifié côté PME360 ; le document ERPNext reste "Payé" avec `pme360_notified_at` vide. Un bouton custom "Renvoyer à PME360" sur le doctype permet de rejouer uniquement cette étape 3 sans re-générer de paiement.

### 4. Côté PME360 — nouveau webhook `POST /webhooks/erpnext/subscription-paid`

Nouveau contrôleur `ErpNextSubscriptionPaidWebhookController` (patron identique à `ErpNextFinancingDossierWebhookController`) :
- Authentifie via `X-PME360-Webhook-Token`.
- Résout la PME via `User::where('erpnext_company_name', $payload['company'])->firstOrFail()`.
- Calcule `premium_ends_at` : si `premium_ends_at` actuel est dans le futur (renouvellement anticipé), ajoute `duration_months` à cette date ; sinon part de `now()`.
- Met à jour `is_premium = true`, `premium_status = 'active'`, `premium_ends_at` calculé.
- Route exemptée de CSRF dans `bootstrap/app.php` (comme les webhooks ERPNext existants).

## UI ERPNext

- **Desktop Icon** "Abonnement" : `standard=0`, `owner=Administrator` (pattern obligatoire établi après l'incident de suppression par `bench migrate` sur les tuiles Scoring/Financement).
- **Workspace Sidebar** "Abonnement" : un item pointant vers la liste `Subscription Payment` (DocType).
- Pas de tuile/sidebar séparée pour la configuration : le tarif (15000F/1 mois) est simplement la valeur par défaut du doctype, pas une page de réglage distincte.

## Hors périmètre (explicitement exclu par l'utilisateur ou par simplicité)

- Pas d'envoi automatique du lien de paiement à la PME (email/SMS/WhatsApp) — partage manuel par l'admin.
- Pas de facturation récurrente automatique (CinetPay Business ne propose pas d'endpoint d'abonnement récurrent dans la doc consultée) — chaque période se paie via un nouveau `Subscription Payment` créé manuellement.
- Pas de page de configuration tarifaire — tarif fixe en dur comme valeur par défaut.
- Pas d'usage de l'API `/v1/transfer` (payout) — hors périmètre de ce module, réservé à un usage futur éventuel.
- Pas de rapport de réconciliation dédié — la liste filtrable du doctype suffit au volume attendu.

## Tests

- **ERPNext** : script one-off `bench execute` simulant : génération de lien (avec mock ou en sandbox via la clé `sk_test_...` déjà en main), réception webhook avec `notify_token` valide/invalide, vérification de l'idempotence (webhook rejoué deux fois), vérification que le statut ne bascule qu'après le `GET` de confirmation (jamais sur la seule foi du payload POST).
- **PME360** : test Feature sur `ErpNextSubscriptionPaidWebhookController` — payload valide/invalide, jeton correct/incorrect, calcul correct de `premium_ends_at` en cas de renouvellement anticipé vs premium déjà expiré, route exemptée de CSRF vérifiée.
