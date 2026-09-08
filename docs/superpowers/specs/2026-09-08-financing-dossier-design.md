# Dossier de Financement — socle + Étapes 1-2 (Demande & Entreprise)

Statut : validé en conversation le 2026-09-08 (sous-projet 1 sur 5, découpage
approuvé). Basé sur la maquette fournie par l'utilisateur
(`open_source/Maquette_Dossier_Financement_PME360 (1).html`), un assistant en
12 étapes.

## Contexte

`InvestmentRequest` (7 champs exploitables : montant, devise, horizon, objet,
statut, représentant légal, CA/capitaux N-1) est trop pauvre face au besoin
réel d'instruction d'un dossier de financement décrit par la maquette : 12
étapes, ~60 champs, tableaux financiers, pièces justificatives, scoring
pondéré, circuit de validation.

Décision (validée avec l'utilisateur) : un nouveau modèle plus riche
remplace `InvestmentRequest`, en récupérant tout ce que l'ancien modèle
couvrait déjà (voir tableau de correspondance ci-dessous) plutôt que de le
perdre. L'analyste financier saisit seul l'intégralité du dossier (pas de
saisie côté PME).

## Périmètre de ce sous-projet

Livré maintenant : le modèle de données complet (les 12 étapes, pour éviter
une migration à chaque sous-projet livré), l'assistant multi-étapes
(navigation, progression, brouillon), et les étapes **1 (Demande de
financement)** et **2 (Entreprise, avec pré-remplissage automatique)**
fonctionnelles. Les étapes 3 à 12 apparaissent dans la navigation avec un
état "à venir" — elles seront branchées dans les sous-projets suivants.

## Modèle de données — `FinancingDossier`

Remplace `InvestmentRequest`. Table `financing_dossiers`.

### Colonnes scalaires (recherchables/filtrables)

| Colonne | Origine |
|---|---|
| `id`, `user_id` (PME), `analyst_user_id` (créateur) | — |
| `reference` (ex. `DF-2026-000184`, généré) | Maquette §1 |
| `status` (`draft`, `pending`, `in_review`, `accepted`, `declined`) | = `InvestmentRequest.status` + `draft` en plus — **le workflow existant (`ALLOWED_TRANSITIONS` dans `FinancialAnalystController`) n'est pas modifié dans ce sous-projet** ; un dossier créé ici reste en `draft` (pas encore soumis, l'étape 12 "Validation" qui fait passer en `pending` n'est pas encore construite) |
| `reviewed_by`, `reviewed_at`, `review_note` | = `InvestmentRequest` (inchangé) |
| `amount_requested`, `currency` | = `InvestmentRequest` |
| `financing_type`, `financing_purpose` (= ancien `purpose`) | Étape 1 |
| `desired_term_months` (= ancien `horizon`), `grace_period_months`, `repayment_frequency` | Étape 1 |
| `promoter_contribution`, `desired_disbursement_date` | Étape 1 |
| `legal_name`, `trade_name`, `legal_form`, `rccm_number`, `taxpayer_number`, `incorporation_date`, `registered_office`, `city_country` | Étape 2 — Identité juridique |
| `business_sector`, `main_activity`, `employee_count`, `website` | Étape 2 — Activité |
| `share_capital`, `major_shareholders`, `beneficial_owners`, `authorized_representative` (= ancien `legal_representative`) | Étape 2 — Capital/gouvernance |
| `attachments_commitment`, `certifies_accuracy` (booléens) | = `InvestmentRequest` (utilisés à l'étape 12) |
| `photo_path`, `identity_document_front_path`, `identity_document_back_path`, `identity_document_type`, `identity_document_number`, `identity_document_expires_at` | = `InvestmentRequest` (pièce d'identité du dirigeant, étape 3/10) |

### Colonnes JSON (une par groupe d'étapes, remplies dans les sous-projets suivants)

`financing_summary_data` (étape 1 — résumé/source remboursement texte),
`promoters_data` (étape 3), `project_data` (étape 4, inclut le tableau des
coûts), `market_data` (étape 5), `historical_financials_data` (étape 6,
tableau 3 exercices + situation actuelle + engagements existants),
`forecast_data` (étape 7, hypothèses + compte de résultat prévisionnel +
DSCR), `financing_plan_data` (étape 8, emplois/ressources), `collateral_data`
(étape 9), `documents_data` (étape 10, métadonnées des pièces), `scoring_data`
(étape 11, les 8 critères pondérés + avis), `review_data` (étape 12, circuit
de validation). Toutes nullable, remplies au fur et à mesure de la saisie.

### Pré-remplissage étape 2 (depuis `User`)

| Champ dossier | Source `User` |
|---|---|
| `legal_name` | `company_name` |
| `trade_name` | `company_sigle` |
| `rccm_number` | `rccm` |
| `taxpayer_number` | `company_tax_id` |
| `registered_office` | `address` |
| `city_country` | `city` (+ "Côte d'Ivoire" par défaut) |
| `business_sector` | `sector` |
| `main_activity` | `main_activity_description` |
| `authorized_representative` | `contact_person_name` |

Ces champs sont pré-remplis à la **création** du dossier (pas resynchronisés
ensuite) — l'analyste voit une pastille "pré-rempli depuis la fiche PME" et
peut corriger si l'info a changé, cohérent avec la maquette qui distingue
visuellement les champs pré-remplis.

## Assistant multi-étapes (UI)

Reprend fidèlement la structure de la maquette : barre latérale des 12
étapes avec statut (fait/en cours/à venir), barre de progression en tête,
zone de contenu avec blocs de champs groupés, pied de page
Précédent/Enregistrer le brouillon/Suivant. Étapes 3-12 : lien désactivé
avec la mention "à venir" tant que le sous-projet correspondant n'est pas
livré.

## Intégration avec l'existant

- Nouveau lien "Nouveau dossier de financement" sur la fiche PME
  (`financial-analyst/show.blade.php`), à côté du bloc "Dossier(s) de
  financement" déjà existant.
- La liste des dossiers déjà affichée sur la fiche PME et sur le Portefeuille
  (filtre "dossier de financement en attente") continue de fonctionner à
  l'identique — mêmes noms de colonnes (`status`, `amount_requested`).
- Migration de données : les `InvestmentRequest` existants sont copiés vers
  `financing_dossiers` (mêmes valeurs pour les colonnes communes, JSON vides
  pour le reste) avant que l'ancien modèle ne soit retiré.

## Vérification

Pas de PHPUnit local (PHP 8.2 vs 8.4 requis) — `php -l` + compilation Blade +
revue manuelle, comme le reste de la session.

1. Créer un dossier depuis la fiche d'une PME ayant `company_name`/`rccm`/
   `sector`/`address` renseignés → étape 2 pré-remplie avec les bonnes
   valeurs.
2. Créer un dossier pour une PME sans ces infos → étape 2 vide, pas d'erreur.
3. Naviguer entre étapes 1 et 2, revenir en arrière → les valeurs saisies
   sont conservées (brouillon).
4. Étapes 3-12 visibles dans la navigation mais non cliquables/marquées "à
   venir".
5. Un `InvestmentRequest` existant apparaît bien comme `FinancingDossier`
   après migration, avec le même statut et montant.
