# Comptes ERPNext pour les PME clientes — Design

## Contexte

Aujourd'hui, quand une PME s'inscrit (sur PME360, ou via "Inscrire une PME" côté ERPNext), le provisioning ERPNext (`ProvisionErpNextCompanyForPme` → `ErpNextClient::provisionCompanyForPme()`) crée une `Company`, un `Warehouse` et un modèle de taxes ERPNext — **mais aucun compte de connexion ERPNext**. Les PME n'ont donc aujourd'hui aucun accès direct à ERPNext ; seul le staff Sitiame (comptes System Manager) s'y connecte.

**Objectif** : donner à chaque PME un vrai compte de connexion ERPNext, automatiquement à l'inscription, restreint à :
- Voir uniquement les données de **leur propre société** (aucune fuite vers les autres PME).
- Voir uniquement 11 modules pertinents sur le Desk : Financement, Scoring, Vente, Achat, Stock, Comptabilité, Subcontracting, Abonnement, Production, Projets, Actifs/Immobilisations.

## Point de sécurité central : cloisonnement par société

Sans restriction explicite, un compte avec les rôles Vente/Achat/Stock/Comptabilité verrait par défaut les données de **toutes** les sociétés ERPNext (toutes les PME clientes), pas seulement la sienne — Frappe ne filtre par société que si un enregistrement `User Permission` (`allow="Company"`, `for_value=<leur société>`) existe pour ce compte. C'est un prérequis non négociable de ce design, posé à la création de chaque compte PME.

**Vérification à faire pendant l'implémentation** (pas supposée acquise) : confirmer qu'aucun des doctypes utilisés par les 11 modules (Sales Invoice, Purchase Order, Stock Entry, etc.) n'a son champ `company` marqué `ignore_user_permissions` — auquel cas la restriction ne s'appliquerait pas automatiquement pour ce doctype et il faudrait une correction ciblée (permission query condition côté `sitiame_core`).

## Rôle "PME Client"

Nouveau `Role` Frappe dédié, remplaçant `System Manager` pour ces comptes (jamais donné à une PME — trop large). Combine :
- Le bundle standard déjà utilisé pour les comptes staff (voir mémoire `feedback_erpnext_account_roles.md`) : `Sales User`, `Sales Manager`, `Purchase Manager`, `Purchase Master Manager`, `Stock Manager`, `Stock User`, `Item Manager`, `Accounts Manager` — nécessaire pour que Vente/Achat/Stock/Comptabilité/Production/Projets/Subcontracting fonctionnent normalement une fois le compte restreint à sa société.
- **Lecture seule** ajoutée sur trois doctypes custom `sitiame_core` qui n'accordaient jusqu'ici la permission qu'à `System Manager` : `Financing Dossier`, `Credit Scoring Dossier`, `Subscription Payment`. La PME consulte (statut de son dossier de financement, sa note de crédit, l'historique de ses paiements d'abonnement) mais ne crée ni ne modifie — ces documents restent gérés par le staff Sitiame ou générés automatiquement par le système (ex. lien de paiement CinetPay). Pas d'accès à `Scoring 360 Settings` (config interne du moteur de scoring, jamais montrée à une PME).

## Visibilité des tuiles et sous-menus

Réutilise le mécanisme déjà construit ("Gérer le menu", champs `sitiame_hidden_desktop_icons`/`sitiame_hidden_sidebar_items` sur `User`, filtré dans `boot.py`) — automatisé à la création au lieu d'être posé manuellement :
- **Tuiles** : calculées dynamiquement à la création (toutes les `Desktop Icon` existantes moins les 11 autorisées), pas une liste figée dans le code — reste correcte si de nouvelles tuiles sont ajoutées plus tard sans mise à jour du code de provisioning.
- **Sous-menu Scoring** : le sidebar que voit le staff contient aussi "Classement financier" (toutes les PME) et "Scoring 360 Settings" (config du moteur) — masqués spécifiquement pour les comptes PME, ne laissant que leur propre "Dossier de notation de crédit".

## Où ça se déclenche

Tout se passe côté PME360 dans `ErpNextClient::provisionCompanyForPme()` (déjà appelée par `ProvisionErpNextCompanyForPme` à chaque inscription), en réutilisant l'API REST ERPNext générique déjà configurée (`ERPNEXT_API_KEY`/`ERPNEXT_API_SECRET`) — pas de nouvel endpoint côté ERPNext :

1. `POST /api/resource/User` : email = celui de la PME sur PME360, mot de passe `SITIAME2026!`, rôle `PME Client` + bundle standard, et directement les champs `sitiame_hidden_desktop_icons`/`sitiame_hidden_sidebar_items` calculés — Frappe accepte tout ça en une seule requête de création.
2. `POST /api/resource/User Permission` : `allow=Company`, `for_value=<société de la PME>`, `user=<le compte créé>`.

## Comptes déjà inscrits (rétroactif)

Nouvelle commande Artisan `php artisan pme:provision-erpnext-access` : parcourt tous les `User` PME360 ayant déjà un `erpnext_company_name` renseigné, et rejoue exactement la même logique de création de compte ERPNext. Ne touche **jamais** aux comptes déjà existants (le script ne cible que les PME identifiées par leur `erpnext_company_name`, jamais le staff Sitiame comme kyliyanisse/joseph/fnguessan — dont les mots de passe actuels ne doivent en aucun cas être modifiés).

## Mot de passe

`SITIAME2026!` par défaut (même convention que `ErpNextPmeRegistrationWebhookController::DEFAULT_PASSWORD`), modifiable ensuite par la PME. Les comptes déjà existants (staff et PME déjà provisionnées manuellement, s'il y en a) ne sont jamais réinitialisés par ce mécanisme.

## Gestion des erreurs

Si la création du `User` ou du `User Permission` échoue (email déjà utilisé côté ERPNext par un autre compte, API indisponible...), ça ne bloque pas le provisioning de la `Company`/`Warehouse`/taxes — même résilience que le code existant (`try/catch` + `Log::warning`, provisioning de la Company non affecté).

## Hors périmètre

- Le module Abonnement reste **lecture seule** pour la PME dans cette itération (pas de self-service de paiement — décision explicite de l'utilisateur, "à revoir plus tard").
- Pas de page admin de suivi plateforme (sous-projet B séparé, spec à venir).
- Pas d'email de bienvenue/notification automatique envoyée à la PME pour l'informer de son nouvel accès — hors périmètre de cette itération.

## Tests

- Étendre la suite PHPUnit existante sur `ErpNextClient` (patron `Http::fake` déjà utilisé dans les tests du projet) : vérifier que `provisionCompanyForPme()` envoie bien les requêtes `POST /api/resource/User` (avec les bons rôles et champs cachés) et `POST /api/resource/User Permission` (bonne société), en plus des requêtes déjà testées pour la Company.
- Vérification manuelle en sandbox après déploiement : inscrire une PME de test, confirmer qu'elle peut se connecter à ERPNext, ne voit que les 11 tuiles autorisées, et que ses listes (factures, stock...) ne montrent que les données de sa propre société.
