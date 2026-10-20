# Copyright (c) 2026, Sitiame Capital
# License: MIT

"""Comptes SYSCOHADA par secteur d'activité et type de document.

Lorsque l'OCR détecte une facture, ce module enrichit la réponse avec les
comptes débit/crédit adaptés au secteur de la PME. La résolution suit cet
ordre de priorité :
  1. Compte mémorisé (dernière facture validée pour ce tiers) — dans ocr_accounts.py
  2. Mot-clé OCR sectoriel (surcharges propres au secteur)
  3. Mot-clé OCR générique (PURCHASE_KEYWORD_ACCOUNTS de ocr_accounts.py)
  4. Compte par défaut du secteur pour ce type de document
  5. Compte par défaut générique

Les valeurs de secteur correspondent aux options du formulaire d'inscription
(company-signup.html) et à celles stockées dans Company Signup.sector.
"""

import re
import unicodedata

import frappe

# ---------------------------------------------------------------------------
# Correspondance secteur inscrit → clé interne normalisée
# Chaque secteur du formulaire est mappé vers une clé courte.
# ---------------------------------------------------------------------------
_SECTOR_ALIASES = {
    # Valeurs exactes issues de Company Signup (production 2026-10-20)
    "commerce & retail":    "commerce",
    "technologies & it":    "it",
    # Valeurs normalisées courtes
    "commerce":             "commerce",
    "retail":               "commerce",
    "technologies":         "it",
    "it":                   "it",
    "informatique":         "it",
    "numerique":            "it",
    "services":         "services",
    "conseil":          "services",
    "industrie":        "industrie",
    "industrie & production": "industrie",
    "production":       "industrie",
    "sante":            "sante",
    "medical":          "sante",
    "education":        "education",
    "enseignement":     "education",
    "formation":        "education",
    "btp":              "btp",
    "construction":     "btp",
    "batiment":         "btp",
    "restauration":     "restauration",
    "hotel":            "restauration",
    "hotellerie":       "restauration",
    "transport":        "transport",
    "logistique":       "transport",
    "agriculture":      "agriculture",
    "agro":             "agriculture",
    "immobilier":       "immobilier",
    "foncier":          "immobilier",
}

# ---------------------------------------------------------------------------
# Comptes par défaut par secteur et type de document
# Format : { clé_secteur : { document_type : (num_debit, num_credit) } }
# num_* = numéro de compte SYSCOHADA, résolu en feuille active via leaf_by_number
# ---------------------------------------------------------------------------
_SECTOR_DOCUMENT_ACCOUNTS = {
    "commerce": {
        "invoice":                  ("601", "401"),
        "purchase_invoice":         ("601", "401"),
        "Achat":                    ("601", "401"),
        "sales_invoice":            ("411", "701"),
        "Vente":                    ("411", "701"),
        "receipt":                  ("571", "411"),
        "Reçu":                     ("571", "411"),
        "expense":                  ("6047", "401"),
        "Frais Généraux":           ("6047", "401"),
        "Paie":                     ("661", "421"),
        "Justificatif":             ("6047", "571"),
        "Règlement Client":         ("521", "411"),
        "Règlement Fournisseur":    ("401", "521"),
        "Opérations Diverses":      ("471", "472"),
    },
    "it": {
        "invoice":                  ("6327", "401"),
        "purchase_invoice":         ("6327", "401"),
        "Achat":                    ("6327", "401"),
        "sales_invoice":            ("411", "706"),
        "Vente":                    ("411", "706"),
        "receipt":                  ("521", "706"),
        "Reçu":                     ("521", "706"),
        "expense":                  ("6343", "401"),
        "Frais Généraux":           ("6343", "401"),
        "Paie":                     ("661", "421"),
        "Justificatif":             ("6343", "571"),
        "Règlement Client":         ("521", "411"),
        "Règlement Fournisseur":    ("401", "521"),
        "Opérations Diverses":      ("471", "472"),
    },
    "services": {
        "invoice":                  ("6327", "401"),
        "purchase_invoice":         ("6327", "401"),
        "Achat":                    ("6327", "401"),
        "sales_invoice":            ("411", "706"),
        "Vente":                    ("411", "706"),
        "receipt":                  ("521", "706"),
        "Reçu":                     ("521", "706"),
        "expense":                  ("6047", "401"),
        "Frais Généraux":           ("6047", "401"),
        "Paie":                     ("661", "421"),
        "Justificatif":             ("6047", "571"),
        "Règlement Client":         ("521", "411"),
        "Règlement Fournisseur":    ("401", "521"),
        "Opérations Diverses":      ("471", "472"),
    },
    "industrie": {
        "invoice":                  ("602", "401"),
        "purchase_invoice":         ("602", "401"),
        "Achat":                    ("602", "401"),
        "sales_invoice":            ("411", "702"),
        "Vente":                    ("411", "702"),
        "receipt":                  ("521", "702"),
        "Reçu":                     ("521", "702"),
        "expense":                  ("604", "401"),
        "Frais Généraux":           ("604", "401"),
        "Paie":                     ("661", "421"),
        "Justificatif":             ("604", "571"),
        "Règlement Client":         ("521", "411"),
        "Règlement Fournisseur":    ("401", "521"),
        "Opérations Diverses":      ("471", "472"),
    },
    "btp": {
        "invoice":                  ("604", "401"),   # Matières consommables
        "purchase_invoice":         ("604", "401"),
        "Achat":                    ("604", "401"),
        "sales_invoice":            ("411", "705"),   # Travaux
        "Vente":                    ("411", "705"),
        "receipt":                  ("521", "705"),
        "Reçu":                     ("521", "705"),
        "expense":                  ("6042", "401"),  # Carburant
        "Frais Généraux":           ("6042", "401"),
        "Paie":                     ("661", "421"),
        "Justificatif":             ("6042", "571"),
        "Règlement Client":         ("521", "411"),
        "Règlement Fournisseur":    ("401", "521"),
        "Opérations Diverses":      ("471", "472"),
    },
    "restauration": {
        "invoice":                  ("601", "401"),   # Achats de denrées alimentaires
        "purchase_invoice":         ("601", "401"),
        "Achat":                    ("601", "401"),
        "sales_invoice":            ("411", "701"),   # Ventes sur place
        "Vente":                    ("411", "701"),
        "receipt":                  ("571", "701"),
        "Reçu":                     ("571", "701"),
        "expense":                  ("6383", "401"),  # Repas / réceptions
        "Frais Généraux":           ("6383", "401"),
        "Paie":                     ("661", "421"),
        "Justificatif":             ("6383", "571"),
        "Règlement Client":         ("521", "411"),
        "Règlement Fournisseur":    ("401", "521"),
        "Opérations Diverses":      ("471", "472"),
    },
    "transport": {
        "invoice":                  ("6042", "401"),  # Carburant
        "purchase_invoice":         ("6042", "401"),
        "Achat":                    ("6042", "401"),
        "sales_invoice":            ("411", "706"),   # Prestations transport
        "Vente":                    ("411", "706"),
        "receipt":                  ("521", "706"),
        "Reçu":                     ("521", "706"),
        "expense":                  ("6242", "401"),  # Entretien véhicules
        "Frais Généraux":           ("6242", "401"),
        "Paie":                     ("661", "421"),
        "Justificatif":             ("6242", "571"),
        "Règlement Client":         ("521", "411"),
        "Règlement Fournisseur":    ("401", "521"),
        "Opérations Diverses":      ("471", "472"),
    },
    "sante": {
        "invoice":                  ("604", "401"),
        "purchase_invoice":         ("604", "401"),
        "Achat":                    ("604", "401"),
        "sales_invoice":            ("411", "706"),
        "Vente":                    ("411", "706"),
        "receipt":                  ("521", "706"),
        "Reçu":                     ("521", "706"),
        "expense":                  ("6047", "401"),
        "Frais Généraux":           ("6047", "401"),
        "Paie":                     ("661", "421"),
        "Justificatif":             ("6047", "571"),
        "Règlement Client":         ("521", "411"),
        "Règlement Fournisseur":    ("401", "521"),
        "Opérations Diverses":      ("471", "472"),
    },
    "education": {
        "invoice":                  ("6047", "401"),
        "purchase_invoice":         ("6047", "401"),
        "Achat":                    ("6047", "401"),
        "sales_invoice":            ("411", "706"),
        "Vente":                    ("411", "706"),
        "receipt":                  ("521", "706"),
        "Reçu":                     ("521", "706"),
        "expense":                  ("6265", "401"),
        "Frais Généraux":           ("6265", "401"),
        "Paie":                     ("661", "421"),
        "Justificatif":             ("6265", "571"),
        "Règlement Client":         ("521", "411"),
        "Règlement Fournisseur":    ("401", "521"),
        "Opérations Diverses":      ("471", "472"),
    },
    "agriculture": {
        "invoice":                  ("602", "401"),   # Semences, intrants
        "purchase_invoice":         ("602", "401"),
        "Achat":                    ("602", "401"),
        "sales_invoice":            ("411", "701"),
        "Vente":                    ("411", "701"),
        "receipt":                  ("521", "701"),
        "Reçu":                     ("521", "701"),
        "expense":                  ("6042", "401"),
        "Frais Généraux":           ("6042", "401"),
        "Paie":                     ("661", "421"),
        "Justificatif":             ("6042", "571"),
        "Règlement Client":         ("521", "411"),
        "Règlement Fournisseur":    ("401", "521"),
        "Opérations Diverses":      ("471", "472"),
    },
    "immobilier": {
        "invoice":                  ("6222", "401"),  # Loyers
        "purchase_invoice":         ("6222", "401"),
        "Achat":                    ("6222", "401"),
        "sales_invoice":            ("411", "706"),
        "Vente":                    ("411", "706"),
        "receipt":                  ("521", "706"),
        "Reçu":                     ("521", "706"),
        "expense":                  ("6243", "401"),  # Maintenance
        "Frais Généraux":           ("6243", "401"),
        "Paie":                     ("661", "421"),
        "Justificatif":             ("6243", "571"),
        "Règlement Client":         ("521", "411"),
        "Règlement Fournisseur":    ("401", "521"),
        "Opérations Diverses":      ("471", "472"),
    },
    # Fallback toutes valeurs inconnues
    "default": {
        "invoice":                  ("601", "401"),
        "purchase_invoice":         ("601", "401"),
        "Achat":                    ("601", "401"),
        "sales_invoice":            ("411", "701"),
        "Vente":                    ("411", "701"),
        "receipt":                  ("521", "701"),
        "Reçu":                     ("521", "701"),
        "expense":                  ("6047", "401"),
        "Frais Généraux":           ("6047", "401"),
        "Paie":                     ("661", "421"),
        "Justificatif":             ("6047", "571"),
        "Règlement Client":         ("521", "411"),
        "Règlement Fournisseur":    ("401", "521"),
        "Opérations Diverses":      ("471", "472"),
    },
}

# Mots-clés OCR supplémentaires propres à certains secteurs (surcharges).
# Format : { clé_secteur : [ (regex_pattern, account_number) ] }
_SECTOR_KEYWORD_OVERRIDES = {
    "commerce": [
        (r"\bmarchandises?\b|\bstock\b|\breassort\b|\breapprovisionnement\b", "601"),
        (r"\bemballages?\b|\bcolis\b|\bconditionnements?\b", "6015"),
    ],
    "it": [
        (r"\bhebergement\b|\bcloud\b|\bserveur\b|\binfrastructure\b", "6345"),
        (r"\bmaintenance\b|\bsupport\b|\btma\b", "6243"),
        (r"\blicences?\b|\bsaas\b|\blogiciels?\b|\bapi\b", "6343"),
        (r"\bformation\b|\bcertification\b|\btraining\b", "633"),
    ],
    "btp": [
        (r"\bciment\b|\bfer\b|\bbeton\b|\bsable\b|\bgravillon\b", "604"),
        (r"\bsous.?traitance\b|\bfaconnage\b", "621"),
        (r"\bcarburant\b|\bgazole\b|\bgazoil\b|\bessence\b", "6042"),
        (r"\boutillage\b|\bpetit materiel\b", "6056"),
    ],
    "industrie": [
        (r"\bmatiere\b|\bmp\b|\bintrant\b|\bmateriaux?\b", "602"),
        (r"\bmatiere consommable\b|\bconsommables?\b|\bemballages?\b", "604"),
        (r"\bsous.?traitance\b|\bfaconnage\b", "621"),
        (r"\bpiece\b|\bcomposant\b|\bsemi.?fini\b", "6056"),
        (r"\bcarburant\b|\bgazole\b|\bgazoil\b", "6042"),
    ],
    "restauration": [
        (r"\bdenrees?\b|\baliments?\b|\bviande\b|\bpoisso\b|\blegumes?\b|\bfruits?\b", "601"),
        (r"\bboissons?\b|\bbiere\b|\bvin\b|\bngas\b|\bgazeuse\b", "601"),
        (r"\bgaz\b|\butopaz\b|\bcombustible\b", "6042"),
    ],
    "transport": [
        (r"\bcarburant\b|\bgazole\b|\bgasoil\b|\bessence\b", "6042"),
        (r"\bpneus?\b|\bpneumatiques?\b|\bvidange\b|\breparation\b", "6242"),
        (r"\bpeages?\b|\bfrais de route\b", "6252"),
    ],
    "sante": [
        (r"\bmedica[ml]ents?\b|\bpharmacie\b|\bspecialites?\b", "604"),
        (r"\bconsultation\b|\bhonoraires? medicaux\b", "6324"),
        (r"\banalyses?\b|\blaboratoire\b|\bdiagnostic\b", "6327"),
        (r"\bmateriels? medicaux?\b|\bconsommables? medicaux?\b", "6056"),
    ],
    "education": [
        (r"\blivres?\b|\bouvrages?\b|\bmanuels?\b|\bpedagogie\b", "6265"),
        (r"\bfournitures? scolaires?\b|\bpapeterie\b", "6047"),
        (r"\bformation\b|\bcertification\b|\bstage\b", "633"),
        (r"\bintervenants?\b|\bvacataires?\b|\bhonoraires? d.?enseignement\b", "6324"),
    ],
    "agriculture": [
        (r"\bsemences?\b|\bentrants?\b|\bintrants?\b|\bengrais\b|\bpesticides?\b", "602"),
        (r"\bcarburant\b|\bgazoil\b|\bgazole\b", "6042"),
        (r"\birrigations?\b|\btuyaux?\b|\bequipement agricole\b", "6056"),
    ],
    "immobilier": [
        (r"\bloyer\b|\blocation\b|\bconge locatif\b", "6222"),
        (r"\btravaux\b|\bravalement\b|\bpeinture\b|\breparation immo\b", "6243"),
        (r"\btaxe\b|\bdroits? enregistrement\b|\bfoncier\b", "6462"),
    ],
}


def _normalize(sector):
    """Normalise le secteur en minuscules sans accents."""
    s = (sector or "").strip().lower()
    s = unicodedata.normalize("NFKD", s)
    s = "".join(ch for ch in s if not unicodedata.combining(ch))
    return s


def _resolve_sector_key(sector):
    """Retourne la clé interne du secteur (ex: 'it', 'btp', 'commerce'...).

    Cherche d'abord une correspondance exacte dans _SECTOR_ALIASES, puis
    une correspondance par sous-chaîne (pour gérer 'Commerce & Retail',
    'Technologies & IT', etc.).
    """
    normalized = _normalize(sector or "")
    if not normalized:
        return "default"
    # Correspondance exacte
    if normalized in _SECTOR_ALIASES:
        return _SECTOR_ALIASES[normalized]
    # Correspondance par mot-clé (premier mot du secteur)
    for alias_key, internal_key in _SECTOR_ALIASES.items():
        if alias_key in normalized or normalized in alias_key:
            return internal_key
    return "default"


def sector_of_company(company):
    """Secteur de la PME tel que saisi lors de l'inscription.

    Cherche dans Company Signup (doctype métier Sitiame) lié à la company.
    Retourne None si introuvable ou non renseigné.
    """
    if not company:
        return None
    sector = frappe.db.get_value("Company Signup", {"company": company}, "sector")
    return sector or None


def sector_of_user(user=None):
    """Secteur de la PME de l'utilisateur connecté (ou `user`)."""
    from sitiame_core.party_isolation import company_of
    user = user or frappe.session.user
    company = company_of(user)
    return sector_of_company(company) if company else None


def sector_keyword_account_number(sector, label):
    """Numéro de compte via les surcharges sectorielles, ou None.

    Testé avant les règles génériques de ocr_accounts.keyword_account_number.
    """
    def fold(text):
        text = (text or "").replace("œ", "oe").replace("æ", "ae")
        text = unicodedata.normalize("NFKD", text)
        text = "".join(ch for ch in text if not unicodedata.combining(ch)).lower()
        return re.sub(r"['']", " ", text)

    sector_key = _resolve_sector_key(sector)
    overrides = _SECTOR_KEYWORD_OVERRIDES.get(sector_key, [])
    folded = fold(label)
    for pattern, number in overrides:
        if re.search(pattern, folded):
            return number
    return None


def default_accounts_for_document(company, document_type, sector=None):
    """Comptes débit/crédit par défaut selon le secteur et le type de document.

    Retourne un dict {"debit": account_name, "credit": account_name, ...}
    où les noms sont des feuilles actives du plan comptable de la company,
    ou None si le compte n'existe pas dans ce plan.
    """
    from sitiame_core.ocr_accounts import leaf_by_number

    resolved_sector = sector or sector_of_company(company)
    sector_key = _resolve_sector_key(resolved_sector)

    sector_map = _SECTOR_DOCUMENT_ACCOUNTS.get(sector_key) or _SECTOR_DOCUMENT_ACCOUNTS["default"]
    pair = sector_map.get(document_type) or sector_map.get("invoice")
    if not pair:
        return None

    debit_num, credit_num = pair
    debit = leaf_by_number(company, debit_num)
    credit = leaf_by_number(company, credit_num)
    return {
        "debit": debit,
        "debit_number": debit_num,
        "credit": credit,
        "credit_number": credit_num,
        "sector": resolved_sector,
        "sector_key": sector_key,
        "source": "secteur",
    }


def suggest_accounts_for_ocr(company, document_type, line_items=None, ocr_text=""):
    """Suggestion complète de comptes pour un document OCR.

    Pour chaque ligne OCR (line_items), enrichit avec le compte SYSCOHADA
    adapté (surcharge sectorielle > mot-clé générique).  Pour le document
    entier, retourne les comptes débit/crédit en-tête selon le secteur.

    Retourne :
    {
        "header": {"debit": ..., "credit": ..., "sector": ..., "source": ...},
        "lines":  [ {"label": ..., "account": ..., "account_number": ..., "source": ...}, ... ]
    }
    """
    from sitiame_core.ocr_accounts import leaf_by_number, keyword_account_number

    sector = sector_of_company(company)
    header = default_accounts_for_document(company, document_type, sector)
    is_purchase = document_type not in ("sales_invoice", "Vente")

    enriched_lines = []
    for item in (line_items or []):
        label = item.get("description") or item.get("label") or ""
        # 1. Surcharge sectorielle
        num = sector_keyword_account_number(sector or "", label)
        source = "secteur"
        # 2. Mot-clé générique
        if not num:
            num = keyword_account_number(label, is_purchase=is_purchase)
            source = "mot-cle"
        account = leaf_by_number(company, num) if num else None
        enriched_lines.append({
            "label": label,
            "account": account,
            "account_number": num,
            "source": source if account else "defaut",
            **{k: v for k, v in item.items() if k not in ("description", "label")},
        })

    return {
        "header": header,
        "lines": enriched_lines,
    }
