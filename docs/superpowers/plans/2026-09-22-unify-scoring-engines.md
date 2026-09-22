# Unify Scoring Engines Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the two separate scoring engines in `sitiame_core` with one — `scoring360_service.py`'s 15-criteria composite engine becomes the single source of truth for both the detailed per-company score and the portfolio-wide "Classement financier" ranking, eliminating the discrepancy where the two engines computed a company's total assets/liabilities from different SYSCOHADA account prefix lists.

**Architecture:** Move the three low-level GL-reading helpers (`_account_ledger`, `_sum_by_prefixes`, `_entries_count`) from `financial_ratio_service.py` into `scoring360_service.py`, add a new `classement_erpnext()` function there that classifies every Company by its Composite score/decision, repoint the three callers (`get_financial_ranking`, `get_scoring_suggestions`, `_build_assistant_context` in `api.py`) and the ranking page (`erp_financial_ranking.js`) at it, then delete `financial_ratio_service.py`.

**Tech Stack:** Python (Frappe/ERPNext v16, app `sitiame_core`), vanilla JS (Frappe page API).

## Global Constraints

- `financial_ratio_service.py` must have zero remaining importers before it is deleted (Task 6) — verify with a repo-wide grep, not by memory.
- Composite decision → ranking category mapping uses the labels already defined in `scoring360_service.DECISION_LABELS["composite"]`/`DECISION_LECTURES["composite"]` — do not invent new wording.
- `get_scoring_suggestions`'s `entries_count == 0` behavior (all four financial suggestions return `None` with the same explanatory note) must be preserved exactly.
- No new automated test suite — this repo's established convention for `sitiame_core` Python changes is one-off `bench execute` verification scripts (write locally → `scp` to the VPS `/tmp/` → `docker cp` into the backend container → `bench execute` → `rm`), run against the live site at `erp.sitiame-capital.com` via `ssh -i ~/.ssh/id_ed25519_sitiame_vps root@31.207.36.253`.
- Deployment always touches both `sitiame-prod-backend-1` and `sitiame-prod-frontend-1` containers (the frontend has no persistent volume for `apps/sitiame_core` — a past divergence there broke asset loading).

---

### Task 1: Move GL helpers into `scoring360_service.py` and add `classement_erpnext()`

**Files:**
- Modify: `sitiame_core/scoring360_service.py`

**Interfaces:**
- Consumes: nothing new (uses `frappe.db.sql`, `frappe.get_all`, `frappe.utils.flt` — same as the functions being moved).
- Produces: `_account_ledger(company, date_from=None, date_to=None) -> dict`, `_sum_by_prefixes(ledger, prefixes, column="credit_net") -> float`, `_entries_count(company, date_from=None, date_to=None) -> int` (now local to this module, same signatures as before), and `classement_erpnext(date_from=None, date_to=None) -> {"lignes": [...], "compteurs": {...}}`. Consumed by Task 2, 3, 4.

- [ ] **Step 1: Replace the `financial_ratio_service` import with local copies of the three helpers**

In `sitiame_core/scoring360_service.py`, replace this line (currently line 26):

```python
from sitiame_core.financial_ratio_service import _account_ledger, _sum_by_prefixes
```

with:

```python
from frappe.utils import flt


def _account_ledger(company, date_from=None, date_to=None):
	conditions = ["gle.company = %(company)s", "gle.is_cancelled = 0"]
	params = {"company": company}
	if date_from:
		conditions.append("gle.posting_date >= %(date_from)s")
		params["date_from"] = date_from
	if date_to:
		conditions.append("gle.posting_date <= %(date_to)s")
		params["date_to"] = date_to

	rows = frappe.db.sql(
		f"""
		select acc.account_number as code, sum(gle.debit) as debit, sum(gle.credit) as credit
		from `tabGL Entry` gle
		inner join `tabAccount` acc on acc.name = gle.account
		where {" and ".join(conditions)}
			and acc.account_number is not null and acc.account_number != ''
		group by acc.account_number
		""",
		params,
		as_dict=True,
	)

	ledger = {}
	for r in rows:
		debit = flt(r.debit)
		credit = flt(r.credit)
		ledger[r.code] = {
			"debit": debit,
			"credit": credit,
			"debit_net": max(debit - credit, 0.0),
			"credit_net": max(credit - debit, 0.0),
		}
	return ledger


def _entries_count(company, date_from=None, date_to=None):
	conditions = ["company = %(company)s", "is_cancelled = 0"]
	params = {"company": company}
	if date_from:
		conditions.append("posting_date >= %(date_from)s")
		params["date_from"] = date_from
	if date_to:
		conditions.append("posting_date <= %(date_to)s")
		params["date_to"] = date_to

	row = frappe.db.sql(
		f"""select count(distinct concat(voucher_type, '||', voucher_no)) as c
		from `tabGL Entry` where {" and ".join(conditions)}""",
		params,
	)
	return int(row[0][0]) if row and row[0][0] else 0


def _sum_by_prefixes(ledger, prefixes, column="credit_net"):
	total = 0.0
	for code, row in ledger.items():
		for prefix in prefixes:
			if prefix and code.startswith(prefix):
				total += row.get(column, 0.0)
				break
	return total
```

(`_account_ledger` and `_sum_by_prefixes` are byte-for-byte copies of the versions in `financial_ratio_service.py`; `_entries_count` likewise. This is intentional duplication-then-deletion, not a rename — Task 6 deletes the original file once nothing imports from it.)

- [ ] **Step 2: Add the ranking category mapping and `classement_erpnext()`**

Append to the end of `sitiame_core/scoring360_service.py`:

```python
RANKING_CATEGORY_FROM_DECISION_LEVEL = {
	"strong": "pret_a_deployer",
	"medium": "solide_mais_a_cadrer",
	"weak": "risque_a_traiter",
}


def classement_erpnext(date_from=None, date_to=None):
	companies = frappe.get_all("Company", fields=["name", "company_name"], order_by="company_name")

	lignes = []
	for company in companies:
		entries_count = _entries_count(company.name, date_from, date_to)

		if entries_count == 0:
			lignes.append(
				{
					"company": company.name,
					"company_name": company.company_name,
					"entries_count": 0,
					"composite_score": None,
					"decision": {
						"level": "insuffisant",
						"label": "Donnees insuffisantes",
						"lecture": "Aucune ecriture comptable sur la periode.",
					},
					"blocks": None,
				}
			)
			continue

		result = score_company(company.name, date_from, date_to)
		composite = result["composite"]
		lignes.append(
			{
				"company": company.name,
				"company_name": company.company_name,
				"entries_count": entries_count,
				"composite_score": composite["total"],
				"decision": composite["decision"],
				"blocks": {
					"bank": result["blocks"]["bank"]["total"],
					"investor": result["blocks"]["investor"]["total"],
					"internal": result["blocks"]["internal"]["total"],
				},
			}
		)

	def sort_key(row):
		score = row["composite_score"]
		return -(score if score is not None else -1)

	lignes.sort(key=sort_key)

	compteurs = {"pret_a_deployer": 0, "solide_mais_a_cadrer": 0, "risque_a_traiter": 0, "insuffisant": 0}
	for row in lignes:
		level = row["decision"]["level"]
		category = RANKING_CATEGORY_FROM_DECISION_LEVEL.get(level, "insuffisant")
		compteurs[category] += 1

	return {"lignes": lignes, "compteurs": compteurs}
```

- [ ] **Step 3: Verify the file parses cleanly**

```bash
python -c "import ast; ast.parse(open('sitiame_core/scoring360_service.py').read())"
```

Expected: no output (no syntax errors).

- [ ] **Step 4: Commit**

```bash
git add sitiame_core/scoring360_service.py
git commit -m "feat(scoring): move GL helpers into scoring360_service and add classement_erpnext"
```

---

### Task 2: Repoint `get_financial_ranking` at the unified engine

**Files:**
- Modify: `sitiame_core/api.py:796-804`

**Interfaces:**
- Consumes: `sitiame_core.scoring360_service.classement_erpnext(date_from, date_to)` (Task 1).

- [ ] **Step 1: Update the import and docstring**

Replace (current lines 796-804):

```python
def get_financial_ranking(date_from=None, date_to=None):
	"""ERPNext port of PME360's classementPlateforme(): ranks every ERPNext
	Company as financable/solvable_seulement/non_retenu/insuffisant from
	its own GL Entry data. See financial_ratio_service.py."""
	frappe.only_for("System Manager")

	from sitiame_core.financial_ratio_service import classement_erpnext

	return classement_erpnext(date_from or None, date_to or None)
```

with:

```python
def get_financial_ranking(date_from=None, date_to=None):
	"""Ranks every ERPNext Company by its Scoring 360 Composite score/
	decision (pret_a_deployer/solide_mais_a_cadrer/risque_a_traiter/
	insuffisant). See scoring360_service.py::classement_erpnext."""
	frappe.only_for("System Manager")

	from sitiame_core.scoring360_service import classement_erpnext

	return classement_erpnext(date_from or None, date_to or None)
```

- [ ] **Step 2: Verify the file parses cleanly**

```bash
python -c "import ast; ast.parse(open('sitiame_core/api.py').read())"
```

Expected: no output.

- [ ] **Step 3: Commit**

```bash
git add sitiame_core/api.py
git commit -m "feat(scoring): point get_financial_ranking at the unified engine"
```

---

### Task 3: Repoint `get_scoring_suggestions` at the unified engine

**Files:**
- Modify: `sitiame_core/api.py:1429-1510`

**Interfaces:**
- Consumes: `sitiame_core.scoring360_service.score_company`, `get_config`, `_entries_count` (Task 1). Reuses `_score_to_note`/`_ratio_to_note` (already defined at `api.py:1324-1340`, unchanged) and `_compute_payment_history`/`_compute_customer_concentration` (unchanged).

- [ ] **Step 1: Replace the function body**

Replace (current lines 1429-1510, the whole `get_scoring_suggestions` function including its docstring), with:

```python
@frappe.whitelist()
def get_scoring_suggestions(company):
	"""Suggested notes (0-5) for the Credit Scoring Dossier, computed from
	data already available in other ERPNext modules instead of asking the
	analyst to judge them blind:

	- Capacité de remboursement / Structure financière / Rentabilité /
	  Liquidité générale: all four now derive from Scoring 360's single
	  engine (sitiame_core.scoring360_service.score_company) -- capacité
	  de remboursement is the Bloc Banque total; structure financière and
	  rentabilité are that engine's own debt_asset/net_margin criterion
	  sub-scores, renormalised to 0-100 (score / configured weight * 100)
	  so they read on the same 0-100 scale _score_to_note expects;
	  liquidité générale is the engine's current_ratio.
	- Historique de paiement: % of Payment Entries that reached their
	  Sales Invoice on or before its due date.
	- Marché et clientèle: revenue concentration on the top customer.
	- Qualité des informations / Identité vérifiée: counts real KYC
	  documents attached to the Company (synced from PME360).

	Returns None for any criterion it can't support with real data (e.g.
	no accounting entries yet, or no sales invoices) -- the analyst still
	has full control, this only pre-fills a starting point with an
	explanation attached. Direction et organisation, Projet et
	financement, and Garanties et recouvrement stay manual: no reliable
	system signal exists for them.
	"""
	if "System Manager" not in frappe.get_roles():
		frappe.throw(_("Réservé aux administrateurs."), frappe.PermissionError)

	from sitiame_core.scoring360_service import _entries_count, get_config, score_company

	entries_count = _entries_count(company)

	kyc_count = frappe.db.count(
		"File", {"attached_to_doctype": "Company", "attached_to_name": company}
	)

	suggestions = {}

	if entries_count == 0:
		suggestions["capacite_remboursement"] = None
		suggestions["structure_financiere"] = None
		suggestions["rentabilite"] = None
		suggestions["liquidite_generale"] = None
		suggestions["_comptabilite_note"] = (
			"Aucune écriture comptable trouvée pour cette société : les critères financiers "
			"ne peuvent pas être suggérés automatiquement."
		)
	else:
		cfg = get_config()
		result = score_company(company)
		bank_block = result["blocks"]["bank"]
		internal_block = result["blocks"]["internal"]
		ratios = result["ratios"]

		bank_score = bank_block["total"]

		debt_asset_weight = float(cfg["bank"]["weights"].get("debt_asset") or 0)
		debt_asset_criterion = bank_block["criteria"].get("debt_asset") or {}
		structure_score = (
			(debt_asset_criterion["score"] / debt_asset_weight * 100)
			if debt_asset_weight and debt_asset_criterion.get("score") is not None
			else None
		)

		net_margin_weight = float(cfg["internal"]["weights"].get("net_margin") or 0)
		net_margin_criterion = internal_block["criteria"].get("net_margin") or {}
		rentabilite_score = (
			(net_margin_criterion["score"] / net_margin_weight * 100)
			if net_margin_weight and net_margin_criterion.get("score") is not None
			else None
		)

		liquidite_ratio = ratios.get("current_ratio")

		suggestions["capacite_remboursement"] = _score_to_note(bank_score)
		suggestions["structure_financiere"] = _score_to_note(structure_score)
		suggestions["rentabilite"] = _score_to_note(rentabilite_score)
		suggestions["liquidite_generale"] = _ratio_to_note(liquidite_ratio)
		suggestions["_comptabilite_note"] = (
			f"Calculé depuis {entries_count} écriture(s) comptable(s) : "
			f"score capacité remboursement (DSCR) {bank_score}/100, score structure financière {structure_score}, "
			f"score rentabilité {rentabilite_score}, ratio de liquidité générale {liquidite_ratio}."
		)

	payment_note, payment_explanation = _compute_payment_history(company)
	suggestions["historique_paiement"] = payment_note
	suggestions["_historique_paiement_note"] = payment_explanation

	market_note, market_explanation = _compute_customer_concentration(company)
	suggestions["marche_clientele"] = market_note
	suggestions["_marche_clientele_note"] = market_explanation

	suggestions["qualite_informations"] = 4 if kyc_count >= 2 else (2 if kyc_count == 1 else 0)
	suggestions["identity_verified"] = 1 if kyc_count >= 1 else 0
	suggestions["_kyc_note"] = (
		f"{kyc_count} document(s) KYC trouvé(s) pour cette société (synchronisés depuis PME360)."
	)

	return suggestions
```

- [ ] **Step 2: Verify the file parses cleanly**

```bash
python -c "import ast; ast.parse(open('sitiame_core/api.py').read())"
```

Expected: no output.

- [ ] **Step 3: Commit**

```bash
git add sitiame_core/api.py
git commit -m "feat(scoring): derive scoring-dossier suggestions from the unified engine"
```

---

### Task 4: Repoint `_build_assistant_context` at the unified engine

**Files:**
- Modify: `sitiame_core/api.py:1074-1108`

**Interfaces:**
- Consumes: `sitiame_core.scoring360_service.classement_erpnext` (Task 1).

- [ ] **Step 1: Update the import and the counters used in the context text**

Replace (current lines 1078-1084 and line 1107):

```python
	try:
		from sitiame_core.financial_ratio_service import classement_erpnext

		ranking = classement_erpnext()
		compteurs = ranking.get("compteurs", {})
	except Exception:
		compteurs = {}
```

with:

```python
	try:
		from sitiame_core.scoring360_service import classement_erpnext

		ranking = classement_erpnext()
		compteurs = ranking.get("compteurs", {})
	except Exception:
		compteurs = {}
```

and replace this line (current line 1107):

```python
		f"- Classement financier : {compteurs}\n"
```

with:

```python
		f"- Classement financier (pret_a_deployer/solide_mais_a_cadrer/risque_a_traiter/insuffisant) : {compteurs}\n"
```

(the dict's keys already carry the new category names from Task 1's `classement_erpnext` — only the surrounding label text changes, to keep the raw dict self-explanatory to whatever LLM reads this context string.)

- [ ] **Step 2: Verify the file parses cleanly**

```bash
python -c "import ast; ast.parse(open('sitiame_core/api.py').read())"
```

Expected: no output.

- [ ] **Step 3: Commit**

```bash
git add sitiame_core/api.py
git commit -m "feat(scoring): point assistant context at the unified engine"
```

---

### Task 5: Rewrite the "Classement financier" page for the new categories

**Files:**
- Modify: `sitiame_core/sitiame_core/page/erp_financial_ranking/erp_financial_ranking.js`

**Interfaces:**
- Consumes: `sitiame_core.api.get_financial_ranking` (Task 2) — response shape `{"lignes": [{company, company_name, entries_count, composite_score, decision: {level, label, lecture}, blocks: {bank, investor, internal} | null}], "compteurs": {pret_a_deployer, solide_mais_a_cadrer, risque_a_traiter, insuffisant}}` (Task 1).

- [ ] **Step 1: Replace the whole file**

Replace the full contents of `sitiame_core/sitiame_core/page/erp_financial_ranking/erp_financial_ranking.js` with:

```js
frappe.pages["erp-financial-ranking"].on_page_load = function (wrapper) {
	var page = frappe.ui.make_app_page({
		parent: wrapper,
		title: __("Classement financier"),
		single_column: true,
	});

	var $filters = $(
		"<div class='row' style='padding:0 15px;margin-top:10px;'>" +
			"<div class='col-sm-3'><label class='control-label'>" + __("Periode du") + "</label>" +
			"<input type='date' class='form-control erp-fr-from'></div>" +
			"<div class='col-sm-3'><label class='control-label'>" + __("au") + "</label>" +
			"<input type='date' class='form-control erp-fr-to'></div>" +
			"<div class='col-sm-3' style='align-self:flex-end;'>" +
			"<button class='btn btn-primary erp-fr-refresh' style='margin-top:22px;'>" + __("Actualiser le classement") + "</button>" +
			"</div></div>"
	).appendTo(page.body);

	var $body = $("<div style='padding:0 15px;margin-top:15px;'></div>").appendTo(page.body);

	function statCard(label, value, colorClass) {
		return (
			"<div class='col-sm-3' style='margin-bottom:15px;'>" +
			"<div class='card' style='padding:15px;border-left:4px solid " + colorClass + ";'>" +
			"<div class='text-muted' style='font-size:12px;'>" + label + "</div>" +
			"<div style='font-size:24px;font-weight:600;'>" + value + "</div>" +
			"</div></div>"
		);
	}

	function render() {
		var args = {};
		var from = $filters.find(".erp-fr-from").val();
		var to = $filters.find(".erp-fr-to").val();
		if (from) args.date_from = from;
		if (to) args.date_to = to;

		$body.html("<p class='text-muted'>" + __("Calcul en cours...") + "</p>");

		frappe.call({ method: "sitiame_core.api.get_financial_ranking", args: args }).then(function (r) {
			var data = r.message || {};
			var lignes = data.lignes || [];
			var c = data.compteurs || {};

			var html = "<div class='row'>";
			html += statCard(__("Pret a deployer"), c.pret_a_deployer || 0, "#2b8a3e");
			html += statCard(__("Solide mais a cadrer"), c.solide_mais_a_cadrer || 0, "#e8a33d");
			html += statCard(__("Risque a traiter"), c.risque_a_traiter || 0, "#e03131");
			html += statCard(__("Donnees insuffisantes"), c.insuffisant || 0, "#868e96");
			html += "</div>";

			if (!lignes.length) {
				html += "<p class='text-muted'>" + __("Aucune societe a classer.") + "</p>";
			} else {
				html +=
					"<table class='table table-bordered bg-white'><thead><tr>" +
					"<th>" + __("Societe") + "</th>" +
					"<th class='text-end'>" + __("Ecritures") + "</th>" +
					"<th class='text-end'>" + __("Score composite") + "</th>" +
					"<th>" + __("Decision") + "</th>" +
					"<th class='text-end'>" + __("Contribution Banque") + "</th>" +
					"<th class='text-end'>" + __("Contribution Investisseur") + "</th>" +
					"<th class='text-end'>" + __("Contribution Interne") + "</th>" +
					"</tr></thead><tbody>";

				var badgeClass = { pret_a_deployer: "success", solide_mais_a_cadrer: "warning", risque_a_traiter: "danger" };

				lignes.forEach(function (row) {
					var decision = row.decision || {};
					var level = decision.level === "insuffisant" ? "insuffisant" :
						(decision.level === "strong" ? "pret_a_deployer" : decision.level === "medium" ? "solide_mais_a_cadrer" : "risque_a_traiter");
					var cls = badgeClass[level] || "light";
					var blocks = row.blocks || {};
					html +=
						"<tr>" +
						"<td class='fw-bold'>" + frappe.utils.escape_html(row.company_name || row.company) + "</td>" +
						"<td class='text-end'>" + row.entries_count + "</td>" +
						"<td class='text-end'>" + (row.composite_score !== null && row.composite_score !== undefined ? row.composite_score : "-") + "</td>" +
						"<td><span class='indicator-pill " + cls + "'>" + frappe.utils.escape_html(decision.label || "-") + "</span></td>" +
						"<td class='text-end'>" + (blocks.bank !== undefined && blocks.bank !== null ? blocks.bank : "-") + "</td>" +
						"<td class='text-end'>" + (blocks.investor !== undefined && blocks.investor !== null ? blocks.investor : "-") + "</td>" +
						"<td class='text-end'>" + (blocks.internal !== undefined && blocks.internal !== null ? blocks.internal : "-") + "</td>" +
						"</tr>";
				});

				html += "</tbody></table>";
			}

			$body.html(html);
		});
	}

	$filters.find(".erp-fr-refresh").on("click", render);

	render();
};
```

(Removed: the "Solvable"/"Financable" boolean columns, the "Motifs" text column, and the help banner explaining the old solvable/financable thresholds — those thresholds now live entirely in `Scoring 360 Settings`, not duplicated as page text. Added: Score composite and the three per-block contribution columns, sourced straight from `classement_erpnext()`'s new `blocks` field.)

- [ ] **Step 2: Verify the file parses cleanly**

```bash
node --check sitiame_core/sitiame_core/page/erp_financial_ranking/erp_financial_ranking.js
```

Expected: no output.

- [ ] **Step 3: Commit**

```bash
git add sitiame_core/sitiame_core/page/erp_financial_ranking/erp_financial_ranking.js
git commit -m "feat(scoring): show composite score and block contributions on the ranking page"
```

---

### Task 6: Delete `financial_ratio_service.py`

**Files:**
- Delete: `sitiame_core/financial_ratio_service.py`

**Interfaces:**
- Consumes: nothing (this task only removes code).

- [ ] **Step 1: Confirm nothing still imports it**

```bash
grep -rn "financial_ratio_service" --include="*.py" --include="*.js" sitiame_core/
```

Expected: no output (Tasks 1-4 already removed every import; this is the final confirmation before deleting the file).

- [ ] **Step 2: Delete the file**

```bash
git rm sitiame_core/financial_ratio_service.py
```

- [ ] **Step 3: Commit**

```bash
git commit -m "chore(scoring): remove financial_ratio_service.py, superseded by scoring360_service"
```

---

### Task 7: Deploy and verify on the live site

**Files:** none (deployment + verification only).

- [ ] **Step 1: Push all commits**

```bash
git push origin master
```

- [ ] **Step 2: Pull into both containers**

```bash
ssh -i ~/.ssh/id_ed25519_sitiame_vps root@31.207.36.253 '
set -e
docker exec sitiame-prod-backend-1 bash -c "cd /home/frappe/frappe-bench/apps/sitiame_core && git pull origin master"
docker exec sitiame-prod-frontend-1 bash -c "cd /home/frappe/frappe-bench/apps/sitiame_core && git pull origin master"
'
```

- [ ] **Step 3: Rebuild assets and clear cache**

```bash
ssh -i ~/.ssh/id_ed25519_sitiame_vps root@31.207.36.253 '
docker exec sitiame-prod-backend-1 bench build --app sitiame_core
docker exec sitiame-prod-backend-1 bench --site erp.sitiame-capital.com clear-cache
'
```

- [ ] **Step 4: Restart both containers**

```bash
ssh -i ~/.ssh/id_ed25519_sitiame_vps root@31.207.36.253 'cd /root && docker compose -f sitiame-prod-compose.yml restart backend frontend'
```

- [ ] **Step 5: Verification script 1 — `classement_erpnext()` runs cleanly on a real company**

Write locally, then deploy via the established one-off pattern (`scp` to `/tmp/`, `docker cp` into `sitiame-prod-backend-1:/home/frappe/frappe-bench/apps/sitiame_core/sitiame_core/tmp_verify_classement.py`, `bench --site erp.sitiame-capital.com execute sitiame_core.tmp_verify_classement.run`, then `rm -f` the temp file in the container):

```python
import frappe
from sitiame_core.scoring360_service import classement_erpnext


def run():
	ranking = classement_erpnext()
	sitiame_row = next((r for r in ranking["lignes"] if r["company"] == "SITIAME"), None)
	return {
		"total_companies": len(ranking["lignes"]),
		"compteurs": ranking["compteurs"],
		"sitiame_row": sitiame_row,
	}
```

Expected: no traceback; `compteurs` has exactly the four keys `pret_a_deployer`/`solide_mais_a_cadrer`/`risque_a_traiter`/`insuffisant` summing to `total_companies`; `sitiame_row` (if the SITIAME company has GL entries) has a non-null `composite_score` and a `decision` dict with `level`/`label`/`lecture`.

- [ ] **Step 6: Verification script 2 — ranking page loads with the new columns**

Ask the user to reload `https://erp.sitiame-capital.com/desk` → Organisation sidebar → "Classement financier" (Ctrl+Shift+R) and confirm the four new stat cards (Prêt à déployer / Solide mais à cadrer / Risque à traiter / Données insuffisantes) and the Score composite / Décision / 3 contribution columns render without a JS console error.

- [ ] **Step 7: Verification script 3 — scoring-dossier suggestions stay consistent**

```python
import frappe
from sitiame_core.api import get_scoring_suggestions


def run():
	frappe.set_user("Administrator")
	return get_scoring_suggestions("SITIAME")
```

Expected: no traceback; `capacite_remboursement`/`structure_financiere`/`rentabilite`/`liquidite_generale` are either all `None` (if SITIAME has zero GL entries) or all integers in `0..5`, with `_comptabilite_note` containing readable score values (not `None` printed as text where a number was expected — if it is, the weight lookup in Task 3 returned 0/falsy and needs checking against `Scoring 360 Settings`' actual field values via `frappe.get_single("Scoring 360 Settings").bank_debt_asset_weight` and `.internal_net_margin_weight`).

Deploy this the same way as verification script 1 (write, `scp`, `docker cp`, `bench execute`, `rm`).
