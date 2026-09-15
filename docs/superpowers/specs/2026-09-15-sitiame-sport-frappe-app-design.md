# Appli Frappe minimale "Sitiame Sport" (icône d'accueil ERPNext) — Design

## Contexte

Depuis le début de cette session, l'affichage d'une icône "Sport" sur la page d'accueil ("Desk") d'ERPNext a été demandé plusieurs fois, et **confirmé impossible** sur le Shared Hosting Frappe Cloud (pas d'accès bench/SSH, donc pas de vraie appli Frappe installable — seul un `Module Def` rattaché à du vrai code d'appli backend peut porter un Workspace visible en icône d'accueil ; un `Workspace` créé "à la main" via l'API REST sans appli réelle derrière ne s'affiche jamais).

L'utilisateur a depuis souscrit à un **Private Bench** Frappe Cloud (`sitiame-bench-prod`, actif, Version 16, apps Frappe/Builder/ERPNext/HRMS/Paiements/Non-Profit) — l'accès bench existe donc maintenant, ce qui débloque ce chantier.

Le module Sport côté ERPNext existe déjà fonctionnellement (construit dans un sous-projet précédent de cette session) : les "membres" sont des `Customer` (groupés via `findOrCreateSportMemberGroup()`), les événements utilisent le `Event` natif de Frappe, les cotisations utilisent `Subscription`/`Subscription Plan` natifs d'ERPNext. Une exploration du code source cloné de l'appli `non_profit` (une vraie appli Frappe installée) a confirmé un point clé qui simplifie radicalement ce chantier : **un `Workspace` n'a pas besoin que l'appli hôte possède ses propres DocTypes** — ses liens peuvent pointer vers n'importe quel DocType déjà installé par une autre appli (preuve : le Workspace de `non_profit` lui-même contient des liens vers `Loan`/`Loan Type`, des DocTypes du module Prêts qu'il ne possède pas).

## Décision de conception

Construire une appli Frappe **minimale**, `sitiame_sport`, dont le seul rôle est de déclarer un Module + un Workspace avec icône — **aucun nouveau DocType, aucun code métier Python**. Le Workspace pointe vers les DocTypes déjà utilisés par l'intégration ERPNext existante : `Customer` (membres), `Event` (événements), `Subscription`/`Subscription Plan` (cotisations).

Structure du dépôt (calquée sur le schéma réel confirmé via `non_profit`, en gardant tout le superflu de côté — pas de `patches.txt`, pas de `config/desktop.py`, pas de `scheduler_events`, pas de doctype personnalisé) :

```
sitiame_sport/                              (racine du dépôt)
├── setup.py
├── requirements.txt                        (vide, juste un commentaire)
├── license.txt
└── sitiame_sport/                          (paquet Python)
    ├── __init__.py                         (__version__ = '0.0.1')
    ├── hooks.py
    ├── modules.txt                         (une ligne : "Club Sportif")
    └── sitiame_sport/                      (dossier du module, même nom que app_name)
        └── workspace/
            └── club_sportif/
                └── club_sportif.json
```

`hooks.py` contient uniquement les champs minimaux confirmés nécessaires (`app_name`, `app_title`, `app_publisher`, `app_description`, `app_email`, `app_license`) plus `required_apps = ["erpnext"]` (le module Sport dépend de `Customer`/`Event`/`Subscription`, tous fournis par `erpnext`/`frappe` de base).

Le Workspace JSON (`club_sportif.json`) suit exactement le schéma confirmé dans `non_profit.json` : `doctype: "Workspace"`, `module: "Club Sportif"` (doit correspondre exactement à la ligne de `modules.txt`), `icon` (un nom d'icône du jeu d'icônes Workspace de Frappe — ex. `"users"` ou `"non-profit"`, à choisir/tester), `public: 1`, `shortcuts` et `links` pointant vers `Customer`, `Event`, `Subscription`.

## Hors périmètre

- Aucun nouveau DocType (Member/Cotisation/Event personnalisés) — décision explicite, pour rester minimal et ne rien dupliquer de ce qui fonctionne déjà.
- Aucune logique Python (`doc_events`, `scheduler_events`, contrôleurs personnalisés) — l'appli n'existe que pour porter le Module + Workspace.
- Le filtrage "membres du club uniquement" (actuellement fait côté PME360 via `findOrCreateSportMemberGroup()`/`listSportMembers()`) n'est pas reproduit dans le Workspace lui-même — le raccourci "Membres" pointera vers la liste `Customer` standard d'ERPNext (l'utilisateur peut filtrer par groupe client dans l'interface native). Reproduire un filtre pré-appliqué dans un raccourci de Workspace demanderait une vue/liste personnalisée, hors scope pour une V1.

## Déploiement (processus, pas du code)

1. Le dépôt `sitiame_sport` est créé et poussé sur GitHub (nouveau dépôt séparé du dépôt Laravel `YANIXX35/AH`).
2. Sur le tableau de bord Frappe Cloud du bench `sitiame-bench-prod`, page "Applications" → "+ Ajouter une application" → "Ajouter depuis GitHub" → renseigner l'URL du nouveau dépôt et la branche.
3. "Déployer dès maintenant" sur le bench (comme lors de sa création).
4. Une fois déployé, installer l'appli sur le site ERPNext réel (`sitiame-erp-essai.z.frappe.cloud` ou le futur site de production) — ceci se fait soit depuis l'interface Frappe Cloud (bouton "Installer une application" sur la page du site), soit via `bench --site <site> install-app sitiame_sport` en SSH.
5. Vérification live : ouvrir `/desk` sur le site ERPNext, confirmer que l'icône "Club Sportif" apparaît sur la page d'accueil aux côtés de Comptabilité/Stock.

**Limite connue de cette session** : je (l'assistant) n'ai pas d'accès SSH/identifiants au bench Frappe Cloud (bloqué explicitement par le sandbox de sécurité lors d'une tentative précédente ce jour) — les étapes 2 à 4 ci-dessus doivent être réalisées par l'utilisateur lui-même dans l'interface Frappe Cloud, comme il l'a déjà fait pour la création du bench et le déploiement `deploy.sh` sur LWS. Je peux en revanche écrire tous les fichiers du dépôt `sitiame_sport` et, si un accès GitHub via `gh` est disponible dans cette session, le pousser sur un nouveau dépôt.

## Vérification

1. Le dépôt `sitiame_sport` est syntaxiquement valide (`python -m py_compile` sur chaque fichier `.py`, `python -m json.tool` sur `club_sportif.json` pour valider le JSON).
2. Après installation manuelle par l'utilisateur sur le bench : icône "Club Sportif" visible sur `/desk`, raccourcis "Customer"/"Event"/"Subscription" fonctionnels (ouvrent bien les listes correspondantes).
