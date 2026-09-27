# Bouton « Télécharger » sur les listes ERPNext — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Un bouton « Telecharger » sur 10 listes ERPNext qui livre les documents cochés (ou filtrés) en PDF, Word, Excel ou CSV, limités à la société de l'utilisateur.

**Architecture:** `sitiame_core/exports.py` (config par doctype, sélection permission-aware via `frappe.get_list`, 4 générateurs, 2 endpoints) + `public/js/list_download.js` (bouton + dialogue) branché par `doctype_list_js`.

**Tech Stack:** Frappe/ERPNext v16, openpyxl, python-docx, pypdf (via `frappe.get_print`), pytest + stub frappe.

**Spec:** `docs/superpowers/specs/2026-09-27-erpnext-list-download-design.md`

## Global Constraints

- Repo `C:\Users\yaniss\Desktop\sitiame_core`, branche `master`, tests `python -m pytest -q tests` (103 au départ).
- Les documents ne viennent QUE de `frappe.get_list` (droits + User Permissions). Ne pas accorder le droit natif « Export ».
- Limites : PDF/Word 200, Excel/CSV 5000.
- CSV : `;`, UTF-8 BOM, virgule décimale, dates JJ/MM/AAAA.
- Pas de `_` comme variable jetable (traduction frappe).
- Déploiement : `bench pip install python-docx` dans le conteneur backend AVANT `sitiame_core_deploy.sh`.
- Commits terminés par `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

---

### Task 1: `exports.py` — configuration, sélection, générateurs, endpoints

**Files:**
- Create: `sitiame_core/exports.py`
- Test: `tests/test_exports.py`
- Modify: `pyproject.toml` (`dependencies = ["python-docx"]`), `.github/workflows/tests.yml` (`pip install pytest requests openpyxl python-docx`)

**Interfaces:**
- Produces: `EXPORTS`, `LIMITS`, `FORMATS`, `count_documents(doctype, fmt, names=None, filters=None) -> {"count", "limit"}`, `download(doctype, fmt, names=None, filters=None)` (remplit `frappe.local.response`), `build_csv/build_xlsx/build_docx(config, docs) -> bytes`, `build_pdf(doctype, names) -> bytes`.

- [ ] **Step 1: Failing tests** — `tests/test_exports.py` :

```python
# Copyright (c) 2026, Sitiame Capital
# License: MIT

"""List "Telecharger" button: document selection and the four file
formats (sitiame_core.exports)."""

import csv
import datetime
import io

import frappe
import pytest

from sitiame_core import exports

CONFIG = exports.EXPORTS["Sales Invoice"]
DOCS = [
	frappe._dict(
		name="ACC-SINV-2026-00001",
		posting_date=datetime.date(2026, 9, 17),
		customer_name="Entreprise Kouassi et Fils",
		net_total=400000.0,
		total_taxes_and_charges=0.0,
		grand_total=400000.0,
		outstanding_amount=150000.5,
		status="Unpaid",
		company="SITIAME",
		items=[
			frappe._dict(item_name="Prestation de conseil comptable", qty=1.0, rate=250000.0, amount=250000.0, income_account="7061 - S"),
			frappe._dict(item_name="Licence logiciel annuelle", qty=1.0, rate=150000.0, amount=150000.0, income_account="7061 - S"),
		],
	)
]


def test_every_export_has_a_title_a_file_and_columns():
	for doctype, config in exports.EXPORTS.items():
		assert config["title"] and config["file"], doctype
		assert config["columns"][0][0] == ("item_code" if doctype == "Item" else "name"), doctype
		assert all(label for field, label in config["columns"]), doctype


@pytest.fixture
def listed(monkeypatch):
	calls = []

	def get_list(doctype, filters=None, pluck=None, limit_page_length=None, **kwargs):
		calls.append({"doctype": doctype, "filters": filters, "limit": limit_page_length})
		return [f"DOC-{i}" for i in range(min(limit_page_length, 7))]

	monkeypatch.setattr(frappe, "get_list", get_list, raising=False)
	return calls


def test_checked_rows_win_over_the_list_filters(listed):
	exports.count_documents("Sales Invoice", "csv", names='["A", "B"]', filters='[["Sales Invoice", "status", "=", "Paid"]]')
	assert listed[0]["filters"] == [["name", "in", ["A", "B"]]]


def test_list_filters_are_used_without_checked_rows(listed):
	exports.count_documents("Sales Invoice", "csv", filters='[["Sales Invoice", "status", "=", "Paid"]]')
	assert listed[0]["filters"] == [["Sales Invoice", "status", "=", "Paid"]]


def test_count_reads_one_more_than_the_limit(listed, monkeypatch):
	monkeypatch.setitem(exports.LIMITS, "pdf", 5)
	assert exports.count_documents("Sales Invoice", "pdf") == {"count": 6, "limit": 5}
	assert listed[0]["limit"] == 6


def test_download_refuses_too_many_documents(listed, monkeypatch):
	monkeypatch.setitem(exports.LIMITS, "csv", 5)
	with pytest.raises(frappe.ValidationError):
		exports.download("Sales Invoice", "csv")


def test_download_refuses_an_empty_selection(monkeypatch):
	monkeypatch.setattr(frappe, "get_list", lambda *a, **k: [], raising=False)
	with pytest.raises(frappe.ValidationError):
		exports.download("Sales Invoice", "csv")


def test_unknown_doctype_or_format_is_refused():
	with pytest.raises(frappe.ValidationError):
		exports.count_documents("User", "csv")
	with pytest.raises(frappe.ValidationError):
		exports.count_documents("Sales Invoice", "exe")


def test_download_sets_a_file_response(monkeypatch):
	monkeypatch.setattr(exports, "_names", lambda *a: ["ACC-SINV-2026-00001"])
	monkeypatch.setattr(exports, "_rows", lambda doctype, config, names: DOCS)
	monkeypatch.setattr(frappe.local, "response", frappe._dict(), raising=False)
	exports.download("Sales Invoice", "csv")
	response = frappe.local.response
	assert response.type == "download"
	assert response.filename.startswith("factures_de_vente_") and response.filename.endswith(".csv")
	assert response.filecontent.startswith("\ufeff".encode("utf-8"))


def test_csv_is_excel_french_friendly():
	text = exports.build_csv(CONFIG, DOCS).decode("utf-8-sig")
	rows = list(csv.reader(io.StringIO(text), delimiter=";"))
	assert rows[0][:3] == ["N°", "Date", "Client"]
	assert rows[1][:4] == ["ACC-SINV-2026-00001", "17/09/2026", "Entreprise Kouassi et Fils", "400 000"]
	assert rows[1][6] == "150 000,50"


def test_xlsx_has_documents_and_lines_sheets():
	from openpyxl import load_workbook

	book = load_workbook(io.BytesIO(exports.build_xlsx(CONFIG, DOCS)))
	assert book.sheetnames == ["Documents", "Lignes"]
	documents = list(book["Documents"].values)
	assert documents[0][0] == "N°" and documents[1][0] == "ACC-SINV-2026-00001"
	assert documents[1][3] == 400000  # a number, not text
	lines = list(book["Lignes"].values)
	assert lines[0][0] == "Document" and len(lines) == 3
	assert lines[2][1] == "Licence logiciel annuelle"


def test_docx_has_one_section_per_document():
	from docx import Document

	document = Document(io.BytesIO(exports.build_docx(CONFIG, DOCS + DOCS)))
	headings = [p.text for p in document.paragraphs if p.style.name.startswith("Heading 1")]
	assert headings == ["Facture de vente ACC-SINV-2026-00001"] * 2
	first_table = document.tables[0]
	assert first_table.rows[2].cells[0].text == "Client" and first_table.rows[2].cells[1].text == "Entreprise Kouassi et Fils"
	lines_table = document.tables[1]
	assert lines_table.rows[0].cells[0].text == "Article" and lines_table.rows[2].cells[0].text == "Licence logiciel annuelle"


def test_booleans_read_oui_non():
	assert exports._text(1, "is_stock_item") == "Oui"
	assert exports._text(0, "is_stock_item") == "Non"
```

- [ ] **Step 2:** `python -m pytest -q tests/test_exports.py` → FAIL (module absent).

- [ ] **Step 3: Implementation** — créer `sitiame_core/exports.py` :

```python
# Copyright (c) 2026, Sitiame Capital
# License: MIT

"""List-view "Telecharger" button (public/js/list_download.js): the
documents of a list -- the checked rows, else every row matching the
list's current filters -- as PDF, Word, Excel or CSV.

Documents only ever come from frappe.get_list, i.e. with the caller's read
permission and User Permissions applied: a PME gets its own company's
documents and nothing else, without opening ERPNext's native "Export"
right (which would expose far more than these lists).
"""

import csv
import datetime
import decimal
import io

import frappe
from frappe import _

FORMATS = ("pdf", "docx", "xlsx", "csv")
# Laid-out formats render every document one by one: slow, so capped lower.
LIMITS = {"pdf": 200, "docx": 200, "xlsx": 5000, "csv": 5000}
BOOLEAN_FIELDS = {"is_stock_item"}

_LINES = [("item_name", "Article"), ("qty", "Qté"), ("rate", "Prix unitaire"), ("amount", "Montant")]
_STOCK_LINES = [("item_code", "Code"), ("item_name", "Article"), ("qty", "Qté"), ("rate", "Prix"), ("amount", "Montant"), ("warehouse", "Dépôt")]

EXPORTS = {
	"Sales Invoice": {
		"title": "Facture de vente",
		"file": "factures_de_vente",
		"columns": [
			("name", "N°"), ("posting_date", "Date"), ("customer_name", "Client"), ("net_total", "HT"),
			("total_taxes_and_charges", "TVA"), ("grand_total", "TTC"), ("outstanding_amount", "Reste à payer"), ("status", "Statut"),
		],
		"items": ("Sales Invoice Item", _LINES + [("income_account", "Compte")]),
	},
	"Purchase Invoice": {
		"title": "Facture d'achat",
		"file": "factures_d_achat",
		"columns": [
			("name", "N°"), ("posting_date", "Date"), ("supplier_name", "Fournisseur"), ("bill_no", "N° fournisseur"),
			("net_total", "HT"), ("total_taxes_and_charges", "TVA"), ("grand_total", "TTC"),
			("outstanding_amount", "Reste à payer"), ("status", "Statut"),
		],
		"items": ("Purchase Invoice Item", _LINES + [("expense_account", "Compte")]),
	},
	"Payment Entry": {
		"title": "Paiement",
		"file": "paiements",
		"columns": [
			("name", "N°"), ("posting_date", "Date"), ("payment_type", "Type"), ("party_name", "Tiers"),
			("mode_of_payment", "Moyen de paiement"), ("paid_amount", "Montant"), ("reference_no", "Référence"), ("status", "Statut"),
		],
		"items": (
			"Payment Entry Reference",
			[("reference_doctype", "Type de document"), ("reference_name", "Document réglé"), ("allocated_amount", "Montant alloué")],
		),
	},
	"Journal Entry": {
		"title": "Écriture de journal",
		"file": "ecritures_de_journal",
		"columns": [
			("name", "N°"), ("posting_date", "Date"), ("voucher_type", "Type"), ("user_remark", "Libellé"),
			("total_debit", "Total débit"), ("total_credit", "Total crédit"),
		],
		"items": (
			"Journal Entry Account",
			[("account", "Compte"), ("party", "Tiers"), ("debit_in_account_currency", "Débit"), ("credit_in_account_currency", "Crédit")],
		),
	},
	"Stock Entry": {
		"title": "Mouvement de stock",
		"file": "mouvements_de_stock",
		"columns": [
			("name", "N°"), ("posting_date", "Date"), ("stock_entry_type", "Type"),
			("total_outgoing_value", "Valeur sortie"), ("total_incoming_value", "Valeur entrée"),
		],
		"items": (
			"Stock Entry Detail",
			[
				("item_code", "Code"), ("item_name", "Article"), ("qty", "Qté"), ("s_warehouse", "Dépôt source"),
				("t_warehouse", "Dépôt cible"), ("basic_rate", "Prix"), ("amount", "Montant"),
			],
		),
	},
	"Purchase Receipt": {
		"title": "Réception d'achat",
		"file": "receptions_d_achat",
		"columns": [("name", "N°"), ("posting_date", "Date"), ("supplier_name", "Fournisseur"), ("grand_total", "Total"), ("status", "Statut")],
		"items": ("Purchase Receipt Item", _STOCK_LINES),
	},
	"Delivery Note": {
		"title": "Bon de livraison",
		"file": "bons_de_livraison",
		"columns": [("name", "N°"), ("posting_date", "Date"), ("customer_name", "Client"), ("grand_total", "Total"), ("status", "Statut")],
		"items": ("Delivery Note Item", _STOCK_LINES),
	},
	"Purchase Order": {
		"title": "Commande d'achat",
		"file": "commandes_d_achat",
		"columns": [("name", "N°"), ("transaction_date", "Date"), ("supplier_name", "Fournisseur"), ("grand_total", "Total"), ("status", "Statut")],
		"items": ("Purchase Order Item", _LINES + [("schedule_date", "Date prévue")]),
	},
	"Sales Order": {
		"title": "Commande client",
		"file": "commandes_clients",
		"columns": [
			("name", "N°"), ("transaction_date", "Date"), ("customer_name", "Client"), ("delivery_date", "Livraison"),
			("grand_total", "Total"), ("status", "Statut"),
		],
		"items": ("Sales Order Item", _LINES + [("delivery_date", "Date prévue")]),
	},
	"Item": {
		"title": "Article",
		"file": "articles_et_stock",
		"columns": [
			("item_code", "Code"), ("item_name", "Désignation"), ("item_group", "Groupe"), ("stock_uom", "Unité"),
			("is_stock_item", "Stocké"), ("valuation_rate", "Valorisation"), ("stock", "Stock actuel"),
		],
		"stock": True,
	},
}


def _config(doctype, fmt):
	if doctype not in EXPORTS:
		frappe.throw(_("Telechargement non disponible pour {0}.").format(doctype))
	if fmt not in FORMATS:
		frappe.throw(_("Format non pris en charge : {0}").format(fmt))
	return EXPORTS[doctype]


def _parse(value):
	return frappe.parse_json(value) if isinstance(value, str) and value else value


def _names(doctype, names, filters, limit):
	"""Names of the checked rows, else of the rows matching the list
	filters -- read-permission and User Permission checked, at most
	limit + 1 so "too many" can be told apart from "exactly the limit"."""
	names = _parse(names) or []
	return frappe.get_list(
		doctype,
		filters=[["name", "in", names]] if names else (_parse(filters) or []),
		pluck="name",
		order_by="creation asc",
		limit_page_length=limit + 1,
	)


@frappe.whitelist()
def count_documents(doctype, fmt, names=None, filters=None):
	_config(doctype, fmt)
	return {"count": len(_names(doctype, names, filters, LIMITS[fmt])), "limit": LIMITS[fmt]}


@frappe.whitelist()
def download(doctype, fmt, names=None, filters=None):
	config = _config(doctype, fmt)
	found = _names(doctype, names, filters, LIMITS[fmt])
	if not found:
		frappe.throw(_("Aucun document a telecharger."))
	if len(found) > LIMITS[fmt]:
		frappe.throw(
			_("Plus de {0} documents : filtrez davantage (par mois par exemple) pour ce format.").format(LIMITS[fmt])
		)

	if fmt == "pdf":
		content = build_pdf(doctype, found)
	else:
		builder = {"docx": build_docx, "xlsx": build_xlsx, "csv": build_csv}[fmt]
		content = builder(config, _rows(doctype, config, found))

	frappe.local.response.filename = f"{config['file']}_{frappe.utils.today()}.{fmt}"
	frappe.local.response.filecontent = content
	frappe.local.response.type = "download"


def _rows(doctype, config, names):
	fields = ["name"] + [field for field, label in config["columns"] if field not in ("name", "stock")]
	if doctype != "Item":
		fields.append("company")
	docs = frappe.get_list(
		doctype, filters=[["name", "in", names]], fields=fields, order_by="creation asc", limit_page_length=len(names)
	)

	if config.get("items"):
		child, columns = config["items"]
		lines = {}
		# parents already permission-filtered above
		for row in frappe.get_all(
			child,
			filters={"parent": ["in", names], "parenttype": doctype},
			fields=["parent"] + [field for field, label in columns],
			order_by="parent asc, idx asc",
		):
			lines.setdefault(row.parent, []).append(row)
		for doc in docs:
			doc["items"] = lines.get(doc.name, [])

	if config.get("stock"):
		warehouses = frappe.get_list("Warehouse", filters={"is_group": 0}, pluck="name", limit_page_length=0)
		stock = {}
		for row in frappe.get_all(
			"Bin",
			filters={"item_code": ["in", names], "warehouse": ["in", warehouses or [""]]},
			fields=["item_code", "actual_qty"],
		):
			stock[row.item_code] = stock.get(row.item_code, 0.0) + float(row.actual_qty or 0)
		for doc in docs:
			doc["stock"] = stock.get(doc.name, 0.0)

	return docs


def _number(value):
	text = f"{float(value):,.2f}".replace(",", " ").replace(".", ",")
	return text[:-3] if text.endswith(",00") else text


def _text(value, field=None):
	if field in BOOLEAN_FIELDS:
		return _("Oui") if value else _("Non")
	if value is None:
		return ""
	if isinstance(value, datetime.date):
		return value.strftime("%d/%m/%Y")
	if isinstance(value, (int, float, decimal.Decimal)) and not isinstance(value, bool):
		return _number(value)
	return str(value)


def _cell(value, field=None):
	if field in BOOLEAN_FIELDS:
		return _text(value, field)
	if isinstance(value, decimal.Decimal):
		return float(value)
	return value


def build_csv(config, docs):
	out = io.StringIO()
	writer = csv.writer(out, delimiter=";")
	writer.writerow([label for field, label in config["columns"]])
	for doc in docs:
		writer.writerow([_text(doc.get(field), field) for field, label in config["columns"]])
	# BOM: Excel otherwise reads UTF-8 accents as mojibake
	return ("\ufeff" + out.getvalue()).encode("utf-8")


def _fill_sheet(sheet, header, rows):
	from openpyxl.styles import Font

	sheet.append(header)
	for cell in sheet[1]:
		cell.font = Font(bold=True)
	for row in rows:
		sheet.append(row)
	for column in sheet.columns:
		for cell in column:
			if isinstance(cell.value, datetime.date):
				cell.number_format = "DD/MM/YYYY"
		width = max(len(_text(cell.value)) for cell in column)
		sheet.column_dimensions[column[0].column_letter].width = min(50, width + 2)
	sheet.freeze_panes = "A2"


def build_xlsx(config, docs):
	from openpyxl import Workbook

	book = Workbook()
	sheet = book.active
	sheet.title = "Documents"
	columns = config["columns"]
	_fill_sheet(sheet, [label for field, label in columns], [[_cell(doc.get(field), field) for field, label in columns] for doc in docs])

	if config.get("items"):
		child, item_columns = config["items"]
		_fill_sheet(
			book.create_sheet("Lignes"),
			["Document"] + [label for field, label in item_columns],
			[
				[doc.name] + [_cell(line.get(field), field) for field, label in item_columns]
				for doc in docs
				for line in doc.get("items") or []
			],
		)

	out = io.BytesIO()
	book.save(out)
	return out.getvalue()


def _bold_header(row):
	for cell in row.cells:
		for run in cell.paragraphs[0].runs:
			run.bold = True


def build_docx(config, docs):
	from docx import Document

	document = Document()
	for index, doc in enumerate(docs):
		if index:
			document.add_page_break()
		key_field, key_label = config["columns"][0]
		document.add_heading(f"{config['title']} {doc.get(key_field)}", level=1)
		if doc.get("company"):
			document.add_paragraph(doc.company)

		table = document.add_table(rows=0, cols=2)
		table.style = "Table Grid"
		for field, label in config["columns"]:
			cells = table.add_row().cells
			cells[0].text = label
			cells[1].text = _text(doc.get(field), field)

		if config.get("items") and doc.get("items"):
			child, item_columns = config["items"]
			document.add_heading("Lignes", level=2)
			lines = document.add_table(rows=1, cols=len(item_columns))
			lines.style = "Table Grid"
			for cell, (field, label) in zip(lines.rows[0].cells, item_columns):
				cell.text = label
			_bold_header(lines.rows[0])
			for line in doc["items"]:
				for cell, (field, label) in zip(lines.add_row().cells, item_columns):
					cell.text = _text(line.get(field), field)

	out = io.BytesIO()
	document.save(out)
	return out.getvalue()


def build_pdf(doctype, names):
	"""Each document with its default print format, one after the other in
	a single PDF. Unlike frappe's download_multi_pdf, a failing document
	raises instead of being silently left out."""
	from pypdf import PdfWriter

	writer = PdfWriter()
	for name in names:
		writer = frappe.get_print(doctype, name, as_pdf=True, output=writer)
	out = io.BytesIO()
	writer.write(out)
	return out.getvalue()
```

Note : l'en-tête Word reprend la 1re colonne (N° ou Code article) ; le tableau libellé/valeur liste toutes les colonnes dans l'ordre de la config (N°, Date, tiers… — cf. test).

`pyproject.toml` : `dependencies = ["python-docx"]`. `tests.yml` : `pip install pytest requests openpyxl python-docx`.

- [ ] **Step 4:** `python -m pytest -q tests` → tout passe.
- [ ] **Step 5: Commit** `feat(exports): download list documents as PDF, Word, Excel or CSV`.

---

### Task 2: Bouton et dialogue sur les listes

**Files:**
- Create: `sitiame_core/public/js/list_download.js`
- Modify: `sitiame_core/hooks.py` (`doctype_list_js`)

**Interfaces:**
- Consumes: `sitiame_core.exports.count_documents`, `sitiame_core.exports.download` (Task 1).

- [ ] **Step 1: JS** — `public/js/list_download.js` :

```js
// "Telecharger" button on the list views below: the checked rows, else
// every row matching the list's current filters, as PDF, Word, Excel or
// CSV (sitiame_core/exports.py). Loaded through doctype_list_js, i.e.
// after ERPNext's own *_list.js, so it extends onload instead of being
// overwritten by it (same pattern as ocr_invoice_list.js).
[
	"Sales Invoice",
	"Purchase Invoice",
	"Payment Entry",
	"Journal Entry",
	"Stock Entry",
	"Purchase Receipt",
	"Delivery Note",
	"Purchase Order",
	"Sales Order",
	"Item",
].forEach(function (doctype) {
	var settings = (frappe.listview_settings[doctype] = frappe.listview_settings[doctype] || {});
	if (settings.sitiame_download_button) return;
	settings.sitiame_download_button = true;

	var previous_onload = settings.onload;
	settings.onload = function (listview) {
		if (previous_onload) previous_onload(listview);
		listview.page.add_inner_button(__("Telecharger"), function () {
			window.sitiame_open_download_dialog(listview);
		});
	};
});

window.sitiame_open_download_dialog = function (listview) {
	var checked = listview.get_checked_items(true);
	var scope = { doctype: listview.doctype };
	if (checked.length) {
		scope.names = checked;
	} else {
		scope.filters = listview.get_filters_for_args();
	}

	var dialog = new frappe.ui.Dialog({
		title: __("Telecharger"),
		fields: [
			{
				fieldname: "fmt",
				fieldtype: "Select",
				label: __("Format"),
				reqd: 1,
				default: "pdf",
				options: [
					{ value: "pdf", label: "PDF" },
					{ value: "docx", label: "Word (.docx)" },
					{ value: "xlsx", label: "Excel (.xlsx)" },
					{ value: "csv", label: "CSV" },
				],
				onchange: function () {
					refresh_count();
				},
			},
			{ fieldname: "info", fieldtype: "HTML" },
		],
		primary_action_label: __("Telecharger"),
		primary_action: function (values) {
			var c = dialog.sitiame_count;
			if (!c || !c.count || c.count > c.limit) return;
			open_url_post("/api/method/sitiame_core.exports.download", Object.assign({ fmt: values.fmt }, scope));
			dialog.hide();
		},
	});

	function refresh_count() {
		var fmt = dialog.get_value("fmt");
		dialog.sitiame_count = null;
		dialog.fields_dict.info.$wrapper.html("<p class='text-muted'>" + __("Comptage en cours...") + "</p>");
		frappe.call({
			method: "sitiame_core.exports.count_documents",
			args: Object.assign({ fmt: fmt }, scope),
		}).then(function (r) {
			if (dialog.get_value("fmt") !== fmt) return;
			var c = r.message || {};
			dialog.sitiame_count = c;
			var html;
			if (!c.count) {
				html = "<p class='text-muted'>" + __("Aucun document a telecharger.") + "</p>";
			} else if (c.count > c.limit) {
				html =
					"<p class='text-danger'>" +
					__("Plus de {0} documents : filtrez davantage (par mois par exemple) pour ce format.", [c.limit]) +
					"</p>";
			} else {
				html =
					"<p>" +
					(checked.length
						? __("{0} document(s) coche(s)", [c.count])
						: __("{0} document(s) selon les filtres de la liste", [c.count])) +
					"</p>";
			}
			dialog.fields_dict.info.$wrapper.html(html);
		});
	}

	dialog.show();
	refresh_count();
};
```

- [ ] **Step 2: hooks** — remplacer `doctype_list_js` par :

```python
doctype_list_js = {
	"Purchase Invoice": ["public/js/ocr_invoice_list.js", "public/js/list_download.js"],
	"Sales Invoice": ["public/js/ocr_invoice_list.js", "public/js/list_download.js"],
	"Payment Entry": ["public/js/ocr_invoice_list.js", "public/js/list_download.js"],
	"Journal Entry": "public/js/list_download.js",
	"Stock Entry": "public/js/list_download.js",
	"Purchase Receipt": "public/js/list_download.js",
	"Delivery Note": "public/js/list_download.js",
	"Purchase Order": "public/js/list_download.js",
	"Sales Order": "public/js/list_download.js",
	"Item": "public/js/list_download.js",
}
```

- [ ] **Step 3:** `node --check sitiame_core/public/js/list_download.js` ; `python -m pytest -q tests`.
- [ ] **Step 4: Commit** `feat(exports): Telecharger button on the ERPNext list views`.

---

### Task 3: Dépendance, déploiement, vérification

- [ ] **Step 1:** `ssh -i ~/.ssh/id_ed25519_sitiame_vps root@31.207.36.253 'docker exec -w /home/frappe/frappe-bench sitiame-prod-backend-1 ./env/bin/pip install python-docx'` → installé.
- [ ] **Step 2:** `git push origin master` puis `bash /root/sitiame_core_deploy.sh` → `site OK`.
- [ ] **Step 3:** Vérifier que `python-docx` est présent dans le nouveau conteneur (image figée) : `docker exec sitiame-prod-backend-1 ./env/bin/python -c "import docx"`.
- [ ] **Step 4:** Script en lecture seule : en tant que compte PME (`frappe.set_user`), `count_documents` puis `download` pour chaque format sur ses factures ; contrôler `frappe.local.response.type == "download"`, taille non nulle, et que les documents d'une autre société sont exclus. En tant qu'Administrator : PDF des factures SITIAME.
- [ ] **Step 5:** Contrôle navigateur par l'utilisateur (bouton, dialogue, fichiers).
