# Panneau Scoring 360 dans le Credit Scoring Dossier — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Afficher le détail complet du Scoring 360 (15 critères, 3 blocs, Composite) dans le formulaire `Credit Scoring Dossier`, pour le staff et pour la PME sur sa propre société.

**Architecture:** L'endpoint existant `sitiame_core.api.get_scoring360_score` passe d'un contrôle `System Manager` à un contrôle de lecture sur la `Company` (User Permission) et renvoie en plus `entries_count` et la config (poids/seuils). Le doctype gagne une section avec deux dates de période enregistrées et un champ HTML. Le JS du formulaire rend le panneau à partir de la réponse, sans aucun paramètre de scoring codé en dur.

**Tech Stack:** Frappe/ERPNext v16 (app custom `sitiame_core`), Python 3, pytest avec stub frappe (`tests/conftest.py`), JS Desk (jQuery, `frappe.call`, `frappe.datetime`, `format_number`).

**Spec:** `docs/superpowers/specs/2026-09-27-scoring360-dossier-panel-design.md`

## Global Constraints

- Repo de travail : `C:\Users\yaniss\Desktop\sitiame_core` (repo git `YANIXX35/sitiame_core`, branche `master`), pas le repo PME360.
- Aucun seuil, poids ou sens de comparaison codé en dur côté JS : tout vient de `get_config()` (`Scoring 360 Settings`) via la réponse API.
- Ne pas modifier `scoring360_service.py`, `credit_scoring_dossier.py`, ni les permissions du doctype.
- La réponse de `get_scoring360_score` ne perd aucune clé existante (`period`, `ratios`, `blocks`, `composite`, `inputs`) — `public/js/scoring360_settings_test.js` en dépend.
- Contrôle d'accès exact : `frappe.has_permission("Company", "read", company, throw=True)`.
- Période par défaut : 12 mois glissants finissant à `eval_date` (ou aujourd'hui) : `to = eval_date || today`, `from = add_days(add_months(to, -12), 1)`.
- Tout texte injecté dans le HTML passe par `frappe.utils.escape_html`.
- Tests : `python -m pytest -q tests` depuis la racine du repo `sitiame_core` (55 tests passent aujourd'hui).
- Déploiement uniquement via `bash /root/sitiame_core_deploy.sh` sur le VPS (jamais de `git pull` manuel dans les conteneurs).
- Commits terminés par `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

---

### Task 1: Endpoint `get_scoring360_score` — accès PME + `entries_count` + `config`

**Files:**
- Modify: `sitiame_core/api.py:1148-1156` (fonction `get_scoring360_score`)
- Test: `tests/test_scoring360_endpoint.py` (nouveau)

**Interfaces:**
- Consumes: `sitiame_core.scoring360_service._entries_count(company, date_from, date_to) -> int`, `score_company(company, date_from, date_to) -> dict`, `get_config() -> dict` (inchangés).
- Produces: `get_scoring360_score(company, date_from=None, date_to=None) -> dict` :
  - si aucune écriture : `{"entries_count": 0}`
  - sinon : sortie de `score_company()` + `"entries_count": int` + `"config": {"bank"|"investor"|"internal": {"weights": {crit: float}, "thresholds": {crit: {"strong", "medium", "direction"}}}, "composite": {"weights": {"bank", "investor", "internal"}}}`.

- [ ] **Step 1: Write the failing test**

Créer `tests/test_scoring360_endpoint.py` :

```python
# Copyright (c) 2026, Sitiame Capital
# License: MIT

"""get_scoring360_score: company-scoped access (a PME only reads its own
company, via its Company User Permission) and the extra keys the Credit
Scoring Dossier panel needs (entries_count, config)."""

import frappe
import pytest

from sitiame_core import api
from sitiame_core import scoring360_service as svc

CONFIG = {
	"coefficients": {"strong": 1.0, "medium": 0.6, "weak": 0.2},
	"bank": {
		"weights": {"dscr": 25},
		"thresholds": {"dscr": {"strong": 1.5, "medium": 1.1, "direction": "gte"}},
		"decision": {"strong_min": 80, "medium_min": 60, "labels": {}, "lectures": {}},
	},
	"investor": {
		"weights": {"roe": 20},
		"thresholds": {"roe": {"strong": 0.15, "medium": 0.08, "direction": "gte"}},
		"decision": {"strong_min": 80, "medium_min": 60, "labels": {}, "lectures": {}},
	},
	"internal": {
		"weights": {"receivable_days": 20},
		"thresholds": {"receivable_days": {"strong": 45, "medium": 60, "direction": "lte"}},
		"decision": {"strong_min": 80, "medium_min": 60, "labels": {}, "lectures": {}},
	},
	"composite": {
		"weights": {"bank": 40, "investor": 35, "internal": 25},
		"decision": {"strong_min": 80, "medium_min": 60, "labels": {}, "lectures": {}},
	},
}

SCORE = {
	"period": {"from": "2025-09-28", "to": "2026-09-27"},
	"ratios": {"dscr": 2.0},
	"blocks": {"bank": {"total": 90.0}, "investor": {"total": 50.0}, "internal": {"total": 70.0}},
	"composite": {"total": 71.0},
	"inputs": {"revenue": 1000.0},
}


@pytest.fixture
def engine(monkeypatch):
	calls = {"permission": [], "score": []}

	def has_permission(doctype, ptype, doc, throw=False):
		calls["permission"].append((doctype, ptype, doc, throw))
		if doc != "PME A":
			raise frappe.PermissionError("not allowed")
		return True

	def score_company(company, date_from=None, date_to=None):
		calls["score"].append((company, date_from, date_to))
		return dict(SCORE)

	monkeypatch.setattr(frappe, "has_permission", has_permission, raising=False)
	monkeypatch.setattr(svc, "_entries_count", lambda company, date_from=None, date_to=None: 12)
	monkeypatch.setattr(svc, "score_company", score_company)
	monkeypatch.setattr(svc, "get_config", lambda: CONFIG)
	return calls


def test_checks_read_permission_on_the_company(engine):
	api.get_scoring360_score("PME A")
	assert engine["permission"] == [("Company", "read", "PME A", True)]


def test_other_company_is_refused_before_any_computation(engine):
	with pytest.raises(frappe.PermissionError):
		api.get_scoring360_score("PME B")
	assert engine["score"] == []


def test_keeps_engine_output_and_adds_entries_count_and_config(engine):
	result = api.get_scoring360_score("PME A", "2025-09-28", "2026-09-27")

	for key in ("period", "ratios", "blocks", "composite", "inputs"):
		assert result[key] == SCORE[key]
	assert result["entries_count"] == 12
	assert result["config"]["bank"]["weights"] == {"dscr": 25}
	assert result["config"]["internal"]["thresholds"]["receivable_days"]["direction"] == "lte"
	assert result["config"]["composite"] == {"weights": {"bank": 40, "investor": 35, "internal": 25}}
	assert "decision" not in result["config"]["bank"]


def test_empty_dates_are_passed_as_none(engine):
	api.get_scoring360_score("PME A", "", "")
	assert engine["score"] == [("PME A", None, None)]


def test_no_entries_returns_only_the_count(engine, monkeypatch):
	monkeypatch.setattr(svc, "_entries_count", lambda company, date_from=None, date_to=None: 0)
	assert api.get_scoring360_score("PME A") == {"entries_count": 0}
	assert engine["score"] == []
```

- [ ] **Step 2: Run test to verify it fails**

Run (depuis `C:\Users\yaniss\Desktop\sitiame_core`) : `python -m pytest -q tests/test_scoring360_endpoint.py`
Expected: FAIL — `test_checks_read_permission_on_the_company` (liste vide, `only_for` au lieu de `has_permission`), `test_other_company_is_refused...` (pas d'exception), `KeyError: 'entries_count'`, etc.

- [ ] **Step 3: Write minimal implementation**

Remplacer dans `sitiame_core/api.py` la fonction entière `get_scoring360_score` par :

```python
@frappe.whitelist()
def get_scoring360_score(company, date_from=None, date_to=None):
	"""ERPNext port of PME360's Scoring360Service::scoreUser(), driven by
	the "Scoring 360 Settings" single doctype. See scoring360_service.py.

	Read access on the Company is enough (same rule as the Liasse
	SYSCOHADA report): staff see every company, a PME account is limited
	to its own by its Company User Permission -- it powers the detailed
	panel of its Credit Scoring Dossier. Also returns entries_count and
	the weights/thresholds in use, so the panel shows them without
	hardcoding anything."""
	frappe.has_permission("Company", "read", company, throw=True)

	from sitiame_core.scoring360_service import _entries_count, get_config, score_company

	date_from = date_from or None
	date_to = date_to or None

	entries_count = _entries_count(company, date_from, date_to)
	if not entries_count:
		return {"entries_count": 0}

	result = score_company(company, date_from, date_to)
	cfg = get_config()
	result["entries_count"] = entries_count
	result["config"] = {
		block: {"weights": cfg[block]["weights"], "thresholds": cfg[block]["thresholds"]}
		for block in ("bank", "investor", "internal")
	}
	result["config"]["composite"] = {"weights": cfg["composite"]["weights"]}
	return result
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `python -m pytest -q tests`
Expected: `60 passed` (55 existants + 5 nouveaux).

- [ ] **Step 5: Commit**

```bash
git add sitiame_core/api.py tests/test_scoring360_endpoint.py
git commit -m "feat(scoring): open the Scoring 360 detail to a PME on its own company

get_scoring360_score now checks read permission on the Company instead of
System Manager only, and also returns entries_count and the weights/
thresholds in use, for the Credit Scoring Dossier panel.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Champs du doctype `Credit Scoring Dossier`

**Files:**
- Modify: `sitiame_core/sitiame_core/doctype/credit_scoring_dossier/credit_scoring_dossier.json`
- Test: `tests/test_credit_scoring_dossier_fields.py` (nouveau)

**Interfaces:**
- Produces (utilisés par la Task 3) : champs `scoring_period_from` (Date), `scoring_period_to` (Date), `scoring360_panel` (HTML), placés entre `purpose` et `company_info_section`.

Note : le fichier est en CRLF, indentation d'un espace par niveau. L'éditer avec l'outil Edit (pas de `json.dump`, qui reformaterait tout le fichier).

- [ ] **Step 1: Write the failing test**

Créer `tests/test_credit_scoring_dossier_fields.py` :

```python
# Copyright (c) 2026, Sitiame Capital
# License: MIT

"""The Scoring 360 panel section of Credit Scoring Dossier: fields exist,
sit between "Le dossier" and the company info, and the DocType's modified
timestamp moved (bench migrate skips a DocType JSON whose modified didn't
change)."""

import json
from pathlib import Path

DOCTYPE = (
	Path(__file__).resolve().parents[1]
	/ "sitiame_core/sitiame_core/doctype/credit_scoring_dossier/credit_scoring_dossier.json"
)

NEW_FIELDS = [
	("scoring360_section", "Section Break"),
	("scoring_period_from", "Date"),
	("scoring_period_col", "Column Break"),
	("scoring_period_to", "Date"),
	("scoring360_panel_break", "Section Break"),
	("scoring360_panel", "HTML"),
]


def _doctype():
	return json.loads(DOCTYPE.read_text(encoding="utf-8"))


def test_new_fields_are_defined_with_their_types():
	fields = {f["fieldname"]: f for f in _doctype()["fields"]}
	for fieldname, fieldtype in NEW_FIELDS:
		assert fields[fieldname]["fieldtype"] == fieldtype


def test_new_fields_sit_between_purpose_and_company_info():
	order = _doctype()["field_order"]
	start = order.index("purpose") + 1
	assert order[start : start + len(NEW_FIELDS)] == [name for name, _ in NEW_FIELDS]
	assert order[start + len(NEW_FIELDS)] == "company_info_section"


def test_modified_was_bumped_so_migrate_applies_it():
	assert _doctype()["modified"] > "2026-09-18 00:00:00.000000"
```

- [ ] **Step 2: Run test to verify it fails**

Run: `python -m pytest -q tests/test_credit_scoring_dossier_fields.py`
Expected: FAIL — `KeyError: 'scoring360_section'`, liste d'ordre différente, `modified` inchangé.

- [ ] **Step 3: Edit `field_order`**

Dans `credit_scoring_dossier.json`, remplacer :

```json
  "purpose",
  "company_info_section",
```

par :

```json
  "purpose",
  "scoring360_section",
  "scoring_period_from",
  "scoring_period_col",
  "scoring_period_to",
  "scoring360_panel_break",
  "scoring360_panel",
  "company_info_section",
```

- [ ] **Step 4: Add the field definitions**

Remplacer :

```json
   "fieldname": "purpose",
   "fieldtype": "Data",
   "label": "Objet du credit"
  },
```

par :

```json
   "fieldname": "purpose",
   "fieldtype": "Data",
   "label": "Objet du credit"
  },
  {
   "fieldname": "scoring360_section",
   "fieldtype": "Section Break",
   "label": "Analyse automatique Scoring 360"
  },
  {
   "description": "Pre-remplie sur 12 mois glissants a la date d'evaluation. La croissance est calculee contre la periode de meme duree qui precede.",
   "fieldname": "scoring_period_from",
   "fieldtype": "Date",
   "label": "Periode analysee du"
  },
  {
   "fieldname": "scoring_period_col",
   "fieldtype": "Column Break"
  },
  {
   "fieldname": "scoring_period_to",
   "fieldtype": "Date",
   "label": "au"
  },
  {
   "fieldname": "scoring360_panel_break",
   "fieldtype": "Section Break"
  },
  {
   "fieldname": "scoring360_panel",
   "fieldtype": "HTML"
  },
```

- [ ] **Step 5: Bump `modified`**

Remplacer :

```json
 "modified": "2026-09-18 00:00:00.000000",
```

par :

```json
 "modified": "2026-09-27 00:00:00.000000",
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `python -m pytest -q tests`
Expected: `63 passed`.

- [ ] **Step 7: Commit**

```bash
git add sitiame_core/sitiame_core/doctype/credit_scoring_dossier/credit_scoring_dossier.json tests/test_credit_scoring_dossier_fields.py
git commit -m "feat(scoring): add Scoring 360 period and panel fields to Credit Scoring Dossier

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Rendu du panneau dans `credit_scoring_dossier.js`

**Files:**
- Modify: `sitiame_core/sitiame_core/doctype/credit_scoring_dossier/credit_scoring_dossier.js` (ajout de fonctions avant `var handlers = {` et remplacement du bloc `handlers`)

**Interfaces:**
- Consumes: `sitiame_core.api.get_scoring360_score` (Task 1 — clés `entries_count`, `period`, `ratios`, `blocks.<bloc>.{total, decision, criteria.<crit>.{value, score, level}}`, `composite.{total, decision, contributions}`, `config`), champs `scoring_period_from`, `scoring_period_to`, `scoring360_panel` (Task 2).
- Produces: fonctions `credit_scoring_scoring360_period(frm) -> {from, to}`, `credit_scoring_prefill_period(frm)`, `credit_scoring_schedule_scoring360(frm)`, `credit_scoring_render_scoring360(frm)`.

Pas d'infrastructure de test JS dans ce repo : vérification par `node --check` ici, puis en sandbox (Task 4).

- [ ] **Step 1: Add the presentation table and helpers**

Insérer, juste avant la ligne `var handlers = {` :

```js
// Scoring 360 detail panel. Presentation only (French labels, display
// format, why a criterion can be non-computable): weights, thresholds and
// comparison direction all come from Scoring 360 Settings through the API
// -- nothing that decides the score is hardcoded here.
const SCORING360_BLOCKS = [
	{
		key: "bank",
		label: "Banque",
		criteria: [
			{ key: "dscr", label: "DSCR (capacite de remboursement)", format: "ratio", missing: "Aucune charge financiere (comptes 66) sur la periode." },
			{ key: "interest_coverage", label: "Couverture des interets", format: "ratio", missing: "Aucune charge financiere (comptes 66) sur la periode." },
			{ key: "current_ratio", label: "Liquidite generale", format: "ratio", missing: "Aucun passif circulant sur la periode." },
			{ key: "debt_asset", label: "Dettes financieres / actif", format: "pct", missing: "Aucun actif (immobilisations, stocks, creances, tresorerie)." },
			{ key: "bfr_days", label: "BFR en jours de CA", format: "days", missing: "Aucun chiffre d'affaires (classe 7) sur la periode." },
		],
	},
	{
		key: "investor",
		label: "Investisseur",
		criteria: [
			{ key: "revenue_growth", label: "Croissance du CA", format: "pct", missing: "Pas de chiffre d'affaires sur la periode precedente." },
			{ key: "ebitda_margin", label: "Marge EBITDA", format: "pct", missing: "Aucun chiffre d'affaires (classe 7) sur la periode." },
			{ key: "roe", label: "Rentabilite des capitaux propres", format: "pct", missing: "Capitaux propres nuls ou negatifs." },
			{ key: "fcf_margin", label: "Marge de tresorerie nette", format: "pct", missing: "Aucun chiffre d'affaires (classe 7) sur la periode." },
			{ key: "asset_turnover", label: "Rotation de l'actif", format: "ratio", missing: "Aucun actif (immobilisations, stocks, creances, tresorerie)." },
		],
	},
	{
		key: "internal",
		label: "Interne",
		criteria: [
			{ key: "net_margin", label: "Marge nette", format: "pct", missing: "Aucun chiffre d'affaires (classe 7) sur la periode." },
			{ key: "quick_ratio", label: "Liquidite reduite", format: "ratio", missing: "Aucun passif circulant sur la periode." },
			{ key: "receivable_days", label: "Delai clients (jours)", format: "days", missing: "Aucun chiffre d'affaires (classe 7) sur la periode." },
			{ key: "inventory_days", label: "Rotation des stocks (jours)", format: "days", missing: "Aucun achat (comptes 60/61) sur la periode." },
			{ key: "ebitda_growth", label: "Croissance de l'EBITDA", format: "pct", missing: "EBITDA nul ou negatif sur la periode precedente." },
		],
	},
];

const SCORING360_DECISION_COLORS = { strong: "green", medium: "orange", weak: "red" };
const SCORING360_LEVELS = {
	strong: { label: "Fort", color: "green" },
	medium: { label: "Moyen", color: "orange" },
	weak: { label: "Faible", color: "red" },
	missing: { label: "Non calculable", color: "gray" },
};

function scoring360_esc(value) {
	return frappe.utils.escape_html(value === null || value === undefined ? "" : String(value));
}

function scoring360_format(value, format) {
	if (value === null || value === undefined) return "n/c";
	if (format === "pct") return format_number(value * 100, null, 1) + " %";
	if (format === "days") return format_number(value, null, 0) + " j";
	return format_number(value, null, 2) + " x";
}

function scoring360_pill(label, color) {
	return "<span class='indicator-pill " + color + "'>" + scoring360_esc(label) + "</span>";
}

function scoring360_message(text) {
	return "<p class='text-muted' style='margin:10px 0;'>" + scoring360_esc(text) + "</p>";
}

// Saved period if both dates are set, otherwise 12 rolling months ending
// on the evaluation date (or today).
function credit_scoring_scoring360_period(frm) {
	if (frm.doc.scoring_period_from && frm.doc.scoring_period_to) {
		return { from: frm.doc.scoring_period_from, to: frm.doc.scoring_period_to };
	}
	var to = frm.doc.eval_date || frappe.datetime.get_today();
	return { from: frappe.datetime.add_days(frappe.datetime.add_months(to, -12), 1), to: to };
}

// Saves the default period with the dossier (credit decision traceability)
// -- only when the user can edit it; a read-only PME keeps the in-memory
// default.
function credit_scoring_prefill_period(frm) {
	if (!frm.doc.company || frm.doc.scoring_period_from || frm.doc.scoring_period_to) return;
	if (!frm.is_new() && !frm.has_perm("write")) return;
	var period = credit_scoring_scoring360_period(frm);
	frm.set_value({ scoring_period_from: period.from, scoring_period_to: period.to });
}

// Company + both dates changing together fire several events: render once.
function credit_scoring_schedule_scoring360(frm) {
	clearTimeout(frm._scoring360_timer);
	frm._scoring360_timer = setTimeout(function () {
		credit_scoring_render_scoring360(frm);
	}, 300);
}

function scoring360_header_html(data, period) {
	var composite = data.composite || {};
	var decision = composite.decision || {};
	var contributions = composite.contributions || {};
	var weights = ((data.config || {}).composite || {}).weights || {};

	var parts = SCORING360_BLOCKS.map(function (block) {
		return (
			scoring360_esc(block.label) + " <b>" + format_number(contributions[block.key] || 0, null, 1) +
			"</b> / " + format_number(weights[block.key] || 0, null, 0)
		);
	});

	return (
		"<div class='card' style='padding:15px;margin-bottom:15px;'>" +
		"<div class='text-muted' style='font-size:12px;'>" +
		scoring360_esc(__("Score composite - periode du {0} au {1}", [
			frappe.datetime.str_to_user(period.from),
			frappe.datetime.str_to_user(period.to),
		])) +
		"</div>" +
		"<div style='display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:6px 0;'>" +
		"<span style='font-size:28px;font-weight:600;'>" + format_number(composite.total || 0, null, 1) + " / 100</span>" +
		scoring360_pill(decision.label || "-", SCORING360_DECISION_COLORS[decision.level] || "gray") +
		"</div>" +
		"<div>" + scoring360_esc(decision.lecture || "") + "</div>" +
		"<div class='text-muted' style='margin-top:6px;'>" + __("Contributions") + " : " + parts.join(" &middot; ") + "</div>" +
		"</div>"
	);
}

function scoring360_block_html(block, data) {
	var result = (data.blocks || {})[block.key] || {};
	var decision = result.decision || {};
	var criteria = result.criteria || {};
	var cfg = (data.config || {})[block.key] || {};
	var weights = cfg.weights || {};
	var thresholds = cfg.thresholds || {};

	var rows = block.criteria.map(function (crit) {
		var c = criteria[crit.key] || { value: null, score: 0, level: "missing" };
		var t = thresholds[crit.key] || {};
		var op = t.direction === "lte" ? "&le;" : "&ge;";
		var level = SCORING360_LEVELS[c.level] || SCORING360_LEVELS.missing;
		var value = scoring360_format(c.value, crit.format);
		var valueCell = c.value === null || c.value === undefined
			? "<span title='" + scoring360_esc(crit.missing) + "' style='cursor:help;border-bottom:1px dotted;'>" + value + "</span>"
			: value;

		return (
			"<tr>" +
			"<td>" + scoring360_esc(crit.label) + "</td>" +
			"<td class='text-end'>" + valueCell + "</td>" +
			"<td class='text-end text-muted' style='white-space:nowrap;'>" +
			op + " " + scoring360_format(t.strong, crit.format) + " / " + op + " " + scoring360_format(t.medium, crit.format) +
			"</td>" +
			"<td>" + scoring360_pill(level.label, level.color) + "</td>" +
			"<td class='text-end' style='white-space:nowrap;'>" +
			format_number(c.score || 0, null, 1) + " / " + format_number(weights[crit.key] || 0, null, 0) +
			"</td>" +
			"</tr>"
		);
	});

	return (
		"<div class='col-md-4' style='margin-bottom:15px;'>" +
		"<div class='card' style='padding:12px;height:100%;'>" +
		"<div style='display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;'>" +
		"<b>" + scoring360_esc(__("Bloc {0}", [block.label])) + "</b>" +
		"<span><b>" + format_number(result.total || 0, null, 1) + "</b> / 100 " +
		scoring360_pill(decision.label || "-", SCORING360_DECISION_COLORS[decision.level] || "gray") + "</span>" +
		"</div>" +
		"<div class='table-responsive' style='margin-top:8px;'>" +
		"<table class='table table-sm' style='font-size:12px;margin:0;'><thead><tr>" +
		"<th>" + __("Critere") + "</th>" +
		"<th class='text-end'>" + __("Valeur") + "</th>" +
		"<th class='text-end'>" + __("Seuils fort / moyen") + "</th>" +
		"<th>" + __("Niveau") + "</th>" +
		"<th class='text-end'>" + __("Points") + "</th>" +
		"</tr></thead><tbody>" + rows.join("") + "</tbody></table>" +
		"</div></div></div>"
	);
}

function credit_scoring_render_scoring360(frm) {
	var field = frm.get_field("scoring360_panel");
	if (!field) return;
	var $wrapper = field.$wrapper;

	if (!frm.doc.company) {
		$wrapper.html(scoring360_message(__("Choisissez une societe pour afficher l'analyse Scoring 360.")));
		return;
	}

	var period = credit_scoring_scoring360_period(frm);
	// A slow answer must never overwrite a more recent one.
	var requestId = (frm._scoring360_request || 0) + 1;
	frm._scoring360_request = requestId;
	$wrapper.html(scoring360_message(__("Calcul en cours...")));

	frappe.call({
		method: "sitiame_core.api.get_scoring360_score",
		args: { company: frm.doc.company, date_from: period.from, date_to: period.to },
		callback: function (r) {
			if (frm._scoring360_request !== requestId) return;
			var data = r.message || {};
			if (!data.entries_count) {
				$wrapper.html(scoring360_message(__("Donnees insuffisantes - aucune ecriture comptable sur la periode.")));
				return;
			}
			$wrapper.html(
				scoring360_header_html(data, period) +
				"<div class='row'>" +
				SCORING360_BLOCKS.map(function (block) {
					return scoring360_block_html(block, data);
				}).join("") +
				"</div>"
			);
		},
		error: function () {
			if (frm._scoring360_request !== requestId) return;
			$wrapper.html(scoring360_message(__("Analyse indisponible.")));
		},
	});
}

```

- [ ] **Step 2: Replace the `handlers` block**

Remplacer :

```js
var handlers = {
	refresh: credit_scoring_recompute,
	company: function (frm) {
		credit_scoring_prefill_company_info(frm);
		credit_scoring_apply_suggestions(frm);
	},
};
```

par :

```js
var handlers = {
	refresh: function (frm) {
		credit_scoring_recompute(frm);
		credit_scoring_schedule_scoring360(frm);
	},
	company: function (frm) {
		credit_scoring_prefill_company_info(frm);
		credit_scoring_apply_suggestions(frm);
		credit_scoring_prefill_period(frm);
		credit_scoring_schedule_scoring360(frm);
	},
	scoring_period_from: credit_scoring_schedule_scoring360,
	scoring_period_to: credit_scoring_schedule_scoring360,
	eval_date: function (frm) {
		// Only the in-memory default period depends on eval_date.
		if (!frm.doc.scoring_period_from || !frm.doc.scoring_period_to) {
			credit_scoring_schedule_scoring360(frm);
		}
	},
};
```

- [ ] **Step 3: Syntax check**

Run: `node --check sitiame_core/sitiame_core/doctype/credit_scoring_dossier/credit_scoring_dossier.js`
Expected: aucune sortie, code retour 0.

Vérifier aussi qu'aucun seuil n'est codé en dur : `grep -nE "1\.5|0\.15|strong_min" sitiame_core/sitiame_core/doctype/credit_scoring_dossier/credit_scoring_dossier.js`
Expected: aucune ligne.

- [ ] **Step 4: Run the Python suite (non-régression)**

Run: `python -m pytest -q tests`
Expected: `63 passed`.

- [ ] **Step 5: Commit**

```bash
git add sitiame_core/sitiame_core/doctype/credit_scoring_dossier/credit_scoring_dossier.js
git commit -m "feat(scoring): render the Scoring 360 detail panel in Credit Scoring Dossier

Composite score and decision, block contributions, then the 15 criteria
per block with value, thresholds in use, level and points. The period is
saved with the dossier (12 rolling months by default) when editable.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Déploiement et vérification en sandbox

**Files:** aucun (déploiement + vérifications). **Demander le feu vert de l'utilisateur avant le push et le déploiement** (action visible en production, ~1 min de coupure).

- [ ] **Step 1: Push**

Run (repo `sitiame_core`) : `git push origin master`
Expected: push accepté ; le workflow GitHub `tests.yml` passe.

- [ ] **Step 2: Deploy**

Sur le VPS `31.207.36.253`, en root : `bash /root/sitiame_core_deploy.sh`
Expected: migrate sans erreur (les nouveaux champs apparaissent), build OK, nouvelle image taguée `:current`, health check OK.

- [ ] **Step 3: Server check (staff)**

```bash
docker exec -w /home/frappe/frappe-bench sitiame-prod-backend-1 bench --site erp.sitiame-capital.com execute sitiame_core.api.get_scoring360_score --kwargs '{"company": "SITIAME"}'
```

(nom exact de la société : `bench --site erp.sitiame-capital.com execute frappe.get_all --kwargs '{"doctype": "Company", "pluck": "name"}'`)
Expected: JSON avec `entries_count`, `config` (5 poids par bloc), `blocks`, `composite`.

- [ ] **Step 4: Access check (PME)**

Choisir un compte PME (`frappe.get_all("User Permission", filters={"allow": "Company"}, fields=["user", "for_value"])`), puis dans `bench --site erp.sitiame-capital.com console` :

```python
frappe.set_user("<email PME>")
from sitiame_core.api import get_scoring360_score
get_scoring360_score("<sa société>")["entries_count"]   # OK
get_scoring360_score("<une autre société>")             # doit lever frappe.PermissionError
```

- [ ] **Step 5: UI check**

Dans le navigateur (vider le cache : Ctrl+Maj+R) :
1. En System Manager : nouveau `Credit Scoring Dossier`, choisir une société → dates pré-remplies sur 12 mois, panneau affiché (en-tête composite + 3 cartes de 5 critères) ; modifier une date → le panneau se recalcule ; enregistrer → les dates sont conservées.
2. En compte PME : ouvrir le dossier de sa société → panneau visible, dates non modifiables.
3. Société sans écriture → "Donnees insuffisantes".
4. `Scoring 360 Settings` → bouton "Tester sur une societe" fonctionne toujours.
