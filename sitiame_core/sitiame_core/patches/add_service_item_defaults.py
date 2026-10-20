# Copyright (c) 2026, Sitiame Capital
# License: MIT

"""Ajoute les Item Defaults manquants pour l'article de service
'Élaboration détaillée du business plan…' sur toutes les companies actives.

Contexte (audit 2026-10-20, anomalie E5) : cet article n'a aucune ligne
Item Default. Toute facture l'utilisant tomberait sur le fallback de la
Company, qui peut être 6011 (charges) côté achat — acceptable — mais
incorrect côté vente si l'income_account de la Company pointe sur 7061 ou
7011 indifféremment selon le secteur.

Logique d'attribution :
- Secteur services/it/education → income_account 7061 (prestations de services)
- Autres secteurs              → income_account 7011 (ventes de marchandises/produits)
- expense_account              → 6011 systématiquement (achats généraux)
"""

import frappe


# Numéros de compte SYSCOHADA à rechercher
INCOME_SERVICE = "7061"
INCOME_COMMERCE = "7011"
EXPENSE = "6011"

# item_code exact — à jour au moment de l'audit
ITEM_CODE = "Élaboration détaillée du business plan pour l'établissement d'une école maternelle et primaire"

_SERVICE_SECTORS = {"it", "services", "education", "sante", "transport", "immobilier"}


def _leaf(company: str, number: str) -> str | None:
    return frappe.db.get_value(
        "Account",
        {"company": company, "account_number": number, "is_group": 0, "disabled": 0},
        "name",
    )


def _sector_key(company: str) -> str:
    from sitiame_core.secteur_accounts import sector_of_company, _resolve_sector_key
    return _resolve_sector_key(sector_of_company(company))


def execute():
    if not frappe.db.exists("Item", ITEM_CODE):
        frappe.log_error(
            title="add_service_item_defaults",
            message=f"Article introuvable : {ITEM_CODE!r}",
        )
        return

    companies = frappe.get_all("Company", pluck="name")
    added = []

    for company in companies:
        # Ne pas écraser un Item Default existant
        if frappe.db.exists("Item Default", {"parent": ITEM_CODE, "company": company}):
            continue

        sector = _sector_key(company)
        income_num = INCOME_SERVICE if sector in _SERVICE_SECTORS else INCOME_COMMERCE

        income_acc = _leaf(company, income_num)
        expense_acc = _leaf(company, EXPENSE)

        if not income_acc and not expense_acc:
            continue

        doc = frappe.get_doc("Item", ITEM_CODE)
        doc.append(
            "item_defaults",
            {
                "company": company,
                "income_account": income_acc,
                "expense_account": expense_acc,
            },
        )
        doc.save(ignore_permissions=True)
        added.append(f"{company}: vente={income_acc} achat={expense_acc}")

    frappe.db.commit()
    frappe.log_error(
        title="add_service_item_defaults",
        message="Item Defaults ajoutés :\n" + "\n".join(added) if added else "Rien à ajouter.",
    )
