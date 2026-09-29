# Journal des écritures + paiement créé à la validation — Design

## Contexte

Demande utilisateur (2026-09-29) : après import OCR et validation d'une facture, les écritures apparaissent dans les rapports financiers, mais « rien » n'apparaît dans la rubrique **Paiements** (Écriture de paiement, Écriture comptable). L'utilisateur veut que les écritures s'y voient automatiquement aussi.

Fonctionnement ERPNext (vérifié) : une facture validée enregistre ses lignes de grand livre (GL Entry) sur **la facture elle-même** ; « Écriture comptable » (Journal Entry) ne liste que les écritures manuelles ; « Écriture de paiement » (Payment Entry) n'existe qu'une fois la facture payée. La case native « Est payé » de la facture d'achat comptabilise le paiement **dans** la facture, sans créer de Payment Entry. Créer un Journal Entry en double compterait deux fois charge et TVA : exclu.

**Décision utilisateur** : les deux ajouts proposés.

## 1. Rapport « Journal des ecritures » (`sitiame_core/sitiame_core/report/journal_des_ecritures/`)

Script Report, `ref_doctype` GL Entry, rôles Accounts User / Accounts Manager / Auditor (comme la Liasse), `add_total_row` = 1.

- Filtres : Société (obligatoire, défaut = société par défaut de l'utilisateur), Du (défaut 1er du mois), Au (défaut aujourd'hui), Compte (Link Account), Type de pièce (Select).
- Contrôle : `frappe.has_permission("Company", "read", company, throw=True)` (un compte PME ne lit que sa société).
- Données : `tabGL Entry` de la société, `is_cancelled = 0`, dans la période, triées par date puis pièce.
- Colonnes : Date, Type (libellé français : Facture d'achat, Facture de vente, Paiement, Écriture manuelle…), Pièce (lien vers le document), Compte, Libellé (tiers, sinon contrepartie, sinon remarque), Débit, Crédit ; total en bas.
- Menu : entrée « Journal des écritures » ajoutée après « Journal Entry » dans les barres latérales **Payments** et **Invoicing** (standard ERPNext → réinsérée par `after_migrate`, même mécanisme que la Liasse SYSCOHADA).

## 2. Paiement créé à la validation

- Champs personnalisés sur Purchase Invoice et Sales Invoice (via `after_migrate`) : `sitiame_pay_on_submit` (Check « Payée : créer le paiement à la validation », masqué si « Est payé »/PDV/avoir) et `sitiame_payment_mode` (Link Mode of Payment « Moyen de paiement », visible et obligatoire si la case est cochée). `no_copy`.
- `doc_events` `on_submit` des deux factures → `sitiame_core.pay_on_submit.create_payment` : si la case est cochée et qu'il reste un montant dû, crée via `get_payment_entry` puis **valide** l'écriture de paiement (compte = celui du moyen de paiement : `ocr_payment._mode_account`, Mobile Money → 552, Espèces → caisse ; sinon banque par défaut), date et référence de la facture. Même transaction : si le paiement échoue, la validation de la facture est annulée (rien d'à moitié fait).
- Écran de validation OCR : case « Facture déjà payée » + « Moyen de paiement » ; `ocr_create_invoice_draft(paid_mode=)` renseigne les deux champs sur le brouillon. La case reste aussi utilisable sur une facture saisie à la main.

## Hors périmètre

Écritures en double dans Journal Entry ; paiements partiels à la validation (le paiement couvre le reste dû) ; reçus de paiement OCR (déjà existant).

## Vérification

pytest (rapport : filtres, permission, libellés ; paiement : non coché / coché sans moyen / coché → création et validation) ; production : en transaction annulée, validation d'une facture OCR « payée en espèces » → Payment Entry créé, facture « Payée », lignes visibles dans le Journal ; menus Payments/Invoicing.
