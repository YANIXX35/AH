# Interface « Chargé d'affaires » dans ERPNext — Design

## Contexte

Demande utilisateur (2026-09-29) : créer une interface ERPNext pour les chargés d'affaires Sitiame, avec seulement les modules utiles.

**Décisions utilisateur** (recommandations acceptées par « ok vasy ») :
1. **Portefeuille** : un chargé d'affaires ne voit **que les PME qui lui sont attribuées**.
2. **Quatre yeux** : il **propose** (notes, dossiers) ; la **décision** de crédit et l'avis de financement reviennent à un validateur (System Manager ou Analyste Credit Sitiame).
3. **Liens de paiement** : il peut générer un lien Jèko pour une PME de son portefeuille.

Existant (vérifié) :
- Le « commercial » de PME360 fait de la prospection. Il n'y a **aucune attribution de PME à une personne** synchronisée vers ERPNext : ERPNext porte sa propre attribution.
- La Liasse SYSCOHADA, le Journal des écritures et Scoring 360 (`get_scoring360_score`, `get_company_signup_info`) appellent tous `frappe.has_permission("Company", "read", company, throw=True)`. **Restreindre la lecture de Company au portefeuille verrouille donc ces rapports.**
- `staff_isolation.py` définit un « staff » comme un utilisateur système **sans** User Permission sur Company. Donner des User Permission Company au chargé d'affaires le ferait passer pour une PME (`get_user_company`, page Abonnement). **On n'utilise donc pas les User Permissions** : on utilise des hooks de permission.

## 1. Attribution du portefeuille

- Champ personnalisé sur **Company** : `sitiame_charge_affaires` (Link User, « Chargé d'affaires »). Il est créé par `after_migrate`, avec un filtre sur les utilisateurs actifs qui ont le rôle.
- Seul un System Manager (ou un Analyste Credit Sitiame) le modifie.
- Page d'accueil admin : colonne « Chargé d'affaires » + modification en masse. Ce point est optionnel, et la fiche Company suffit en V1.

## 2. Rôle « Charge d'affaires Sitiame »

Il est créé comme les rôles existants (patch `pre_model_sync` + `after_migrate`). La constante est dans `sitiame_core/roles.py`.

| Élément | Droits |
|---|---|
| Company | Lecture, **portefeuille uniquement** (hook) |
| Credit Scoring Dossier | Lire / créer / modifier, portefeuille uniquement, **pas de suppression** |
| Financing Dossier | Idem |
| Scoring 360 Settings | Aucun |
| Page Classement financier (`erp-financial-ranking`) | Oui, **filtré au portefeuille** |
| Liasse SYSCOHADA (rapport) | Oui : le rôle est ajouté au rapport, verrouillé par Company |
| Documents KYC (`erp-kyc`) | Lecture, filtré au portefeuille |
| Abonnements (`create_company_payment_link`, liste) | Générer un lien + voir l'état, **portefeuille uniquement** |
| CRM : Lead, Opportunity, Prospect, Contact, Event, ToDo | Créer / modifier les siens |
| Comptabilité en écriture, Stock, Achats, RH/Paie, Paramètres, Administration | **Aucun** |

Les rôles ERPNext standards (Sales User, etc.) **ne sont pas** donnés : ils ouvrent les factures. Les droits CRM sont ajoutés par Custom DocPerm pour ce rôle seul.

## 3. Filtrage par portefeuille (`sitiame_core/portfolio.py`)

- `portfolio(user)` renvoie les Company où `sitiame_charge_affaires = user`. Le résultat est mis en cache pour la requête.
- `is_charge_affaires(user)` est vrai si l'utilisateur a le rôle **sans** System Manager ni Analyste Credit Sitiame.
- **Hooks** `permission_query_conditions` + `has_permission` sur **Company**, **Credit Scoring Dossier** et **Financing Dossier**. Pour un chargé d'affaires, ils limitent au portefeuille. Pour les autres utilisateurs, rien ne change.
- Les hooks existants de `staff_isolation` sur d'autres doctypes ne changent pas. Les deux mécanismes s'additionnent.
- `get_financial_ranking` : pour un chargé d'affaires, le classement est filtré sur son portefeuille.
- `list_erp_kyc_documents` et `list_company_subscriptions` : ouverts au rôle, filtrés au portefeuille.
- `create_company_payment_link(company)` : ouvert au rôle si la société est dans son portefeuille.

## 4. Quatre yeux (proposer / valider)

Dans le `validate` des deux doctypes, pour un chargé d'affaires :
- **Credit Scoring Dossier** : `decision` doit rester « En attente », et `validator` ne peut pas être saisi. Il remplit les notes, les pièces et le champ `ready` (« prêt pour décision »).
- **Financing Dossier** : `status` n'accepte que « A examiner » ou « En cours d'analyse ». « Avis favorable », « Avis defavorable » et « Ajourne » sont réservés aux validateurs.
- Message clair en cas de refus : « La décision revient à un validateur. »
- Côté formulaire, les champs de décision sont en lecture seule pour ce rôle (confort d'affichage). La règle serveur fait foi.

## 5. Espace de travail « Chargé d'affaires »

- Page `erp-charge-affaires`, avec un Workspace Sidebar dédié et une icône d'accueil « Chargé d'affaires ». Rôles : Charge d'affaires Sitiame et System Manager.
- Contenu de la page (API `get_charge_affaires_dashboard`) :
  - **Mon portefeuille** : un tableau de ses PME avec le score et la note Scoring 360, le statut d'abonnement (Actif / Essai / Expiré), le dernier dossier de scoring et son état, et le dernier dossier de financement et son statut. Chaque ligne a des actions rapides : ouvrir le dossier, Liasse SYSCOHADA, KYC, lien de paiement.
  - **Mes relances** : ToDo/Event ouverts assignés à lui, et Opportunités dont la date de relance est échue.
  - **Dossiers à compléter** : scorings non « prêts » et financements « A examiner ».
- Barre latérale : Mon portefeuille, Prospects (Lead), Opportunités, Dossiers de scoring, Dossiers de financement, Classement financier, Liasse SYSCOHADA, Documents KYC, Abonnements.
- Icônes masquées pour ce rôle : tout le reste (même mécanisme `sitiame_hidden_desktop_icons`, appliqué à la création du compte).

## Hors périmètre (V1)

Synchronisation de l'attribution avec PME360 ; objectifs et commissions ; rapports de prospection (ils restent dans PME360) ; circuit de validation multi-niveaux (comité).

## Vérification

- **pytest** :
  - hooks portefeuille : un chargé d'affaires ne voit que ses sociétés et dossiers, les autres profils ne changent pas ;
  - quatre yeux : décision refusée pour le chargé d'affaires, acceptée pour le validateur ;
  - `create_company_payment_link` refusé hors portefeuille ;
  - classement filtré ;
  - droits JSON du rôle.
- **Production** : compte de test chargé d'affaires avec 2 PME attribuées. Vérifications :
  - il voit ces 2 PME et pas les autres (liste Company, Liasse sur une PME hors portefeuille → refus) ;
  - il crée un dossier mais ne peut pas le décider ;
  - il génère un lien Jèko pour sa PME ;
  - capture de l'espace de travail.
- Le compte de test est supprimé à la fin.
