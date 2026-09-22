# Unification des moteurs de scoring ERPNext — Design

## Contexte

`sitiame_core` (app ERPNext custom) contient aujourd'hui **deux moteurs de scoring séparés** qui coexistent sans jamais avoir été rapprochés :

1. **`financial_ratio_service.py`** — moteur simple (rentabilité/solvabilité), portage de PME360 `SmeFinancialRatioService.php`. Calcule un classement par société (`financable`/`solvable_seulement`/`non_retenu`/`insuffisant`), affiché sur la page "Classement financier" (`erp-financial-ranking`) et utilisé par l'assistant IA (`_build_assistant_context`).
2. **`scoring360_service.py`** — moteur détaillé 3 blocs (Banque/Investisseur/Interne + Composite, 15 critères pondérés), portage de PME360 `Scoring360Service.php`. Entièrement piloté par le doctype singleton `Scoring 360 Settings` (seuils, poids, coefficients). Correspond **exactement** à la méthodologie documentée dans `docs/audits/SITIAME_360_Integrated_mapping_v2.xlsx` (même 15 critères, mêmes poids par défaut 40/35/25). Aujourd'hui exposé seulement via un bouton de test basique sur la page Settings, et partiellement utilisé dans les suggestions auto du `Credit Scoring Dossier`.

**Problème découvert en auditant le code** (pas une supposition — vérifié dans les deux fichiers) : les deux moteurs recalculent le **même** total actif/passif d'une société avec des listes de comptes SYSCOHADA légèrement différentes (ex. `financial_ratio_service.summarize()` décompose l'actif en 5 sous-totaux séparés — charges immobilisées, primes de remboursement, actif incorporel, corporel, financier — tandis que `scoring360_service.score_company()` calcule `immobilisations` en une seule somme sur une liste de préfixes différente). Résultat : les deux moteurs peuvent afficher des chiffres de base différents pour la même PME, en plus de méthodologies de score différentes — source réelle de confusion pour un analyste qui passerait de la page "Classement financier" à `Scoring 360 Settings`.

**Décision utilisateur (2026-09-22)** : unification complète — un seul moteur (`scoring360_service.py`), le score Composite du moteur 3-blocs remplace entièrement le classement simple partout, y compris sur la page "Classement financier" (changement de comportement visible assumé).

## Architecture cible

### 1. `scoring360_service.py` devient l'unique moteur

Les 3 fonctions bas-niveau de lecture du grand livre déjà partagées entre les deux fichiers (`_account_ledger`, `_sum_by_prefixes`, `_entries_count`, actuellement définies dans `financial_ratio_service.py` et importées par `scoring360_service.py`) sont rapatriées directement dans `scoring360_service.py`. `financial_ratio_service.py` est supprimé entièrement une fois tous ses appelants migrés (voir sections suivantes) — plus aucun fichier ne recalcule les agrégats financiers d'une PME de deux façons différentes.

### 2. Nouvelle fonction `classement_erpnext(date_from=None, date_to=None)` dans `scoring360_service.py`

Remplace l'ancienne fonction du même nom (qui vivait dans `financial_ratio_service.py`). Pour chaque `Company` ERPNext :

1. Vérifie `_entries_count(company, date_from, date_to)`. Si `0` → catégorie `"insuffisant"`, libellé `"Données insuffisantes"`, pas de calcul de score (identique au comportement actuel — c'est la seule "porte" de qualité de données conservée ; les paliers historiques à 5/15 écritures pour distinguer solvable/finançable sont abandonnés : le moteur composite gère déjà nativement un critère non calculable comme 0 point, donc une société avec très peu de données obtient naturellement un score bas sans règle séparée à maintenir).
2. Sinon, appelle `score_company(company, date_from, date_to)` et prend la décision du bloc **Composite** (`strong`/`medium`/`weak`), avec les libellés déjà définis dans `DECISION_LABELS["composite"]` et `DECISION_LECTURES["composite"]` (aucune nouvelle terminologie à créer) :
   - `strong` → `"PRET A DEPLOYER"`
   - `medium` → `"SOLIDE MAIS A CADRER"`
   - `weak` → `"RISQUE A TRAITER"`
3. Trie les sociétés par score Composite décroissant (remplace l'ancien tri par "synthèse fiabilisée").
4. Retourne `{"lignes": [...], "compteurs": {...}}`, même forme générale qu'avant mais avec les nouvelles clés de catégorie (`pret_a_deployer`, `solide_mais_a_cadrer`, `risque_a_traiter`, `insuffisant`) au lieu de (`financable`, `solvable_seulement`, `non_retenu`, `insuffisant`).

Chaque ligne contient : `company`, `company_name`, `entries_count`, `composite_score` (0-100 ou `None` si insuffisant), `decision` (`{level, label, lecture}`), `blocks` (scores Banque/Investisseur/Interne pour affichage détaillé optionnel).

### 3. `api.py::get_financial_ranking` — mise à jour de l'import

```python
from sitiame_core.scoring360_service import classement_erpnext
return classement_erpnext(date_from or None, date_to or None)
```
(seul changement : la fonction vient maintenant de `scoring360_service`, plus de `financial_ratio_service`.)

### 4. Page `erp_financial_ranking.js` — nouvelles colonnes

- **Cartes de synthèse** (haut de page) : "Prêt à déployer" / "Solide mais à cadrer" / "Risque à traiter" / "Données insuffisantes" (remplace Financables/Solvables seulement/Non retenus/Données insuffisantes).
- **Tableau** : Société · Écritures · **Score composite** (/100) · **Décision** · Contribution Banque · Contribution Investisseur · Contribution Interne (remplace les colonnes Solvable/Financable/Synthèse fiabilisée/Motifs — les "motifs" texte disparaissent, remplacés par les 3 contributions chiffrées déjà calculées par `_score_composite()`).
- Le bandeau d'aide sous les cartes (qui expliquait les seuils solvable/finançable) est retiré — les seuils sont désormais visibles/éditables directement dans `Scoring 360 Settings`, pas besoin de les dupliquer en texte sur cette page.

### 5. `api.py::get_scoring_suggestions` — dérivation depuis un seul moteur

Remplace l'appel à `financial_ratio_service.analyze()` par une lecture directe des ratios déjà calculés par `score_company()` :

| Suggestion | Avant (financial_ratio_service) | Après (scoring360_service) |
|---|---|---|
| Capacité de remboursement | bloc Banque (`score_company`) | inchangé — bloc Banque |
| Structure financière | `scores.solvabilite.valeur` | dérivé du critère `debt_asset` (bloc Banque) : `bank_block["criteria"]["debt_asset"]["score"] / cfg["bank"]["weights"]["debt_asset"] * 100` (`cfg = get_config()`, `bank_block = result["blocks"]["bank"]` — normalise le sous-score pondéré du critère en une échelle 0-100 indépendante de son poids), puis passé à `_score_to_note` — les seuils forts/moyens restent ceux déjà configurés dans `Scoring 360 Settings` |
| Rentabilité | `scores.rentabilite.valeur` | dérivé du critère `net_margin` (bloc Interne) : même normalisation `internal_block["criteria"]["net_margin"]["score"] / cfg["internal"]["weights"]["net_margin"] * 100`, passé à `_score_to_note` |
| Liquidité générale | `ratios.liquidite_generale` | `ratios["current_ratio"]` (déjà retourné par `score_company()`) |

Le cas `entries_count == 0` (aucune écriture comptable) reste géré à l'identique : les 4 suggestions restent `None` avec la même note explicative.

### 6. `api.py::_build_assistant_context` — mise à jour

Remplace l'import `financial_ratio_service.classement_erpnext` par `scoring360_service.classement_erpnext`. Les clés de `compteurs` utilisées dans le texte de contexte de l'assistant sont mises à jour pour les nouveaux noms de catégorie (`pret_a_deployer`/`solide_mais_a_cadrer`/`risque_a_traiter`/`insuffisant`).

### 7. Suppression de `financial_ratio_service.py`

Une fois les 4 points ci-dessus migrés, plus aucun fichier n'importe `financial_ratio_service` — le fichier est supprimé du dépôt.

## Hors périmètre

- Pas de changement aux seuils/poids par défaut du moteur composite (`Scoring 360 Settings` reste la seule source de config, inchangée).
- Pas de construction de la page tableau de bord complète (équivalent `Dashboard_360` de l'Excel) — c'est l'étape suivante, après cette unification, sur une base désormais propre.
- Pas de changement à `Credit Scoring Dossier` lui-même (doctype, champs) — seule la fonction qui alimente ses suggestions change de source.

## Tests

Pas de suite de tests automatisés existante pour ces modules Python ERPNext dans ce dépôt (convention du projet : vérification via scripts `bench execute` one-off, cohérente avec le reste de `sitiame_core`). Vérification prévue après déploiement :
1. Script `bench execute` comparant `classement_erpnext()` avant/après sur une société réelle (SITIAME) pour confirmer que le score composite et la décision sont calculés sans erreur.
2. Rechargement de la page "Classement financier" en sandbox pour confirmer les nouvelles colonnes.
3. Génération des suggestions sur un `Credit Scoring Dossier` existant pour confirmer que les 4 valeurs financières restent cohérentes avec les scores affichés par ailleurs.
