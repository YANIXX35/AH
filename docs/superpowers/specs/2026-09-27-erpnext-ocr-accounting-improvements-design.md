# Fiabilisation de l'OCR comptable ERPNext — Design

## Contexte

Audit du 2026-09-27 (OCR.space réel sur les factures de test + exécution du code, sans rien créer) de `sitiame_core/ocr_invoice.py` (facture scannée → brouillon de Purchase/Sales Invoice) et `ocr_payment.py` (reçu scanné → brouillon de Payment Entry). La conception est saine — brouillons, soumission manuelle par le comptable, TVA ventilée, anti-doublon — et la seule facture OCR réelle en production (ACC-PINV-2026-00002) est correctement comptabilisée. Quatre lacunes ont été constatées :

1. `facture-test.pdf` (facture sans TVA, « TOTAL A PAYER : 400 000 FCFA », tableau « Description | Qte | Prix unitaire | Montant ») : l'OCR lit tout, mais l'extraction ne reconnaît ni « TOTAL A PAYER » ni l'en-tête « Description » → « Aucun montant n'a pu etre lu », facture refusée. En plus, l'OCR a perdu la cellule d'en-tête « Qte » : les colonnes chiffrées sont décalées d'un cran (le prix unitaire serait lu = 1).
2. Les lignes du tableau sont extraites (`line_items`) mais jamais utilisées : la facture est créée avec une seule ligne au HT.
3. Tout le HT va sur le compte par défaut de la société (6011 « Achats de marchandises » pour un achat), même pour une prestation de service — ce qui fausse aussi le critère `inventory_days` du Scoring 360 (achats 60/61).
4. Les modes de paiement Wave/Orange Money/MTN MoMo/Moov Money ne sont reliés à un compte (5521-5524, type Bank) que pour SITIAME. Les 7 autres sociétés ont un compte feuille `552 - Monnaie électronique téléphone portable` sans `account_type` et sans lien → un reçu Mobile Money y serait passé sur la banque 5211.

**Décisions utilisateur (2026-09-27)** : améliorer uniquement ERPNext (pas PME360) ; stratégie de compte = mémoire du tiers, puis mots-clés, puis défaut avec avertissement.

## Architecture

### 1. Extraction (`sitiame_core/api.py`)

- `_GRAND_TOTAL_PATTERNS` : le motif `NET\s*A\s*PAYER` devient `(?:NET|TOTAL)\s*[AÀ]\s*PAYER` (reconnaît « TOTAL A PAYER », « TOTAL À PAYER », « NET À PAYER »). Les motifs TTC existants restent prioritaires (premier motif trouvé gagne).
- `_extract_line_items` : l'en-tête est reconnu par `désignation|designation|description|libellé|libelle` en début de ligne.
- Alignement à droite : quand une ligne a plus de cellules que l'en-tête (cellule d'en-tête perdue par l'OCR), les index des colonnes chiffrées (qté, prix, montant) sont décalés de `len(cells) - len(header_cells)`. Les colonnes chiffrées sont toujours à droite du libellé, donc c'est l'en-tête perdu à gauche d'elles qui décale tout.
- Si la quantité n'est pas lue mais que prix et montant le sont, `qty = montant / prix` (arrondi à 2 décimales).

### 2. Lignes de facture (`sitiame_core/ocr_invoice.py`)

Nouvelle fonction `_invoice_lines(line_items, net, reference, party) -> (lines, warnings)` :
- Chaque ligne lue valide (libellé non vide, prix > 0 ; qté par défaut 1) devient une ligne `{item_name, qty, rate}`.
- Si toutes les lignes sont valides et que `|Σ qty×rate − HT| ≤ AMOUNT_TOLERANCE × nb_lignes`, ce sont les lignes de la facture.
- Sinon : une seule ligne au HT (comportement actuel), et si des lignes avaient été lues, un avertissement « Lignes lues (Σ) incohérentes avec le HT (HT) : une seule ligne au HT a été créée ».

### 3. Choix du compte (nouveau module `sitiame_core/ocr_accounts.py`)

- `remembered_account(is_purchase, party, company)` : compte de charge (`expense_account`) ou de produit (`income_account`) de la **première ligne** de la dernière facture **soumise** (`docstatus = 1`) de ce fournisseur/client dans cette société (tri `posting_date desc, creation desc`), à condition que ce compte soit une feuille active de la société. Calculé une fois par facture.
- `keyword_account(company, label)` : achats uniquement. Le libellé est normalisé (minuscules, accents retirés) puis confronté à `PURCHASE_KEYWORD_ACCOUNTS`, liste ordonnée `(regex, numéro de compte)` — premier motif trouvé gagne, les formulations spécifiques d'abord (« produits d'entretien » avant « entretien », « honoraires » avant « prestation ») :

  | Motifs (libellé normalisé) | Compte |
  |---|---|
  | honoraires, avocat, notaire, expert-comptable, commissaire aux comptes, huissier | 6324 |
  | loyer(s), location de bureau/local/locaux/bâtiment/magasin/entrepôt | 6222 |
  | électricité, CIE | 6052 |
  | eau, SODECI | 6051 |
  | carburant, gasoil, gazole, essence, super sans plomb | 6042 |
  | téléphone/téléphonie, forfait mobile, crédit de communication | 6281 |
  | hébergement, nom de domaine, site web/internet | 6345 |
  | internet, fibre, wifi, adsl | 6288 |
  | logiciel, licence, saas | 6343 |
  | maintenance | 6243 |
  | produits d'entretien, nettoyage, détergent | 6043 |
  | entretien, réparation | 6242 |
  | fournitures de bureau, papeterie, rame(s), cartouche, toner | 6047 |
  | assurance | 6258 |
  | billet d'avion, hôtel, voyage, déplacement, mission | 6181 |
  | publicité, annonce, affichage, spot radio/tv | 6271 |
  | formation | 633 |
  | frais bancaires, commission bancaire, agio(s), tenue de compte | 6318 |
  | conseil, consultant, consulting, prestation | 6327 |

  Le numéro est résolu en compte réel par `Account` (`company`, `account_number`, `is_group = 0`, `disabled = 0`) ; s'il n'existe pas dans la société, pas de proposition.
- `choose_account(is_purchase, company, label, default_account, remembered) -> (account, source)` : `remembered` → source `"memoire"` ; sinon (achat) mot-clé → `"mot-cle"` ; sinon `default_account` → `"defaut"`.

Dans `ocr_create_invoice_draft`, chaque ligne reçoit son compte via `choose_account`. Si au moins une ligne est en `"defaut"`, un avertissement « Compte par défaut (X) utilisé faute d'historique ou de mot-clé reconnu : vérifiez-le avant de soumettre ». Le commentaire de revue gagne une section « Comptes proposés » : `libellé → compte (dernière facture de ce tiers | mot-clé | compte par défaut, à vérifier)`.

### 4. Mobile Money (`ocr_accounts.py`, `ocr_payment.py`, patch, `api.py`)

- `mobile_money_account(company, mode_of_payment)` : parmi les comptes feuilles actifs `552%` de la société, celui dont le nom contient le nom du mode (SITIAME : `5521 - Wave`…), sinon le compte `552` générique, sinon `None`.
- `ensure_mobile_money_modes(company)` : pour chaque Mode of Payment de type `Phone` sans ligne pour cette société, ajoute `{company, default_account: mobile_money_account(...)}` ; donne `account_type = "Bank"` au compte utilisé s'il n'en a pas (comme les 5521-5524 de SITIAME — requis pour servir de compte de trésorerie d'un Payment Entry).
- Patch Frappe `sitiame_core.patches.map_mobile_money_modes` (listé dans `patches.txt`, exécuté une fois par `bench migrate`) : `ensure_mobile_money_modes` pour chaque société existante.
- `_apply_syscohada_defaults` (création de société via `register_company`) appelle aussi `ensure_mobile_money_modes`.
- `ocr_payment._mode_account` : si aucun compte n'est relié et que le mode est de type `Phone`, repli sur `mobile_money_account(...)` au lieu de `None` (couvre une société créée par un autre chemin).

## Hors périmètre

- PME360 (son OCR a ses propres défauts, traités séparément).
- Avoirs, factures multi-devises, rattachement des lignes à des fiches Article (`match_items` reste utilisé seulement par le pré-remplissage de formulaire).
- Mots-clés pour les ventes (le produit d'une vente dépend du métier de la PME : mémoire du client, sinon défaut).
- Toute soumission automatique : les documents restent des brouillons.

## Vérification

- Tests pytest (stub frappe, `tests/conftest.py`) : nouveaux motifs de total, en-tête « Description » et alignement à droite sur le texte OCR réel de `facture-test.pdf` ; `_invoice_lines` (lignes cohérentes / incohérentes / absentes) ; `keyword_account` et `choose_account` (ordre de priorité, accents, « produits d'entretien » vs « entretien ») ; `remembered_account` ; `mobile_money_account` (sous-compte de marque, 552 générique, rien).
- Après déploiement : rejouer OCR.space + extraction + choix des comptes sur les 3 factures de test sans créer de document ; vérifier en base les Mode of Payment Account et `account_type` des 552 des 8 sociétés.
