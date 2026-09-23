# Page admin "Utilisateurs de la plateforme" — Design

## Contexte

Sous-projet B, suite de [[2026-09-23-pme-erpnext-accounts-design]] (livré et déployé). Trois admins ERPNext (`fnguessan@sitiame-capital.com`, `joseph@sitiame-capital.com`, `kyliyanisse@gmail.com`) veulent une vue d'ensemble des PME inscrites sur PME360 : statut de paiement, activité de connexion, date d'inscription/essai gratuit.

**Bonne nouvelle découverte en explorant le code** : PME360 trace déjà tout ce qu'il faut, rien à construire côté suivi de données :
- `UserLoginLog` (table déjà active, alimentée à chaque login/logout — déjà utilisée pour un tableau de bord équivalent côté équipe commerciale, `CommercialTeamOverviewService`).
- `premium_trial_ends_at`, `is_premium`, `premium_status`, `premium_ends_at` (déjà gérés par le module Abonnement CinetPay livré cette session).
- `created_at` (date d'inscription, standard Eloquent).

## Architecture

### 1. PME360 — nouvel endpoint `GET /webhooks/erpnext/platform-users`

Même patron exact que `get_financing_dossier_from_pme360`/`ErpNextFinancingDossierWebhookController` déjà en place : authentification par jeton partagé `X-PME360-Webhook-Token`, lecture seule.

**Périmètre des utilisateurs retournés** : `User::whereNotNull('erpnext_company_name')` — même filtre déjà validé dans `ProvisionErpNextAccessForExistingPmes` (sous-projet A) pour identifier "une vraie PME provisionnée", par opposition aux comptes staff/commercial internes qui n'ont pas de société ERPNext.

**Champs par PME** :
- `email`, `name` (contact), `company_name`, `erpnext_company_name`
- `is_premium`, `premium_status`
- `premium_trial_ends_at`, `premium_ends_at`
- `registered_at` (= `created_at`)
- `last_login_at` (calculé via `UserLoginLog::where('event','login')->selectRaw('user_id, MAX(created_at) as last_login_at')->groupBy('user_id')`, même requête que `CommercialTeamOverviewService`)

### 2. ERPNext — nouvelle page `sitiame_core` (patron `erp_financial_ranking.js`)

Tableau : Société / Email / Statut / Fin d'essai gratuit / Fin d'abonnement / Inscrit le (date+heure) / Dernière connexion.

**Statut affiché**, calculé côté page à partir des champs bruts :
- `is_premium = true` → **"Payant"**
- `is_premium = false` et `premium_trial_ends_at` dans le futur → **"Essai gratuit"** (avec le nombre de jours restants)
- sinon → **"Expiré"**

### 3. Double protection d'accès (pas juste "System Manager")

- **Contrôle serveur strict** dans la fonction whitelisted `sitiame_core.api.get_platform_users` : liste en dur `["fnguessan@sitiame-capital.com", "joseph@sitiame-capital.com", "kyliyanisse@gmail.com"]`, `frappe.throw(..., frappe.PermissionError)` sinon — refuse même à un autre System Manager.
- **Tuile masquée par défaut** pour les comptes admin déjà existants qui ne sont pas dans cette liste (ekonan, Ahoulou Ariel, Chrys-Ivan) via le mécanisme `sitiame_hidden_desktop_icons` déjà en place (un-off script sur ces 3 comptes). Un futur nouveau compte admin devra être traité au cas par cas (pas automatisé dans ce sous-projet — hors périmètre).

## Gestion des erreurs

- Si `pme360_webhook_token`/`pme360_base_url` non configurés ou PME360 injoignable : la page affiche un message d'erreur clair au lieu de planter (même style que les autres pages `erp_*` déjà en place — `frappe.msgprint`/zone de texte d'erreur).
- Si un utilisateur non autorisé accède directement à l'URL de la page (contournant la tuile masquée) : bloqué par le contrôle serveur strict de la section 3, pas seulement par l'absence de tuile.

## Hors périmètre

- Pas d'automatisation pour restreindre l'accès aux futurs nouveaux comptes admin — geste manuel à chaque nouvel admin créé.
- Pas d'action possible depuis cette page (ex: forcer un renouvellement, désactiver un compte) — lecture seule dans cette itération.
- Pas de pagination/recherche avancée — la liste actuelle est petite (quelques PME), un tableau simple suffit.

## Tests

- PME360 : test Feature sur le nouvel endpoint (patron `ErpNextFinancingDossierTest`) — jeton correct/incorrect, liste filtrée correctement (seuls les comptes avec `erpnext_company_name` apparaissent), champs de statut/dates corrects pour un compte payant vs essai vs expiré.
- ERPNext : vérification manuelle en sandbox après déploiement — connecté en tant que `kyliyanisse@gmail.com`, la tuile et la page fonctionnent ; connecté en tant qu'ekonan (ou tentative d'URL directe), accès refusé.
