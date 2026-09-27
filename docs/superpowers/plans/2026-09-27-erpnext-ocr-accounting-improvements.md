# Fiabilisation de l'OCR comptable ERPNext — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Faire lire à l'OCR ERPNext les factures « TOTAL A PAYER »/« Description », créer une ligne de brouillon par ligne lue, proposer le bon compte SYSCOHADA par ligne (mémoire du tiers → mot-clé → défaut signalé) et relier Wave/Orange/MTN/Moov au compte 552 de chaque société.

**Architecture:** Extraction corrigée dans `api.py`. Nouveau module `ocr_accounts.py` (choix des comptes + Mobile Money), consommé par `ocr_invoice.py`, `ocr_payment.py`, `_apply_syscohada_defaults` et un patch Frappe exécuté une fois au `migrate`. Les documents restent des brouillons.

**Tech Stack:** Frappe/ERPNext v16, app `sitiame_core`, pytest avec stub frappe (`tests/conftest.py`).

**Spec:** `docs/superpowers/specs/2026-09-27-erpnext-ocr-accounting-improvements-design.md`

## Global Constraints

- Repo : `C:\Users\yaniss\Desktop\sitiame_core` (branche `master`). Tests : `python -m pytest -q tests` (63 passent au départ).
- Aucun document n'est soumis automatiquement (brouillons uniquement).
- Mots-clés pour les achats seulement ; ventes = mémoire du client, sinon défaut.
- Ne jamais utiliser `_` comme variable jetable dans les modules qui importent `from frappe import _`.
- Déploiement uniquement via `ssh -i ~/.ssh/id_ed25519_sitiame_vps root@31.207.36.253 'bash /root/sitiame_core_deploy.sh'`.
- Commits terminés par `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

---

### Task 1: Extraction — « TOTAL A PAYER », en-tête « Description », colonnes décalées

**Files:**
- Modify: `sitiame_core/api.py` (`_GRAND_TOTAL_PATTERNS`, `_extract_line_items`)
- Test: `tests/test_ocr_parsing.py`

**Interfaces:**
- Produces: `api._extract_invoice_fields(text)` renvoie `grand_total` pour « TOTAL A PAYER » ; `api._extract_line_items(text)` lit l'en-tête Description/Libellé et renvoie `qty` dérivée quand prix et montant sont lus.

- [ ] **Step 1: Failing tests** — ajouter à `tests/test_ocr_parsing.py` :

```python
# Verbatim OCR.space output for facture-test.pdf (2026-09-27): no VAT,
# "TOTAL A PAYER", "Description" header, and the "Qte" header cell lost by
# the OCR (rows keep 4 cells under a 3-cell header).
REAL_OCR_TEXT_NO_VAT = (
	"SITIAME CAPITAL SARL\t\r\n"
	"Facture N: FAC-2026-0042\t\r\n"
	"Date: 17/09/2026\t\r\n"
	"Client : Entreprise Kouassi et Fils\t\r\n"
	"Adresse : Abidjan, Cocody Riviera\t\r\n"
	"Description\tPrix unitaire\tMontant\t\r\n"
	"Prestation de conseil comptable\t1\t250 000\t250 000\t\r\n"
	"Licence logiciel annuelle\t1\t150 000\t150 000\t\r\n"
	"TOTAL A PAYER : 400 000 FCFA"
)


@pytest.mark.parametrize("label", ["TOTAL A PAYER : 400 000", "Total à payer 400 000", "NET À PAYER : 400 000", "Net a payer 400 000"])
def test_amount_to_pay_is_the_grand_total(label):
	fields, _ = api._extract_invoice_fields(label + " FCFA\n")
	assert fields["grand_total"] == 400000.0


def test_real_no_vat_invoice_reads_its_total():
	fields, _ = api._extract_invoice_fields(REAL_OCR_TEXT_NO_VAT)
	assert fields["grand_total"] == 400000.0
	assert "subtotal" not in fields and "tax_amount" not in fields
```

et dans `class TestLineItems` :

```python
	def test_description_header_and_lost_header_cell(self):
		"""The OCR dropped the "Qte" header cell: numbers are right-aligned,
		so the unit price must not be read from the quantity column."""
		assert api._extract_line_items(REAL_OCR_TEXT_NO_VAT) == [
			{"item_name": "Prestation de conseil comptable", "qty": 1.0, "rate": 250000.0},
			{"item_name": "Licence logiciel annuelle", "qty": 1.0, "rate": 150000.0},
		]

	def test_libelle_header(self):
		text = "Libellé\tMontant\nLoyer septembre\t300 000\n\n"
		assert api._extract_line_items(text) == [{"item_name": "Loyer septembre", "rate": 300000.0, "qty": 1}]

	def test_trailing_currency_cell_keeps_the_unshifted_columns(self):
		text = "Designation\tMontant\nLoyer septembre\t300 000\tFCFA\n\n"
		assert api._extract_line_items(text) == [{"item_name": "Loyer septembre", "rate": 300000.0, "qty": 1}]
```

- [ ] **Step 2:** `python -m pytest -q tests/test_ocr_parsing.py` → FAIL (grand_total absent, `[]`).

- [ ] **Step 3: Implementation** — dans `api.py` :

Remplacer dans `_GRAND_TOTAL_PATTERNS` la ligne
`re.compile(r"NET\s*A\s*PAYER\s*[:\-]?\s*" + _AMOUNT_VALUE, re.IGNORECASE),`
par :

```python
	# "Net a payer" / "Total a payer", with or without the accent: invoices
	# without VAT often print only this line (no HT/TTC at all).
	re.compile(r"(?:NET|TOTAL)\s*[AÀ]\s*PAYER\s*[:\-]?\s*" + _AMOUNT_VALUE, re.IGNORECASE),
```

Dans `_extract_line_items`, remplacer `if re.match(r"^\s*d[ée]signation\b", line, re.IGNORECASE):` par
`if re.match(r"^\s*(?:d[ée]signation|description|libell[ée])\b", line, re.IGNORECASE):`,

remplacer `number_at` par :

```python
	def number_at(cells, idx, offset=0):
		if idx is None or idx + offset >= len(cells):
			return None
		return _parse_amount(cells[idx + offset])
```

et, dans la boucle des lignes, remplacer les trois lectures `qty = ...`, `rate = ...`, `amount = ...` par :

```python
		# Numbers sit right of the label: when the OCR lost a header cell
		# (more cells in the row than in the header), read them aligned on
		# the right -- unless that finds nothing (an extra trailing cell such
		# as "FCFA" instead), then keep the header positions.
		shift = len(cells) - len(header_cells)
		for offset in ([shift, 0] if shift > 0 else [0]):
			qty = number_at(cells, qty_idx, offset)
			rate = number_at(cells, rate_idx, offset)
			amount = number_at(cells, amount_idx, offset)
			if rate is not None or amount is not None:
				break
		if qty is None and rate and amount:
			qty = round(amount / rate, 2)
```

- [ ] **Step 4:** `python -m pytest -q tests` → tout passe.
- [ ] **Step 5: Commit** `fix(ocr): read "total a payer" invoices and Description/Libelle item tables`.

---

### Task 2: Module `ocr_accounts.py` — choix du compte et compte Mobile Money

**Files:**
- Create: `sitiame_core/ocr_accounts.py`
- Test: `tests/test_ocr_accounts.py`

**Interfaces:**
- Produces:
  - `keyword_account_number(label: str) -> str | None`
  - `keyword_account(company, label) -> str | None`
  - `remembered_account(is_purchase: bool, party, company) -> str | None`
  - `choose_account(is_purchase, company, label, default_account, remembered) -> (account, source)`, `source ∈ {"memoire", "mot-cle", "defaut"}`
  - `mobile_money_account(company, mode_of_payment) -> str | None`
  - `ensure_mobile_money_modes(company) -> None`

- [ ] **Step 1: Failing tests** — `tests/test_ocr_accounts.py` :

```python
# Copyright (c) 2026, Sitiame Capital
# License: MIT

"""Account proposed for each OCR-read invoice line, and the Mobile Money
account a receipt lands on (sitiame_core.ocr_accounts)."""

import frappe
import pytest

from sitiame_core import ocr_accounts as oa


@pytest.mark.parametrize(
	"label, number",
	[
		("Loyer bureau septembre 2026", "6222"),
		("Facture électricité CIE août", "6052"),
		("Consommation d'eau SODECI", "6051"),
		("Carburant véhicule de service", "6042"),
		("Maintenance mensuelle serveur", "6243"),
		("Produits d'entretien et détergents", "6043"),
		("Réparation climatiseur", "6242"),
		("Honoraires expert-comptable", "6324"),
		("Prestation de conseil informatique", "6327"),
		("Licence logiciel annuelle", "6343"),
		("Hébergement site internet", "6345"),
		("Abonnement internet fibre", "6288"),
		("Fournitures de bureau", "6047"),
		("Billet d'avion Abidjan-Dakar", "6181"),
		("Frais bancaires trimestriels", "6318"),
		("Formation Excel du personnel", "633"),
		("Carton tomates 5kg", None),
	],
)
def test_keyword_account_number(label, number):
	assert oa.keyword_account_number(label) == number


def test_information_is_not_formation():
	assert oa.keyword_account_number("Système d'information") is None


def test_commission_bancaire_is_not_a_mission():
	assert oa.keyword_account_number("Commission bancaire") == "6318"


@pytest.fixture
def ledger(monkeypatch):
	"""Accounts of company "C": 6222/6327 leaves; 6243 disabled."""
	accounts = {
		"6222 - Locations de bâtiments - C": {"company": "C", "account_number": "6222", "is_group": 0, "disabled": 0},
		"6327 - Autres prestataires - C": {"company": "C", "account_number": "6327", "is_group": 0, "disabled": 0},
		"6243 - Maintenance - C": {"company": "C", "account_number": "6243", "is_group": 0, "disabled": 1},
		"6011 - Dans la Région - C": {"company": "C", "account_number": "6011", "is_group": 0, "disabled": 0},
	}
	state = {"invoices": [], "items": {}}

	def matches(row, filters):
		return all(row.get(k) == v for k, v in filters.items() if k != "name")

	def get_value(doctype, filters, fieldname=None, **kwargs):
		if doctype == "Account":
			for name, row in accounts.items():
				if isinstance(filters, dict) and filters.get("name", name) == name and matches(row, filters):
					return name
			return None
		if doctype.endswith(" Item"):
			return state["items"].get(filters["parent"])
		return None

	def get_all(doctype, filters=None, pluck=None, **kwargs):
		return [name for dt, invoice_filters, name in state["invoices"] if dt == doctype and invoice_filters == filters][:1]

	monkeypatch.setattr(frappe.db, "get_value", get_value, raising=False)
	monkeypatch.setattr(frappe, "get_all", get_all, raising=False)
	return state


def test_keyword_account_resolves_an_active_leaf(ledger):
	assert oa.keyword_account("C", "Loyer bureau") == "6222 - Locations de bâtiments - C"
	assert oa.keyword_account("C", "Maintenance serveur") is None  # 6243 disabled here
	assert oa.keyword_account("C", "Carton tomates") is None


def test_remembered_account_takes_the_last_submitted_invoice(ledger):
	filters = {"supplier": "ACME", "company": "C", "docstatus": 1}
	ledger["invoices"].append(("Purchase Invoice", filters, "PINV-9"))
	ledger["items"]["PINV-9"] = "6327 - Autres prestataires - C"
	assert oa.remembered_account(True, "ACME", "C") == "6327 - Autres prestataires - C"
	assert oa.remembered_account(True, "OTHER", "C") is None


def test_remembered_account_ignores_a_disabled_account(ledger):
	ledger["invoices"].append(("Purchase Invoice", {"supplier": "ACME", "company": "C", "docstatus": 1}, "PINV-1"))
	ledger["items"]["PINV-1"] = "6243 - Maintenance - C"
	assert oa.remembered_account(True, "ACME", "C") is None


def test_choose_account_priority(ledger):
	default = "6011 - Dans la Région - C"
	assert oa.choose_account(True, "C", "Loyer bureau", default, "6327 - Autres prestataires - C") == (
		"6327 - Autres prestataires - C",
		"memoire",
	)
	assert oa.choose_account(True, "C", "Loyer bureau", default, None) == ("6222 - Locations de bâtiments - C", "mot-cle")
	assert oa.choose_account(True, "C", "Carton tomates", default, None) == (default, "defaut")
	# no keywords on sales: the income account depends on the PME's business
	assert oa.choose_account(False, "C", "Loyer bureau", "7061 - C", None) == ("7061 - C", "defaut")


def _mm_accounts(monkeypatch, rows):
	monkeypatch.setattr(
		frappe, "get_all", lambda doctype, **kwargs: [frappe._dict(r) for r in rows], raising=False
	)


def test_mobile_money_prefers_the_brand_sub_account(monkeypatch):
	_mm_accounts(
		monkeypatch,
		[
			{"name": "5521 - Wave - S", "account_name": "Wave", "account_number": "5521"},
			{"name": "5522 - Orange Money - S", "account_name": "Orange Money", "account_number": "5522"},
		],
	)
	assert oa.mobile_money_account("S", "Orange Money") == "5522 - Orange Money - S"


def test_mobile_money_falls_back_to_generic_552(monkeypatch):
	_mm_accounts(
		monkeypatch,
		[{"name": "552 - Monnaie électronique téléphone portable - Y", "account_name": "Monnaie électronique téléphone portable", "account_number": "552"}],
	)
	assert oa.mobile_money_account("Y", "Wave") == "552 - Monnaie électronique téléphone portable - Y"


def test_mobile_money_none_without_552(monkeypatch):
	_mm_accounts(monkeypatch, [])
	assert oa.mobile_money_account("Z", "Wave") is None
```

- [ ] **Step 2:** `python -m pytest -q tests/test_ocr_accounts.py` → FAIL (module absent).

- [ ] **Step 3: Implementation** — créer `sitiame_core/ocr_accounts.py` :

```python
# Copyright (c) 2026, Sitiame Capital
# License: MIT

"""Which account an OCR-read invoice line is booked on, and which account
a Mobile Money receipt lands on.

An invoice line gets, in order: the account the accountant already
validated on this party's last submitted invoice (in this company); for a
purchase, the SYSCOHADA account its wording points to (loyer -> 6222,
electricite -> 6052...); otherwise the company's default account, flagged
for review. Nothing is submitted here: the accountant still checks every
proposal on the draft before booking it.
"""

import re
import unicodedata

import frappe

# (pattern on the lower-cased, accent-free line label, SYSCOHADA account
# number). First match wins, so the more specific wordings come first
# ("produits d'entretien" before "entretien", "honoraires" before
# "prestation", "site internet" before "internet").
PURCHASE_KEYWORD_ACCOUNTS = [
	(r"honoraires?|\bavocats?\b|\bnotaires?\b|expert[- ]?comptable|commissaires? aux comptes|\bhuissiers?\b", "6324"),
	(r"\bloyers?\b|\blocation (?:de |du |des |d un )?(?:bureaux?|local|locaux|batiments?|magasins?|entrepots?)\b", "6222"),
	(r"electricite|\bcie\b", "6052"),
	(r"\beau\b|\bsodeci\b", "6051"),
	(r"carburant|gasoil|gas-oil|gazole|\bessence\b|super sans plomb", "6042"),
	(r"telephon|forfait mobile|credit de communication", "6281"),
	(r"hebergement|nom de domaine|site (?:web|internet)", "6345"),
	(r"internet|\bfibre\b|\bwifi\b|\badsl\b", "6288"),
	(r"logiciels?|licences?|\bsaas\b", "6343"),
	(r"maintenance", "6243"),
	(r"produits? d.?entretien|nettoyage|detergents?", "6043"),
	(r"entretien|reparations?", "6242"),
	(r"fournitures? de bureau|papeterie|\brames?\b|cartouches?|\btoners?\b", "6047"),
	(r"assurances?", "6258"),
	(r"billets? d.?avion|\bhotels?\b|voyages?|deplacements?|\bmissions?\b", "6181"),
	(r"publicite|annonces?|affichage|spots? (?:radio|tv|television)", "6271"),
	(r"\bformations?\b", "633"),
	(r"frais bancaires|commissions? bancaires?|\bagios?\b|tenue de compte", "6318"),
	(r"conseil|consultant|consulting|prestations?", "6327"),
]
_KEYWORD_PATTERNS = [(re.compile(pattern), number) for pattern, number in PURCHASE_KEYWORD_ACCOUNTS]


def _fold(text):
	text = unicodedata.normalize("NFKD", text or "")
	text = "".join(ch for ch in text if not unicodedata.combining(ch)).lower()
	return re.sub(r"[’']", " ", text)


def keyword_account_number(label):
	folded = _fold(label)
	for pattern, number in _KEYWORD_PATTERNS:
		if pattern.search(folded):
			return number
	return None


def _active_leaf(company, **filters):
	return frappe.db.get_value("Account", {"company": company, "is_group": 0, "disabled": 0, **filters}, "name")


def keyword_account(company, label):
	number = keyword_account_number(label)
	return _active_leaf(company, account_number=number) if number else None


def remembered_account(is_purchase, party, company):
	"""Account of the first line of this party's last submitted invoice in
	this company: the accountant already validated it for this party."""
	doctype = "Purchase Invoice" if is_purchase else "Sales Invoice"
	last = frappe.get_all(
		doctype,
		filters={("supplier" if is_purchase else "customer"): party, "company": company, "docstatus": 1},
		pluck="name",
		order_by="posting_date desc, creation desc",
		limit=1,
	)
	if not last:
		return None
	account = frappe.db.get_value(
		f"{doctype} Item",
		{"parent": last[0], "parenttype": doctype},
		"expense_account" if is_purchase else "income_account",
		order_by="idx asc",
	)
	return _active_leaf(company, name=account) if account else None


def choose_account(is_purchase, company, label, default_account, remembered):
	"""-> (account, source), source being "memoire", "mot-cle" or "defaut"."""
	if remembered:
		return remembered, "memoire"
	if is_purchase:
		account = keyword_account(company, label)
		if account:
			return account, "mot-cle"
	return default_account, "defaut"


def mobile_money_account(company, mode_of_payment):
	"""The brand's own 552x sub-account when the company has one (SITIAME:
	5521 - Wave...), else the generic 552 "Monnaie electronique telephone
	portable" leaf of the SYSCOHADA chart."""
	accounts = frappe.get_all(
		"Account",
		filters={"company": company, "account_number": ["like", "552%"], "is_group": 0, "disabled": 0},
		fields=["name", "account_name", "account_number"],
		order_by="account_number",
	)
	mode = _fold(mode_of_payment)
	for account in accounts:
		name = _fold(account.account_name)
		if account.account_number != "552" and name and (name in mode or mode in name):
			return account.name
	generic = [account.name for account in accounts if account.account_number == "552"]
	return generic[0] if generic else None


def ensure_mobile_money_modes(company):
	"""Links every Phone-type Mode of Payment (Wave, Orange Money...) to the
	company's Mobile Money account, typed Bank like any account a Payment
	Entry pays through (as SITIAME's 5521-5524 already are)."""
	for mode in frappe.get_all("Mode of Payment", filters={"type": "Phone"}, pluck="name"):
		doc = frappe.get_doc("Mode of Payment", mode)
		if any(row.company == company for row in doc.accounts):
			continue
		account = mobile_money_account(company, mode)
		if not account:
			continue
		if not frappe.db.get_value("Account", account, "account_type"):
			frappe.db.set_value("Account", account, "account_type", "Bank")
		doc.append("accounts", {"company": company, "default_account": account})
		doc.save(ignore_permissions=True)
```

- [ ] **Step 4:** `python -m pytest -q tests` → tout passe.
- [ ] **Step 5: Commit** `feat(ocr): propose the SYSCOHADA account of each OCR invoice line`.

---

### Task 3: `ocr_invoice.py` — une ligne par ligne lue, compte proposé par ligne

**Files:**
- Modify: `sitiame_core/ocr_invoice.py`
- Test: `tests/test_ocr_invoice_lines.py`

**Interfaces:**
- Consumes: `choose_account`, `remembered_account` (Task 2) ; `line_items` de `read_invoice_file` (Task 1).
- Produces: `_invoice_lines(line_items, net, reference, party) -> (list[dict], list[str])`, chaque dict `{item_name, qty, rate, description}`.

- [ ] **Step 1: Failing tests** — `tests/test_ocr_invoice_lines.py` :

```python
# Copyright (c) 2026, Sitiame Capital
# License: MIT

"""OCR invoice draft lines (sitiame_core.ocr_invoice._invoice_lines)."""

from sitiame_core.ocr_invoice import _invoice_lines

LINES = [
	{"item_name": "Prestation de conseil informatique", "rate": 250000.0, "qty": 1},
	{"item_name": "Maintenance mensuelle serveur", "rate": 104000.0, "qty": 1},
]


def test_lines_matching_the_ht_are_kept():
	lines, warnings = _invoice_lines(LINES, 354000.0, "FAC-1", "ACME")
	assert [(l["item_name"], l["qty"], l["rate"]) for l in lines] == [
		("Prestation de conseil informatique", 1, 250000.0),
		("Maintenance mensuelle serveur", 1, 104000.0),
	]
	assert warnings == []


def test_lines_contradicting_the_ht_fall_back_to_one_line():
	lines, warnings = _invoice_lines(LINES, 400000.0, "FAC-1", "ACME")
	assert len(lines) == 1 and lines[0]["rate"] == 400000.0 and lines[0]["qty"] == 1
	assert "FAC-1" in lines[0]["item_name"] and "ACME" in lines[0]["item_name"]
	assert len(warnings) == 1 and "354000" in warnings[0]


def test_a_line_without_price_falls_back_to_one_line():
	lines, warnings = _invoice_lines([{"item_name": "Ciment CPJ 45", "qty": 20.0}], 90000.0, "BL-3", "ACME")
	assert len(lines) == 1 and lines[0]["rate"] == 90000.0
	assert len(warnings) == 1


def test_no_table_read_is_one_line_without_warning():
	lines, warnings = _invoice_lines([], 118000.0, "F-2", "ACME")
	assert len(lines) == 1 and lines[0]["rate"] == 118000.0
	assert warnings == []


def test_quantities_are_multiplied():
	items = [{"item_name": "Carton tomates 5kg", "qty": 10.0, "rate": 4500.0}, {"item_name": "Oignons sac", "qty": 2.0, "rate": 12000.0}]
	lines, warnings = _invoice_lines(items, 69000.0, "F-3", "ACME")
	assert len(lines) == 2 and warnings == []
```

- [ ] **Step 2:** `python -m pytest -q tests/test_ocr_invoice_lines.py` → FAIL (ImportError).

- [ ] **Step 3: Implementation** — dans `ocr_invoice.py` :

Import : ajouter `from sitiame_core.ocr_accounts import choose_account, remembered_account`.

Mettre à jour la docstring du module (« one HT line » → « one line per line read when they add up to the HT, else one HT line; each line on the account proposed by ocr_accounts »).

Remplacer le bloc du `reference = ...` jusqu'à la fin de `invoice.append("items", {...})` par :

```python
	reference = fields.get("invoice_number") or _("sans numero")
	lines, line_warnings = _invoice_lines(data.get("line_items") or [], net, reference, party)
	warnings += line_warnings

	remembered = remembered_account(is_purchase, party, company)
	cost_center = frappe.get_cached_value("Company", company, "cost_center")
	proposals = []
	for line in lines:
		account, source = choose_account(is_purchase, company, line["item_name"], company_account, remembered)
		proposals.append((line["item_name"], account, source))
		invoice.append(
			"items",
			{
				"item_name": line["item_name"][:140],
				"description": line["description"],
				"qty": line["qty"],
				"uom": "Nos",
				"rate": line["rate"],
				account_field: account,
				"cost_center": cost_center,
			},
		)
	if any(source == "defaut" for label, account, source in proposals):
		warnings.append(
			_("Compte par defaut ({0}) utilise faute d'historique ou de mot-cle reconnu : verifiez-le avant de soumettre.").format(
				company_account
			)
		)
```

Remplacer `_add_review_comment(invoice, fields, data.get("confidence") or {}, warnings)` par
`_add_review_comment(invoice, fields, data.get("confidence") or {}, warnings, proposals)`.

Ajouter après `_resolve_amounts` :

```python
def _invoice_lines(line_items, net, reference, party):
	"""One draft line per line read on the document when they add up to
	the HT; otherwise the single HT line -- never lines that contradict the
	invoice's own total (a misread table, or no table found)."""
	lines = []
	for item in line_items:
		label = (item.get("item_name") or "").strip()
		rate = flt(item.get("rate"))
		if not label or rate <= 0:
			lines = []
			break
		lines.append(
			{
				"item_name": label,
				"qty": flt(item.get("qty")) or 1,
				"rate": rate,
				"description": _("Ligne lue sur la facture scannee -- verifiez le compte."),
			}
		)

	read_total = sum((flt(item.get("qty")) or 1) * flt(item.get("rate")) for item in line_items)
	if lines and abs(read_total - net) <= AMOUNT_TOLERANCE * len(lines):
		return lines, []

	warnings = []
	if line_items:
		warnings.append(
			_("Lignes lues ({0}) incoherentes avec le HT ({1}) : une seule ligne au HT a ete creee.").format(
				flt(read_total), net
			)
		)
	single = {
		"item_name": _("Facture {0} - {1}").format(reference, party),
		"qty": 1,
		"rate": net,
		"description": _("Montant HT importe depuis la facture scannee -- verifiez le compte de charge/produit."),
	}
	return [single], warnings
```

Dans `_add_review_comment`, nouvelle signature `def _add_review_comment(invoice, fields, confidence, warnings, proposals=()):` et, juste avant `if warnings:`, ajouter :

```python
	if proposals:
		sources = {
			"memoire": _("derniere facture de ce tiers"),
			"mot-cle": _("mot-cle du libelle"),
			"defaut": _("compte par defaut, a verifier"),
		}
		html += "<b>" + _("Comptes proposes :") + "</b><ul>"
		html += "".join(
			f"<li>{frappe.utils.escape_html(label)} &rarr; {frappe.utils.escape_html(account or '-')}"
			f" ({frappe.utils.escape_html(sources[source])})</li>"
			for label, account, source in proposals
		) + "</ul>"
```

- [ ] **Step 4:** `python -m pytest -q tests` → tout passe.
- [ ] **Step 5: Commit** `feat(ocr): one draft line per scanned invoice line, each on its proposed account`.

---

### Task 4: Mobile Money — patch, création de société, repli OCR paiement

**Files:**
- Create: `sitiame_core/patches/__init__.py` (vide), `sitiame_core/patches/map_mobile_money_modes.py`
- Modify: `sitiame_core/patches.txt`, `sitiame_core/api.py` (`_apply_syscohada_defaults`), `sitiame_core/ocr_payment.py` (`_mode_account`)
- Test: `tests/test_amounts_and_payments.py`

**Interfaces:**
- Consumes: `ensure_mobile_money_modes`, `mobile_money_account` (Task 2).

- [ ] **Step 1: Failing test** — ajouter à `tests/test_amounts_and_payments.py` :

```python
def test_phone_mode_without_linked_account_uses_the_mobile_money_account(monkeypatch):
	"""Only SITIAME had Wave/Orange/MTN/Moov linked: elsewhere a Mobile
	Money receipt fell back to the 5211 bank account."""
	def get_value(doctype, filters, fieldname=None, **kwargs):
		if doctype == "Mode of Payment" and fieldname == "type":
			return "Phone"
		return None

	monkeypatch.setattr(frappe.db, "get_value", get_value, raising=False)
	monkeypatch.setattr(ocr_payment, "mobile_money_account", lambda company, mode: f"552 - MM - {company}", raising=False)
	assert ocr_payment._mode_account("Wave", "YAO") == "552 - MM - YAO"
```

- [ ] **Step 2:** `python -m pytest -q tests/test_amounts_and_payments.py` → FAIL (`None`).

- [ ] **Step 3: Implementation**

`ocr_payment.py` : import `from sitiame_core.ocr_accounts import mobile_money_account` ; dans `_mode_account`, remplacer la fin

```python
	if frappe.db.get_value("Mode of Payment", mode_of_payment, "type") == "Cash":
		return frappe.get_cached_value("Company", company, "default_cash_account")
	return None
```

par :

```python
	mode_type = frappe.db.get_value("Mode of Payment", mode_of_payment, "type")
	if mode_type == "Cash":
		return frappe.get_cached_value("Company", company, "default_cash_account")
	if mode_type == "Phone":
		# Wave/Orange/MTN/Moov not linked for this company yet: its 552
		# Mobile Money account, never the bank account.
		return mobile_money_account(company, mode_of_payment)
	return None
```

`patches/map_mobile_money_modes.py` :

```python
# Copyright (c) 2026, Sitiame Capital
# License: MIT

"""Wave/Orange Money/MTN MoMo/Moov Money were linked to an account for
SITIAME only: link them to every company's 552 Mobile Money account."""

import frappe

from sitiame_core.ocr_accounts import ensure_mobile_money_modes


def execute():
	for company in frappe.get_all("Company", pluck="name"):
		ensure_mobile_money_modes(company)
```

`patches.txt` :

```
[post_model_sync]
sitiame_core.patches.map_mobile_money_modes
```

`api.py::_apply_syscohada_defaults` : à la fin de la fonction, ajouter

```python
	from sitiame_core.ocr_accounts import ensure_mobile_money_modes

	ensure_mobile_money_modes(company_name)
```

- [ ] **Step 4:** `python -m pytest -q tests` → tout passe ; `python -c "import ast,sys; ast.parse(open('sitiame_core/patches/map_mobile_money_modes.py').read())"`.
- [ ] **Step 5: Commit** `fix(ocr): Mobile Money receipts land on the 552 account in every company`.

---

### Task 5: Déploiement et vérification en production

- [ ] **Step 1:** `git push origin master`, puis `ssh -i ~/.ssh/id_ed25519_sitiame_vps root@31.207.36.253 'bash /root/sitiame_core_deploy.sh'` → `site OK`, migrate exécute le patch.
- [ ] **Step 2:** Script en lecture seule dans le conteneur backend : pour chaque société, lignes Mode of Payment Account des 4 modes Phone et `account_type` du compte ; attendu 4 lignes/société, `Bank`.
- [ ] **Step 3:** Rejouer OCR.space + `_extract_invoice_fields` + `_extract_line_items` + `_resolve_amounts` + `_invoice_lines` + `choose_account` (société SITIAME, sans rien insérer) sur `Facture_Propre_Test.jpg`, `Facture_Floue_Test.jpg`, `facture-test.pdf`. Attendu : propre → 2 lignes (6327 conseil, 6243 maintenance, ou mémoire fournisseur TechServices si la facture ACC-PINV-2026-00002 en fournit une) ; floue → refus ; facture-test → total 400 000, 2 lignes.
