# Copyright (c) 2026, Sitiame Capital
# License: MIT

"""Tests pour account_guard.validate_purchase_expense_account.

Exécution dans le container :
  cd /home/frappe/frappe-bench
  bench --site erp.sitiame-capital.com run-tests \
      --app sitiame_core --module sitiame_core.tests.test_account_guard
"""

import frappe
from frappe.tests.utils import FrappeTestCase


class TestAccountGuard(FrappeTestCase):
    """Tests du garde-fou comptable sur Purchase Invoice."""

    COMPANY = "GROUPRE PRO ABK INC SARL"
    SUPPLIER = "LEVCI"
    ITEM_COMMERCE = "VIANDE FRAICHE"  # is_stock_item=0 après patch

    # ── helpers ──────────────────────────────────────────────────────

    def _get_account(self, number: str) -> str:
        acc = frappe.db.get_value(
            "Account",
            {"company": self.COMPANY, "account_number": number, "is_group": 0, "disabled": 0},
            "name",
        )
        self.assertIsNotNone(acc, f"Compte {number} introuvable pour {self.COMPANY}")
        return acc

    def _make_pinv(self, item_code: str, expense_account: str, update_stock: int = 0):
        """Crée un Purchase Invoice brouillon avec le compte spécifié."""
        doc = frappe.new_doc("Purchase Invoice")
        doc.company = self.COMPANY
        doc.supplier = self.SUPPLIER
        doc.posting_date = frappe.utils.today()
        doc.due_date = frappe.utils.add_days(frappe.utils.today(), 30)
        doc.update_stock = update_stock
        doc.append(
            "items",
            {
                "item_code": item_code,
                "qty": 1,
                "rate": 1000,
                "expense_account": expense_account,
                "uom": "Nos",
            },
        )
        doc.insert(ignore_permissions=True)
        return doc

    # ── T1 : Compte 4081 corrigé automatiquement en 6011 ─────────────

    def test_4081_replaced_by_item_default(self):
        """Si la ligne a 4081 et update_stock=0, before_submit corrige vers 6011."""
        acc_4081 = self._get_account("4081")
        acc_6011 = self._get_account("6011")

        doc = self._make_pinv(self.ITEM_COMMERCE, acc_4081, update_stock=0)
        try:
            doc.submit()
        except Exception:
            doc.reload()
            raise

        # Vérifier que la GL Entry utilise 6011, pas 4081
        gl_bad = frappe.db.exists(
            "GL Entry",
            {"voucher_no": doc.name, "account": acc_4081, "is_cancelled": 0, "debit": [">", 0]},
        )
        self.assertIsNone(gl_bad, "GL Entry avec 4081 trouvée après soumission")

        gl_ok = frappe.db.exists(
            "GL Entry",
            {"voucher_no": doc.name, "account": acc_6011, "is_cancelled": 0, "debit": [">", 0]},
        )
        self.assertIsNotNone(gl_ok, "GL Entry avec 6011 absente après correction")

        doc.cancel()

    # ── T2 : 6011 déjà correct → pas de modification ─────────────────

    def test_6011_unchanged(self):
        """Une ligne déjà sur 6011 ne doit pas être modifiée."""
        acc_6011 = self._get_account("6011")
        doc = self._make_pinv(self.ITEM_COMMERCE, acc_6011, update_stock=0)
        doc.submit()

        gl_ok = frappe.db.exists(
            "GL Entry",
            {"voucher_no": doc.name, "account": acc_6011, "is_cancelled": 0, "debit": [">", 0]},
        )
        self.assertIsNotNone(gl_ok, "GL Entry avec 6011 absente")
        doc.cancel()

    # ── T3 : update_stock=1 → pas d'intervention ─────────────────────

    def test_update_stock_1_untouched(self):
        """Avec update_stock=1, account_guard ne doit pas intervenir.
        ERPNext gère la comptabilité de stock normalement.
        """
        acc_4081 = self._get_account("4081")
        # Avec update_stock=1, ERPNext crée aussi des Stock Ledger Entries.
        # Ce test vérifie uniquement que le hook ne lève pas d'erreur.
        doc = self._make_pinv(self.ITEM_COMMERCE, acc_4081, update_stock=1)
        try:
            doc.submit()
        except frappe.exceptions.ValidationError:
            self.fail("account_guard ne devrait pas lever d'erreur avec update_stock=1")
        except Exception:
            # D'autres erreurs ERPNext possibles avec update_stock=1 (warehouse, etc.)
            pass
        finally:
            try:
                doc.reload()
                if doc.docstatus == 1:
                    doc.cancel()
            except Exception:
                pass

    # ── T4 : secteurs — résolution ────────────────────────────────────

    def test_sector_resolution(self):
        """Tous les secteurs utilisés en production se résolvent correctement."""
        from sitiame_core.secteur_accounts import _resolve_sector_key

        cases = {
            "commerce":           "commerce",
            "Commerce & Retail":  "commerce",
            "commerce & retail":  "commerce",
            "Technologies & IT":  "it",
            "technologies & it":  "it",
            "it":                 "it",
            "services":           "services",
            "agriculture":        "agriculture",
            "Services":           "services",
            "COMMERCE":           "commerce",
            None:                 "default",
            "":                   "default",
            "xyz_inconnu":        "default",
        }
        for val, expected in cases.items():
            with self.subTest(sector=val):
                self.assertEqual(
                    _resolve_sector_key(val),
                    expected,
                    f"_resolve_sector_key({val!r}) doit retourner {expected!r}",
                )

    # ── T5 : isolation — GROUPRE ne voit pas les données d'ALI ────────

    def test_groupre_isolation_from_ali(self):
        """Les factures de GROUPRE PRO ABK ne sont pas visibles pour un
        utilisateur d'ALI SARL (isolation inter-PME).
        """
        from sitiame_core.party_isolation import company_of

        ali_user = frappe.db.get_value(
            "User Permission", {"allow": "Company", "for_value": "ALI SARL"}, "user"
        )
        if not ali_user:
            self.skipTest("Aucun utilisateur ALI SARL trouvé")

        frappe.set_user(ali_user)
        try:
            pinv_groupre = frappe.get_all(
                "Purchase Invoice",
                filters={"company": "GROUPRE PRO ABK INC SARL", "docstatus": 1},
                pluck="name",
            )
            self.assertEqual(
                len(pinv_groupre),
                0,
                f"ALI SARL peut voir {len(pinv_groupre)} factures de GROUPRE PRO ABK",
            )
        finally:
            frappe.set_user("Administrator")

    # ── T6 : article sans Item Default → fallback Company ─────────────

    def test_no_item_default_uses_company_fallback(self):
        """Un article sans Item Default doit utiliser default_expense_account
        de la Company (6011), pas 4081.
        """
        # Créer un article temporaire sans Item Default
        tmp_item = frappe.new_doc("Item")
        tmp_item.item_code = "_test_no_default_guard"
        tmp_item.item_name = "_test_no_default_guard"
        tmp_item.item_group = "Consumable"
        tmp_item.is_stock_item = 0
        tmp_item.insert(ignore_permissions=True)

        acc_4081 = self._get_account("4081")
        acc_6011 = self._get_account("6011")

        doc = self._make_pinv("_test_no_default_guard", acc_4081, update_stock=0)
        doc.submit()

        gl_bad = frappe.db.exists(
            "GL Entry",
            {"voucher_no": doc.name, "account": acc_4081, "is_cancelled": 0, "debit": [">", 0]},
        )
        self.assertIsNone(gl_bad, "GL Entry avec 4081 sur article sans Item Default")

        doc.cancel()
        tmp_item.delete(ignore_permissions=True)
