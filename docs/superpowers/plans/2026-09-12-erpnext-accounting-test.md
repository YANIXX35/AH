# Espace de test "Comptabilité ERPNext" Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an isolated, admin-only `/admin/erpnext-accounting-test` dashboard that displays 5 ERPNext accounting reports (Chart of Accounts, General Ledger, Trial Balance, Balance Sheet, Profit and Loss) for a selected already-provisioned PME, without touching any existing local accounting report.

**Architecture:** Four new `ErpNextClient` methods (reusing the existing `get()`/`post()` REST pattern for Chart of Accounts and General Ledger, a new `postForm()` helper for the two financial-statement reports which use Frappe's form-encoded `query_report.run` endpoint) feed a single new controller that renders one view with 5 tabs.

**Tech Stack:** Laravel 13, PHP 8.4, the existing `ErpNextClient` (`app/Services/ErpNextClient.php`), Bootstrap tabs (no new JS dependency — reuse the nav-tabs pattern already used elsewhere in this admin area).

## Global Constraints

- Zero modification to `AccountingController`, `BceaoLiasseService`, any `accounting.report.*` or `accounting.liasse-bceao*` route, or the existing `admin/erpnext-test/*` invoicing dashboard — spec "Ne pas toucher".
- The Balance Sheet / Profit and Loss report call MUST use `application/x-www-form-urlencoded` (via `asForm()`), NOT JSON — verified empirically that JSON body fails identically to the wrong field names; only form encoding with `filter_based_on: "Date Range"`, `period_start_date`, `period_end_date` works — spec "Découverte technique vérifiée empiriquement".
- Trial Balance (Balance générale) is computed in PHP by aggregating General Ledger rows — there is no dedicated reliable API endpoint for it — spec "Architecture".
- A failure on one report tab must not prevent the other 4 tabs from displaying — spec "Gestion d'erreur".
- No automated PHPUnit run is possible on this machine (PHP 8.2 installed vs 8.4 required) — every task's verification step is manual/tinker-based, per the established pattern in this session.

---

## Task 1: Extend `ErpNextClient` with report methods

**Files:**
- Modify: `app/Services/ErpNextClient.php` (add 1 private helper after line 86, add 4 public methods at the end of the class, after line 493)

**Interfaces:**
- Consumes: existing private `get(string $path): array` (line 41), `authHeader()` (28), `baseUrl()` (23), `timeout()` (33), `extractErrorMessage()` (104), `ErpNextApiException`.
- Produces:
  - `ErpNextClient::getChartOfAccountsForCompany(string $company): array` — list of accounts (each an associative array with `name`, `account_name`, `is_group`, `root_type`).
  - `ErpNextClient::getGeneralLedgerForCompany(string $company, string $fromDate, string $toDate): array` — list of GL Entry rows (`account`, `posting_date`, `debit`, `credit`, `voucher_type`, `voucher_no`, `remarks`).
  - `ErpNextClient::getTrialBalanceForCompany(string $company, string $fromDate, string $toDate): array` — list of `['account' => string, 'debit' => float, 'credit' => float, 'balance' => float]`, one row per account, sorted by account name.
  - `ErpNextClient::getFinancialStatementForCompany(string $company, string $reportName, string $fromDate, string $toDate): array` — raw `result` array as returned by ERPNext's query report (hierarchical rows with `account`, `account_name`, `indent`, and a dynamic total-amount key).
  All consumed by Task 3's controller.

- [ ] **Step 1: Add the `postForm()` private helper**

Add this method to `app/Services/ErpNextClient.php` right after the existing `post()` method (i.e., right after line 86's closing `}`, before `put()`):

```php
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function postForm(string $path, array $payload): array
    {
        try {
            $response = Http::withHeaders(['Authorization' => $this->authHeader()])
                ->asForm()
                ->timeout($this->timeout())
                ->post($this->baseUrl().$path, $payload);
        } catch (\Throwable $exception) {
            throw new ErpNextApiException('ERPNext injoignable: '.$exception->getMessage());
        }

        if ($response->failed()) {
            throw new ErpNextApiException($this->extractErrorMessage($response));
        }

        return (array) ($response->json('message') ?? []);
    }
```

Note: unlike `get()`/`post()` (which read the `data` key of the JSON response), `postForm()` reads the `message` key — this is how Frappe's `query_report.run` whitelisted method wraps its return value, confirmed empirically.

- [ ] **Step 2: Add the 4 report methods**

Add these methods at the very end of the class, right before the final closing `}` (i.e., after `cancelSalesInvoiceForPme()`'s closing `}`, which is currently the last method):

```php
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getChartOfAccountsForCompany(string $company): array
    {
        $query = http_build_query([
            'filters' => json_encode([['company', '=', $company]]),
            'fields' => json_encode(['name', 'account_name', 'is_group', 'root_type', 'parent_account']),
            'limit_page_length' => 0,
            'order_by' => 'name asc',
        ]);

        return $this->get('/api/resource/Account?'.$query);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getGeneralLedgerForCompany(string $company, string $fromDate, string $toDate): array
    {
        $query = http_build_query([
            'filters' => json_encode([
                ['company', '=', $company],
                ['posting_date', '>=', $fromDate],
                ['posting_date', '<=', $toDate],
                ['is_cancelled', '=', 0],
            ]),
            'fields' => json_encode(['account', 'posting_date', 'debit', 'credit', 'voucher_type', 'voucher_no', 'remarks']),
            'limit_page_length' => 0,
            'order_by' => 'posting_date asc',
        ]);

        return $this->get('/api/resource/'.rawurlencode('GL Entry').'?'.$query);
    }

    /**
     * @return array<int, array{account: string, debit: float, credit: float, balance: float}>
     */
    public function getTrialBalanceForCompany(string $company, string $fromDate, string $toDate): array
    {
        $entries = $this->getGeneralLedgerForCompany($company, $fromDate, $toDate);

        $totals = [];
        foreach ($entries as $entry) {
            $account = (string) $entry['account'];
            if (! isset($totals[$account])) {
                $totals[$account] = ['account' => $account, 'debit' => 0.0, 'credit' => 0.0];
            }
            $totals[$account]['debit'] += (float) ($entry['debit'] ?? 0);
            $totals[$account]['credit'] += (float) ($entry['credit'] ?? 0);
        }

        $rows = array_values($totals);
        foreach ($rows as &$row) {
            $row['balance'] = round($row['debit'] - $row['credit'], 2);
        }
        unset($row);

        usort($rows, fn ($a, $b) => strcmp($a['account'], $b['account']));

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFinancialStatementForCompany(string $company, string $reportName, string $fromDate, string $toDate): array
    {
        $result = $this->postForm('/api/method/frappe.desk.query_report.run', [
            'report_name' => $reportName,
            'filters' => json_encode([
                'company' => $company,
                'filter_based_on' => 'Date Range',
                'period_start_date' => $fromDate,
                'period_end_date' => $toDate,
                'periodicity' => 'Yearly',
            ]),
        ]);

        return (array) ($result['result'] ?? []);
    }
```

- [ ] **Step 3: Lint the file**

Run: `php -l app/Services/ErpNextClient.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Manual verification against the real ERPNext trial**

Run:
```bash
php artisan tinker --execute="
\$erpNext = new App\Services\ErpNextClient();
\$company = 'NotifyMails #69';

\$coa = \$erpNext->getChartOfAccountsForCompany(\$company);
echo 'Chart of Accounts: ' . count(\$coa) . ' comptes' . PHP_EOL;

\$gl = \$erpNext->getGeneralLedgerForCompany(\$company, '2026-01-01', '2026-12-31');
echo 'General Ledger: ' . count(\$gl) . ' lignes' . PHP_EOL;

\$tb = \$erpNext->getTrialBalanceForCompany(\$company, '2026-01-01', '2026-12-31');
echo 'Trial Balance: ' . count(\$tb) . ' comptes' . PHP_EOL;
foreach (array_slice(\$tb, 0, 3) as \$row) {
    echo '  ' . \$row['account'] . ' : debit=' . \$row['debit'] . ' credit=' . \$row['credit'] . ' solde=' . \$row['balance'] . PHP_EOL;
}

\$bs = \$erpNext->getFinancialStatementForCompany(\$company, 'Balance Sheet', '2026-01-01', '2026-12-31');
echo 'Balance Sheet: ' . count(\$bs) . ' lignes' . PHP_EOL;

\$pl = \$erpNext->getFinancialStatementForCompany(\$company, 'Profit and Loss Statement', '2026-01-01', '2026-12-31');
echo 'Profit and Loss: ' . count(\$pl) . ' lignes' . PHP_EOL;
"
```
Expected: all 5 counts are non-zero (adjust the company name to whichever provisioned PME exists in this environment — e.g. re-check with `App\Models\User::whereNotNull('erpnext_company_name')->first()->erpnext_company_name` if `NotifyMails #69` isn't present locally).

- [ ] **Step 5: Commit**

```bash
git add app/Services/ErpNextClient.php
git commit -m "feat(erpnext-accounting): add Chart of Accounts/GL/Trial Balance/financial statement methods to ErpNextClient

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 2: Controller, routes, and views

**Files:**
- Create: `app/Http/Controllers/ErpNextAccountingTestController.php`
- Modify: `routes/web.php` (add 2 routes inside the existing `admin.` group, right after the `erpnext-test` sub-group)
- Create: `resources/views/admin/erpnext-accounting-test/index.blade.php`
- Create: `resources/views/admin/erpnext-accounting-test/show.blade.php`
- Modify: `resources/views/layouts/partials/sidebar.blade.php` (add 1 admin nav link, right after "ERPNext Test")

**Interfaces:**
- Consumes: `ErpNextClient::getChartOfAccountsForCompany()`/`getGeneralLedgerForCompany()`/`getTrialBalanceForCompany()`/`getFinancialStatementForCompany()` (Task 1), `User::whereNotNull('erpnext_company_name')` (existing, already used by `ProvisionErpNextCompanyForPme`/`ErpNextTestController`).
- Produces: routes `admin.erpnext-accounting-test.index`, `admin.erpnext-accounting-test.show`.

- [ ] **Step 1: Add the routes**

In `routes/web.php`, find the `erpnext-test` sub-group added earlier in this session (inside the `admin.` group, currently reads):
```php
        Route::prefix('erpnext-test')->name('erpnext-test.')->group(function () {
            Route::get('/', [ErpNextTestController::class, 'index'])->name('index');
            Route::get('/create', [ErpNextTestController::class, 'create'])->name('create');
            Route::post('/', [ErpNextTestController::class, 'store'])->name('store');
            Route::get('/{erpNextTestInvoice}', [ErpNextTestController::class, 'show'])->name('show');
        });
```

Add this new sub-group right after it, still inside the same enclosing `admin.` group:
```php
        Route::prefix('erpnext-accounting-test')->name('erpnext-accounting-test.')->group(function () {
            Route::get('/', [ErpNextAccountingTestController::class, 'index'])->name('index');
            Route::get('/show', [ErpNextAccountingTestController::class, 'show'])->name('show');
        });
```

Then add the import alongside the existing `use App\Http\Controllers\ErpNextTestController;` line near the top of the file:
```php
use App\Http\Controllers\ErpNextAccountingTestController;
```

- [ ] **Step 2: Write the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ErpNextClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ErpNextAccountingTestController extends Controller
{
    public function index(): View
    {
        $pmes = User::whereNotNull('erpnext_company_name')->orderBy('company_name')->get();

        return view('admin.erpnext-accounting-test.index', compact('pmes'));
    }

    public function show(Request $request, ErpNextClient $erpNext): View
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);

        $pme = User::findOrFail($validated['user_id']);
        $company = $pme->erpnext_company_name;
        $fromDate = $validated['from_date'];
        $toDate = $validated['to_date'];

        $chartOfAccounts = null;
        $chartOfAccountsError = null;
        $generalLedger = null;
        $generalLedgerError = null;
        $trialBalance = null;
        $trialBalanceError = null;
        $balanceSheet = null;
        $balanceSheetError = null;
        $profitAndLoss = null;
        $profitAndLossError = null;

        if (empty($company)) {
            $error = 'Cette PME n\'a pas de société ERPNext provisionnée (erpnext_company_name manquant).';

            return view('admin.erpnext-accounting-test.show', compact(
                'pme', 'fromDate', 'toDate',
                'chartOfAccounts', 'generalLedger', 'trialBalance', 'balanceSheet', 'profitAndLoss'
            ) + [
                'chartOfAccountsError' => $error,
                'generalLedgerError' => $error,
                'trialBalanceError' => $error,
                'balanceSheetError' => $error,
                'profitAndLossError' => $error,
            ]);
        }

        try {
            $chartOfAccounts = $erpNext->getChartOfAccountsForCompany($company);
        } catch (\Throwable $e) {
            $chartOfAccountsError = $e->getMessage();
        }

        try {
            $generalLedger = $erpNext->getGeneralLedgerForCompany($company, $fromDate, $toDate);
        } catch (\Throwable $e) {
            $generalLedgerError = $e->getMessage();
        }

        try {
            $trialBalance = $erpNext->getTrialBalanceForCompany($company, $fromDate, $toDate);
        } catch (\Throwable $e) {
            $trialBalanceError = $e->getMessage();
        }

        try {
            $balanceSheet = $erpNext->getFinancialStatementForCompany($company, 'Balance Sheet', $fromDate, $toDate);
        } catch (\Throwable $e) {
            $balanceSheetError = $e->getMessage();
        }

        try {
            $profitAndLoss = $erpNext->getFinancialStatementForCompany($company, 'Profit and Loss Statement', $fromDate, $toDate);
        } catch (\Throwable $e) {
            $profitAndLossError = $e->getMessage();
        }

        return view('admin.erpnext-accounting-test.show', compact(
            'pme', 'fromDate', 'toDate',
            'chartOfAccounts', 'chartOfAccountsError',
            'generalLedger', 'generalLedgerError',
            'trialBalance', 'trialBalanceError',
            'balanceSheet', 'balanceSheetError',
            'profitAndLoss', 'profitAndLossError'
        ));
    }
}
```

Save as `app/Http/Controllers/ErpNextAccountingTestController.php`.

- [ ] **Step 3: Write the index view**

```blade
@extends('layouts.app')

@section('title', 'Comptabilité ERPNext Test | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="container-fluid py-4">
    <h1 class="h3 mb-3">Comptabilité ERPNext — Test</h1>

    <form method="GET" action="{{ route('admin.erpnext-accounting-test.show') }}">
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label" for="user_id">PME (provisionnée sur ERPNext)</label>
                <select class="form-select" id="user_id" name="user_id" required>
                    <option value="">— Choisir une PME —</option>
                    @foreach ($pmes as $pme)
                        <option value="{{ $pme->id }}">{{ $pme->company_name ?? $pme->name }} ({{ $pme->erpnext_company_name }})</option>
                    @endforeach
                </select>
                @if ($pmes->isEmpty())
                    <div class="form-text text-danger">Aucune PME provisionnée trouvée (erpnext_company_name vide pour tout le monde).</div>
                @endif
            </div>
            <div class="col-md-3">
                <label class="form-label" for="from_date">Du</label>
                <input type="date" class="form-control" id="from_date" name="from_date" value="{{ now()->startOfYear()->toDateString() }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="to_date">Au</label>
                <input type="date" class="form-control" id="to_date" name="to_date" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Afficher</button>
            </div>
        </div>
    </form>
</div>
@endsection
```

Save as `resources/views/admin/erpnext-accounting-test/index.blade.php`.

- [ ] **Step 4: Write the show view**

```blade
@extends('layouts.app')

@section('title', 'Comptabilité ERPNext Test | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">{{ $pme->company_name ?? $pme->name }} — {{ $fromDate }} au {{ $toDate }}</h1>
        <a href="{{ route('admin.erpnext-accounting-test.index') }}">← Retour</a>
    </div>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-coa" type="button">Plan comptable</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-gl" type="button">Grand livre</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-tb" type="button">Balance générale</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-bs" type="button">Bilan</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-pl" type="button">Compte de résultat</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-coa">
            @if ($chartOfAccountsError)
                <div class="alert alert-danger">{{ $chartOfAccountsError }}</div>
            @else
                <table class="table table-sm">
                    <thead><tr><th>Compte</th><th>Type racine</th><th>Groupe ?</th></tr></thead>
                    <tbody>
                        @foreach ($chartOfAccounts as $account)
                            <tr>
                                <td>{{ $account['account_name'] }}</td>
                                <td>{{ $account['root_type'] }}</td>
                                <td>{{ $account['is_group'] ? 'Oui' : 'Non' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="tab-pane fade" id="tab-gl">
            @if ($generalLedgerError)
                <div class="alert alert-danger">{{ $generalLedgerError }}</div>
            @else
                <table class="table table-sm">
                    <thead><tr><th>Date</th><th>Compte</th><th>Débit</th><th>Crédit</th><th>Pièce</th></tr></thead>
                    <tbody>
                        @foreach ($generalLedger as $entry)
                            <tr>
                                <td>{{ $entry['posting_date'] }}</td>
                                <td>{{ $entry['account'] }}</td>
                                <td>{{ number_format((float) $entry['debit'], 0, ',', ' ') }}</td>
                                <td>{{ number_format((float) $entry['credit'], 0, ',', ' ') }}</td>
                                <td>{{ $entry['voucher_type'] }} {{ $entry['voucher_no'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="tab-pane fade" id="tab-tb">
            @if ($trialBalanceError)
                <div class="alert alert-danger">{{ $trialBalanceError }}</div>
            @else
                <table class="table table-sm">
                    <thead><tr><th>Compte</th><th>Débit</th><th>Crédit</th><th>Solde</th></tr></thead>
                    <tbody>
                        @foreach ($trialBalance as $row)
                            <tr>
                                <td>{{ $row['account'] }}</td>
                                <td>{{ number_format($row['debit'], 0, ',', ' ') }}</td>
                                <td>{{ number_format($row['credit'], 0, ',', ' ') }}</td>
                                <td>{{ number_format($row['balance'], 0, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="tab-pane fade" id="tab-bs">
            @if ($balanceSheetError)
                <div class="alert alert-danger">{{ $balanceSheetError }}</div>
            @else
                <table class="table table-sm">
                    <thead><tr><th>Compte</th><th>Montant</th></tr></thead>
                    <tbody>
                        @foreach ($balanceSheet as $row)
                            <tr>
                                <td style="padding-left: {{ ($row['indent'] ?? 0) * 20 }}px">{{ $row['account_name'] ?? $row['account'] ?? '' }}</td>
                                <td>{{ isset($row['total']) ? number_format((float) $row['total'], 0, ',', ' ') : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="tab-pane fade" id="tab-pl">
            @if ($profitAndLossError)
                <div class="alert alert-danger">{{ $profitAndLossError }}</div>
            @else
                <table class="table table-sm">
                    <thead><tr><th>Compte</th><th>Montant</th></tr></thead>
                    <tbody>
                        @foreach ($profitAndLoss as $row)
                            <tr>
                                <td style="padding-left: {{ ($row['indent'] ?? 0) * 20 }}px">{{ $row['account_name'] ?? $row['account'] ?? '' }}</td>
                                <td>{{ isset($row['total']) ? number_format((float) $row['total'], 0, ',', ' ') : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
@endsection
```

Save as `resources/views/admin/erpnext-accounting-test/show.blade.php`.

- [ ] **Step 5: Add the sidebar link**

In `resources/views/layouts/partials/sidebar.blade.php`, find the entry added earlier in this session for "ERPNext Test" (inside the admin collapse block):
```blade
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.erpnext-test.*') ? 'active' : '' }}" href="{{ route('admin.erpnext-test.index') }}">ERPNext Test</a>
                            </li>
```

Add this new entry right after it:
```blade
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.erpnext-accounting-test.*') ? 'active' : '' }}" href="{{ route('admin.erpnext-accounting-test.index') }}">Comptabilité ERPNext Test</a>
                            </li>
```

- [ ] **Step 6: Lint the controller**

Run: `php -l app/Http/Controllers/ErpNextAccountingTestController.php`
Expected: `No syntax errors detected`

- [ ] **Step 7: Manual end-to-end verification**

Run: `php artisan route:list --name=admin.erpnext-accounting-test`
Expected: 2 routes listed (`index`, `show`), both under `platform.admin` middleware.

Then, in a browser (admin logged in), visit `/admin/erpnext-accounting-test`, select a provisioned PME (e.g. "NotifyMails"), leave the default date range, click "Afficher".

Expected: all 5 tabs render without a blank page; "Plan comptable" shows ~1378 rows; "Grand livre" shows real posted entries; "Balance générale" shows non-zero debit/credit sums for at least the Customer/Income/Tax accounts used by the invoices created earlier this session; "Bilan" shows a "Total Asset" row; "Compte de résultat" shows a "Profit for the year" row roughly matching the Bilan's income figure.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/ErpNextAccountingTestController.php routes/web.php resources/views/admin/erpnext-accounting-test resources/views/layouts/partials/sidebar.blade.php
git commit -m "feat(erpnext-accounting): add admin accounting test dashboard (5 report tabs)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```
