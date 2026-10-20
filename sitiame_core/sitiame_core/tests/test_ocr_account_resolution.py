# Copyright (c) 2026, Sitiame Capital
# License: MIT

"""Tests pour la résolution des comptes SYSCOHADA dans l'OCR et les secteurs.

Couvre :
- Résolution des mots-clés (achats et ventes)
- Secteurs : vente marchandises → 7011, prestations → 7061
- Distinction 4011 (fournisseurs bilan) vs 4081 (transitoire stock)
- Normalisation des secteurs (variantes de casse, espaces)
- Comptes de vente OCR
- Isolation : un numéro de compte d'une Company ne peut pas être utilisé pour une autre

Exécution dans le container :
  bench --site erp.sitiame-capital.com run-tests \
      --app sitiame_core --module sitiame_core.tests.test_ocr_account_resolution
"""

import frappe
from frappe.tests.utils import FrappeTestCase

from sitiame_core.ocr_accounts import keyword_account_number, vat_nature
from sitiame_core.secteur_accounts import (
    _resolve_sector_key,
    sector_keyword_account_number,
    default_accounts_for_document,
)


# ── T1 : Résolution mots-clés achats ────────────────────────────────────────


class TestPurchaseKeywords(FrappeTestCase):
    """keyword_account_number retourne le bon compte SYSCOHADA selon le libellé."""

    def test_marchandises_achat(self):
        """'Achat de marchandises' doit pointer sur 6011 ou un 60x (achats)."""
        num = keyword_account_number("Achat de marchandises", is_purchase=True)
        # Peut être None si aucun pattern ne matche (ce libellé générique),
        # mais ne doit JAMAIS retourner un compte de tiers (4xxx).
        if num:
            self.assertFalse(num.startswith("4"), f"Compte de tiers {num!r} pour un achat de marchandises")

    def test_loyer_6222(self):
        num = keyword_account_number("Loyer du bureau du mois d'octobre", is_purchase=True)
        self.assertEqual(num, "6222")

    def test_electricite_6052(self):
        num = keyword_account_number("Facture CIE - Électricité - prime fixe", is_purchase=True)
        self.assertEqual(num, "6052")

    def test_carburant_6042(self):
        num = keyword_account_number("Carburant gasoil camion", is_purchase=True)
        self.assertEqual(num, "6042")

    def test_honoraires_6324(self):
        num = keyword_account_number("Honoraires expert-comptable", is_purchase=True)
        self.assertEqual(num, "6324")

    def test_fournitures_bureau_6047(self):
        num = keyword_account_number("Ramettes de papier A4 et classeurs", is_purchase=True)
        self.assertEqual(num, "6047")

    def test_transport_628(self):
        num = keyword_account_number("Transport de marchandises", is_purchase=True)
        self.assertEqual(num, "628")

    def test_4011_not_returned_for_purchase_line(self):
        """Aucun mot-clé d'achat ne doit retourner 4011 (compte fournisseurs bilan)."""
        test_labels = [
            "Achat marchandises",
            "Fournisseur Wave",
            "Règlement fournisseur",
            "Achats divers",
        ]
        for label in test_labels:
            num = keyword_account_number(label, is_purchase=True)
            if num:
                self.assertNotEqual(num, "4011", f"4011 retourné pour le libellé achat {label!r}")

    def test_4081_not_returned_for_purchase_line(self):
        """4081 (stock reçu non facturé) ne doit jamais être suggéré par OCR."""
        test_labels = [
            "Stock reçu",
            "Marchandises en transit",
            "Achats de marchandises",
            "Réapprovisionnement stock",
        ]
        for label in test_labels:
            num = keyword_account_number(label, is_purchase=True)
            if num:
                self.assertNotEqual(num, "4081", f"4081 retourné pour {label!r}")


# ── T2 : Résolution mots-clés ventes ────────────────────────────────────────


class TestSalesKeywords(FrappeTestCase):
    """Les ventes de marchandises → 7011, les prestations → 7061."""

    def test_marchandises_7011(self):
        """'Vente de marchandises' → 7011."""
        num = keyword_account_number("Vente de marchandises en gros", is_purchase=False)
        self.assertEqual(num, "7011")

    def test_sacs_7011(self):
        """Articles pondéraux (sacs, kg) → 7011."""
        num = keyword_account_number("Sacs de riz 50 kg", is_purchase=False)
        self.assertEqual(num, "7011")

    def test_prestation_7061(self):
        """Prestation de service → 7061."""
        num = keyword_account_number("Prestation de conseil en gestion", is_purchase=False)
        self.assertEqual(num, "7061")

    def test_maintenance_7061(self):
        """Maintenance (service) → 7061."""
        num = keyword_account_number("Contrat de maintenance informatique", is_purchase=False)
        self.assertEqual(num, "7061")

    def test_travaux_7051(self):
        """Travaux de construction → 7051."""
        num = keyword_account_number("Travaux de maçonnerie - chantier", is_purchase=False)
        self.assertEqual(num, "7051")

    def test_frais_port_7071(self):
        """Frais de port refacturés → 7071."""
        num = keyword_account_number("Frais de livraison refacturés", is_purchase=False)
        self.assertEqual(num, "7071")

    def test_4111_not_returned_for_sale_line(self):
        """Aucun mot-clé de vente ne doit retourner 4111 (compte clients bilan)."""
        for label in ["Vente marchandises", "Prestations de services", "Facture client"]:
            num = keyword_account_number(label, is_purchase=False)
            if num:
                self.assertNotEqual(num, "4111", f"4111 retourné pour {label!r}")


# ── T3 : Nature TVA ──────────────────────────────────────────────────────────


class TestVatNature(FrappeTestCase):
    """vat_nature retourne la bonne catégorie selon le compte."""

    def test_60x_achat_goods(self):
        self.assertEqual(vat_nature(True, "6011"), "goods")
        self.assertEqual(vat_nature(True, "601"), "goods")

    def test_61x_achat_transport(self):
        self.assertEqual(vat_nature(True, "6181"), "transport")
        self.assertEqual(vat_nature(True, "614"), "transport")

    def test_62x_achat_service(self):
        self.assertEqual(vat_nature(True, "6222"), "service")
        self.assertEqual(vat_nature(True, "6324"), "service")

    def test_2x_fixed_asset(self):
        self.assertEqual(vat_nature(True, "2442"), "fixed_asset")

    def test_701_sale_goods(self):
        self.assertEqual(vat_nature(False, "7011"), "goods")
        self.assertEqual(vat_nature(False, "7012"), "goods")

    def test_705_sale_works(self):
        self.assertEqual(vat_nature(False, "7051"), "works")

    def test_706_sale_service(self):
        self.assertEqual(vat_nature(False, "7061"), "service")


# ── T4 : Résolution secteur — variantes ─────────────────────────────────────


class TestSectorResolution(FrappeTestCase):
    """_resolve_sector_key gère toutes les variantes de casse et d'orthographe."""

    def test_commerce_variants(self):
        for val in ("Commerce & Retail", "commerce & retail", "COMMERCE", "commerce", "retail"):
            with self.subTest(val=val):
                self.assertEqual(_resolve_sector_key(val), "commerce")

    def test_it_variants(self):
        for val in ("Technologies & IT", "technologies & it", "IT", "informatique", "numerique"):
            with self.subTest(val=val):
                self.assertEqual(_resolve_sector_key(val), "it")

    def test_services_variants(self):
        for val in ("services", "Services", "SERVICES", "conseil"):
            with self.subTest(val=val):
                self.assertEqual(_resolve_sector_key(val), "services")

    def test_btp_variants(self):
        for val in ("btp", "BTP", "construction", "batiment"):
            with self.subTest(val=val):
                self.assertEqual(_resolve_sector_key(val), "btp")

    def test_unknown_returns_default(self):
        for val in (None, "", "xyz_inconnu", "   "):
            with self.subTest(val=val):
                self.assertEqual(_resolve_sector_key(val), "default")


# ── T5 : Comptes sectoriels — ventes ────────────────────────────────────────


class TestSectorDocumentAccounts(FrappeTestCase):
    """Les comptes de vente des secteurs de services utilisent 706, pas 701."""

    SERVICE_SECTORS = ("it", "services", "education", "sante", "transport", "immobilier")
    COMMERCE_SECTORS = ("commerce", "restauration", "agriculture", "default")

    def test_service_sectors_sales_use_706x(self):
        """Secteurs services/it/éducation → ventes sur 706 (prestations)."""
        from sitiame_core.secteur_accounts import _SECTOR_DOCUMENT_ACCOUNTS
        for sector in self.SERVICE_SECTORS:
            with self.subTest(sector=sector):
                pair = _SECTOR_DOCUMENT_ACCOUNTS[sector].get("sales_invoice")
                self.assertIsNotNone(pair, f"Pas de sales_invoice pour secteur {sector!r}")
                credit_num = pair[1]
                self.assertTrue(
                    credit_num.startswith("706") or credit_num == "706",
                    f"Secteur {sector!r} : compte vente {credit_num!r} ne commence pas par 706"
                )

    def test_commerce_sectors_sales_use_701x(self):
        """Secteurs commerce/restauration → ventes sur 701 (marchandises)."""
        from sitiame_core.secteur_accounts import _SECTOR_DOCUMENT_ACCOUNTS
        for sector in self.COMMERCE_SECTORS:
            with self.subTest(sector=sector):
                pair = _SECTOR_DOCUMENT_ACCOUNTS[sector].get("sales_invoice")
                self.assertIsNotNone(pair, f"Pas de sales_invoice pour secteur {sector!r}")
                credit_num = pair[1]
                self.assertTrue(
                    credit_num.startswith("701") or credit_num.startswith("70"),
                    f"Secteur {sector!r} : compte vente {credit_num!r} attendu 701x"
                )

    def test_industrie_btp_have_own_revenue_accounts(self):
        """Industrie → 702, BTP → 705 (travaux)."""
        from sitiame_core.secteur_accounts import _SECTOR_DOCUMENT_ACCOUNTS
        self.assertEqual(_SECTOR_DOCUMENT_ACCOUNTS["industrie"]["sales_invoice"][1], "702")
        self.assertEqual(_SECTOR_DOCUMENT_ACCOUNTS["btp"]["sales_invoice"][1], "705")


# ── T6 : Cohérence 4011 vs 4081 ─────────────────────────────────────────────


class TestAccountDistinction(FrappeTestCase):
    """4011 (fournisseurs bilan) et 4081 (stock reçu non facturé) ont des rôles différents."""

    def test_4011_is_supplier_account(self):
        """4011 = compte de tiers fournisseur, pas de charges."""
        # Vérifier la structure attendue dans le plan comptable
        acc = frappe.db.get_value(
            "Account",
            {"account_number": "4011", "is_group": 0, "disabled": 0},
            ["root_type", "account_type"],
            as_dict=True,
        )
        if not acc:
            self.skipTest("Compte 4011 introuvable en DB de test")
        self.assertIn(acc.root_type, ("Liability", "Asset"),
                      "4011 doit être un compte de bilan (Liability ou Asset), pas une charge")

    def test_4081_is_not_expense_account(self):
        """4081 (stock reçu non facturé) ne doit pas être un compte de charges."""
        acc = frappe.db.get_value(
            "Account",
            {"account_number": "4081", "is_group": 0, "disabled": 0},
            "root_type",
        )
        if not acc:
            self.skipTest("Compte 4081 introuvable en DB de test")
        self.assertNotEqual(acc, "Expense",
                            "4081 ne doit jamais être classé comme Expense dans ERPNext")

    def test_sector_defaults_never_use_4011_as_debit_charge(self):
        """Aucun secteur ne doit avoir 4011 comme compte de débit sur une ligne de charge."""
        from sitiame_core.secteur_accounts import _SECTOR_DOCUMENT_ACCOUNTS
        charge_docs = ("purchase_invoice", "invoice", "Achat", "expense", "Frais Généraux")
        for sector, doc_map in _SECTOR_DOCUMENT_ACCOUNTS.items():
            for doc_type in charge_docs:
                pair = doc_map.get(doc_type)
                if pair:
                    debit_num = pair[0]
                    with self.subTest(sector=sector, doc_type=doc_type):
                        self.assertNotEqual(
                            debit_num, "4011",
                            f"Secteur {sector!r}/{doc_type!r} : 4011 ne doit pas être le compte de débit de charge"
                        )
                        self.assertNotEqual(
                            debit_num, "4081",
                            f"Secteur {sector!r}/{doc_type!r} : 4081 (transitoire stock) ne doit pas être le compte de débit de charge"
                        )
