# Journal des écritures + paiement créé à la validation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Voir toutes les écritures (factures comprises) dans un « Journal des écritures » sous Paiements, et créer automatiquement l'écriture de paiement d'une facture marquée payée à sa validation.

**Architecture:** Script Report sur GL Entry + entrée de menu posée par `after_migrate` ; champs personnalisés + `doc_events.on_submit` qui crée et valide un Payment Entry ; case dans l'écran de validation OCR.

**Tech Stack:** Frappe/ERPNext v16, `sitiame_core`, pytest (stub frappe).

**Spec:** `docs/superpowers/specs/2026-09-29-journal-and-pay-on-submit-design.md`

## Global Constraints

- Repo `C:\Users\yaniss\Desktop\sitiame_core` ; une autre session peut y travailler : ne committer que ses propres fichiers, vérifier `git status` avant chaque commit.
- Aucune écriture en double : le rapport LIT le grand livre, il n'écrit rien.
- Contrôle `frappe.has_permission("Company", "read", company, throw=True)` dans le rapport.
- Le paiement est créé dans la même transaction que la validation de la facture.
- Déploiement : `bash /root/sitiame_core_deploy.sh` (verrou flock, clé de déploiement).

### Task 1: Rapport « Journal des ecritures »
Files: `sitiame_core/sitiame_core/report/journal_des_ecritures/{__init__.py, .json, .py, .js}`, `tests/test_journal_report.py`.
- [ ] Tests : sans société → aucune ligne ; permission Société vérifiée ; filtres période/compte/type traduits en conditions SQL ; libellé = tiers › contrepartie › remarque ; type de pièce en français.
- [ ] Implémentation `execute(filters)` + colonnes + filtres JS ; `add_total_row: 1`.
- [ ] Commit.

### Task 2: Menu « Journal des écritures »
Files: `sitiame_core/setup.py`, `tests/test_setup_sidebar.py`.
- [ ] Test : insertion après « Journal Entry » dans Payments et Invoicing, idempotente, absente si la barre n'existe pas.
- [ ] `add_journal_to_payment_sidebars()` appelée par `after_migrate`.
- [ ] Commit.

### Task 3: Paiement créé à la validation
Files: `sitiame_core/pay_on_submit.py`, `sitiame_core/setup.py` (champs), `sitiame_core/hooks.py` (`doc_events`), `tests/test_pay_on_submit.py`.
- [ ] Tests : case non cochée → rien ; cochée sans moyen → erreur ; cochée → `get_payment_entry(doctype, name, bank_account=compte du moyen)`, mode/date/référence renseignés, `insert()` puis `submit()` ; rien si plus aucun montant dû.
- [ ] Implémentation + champs `sitiame_pay_on_submit` / `sitiame_payment_mode` + hook.
- [ ] Commit.

### Task 4: Écran OCR
Files: `sitiame_core/ocr_invoice.py` (`paid_mode=`), `sitiame_core/public/js/sales_invoice_ocr_import.js`, `tests/test_ocr_invoice_review.py` ou nouveau test.
- [ ] Test : `_apply_payment(invoice, "Cash")` coche la case et pose le moyen ; `None` ne touche à rien ; moyen inconnu → erreur.
- [ ] Case « Facture déjà payée » + « Moyen de paiement » dans le dialogue, transmis à `ocr_create_invoice_draft`.
- [ ] Commit.

### Task 5: Déploiement et vérification
- [ ] Push, déploiement.
- [ ] Production, transaction annulée : facture d'achat OCR payée en espèces validée → Payment Entry validé, facture « Payée », lignes facture + paiement dans le Journal ; menus Payments/Invoicing contiennent l'entrée.
