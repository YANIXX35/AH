# Copyright (c) 2026, Sitiame Capital
# License: MIT

"""Garde-fou comptable : empêche les imputations incorrectes sur les
factures d'achat et de vente.

Problème observé (2026-10-20) : ERPNext v16 en mode Perpetual Inventory
utilise automatiquement stock_received_but_not_billed (4081) comme compte
de débit sur les Purchase Invoice dont les articles sont is_stock_item=1,
même quand update_stock=0 et que l'Item Default indique explicitement 6011.

Ce module intercepte before_submit sur Purchase Invoice pour corriger ce
comportement lorsque update_stock=0 : dans ce cas, le mouvement de stock
n'est pas enregistré et l'imputation doit suivre l'expense_account de
l'Item Default, pas le compte de stock transitoire 4081.
"""

import frappe
from frappe import _


def _stock_received_account(company: str) -> str | None:
    """Retourne le nom ERPNext du compte stock_received_but_not_billed de la Company."""
    return frappe.get_cached_value("Company", company, "stock_received_but_not_billed")


def _item_default_expense(item_code: str, company: str) -> str | None:
    """Retourne l'expense_account de l'Item Default pour cet article/company."""
    return frappe.db.get_value(
        "Item Default",
        {"parent": item_code, "company": company},
        "expense_account",
    )


def _company_expense_account(company: str) -> str | None:
    """Compte de charges par défaut de la Company (fallback si pas d'Item Default)."""
    return frappe.get_cached_value("Company", company, "default_expense_account")


def validate_purchase_expense_account(doc, method=None):
    """before_submit sur Purchase Invoice.

    Quand update_stock=0, ERPNext ne doit pas imputer les charges sur le
    compte transitoire de stock (4081). Si c'est le cas, on corrige
    ligne par ligne depuis l'Item Default, puis depuis le compte de charges
    par défaut de la Company.

    Lève une ValidationError si aucun compte de charges valide n'est trouvable,
    pour éviter de soumettre une facture avec un compte bilan en charges.
    """
    if doc.update_stock:
        # Stock mis à jour → la comptabilité perpétuelle ERPNext est intentionnelle
        return

    company = doc.company
    srb_account = _stock_received_account(company)
    if not srb_account:
        return

    corrections = []
    errors = []

    for item in doc.items:
        if item.expense_account != srb_account:
            continue

        # Chercher un compte de charges valide
        replacement = (
            _item_default_expense(item.item_code, company)
            or _company_expense_account(company)
        )

        if not replacement:
            errors.append(
                _("Article {0} : aucun compte de charges trouvé (Item Default vide, compte par défaut Company absent).").format(
                    item.item_code
                )
            )
            continue

        if replacement == srb_account:
            errors.append(
                _("Article {0} : le compte de charges configuré est {1} (compte transitoire stock). Configurez un compte de charges (6xxx) dans Item Default.").format(
                    item.item_code, srb_account
                )
            )
            continue

        # Vérifier que le compte appartient à cette company et est un compte de charges
        acc_data = frappe.db.get_value(
            "Account",
            replacement,
            ["account_type", "root_type", "company", "disabled", "is_group"],
            as_dict=True,
        )
        if not acc_data:
            errors.append(_("Article {0} : compte {1} introuvable.").format(item.item_code, replacement))
            continue
        if acc_data.disabled or acc_data.is_group:
            errors.append(_("Article {0} : compte {1} désactivé ou compte de groupe.").format(item.item_code, replacement))
            continue
        if acc_data.company != company:
            errors.append(_("Article {0} : compte {1} appartient à {2}, pas à {3}.").format(
                item.item_code, replacement, acc_data.company, company
            ))
            continue
        if acc_data.root_type not in ("Expense",):
            errors.append(_("Article {0} : compte {1} n'est pas un compte de charges (root_type={2}).").format(
                item.item_code, replacement, acc_data.root_type
            ))
            continue

        corrections.append((item, replacement))

    if errors:
        frappe.throw(
            _("Imputation comptable incorrecte sur la facture :")
            + "<ul>"
            + "".join(f"<li>{e}</li>" for e in errors)
            + "</ul>"
            + _("Corrigez les Item Defaults ou le compte par défaut de la société avant de soumettre.")
        )

    for item, replacement in corrections:
        item.expense_account = replacement
        # Mise à jour directe en DB car before_submit ne persiste pas les champs
        # de ligne automatiquement si on ne passe pas par doc.save()
        frappe.db.set_value(
            "Purchase Invoice Item",
            item.name,
            "expense_account",
            replacement,
            update_modified=False,
        )

    if corrections:
        frappe.msgprint(
            _("{0} ligne(s) corrigée(s) : compte transitoire stock remplacé par le compte de charges de l'Item Default.").format(
                len(corrections)
            ),
            title=_("Compte de charges corrigé"),
            indicator="orange",
        )
