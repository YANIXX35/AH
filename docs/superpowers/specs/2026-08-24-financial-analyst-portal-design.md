# Portail Analyste Financier

Statut : validé en conversation le 2026-08-24 (portefeuille multi-PME confirmé, 8 sections listées et approuvées).

## Contexte

Le rôle `role_key = 'analyst'` existe déjà mais est scopé à une seule PME (module
`investor` uniquement). L'utilisateur a besoin d'un vrai portail dédié, multi-PME,
pour un analyste financier qui suit un portefeuille d'entreprises clientes — sur le
même modèle que le portail Comptable (dossiers multiples + bascule de contexte via
`ClientWorkspace`).

## Décisions

- Nouveau `role_key` distinct : `financial_analyst` (ne réutilise pas `analyst`, qui
  reste tel quel pour ne rien casser).
- **Portefeuille = toutes les PME de la plateforme** (pas d'assignation par analyste en
  v1 — comme le fait déjà `AdminFinancialAnalysisController` pour l'admin). Une
  assignation fine par analyste est un follow-up possible, pas bloquant pour ce v1.
- Réutilise au maximum l'existant plutôt que de dupliquer : `SmeFinancialRatioService`
  (ratios), `Scoring360Service` (scoring), `InvestmentRequest` (dossier de
  financement), `accounting_quality_reviewed_at`/`AccountingQualityReview`
  (fiabilité des données), la requête "écritures sans pièce" déjà utilisée sur le
  dashboard entreprise (alertes).
- Pour les pièces justificatives (bilan, liasse BCEAO...), réutilise le mécanisme
  déjà existant `ClientWorkspace::setWorkspaceUserId()` + les routes de rapports
  existantes (`accounting.report.*`), plutôt que de reconstruire un lecteur de
  documents séparé — étend `ClientWorkspace::canUseWorkspace()` /
  `isAssignableClient()` au nouveau rôle, exactement comme c'est déjà fait pour le
  comptable.

## Sections (8, approuvées)

1. Portefeuille de PME (accueil, indicateur santé 🟢🟠🔴, filtre/tri)
2. Fiabilité des données (badge basé sur `accounting_quality_reviewed_at`)
3. Évolution dans le temps — **hors périmètre v1** (nécessite une agrégation
   multi-période qui n'existe nulle part dans le code actuel ; noté comme follow-up,
   pas bloquant pour livrer le reste)
4. Alertes & anomalies (écritures sans pièce, taux de conformité documents)
5. Scoring (réutilise `Scoring360Service`)
6. Ratios financiers (réutilise `SmeFinancialRatioService`)
7. Dossier de financement (réutilise `InvestmentRequest`)
8. Notes et avis de l'analyste (nouveau modèle `FinancialAnalystNote`)
9. Pièces justificatives (lien "Ouvrir le dossier" → rapports existants)
10. Historique des décisions de l'analyste (liste de ses propres notes, toutes PME)

*(Note : la numérotation dépasse "8" car la fiabilité des données et l'accès aux
pièces, discutées séparément, sont chacune leur propre section à l'usage.)*

## Ce qui est implémenté maintenant vs différé

**Maintenant** : 1, 2, 4, 5, 6, 7, 8, 9, 10.
**Différé (follow-up)** : 3 (tendance multi-période) — sujet à lui seul (choix de
granularité, stockage de snapshots ou calcul à la volée sur plusieurs clôtures
mensuelles), à traiter dans un cycle séparé une fois le reste en usage réel.
