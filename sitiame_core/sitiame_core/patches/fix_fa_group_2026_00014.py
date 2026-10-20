# Copyright (c) 2026, Sitiame Capital
# License: MIT

"""Correction historique de FA-GROUP-2026-00014 (GROUPRE PRO ABK INC SARL).

ATTENTION — NE PAS EXÉCUTER SANS VALIDATION EXPLICITE.

Contexte (audit 2026-10-20) :
  FA-GROUP-2026-00014 est soumis avec expense_account = 4081 (compte
  transitoire stock) au lieu de 6011 (achats marchandises). Cause : article
  VIANDE FRAICHE était is_stock_item=1 avec enable_perpetual_inventory=1.
  La facture est liée au Payment Entry REG-GROUP-2026-00004 (58 410 FCFA).

Procédure :
  1. Annuler REG-GROUP-2026-00004 (Payment Entry lié)
  2. Annuler FA-GROUP-2026-00014
  3. Recréer FA-GROUP-2026-00014 → account_guard corrigera automatiquement 4081→6011
  4. Recréer le paiement lié

Prérequis :
  - La patch set_non_stock_items doit avoir été exécutée (is_stock_item=0
    sur VIANDE FRAICHE) OU le hook account_guard doit être déployé.
  - Sinon, même recréée, la facture reprendrait 4081.

Ce script peut être exécuté avec :
  bench --site erp.sitiame-capital.com run-patch \
      sitiame_core.patches.fix_fa_group_2026_00014
"""

import frappe
from frappe.utils import add_days

PINV_NAME = "FA-GROUP-2026-00014"
PAY_NAME = "REG-GROUP-2026-00004"
COMPANY = "GROUPRE PRO ABK INC SARL"


def _get_account(number: str) -> str:
    acc = frappe.db.get_value(
        "Account",
        {"company": COMPANY, "account_number": number, "is_group": 0, "disabled": 0},
        "name",
    )
    if not acc:
        frappe.throw(f"Compte {number} introuvable pour {COMPANY}")
    return acc


def execute():
    # ── Vérifications préalables ──────────────────────────────────────
    pinv = frappe.get_doc("Purchase Invoice", PINV_NAME)
    if pinv.docstatus != 1:
        frappe.log_error(
            title="fix_fa_group_2026_00014",
            message=f"{PINV_NAME} docstatus={pinv.docstatus} — déjà annulé ou brouillon, skip.",
        )
        return

    bad_acc = _get_account("4081")
    has_bad = any(it.expense_account == bad_acc for it in pinv.items)
    if not has_bad:
        frappe.log_error(
            title="fix_fa_group_2026_00014",
            message=f"{PINV_NAME} n'utilise pas 4081 — déjà corrigé ou différent, skip.",
        )
        return

    # Vérifier que la patch is_stock_item ou le hook est en place
    vf_is_stock = frappe.db.get_value("Item", "VIANDE FRAICHE", "is_stock_item")
    if vf_is_stock:
        frappe.throw(
            "VIANDE FRAICHE est encore is_stock_item=1. "
            "Exécutez d'abord la patch set_non_stock_items avant cette correction."
        )

    # ── Sauvegarder les données de la facture ─────────────────────────
    items_data = [
        {
            "item_code": it.item_code,
            "item_name": it.item_name,
            "qty": it.qty,
            "rate": it.rate,
            "uom": it.uom,
            "description": it.description,
        }
        for it in pinv.items
    ]
    taxes_data = [
        {
            "charge_type": t.charge_type,
            "account_head": t.account_head,
            "description": t.description,
            "rate": t.rate,
        }
        for t in pinv.taxes
    ]
    supplier = pinv.supplier
    posting_date = str(pinv.posting_date)
    bill_no = pinv.bill_no

    # ── Sauvegarder les données du paiement ───────────────────────────
    pay = frappe.get_doc("Payment Entry", PAY_NAME)
    if pay.docstatus != 1:
        frappe.throw(f"{PAY_NAME} n'est pas soumis (docstatus={pay.docstatus})")

    pay_amount = pay.paid_amount
    pay_mode = pay.mode_of_payment
    pay_bank = pay.paid_to

    # ── Annuler le paiement ───────────────────────────────────────────
    pay.cancel()
    frappe.db.commit()
    frappe.log_error(title="fix_fa_group_2026_00014", message=f"Paiement {PAY_NAME} annulé.")

    # ── Annuler la facture ────────────────────────────────────────────
    pinv.reload()
    pinv.cancel()
    frappe.db.commit()
    frappe.log_error(title="fix_fa_group_2026_00014", message=f"Facture {PINV_NAME} annulée.")

    # ── Recréer la facture (account_guard corrigera 4081→6011) ────────
    new_pinv = frappe.new_doc("Purchase Invoice")
    new_pinv.company = COMPANY
    new_pinv.supplier = supplier
    new_pinv.posting_date = posting_date
    new_pinv.due_date = add_days(posting_date, 30)
    new_pinv.bill_no = bill_no
    for it in items_data:
        new_pinv.append("items", it)
    for t in taxes_data:
        new_pinv.append("taxes", t)
    new_pinv.insert(ignore_permissions=True)
    new_pinv.submit()
    frappe.db.commit()

    # Vérifier le compte
    new_acc = frappe.db.get_value(
        "Purchase Invoice Item", {"parent": new_pinv.name}, "expense_account"
    )
    frappe.log_error(
        title="fix_fa_group_2026_00014",
        message=f"Nouvelle facture {new_pinv.name} | expense_account={new_acc}",
    )

    good_acc = _get_account("6011")
    if new_acc != good_acc:
        frappe.log_error(
            title="fix_fa_group_2026_00014 ERREUR",
            message=f"Compte incorrect après recréation : {new_acc} (attendu {good_acc})",
        )
        return

    # ── Recréer le paiement ───────────────────────────────────────────
    new_pay = frappe.new_doc("Payment Entry")
    new_pay.payment_type = "Pay"
    new_pay.company = COMPANY
    new_pay.mode_of_payment = pay_mode
    new_pay.paid_to = pay_bank
    new_pay.paid_amount = pay_amount
    new_pay.received_amount = pay_amount
    new_pay.posting_date = posting_date
    new_pay.append(
        "references",
        {
            "reference_doctype": "Purchase Invoice",
            "reference_name": new_pinv.name,
            "allocated_amount": pay_amount,
        },
    )
    new_pay.insert(ignore_permissions=True)
    new_pay.submit()
    frappe.db.commit()

    frappe.log_error(
        title="fix_fa_group_2026_00014 SUCCÈS",
        message=(
            f"Correction terminée.\n"
            f"  Facture originale annulée : {PINV_NAME}\n"
            f"  Nouvelle facture          : {new_pinv.name}\n"
            f"  Compte expense            : {new_acc}\n"
            f"  Paiement original annulé  : {PAY_NAME}\n"
            f"  Nouveau paiement          : {new_pay.name}\n"
        ),
    )
