# Interface « Chargé d'affaires » ERPNext — Plan

Spec : `docs/superpowers/specs/2026-09-29-erpnext-charge-affaires-design.md`. Dépôt : `sitiame_core` (commits limités aux fichiers de la tâche, déploiement `bash /root/sitiame_core_deploy.sh`).

## Tâche 1 — Rôle, champ portefeuille, droits

- `roles.py` : `CHARGE_AFFAIRES_ROLE = "Charge d'affaires Sitiame"`, `VALIDATOR_ROLES = ("System Manager", CREDIT_ANALYST_ROLE)`.
- Patch `pre_model_sync` `create_charge_affaires_role`, plus `ensure_charge_affaires_role()` dans `after_migrate`.
- `setup._create_portfolio_field()` : Custom Field Company `sitiame_charge_affaires`, placé après `company_name`. Link User, libellé « Chargé d'affaires ».
- Doctypes JSON : permissions du rôle sur Credit Scoring Dossier et Financing Dossier (lire / créer / modifier / imprimer / exporter / rapport, sans supprimer). Rôle ajouté à la page `erp-financial-ranking`, à la Liasse SYSCOHADA et à la page `erp-kyc`. Bump de `modified`.
- `setup._grant_crm_permissions()` : `frappe.permissions.add_permission` puis `update_permission_property` sur Lead, Opportunity, Prospect, Contact, Event et ToDo. Cette fonction recopie les permissions standard avant d'écrire une Custom DocPerm. Le rôle obtient lire, créer, modifier, avec `if_owner` pour Lead et Opportunity. L'opération est idempotente.
- Tests : droits JSON, présence dans `after_migrate`.

## Tâche 2 — Filtrage portefeuille (`portfolio.py`)

- `is_charge_affaires(user)` : a le rôle et aucun `VALIDATOR_ROLES`.
- `portfolio(user)` : Company où `sitiame_charge_affaires = user`, en cache `frappe.local`.
- `get_permission_query_conditions(user, doctype)` + `has_permission(doc, ptype, user)` :
  - Company : `name in (portefeuille)` ;
  - Credit Scoring Dossier et Financing Dossier : `company in (portefeuille)` ;
  - Lead et Opportunity : `owner = user` ou responsable (`lead_owner` / `opportunity_owner`) = user.
  - Portefeuille vide : la condition est `1=0` ; `has_permission` renvoie True ou False, jamais une valeur falsy ambiguë.
- `hooks.py` : ajout de ces doctypes aux dicts `permission_query_conditions` / `has_permission`. Aucun ne recoupe `ISOLATED_DOCTYPES`, et un test le garantit.
- Tests : le chargé d'affaires ne voit que son portefeuille ; les autres profils ne changent pas (chaîne vide / True) ; un portefeuille vide ne voit rien.

## Tâche 3 — Quatre yeux

- `portfolio.check_decision(doc)`, appelé dans le `validate` des contrôleurs Credit Scoring Dossier et Financing Dossier. Pour un chargé d'affaires :
  - `decision` doit valoir « En attente » ;
  - `validator` doit être vide ;
  - le statut du financement doit être dans (« A examiner », « En cours d'analyse ») ;
  - sinon `frappe.throw` « La décision revient à un validateur ».
- JS des formulaires : champs de décision en lecture seule si l'utilisateur a le rôle sans rôle validateur.
- Tests : refus pour le chargé d'affaires, accepté pour le validateur, dossier neuf accepté.

## Tâche 4 — APIs ouvertes au rôle, filtrées

- `get_financial_ranking` : `only_for` + rôle ; pour un chargé d'affaires, le résultat est filtré sur le portefeuille.
- `list_erp_kyc_documents` : ouvert au rôle ; `company` imposé dans le portefeuille.
- `subscription_api.create_company_payment_link` : ouvert au rôle si `company` est dans le portefeuille, refus sinon.
- `subscription_api.list_company_subscriptions` : ouvert au rôle, filtré au portefeuille.
- `get_scoring_suggestions` / `get_company_signup_info` : déjà verrouillés par `has_permission("Company")`. Ajout du rôle à la liste autorisée de `get_scoring_suggestions`.
- Tests pour chacun.

## Tâche 5 — Espace de travail

- API `portfolio.get_charge_affaires_dashboard()` : réservée au rôle ou à System Manager. Elle renvoie :
  - **pme** : pour chaque société, `get_company_subscription`, le dernier Credit Scoring Dossier (score, grade, decision, ready) et le dernier Financing Dossier (status, montant) ;
  - **relances** : ToDo ouverts `allocated_to = user` et Opportunités avec `contact_date <= today` ;
  - **a_completer** : scorings non `ready` et financements « A examiner » du portefeuille.
- Page `erp-charge-affaires`, en JS sans dépendance, dans le style des pages existantes : trois blocs, tableau responsive, actions rapides (dossier, Liasse avec `?company=`, KYC, bouton « Lien de paiement » qui appelle `create_company_payment_link` et copie le lien).
- `setup.ensure_charge_affaires_workspace()` : Workspace Sidebar « Chargé d'affaires » + Desktop Icon, sur le même modèle que « Scoring » (`app` sitiame_core, `icon_type` Link, `link_type` Workspace Sidebar). Elément idempotent, avec les éléments listés dans la spec.
- `setup.hide_other_icons_for(user)` + bouton « Configurer comme chargé d'affaires » dans la page d'administration `erp-gerer-menu`, ou fonction appelée à l'ajout du rôle. Elle masque toutes les icônes sauf Chargé d'affaires, CRM, Scoring et Financement.
- Tests : forme du dashboard avec données stub, idempotence de la création sidebar/icône.

## Tâche 6 — Déploiement et vérification en production

1. pytest complet, puis commit / push / deploy.
2. Compte de test `ca.test@sitiame-capital.com` : rôle + 2 PME attribuées, dans une transaction annulée pour les données. Le compte est supprimé à la fin.
3. Vérifier :
   - la liste Company = 2 ;
   - la Liasse sur une PME hors portefeuille → PermissionError ;
   - la création d'un dossier OK, et la décision refusée ;
   - le lien Jèko hors portefeuille refusé (en portefeuille : appel Jèko seulement quand les clés seront installées) ;
   - le dashboard renvoie les 2 PME.
4. Capture de l'espace de travail (Chrome de débogage, connexion par l'utilisateur).
5. Mémoire : rôle et règles.
