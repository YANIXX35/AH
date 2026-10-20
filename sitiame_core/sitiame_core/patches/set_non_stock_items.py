# Copyright (c) 2026, Sitiame Capital
# License: MIT

"""Passe en is_stock_item=0 les articles de négoce simple (marchandises
achetées et revendues directement) qui n'ont jamais eu de mouvement de
stock enregistré dans ERPNext.

Contexte (audit 2026-10-20) : tous les articles de commerce (VIANDE FRAICHE,
BONBON, HUILE DINOR, POISSONS FRAIS, …) avaient is_stock_item=1 alors que les
PMEs ne gèrent pas leur stock dans ERPNext. Avec enable_perpetual_inventory=1
activé sur toutes les companies, ERPNext utilisait automatiquement le compte
transitoire stock_received_but_not_billed (4081) comme compte de débit sur
les Purchase Invoice, au lieu du compte de charges de l'Item Default (6011).

Ne touche que les articles sans Stock Ledger Entry active : un article qui a
réellement des mouvements de stock est laissé intact.
"""

import frappe


def execute():
    # Articles is_stock_item=1 sans aucune SLE active
    candidates = frappe.db.sql(
        """
        SELECT i.name, i.item_name
        FROM `tabItem` i
        WHERE i.is_stock_item = 1
          AND NOT EXISTS (
              SELECT 1 FROM `tabStock Ledger Entry` sle
              WHERE sle.item_code = i.name AND sle.is_cancelled = 0
          )
        """,
        as_dict=True,
    )

    if not candidates:
        frappe.log_error(title="set_non_stock_items", message="Aucun article à modifier.")
        return

    names = [r.name for r in candidates]
    frappe.log_error(
        title="set_non_stock_items",
        message=f"Articles passés en is_stock_item=0 : {names}",
    )

    for item in candidates:
        frappe.db.set_value(
            "Item",
            item.name,
            {
                "is_stock_item": 0,
                "has_serial_no": 0,
                "has_batch_no": 0,
            },
            update_modified=False,
        )

    frappe.db.commit()
