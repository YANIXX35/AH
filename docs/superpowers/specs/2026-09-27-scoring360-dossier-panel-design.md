# Panneau Scoring 360 détaillé dans le Credit Scoring Dossier — Design

## Contexte

Depuis l'unification du 2026-09-22 (`2026-09-22-unify-scoring-engines-design.md`), `sitiame_core/scoring360_service.py::score_company()` est l'unique moteur de scoring. Il calcule déjà tout le détail de la méthodologie 360 (`docs/audits/SITIAME_360_Integrated_mapping_v2.xlsx`) : 15 ratios, et pour chaque critère `{value, score, level}`, le total et la décision de chacun des 3 blocs (Banque/Investisseur/Interne), et le Composite avec ses contributions par bloc.

Ce détail n'est aujourd'hui visible nulle part de façon lisible : la page "Classement financier" ne montre que les totaux par bloc, et le seul autre point d'accès est le bouton de test brut de `Scoring 360 Settings` (`scoring360_settings_test.js`).

**Décisions utilisateur (2026-09-27)** :
- Le détail s'affiche **dans le formulaire `Credit Scoring Dossier`** (pas de nouvelle page).
- Il est visible par le **staff et par la PME** sur son propre dossier (la PME ne peut consulter que sa société).
- La période analysée est **enregistrée dans le dossier** (traçabilité de la décision de crédit), **pré-remplie sur 12 mois glissants** finissant à la date d'évaluation, modifiable par l'analyste.

Le panneau est une **vue d'analyse automatique complémentaire** : il ne remplace pas la grille manuelle de 10 critères notés 0-5 (score /100, classe A-D) du dossier, et n'y écrit rien.

## Architecture

### 1. Doctype `Credit Scoring Dossier` (`credit_scoring_dossier.json`)

Nouvelle section insérée **après la section "Le dossier" et avant `company_info_section`** (l'analyste voit l'analyse chiffrée avant de noter) :

| fieldname | fieldtype | label | notes |
|---|---|---|---|
| `scoring360_section` | Section Break | Analyse automatique Scoring 360 | collapsible = 0 |
| `scoring_period_from` | Date | Période analysée du | |
| `scoring_period_col` | Column Break | | |
| `scoring_period_to` | Date | au | |
| `scoring360_panel_break` | Section Break | | pas de label |
| `scoring360_panel` | HTML | | contenu généré par le JS, jamais stocké |

Aucune modification des permissions du doctype (`System Manager` en écriture, `PME Client` en lecture restent inchangés). Aucune modification de `credit_scoring_dossier.py` : le score/classe manuel reste calculé comme aujourd'hui.

### 2. Serveur — `api.py::get_scoring360_score`

Changement de contrôle d'accès :

```python
frappe.has_permission("Company", "read", company, throw=True)
```

remplace `frappe.only_for("System Manager")`. C'est le même contrôle que le rapport `liasse_syscohada` : un System Manager lit toutes les sociétés ; un compte PME est restreint par sa `User Permission` (`allow = Company`) à sa seule société — toute autre société lève `frappe.PermissionError`.

La réponse de `score_company()` est enrichie (ajout de clés, rien n'est retiré — `scoring360_settings_test.js` continue de fonctionner) :

- `entries_count` : `_entries_count(company, date_from, date_to)`, pour distinguer "aucune écriture" d'un score faible.
- `config` : pour chaque bloc, `{weights, thresholds}` issus de `get_config()` (seuils `strong`/`medium` + `direction`), et `composite.weights`. Permet d'afficher le seuil appliqué à côté de chaque critère **sans aucune valeur codée en dur côté JS** — `Scoring 360 Settings` reste l'unique source.

Ces deux ajouts sont faits dans `get_scoring360_score` (l'endpoint), pas dans `score_company()`, pour ne pas alourdir les appels internes (`classement_erpnext`, `get_scoring_suggestions`).

Si `entries_count == 0`, l'endpoint renvoie `{"entries_count": 0}` sans appeler le moteur (même règle que `classement_erpnext`).

### 3. Rendu — `credit_scoring_dossier.js`

Nouvelle fonction `credit_scoring_render_scoring360(frm)` qui appelle `sitiame_core.api.get_scoring360_score` et remplit `frm.fields_dict.scoring360_panel.$wrapper`.

**Période effective** : `scoring_period_from`/`scoring_period_to` si renseignées ; sinon défaut calculé = 12 mois glissants finissant à `eval_date` (ou aujourd'hui si vide) : `to = eval_date || today`, `from = add_days(add_months(to, -12), 1)`.
- Au changement de `company`, si le document est modifiable (`frm.is_new()` ou permission d'écriture) et que les deux dates sont vides, elles sont remplies avec ce défaut via `frm.set_value` (elles seront enregistrées avec le dossier).
- En lecture seule (PME), le défaut est utilisé pour le calcul sans être écrit.

**Déclencheurs** : `refresh`, `company`, `scoring_period_from`, `scoring_period_to`, `eval_date` (ce dernier seulement si les dates de période sont vides). Un appel en cours est ignoré si un plus récent a été lancé (compteur de requête), pour éviter qu'une réponse lente écrase une plus récente.

**Contenu du panneau** :

1. **En-tête Composite** : score composite `/100`, badge décision (`strong` vert / `medium` orange / `weak` rouge, libellé de `DECISION_LABELS`), phrase de lecture, puis les 3 contributions (Banque x/40, Investisseur x/35, Interne x/25 — dénominateurs = `config.composite.weights`), et la période affichée.
2. **3 cartes, une par bloc** (Banque, Investisseur, Interne), côte à côte sur grand écran, empilées sur petit écran (`col-md-4`). Chaque carte : titre, total `/100`, badge décision du bloc, puis tableau des 5 critères :

   | Critère | Valeur | Seuils (fort / moyen) | Niveau | Points |
   |---|---|---|---|---|
   | DSCR | 1,85× | ≥ 1,5 / ≥ 1,2 | pastille Fort | 25 / 25 |

   - Libellés français et format par critère dans une table JS de présentation uniquement (`label`, `format` ∈ `ratio` ×, `pct` %, `days` j) — ce ne sont pas des paramètres de scoring.
   - `lte`/`gte` affichés `≤`/`≥` depuis `config.thresholds[crit].direction`.
   - Niveau : pastilles Fort (vert) / Moyen (orange) / Faible (rouge) / Non calculable (gris, valeur "n/c", 0 point) ; infobulle sur "n/c" expliquant la cause usuelle (ex. DSCR : aucune charge financière 66 ; croissance : pas de chiffre d'affaires sur la période précédente).

**États particuliers** :
- Pas de société : "Choisissez une société pour afficher l'analyse Scoring 360."
- `entries_count == 0` : "Données insuffisantes — aucune écriture comptable sur la période."
- Erreur de permission ou serveur : message neutre "Analyse indisponible", sans détail technique.
- Pendant le calcul : "Calcul en cours...".

Toutes les valeurs texte injectées passent par `frappe.utils.escape_html`.

## Hors périmètre

- Pas de lien automatique entre le score 360 et la note manuelle A-D ; `get_scoring_suggestions` reste le seul pont (inchangé).
- Pas de graphique (radar/barres) dans cette itération.
- Aucune modification du moteur `scoring360_service.py`, des seuils/poids, ni de la page "Classement financier".
- Pas d'historisation du score calculé (seule la période est enregistrée ; le score se recalcule à l'identique tant que les écritures de la période ne changent pas).

## Vérification

Pas de suite de tests automatisés pour les pages/formulaires de `sitiame_core` (convention : scripts `bench execute` one-off), mais `get_scoring360_score` est testable en unitaire à côté des tests existants (`cbb25c6`) :
1. `bench execute` de `get_scoring360_score` sur une société réelle (SITIAME) : présence de `entries_count`, `config`, `blocks`, `composite`.
2. Contrôle d'accès : en tant que compte PME, appel sur sa propre société → OK ; sur une autre société → `PermissionError`.
3. Sandbox, après déploiement via `/root/sitiame_core_deploy.sh` (migrate inclus pour les nouveaux champs) : ouvrir un dossier existant en System Manager (dates pré-remplies à la sélection de société, panneau complet) puis en compte PME (lecture seule, panneau visible pour sa société).
