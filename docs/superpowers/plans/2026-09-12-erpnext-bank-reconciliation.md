# Rapprochement bancaire ERPNext (v1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add manual Bank Transaction (statement line) creation to ERPNext from the existing accounting test dashboard, auto-provisioning the underlying Bank/Bank Account the first time a treasury account is used, and display the resulting reconciliation status (Pending/Reconciled) in a new 6th tab.

**Architecture:** Three new `ErpNextClient` methods (one public find-or-create-then-list helper for the underlying Bank/Bank Account plumbing, one public creator, one public lister), wired into the existing `ErpNextAccountingTestController` with the same create-form/list-tab pattern already used for Journal Entries and the 5 accounting reports.

**Tech Stack:** Laravel 13, PHP 8.4, the existing `ErpNextClient` (`app/Services/ErpNextClient.php`).

## Global Constraints

- v1 creates statement lines only — no matching/reconciling action against existing Payment Entries/Journal Entries — spec "Décisions verrouillées".
- The `Bank`/`Bank Account` objects are auto-created (find-or-create) the first time a given treasury account is used for a PME — never a manual prerequisite — spec "Décisions verrouillées".
- `Bank Transaction` is NOT submitted (`docstatus` stays `0`/Draft) — its own native `status` field (Pending/Reconciled) already reflects reconciliation state without submission, verified empirically — spec "Architecture".
- Zero modification to `AccountingController::bankReconciliation()`, `TreasuryTransaction`, or the Mobile Money reconciliation feature — spec "Ne pas toucher".
- No automated PHPUnit run is possible on this machine (PHP 8.2 installed vs 8.4 required) — every task's verification step is manual/tinker-based, per the established pattern in this session.

---

## Task 1: Extend `ErpNextClient` with Bank Transaction methods

**Files:**
- Modify: `app/Services/ErpNextClient.php` (add 3 methods at the end of the class, after the current final `}`)

**Interfaces:**
- Consumes: existing private `get()`/`post()` helpers, existing private `findAccountByNumber(string $company, string $accountNumber): string`, `App\Models\User`.
- Produces:
  - `ErpNextClient::createBankTransactionForPme(User $pme, string $treasuryAccountNumber, string $date, float $amount, string $direction, string $description, ?string $referenceNumber = null): array` — `$direction` is `'deposit'` or `'withdrawal'`. Throws `ErpNextApiException` on failure.
  - `ErpNextClient::getBankTransactionsForCompany(string $company): array` — list of `['name' => string, 'status' => string, 'bank_account' => string, 'date' => string, 'deposit' => float, 'withdrawal' => float, 'description' => string, 'unallocated_amount' => float]`.
  Both consumed by Task 2's controller.

- [ ] **Step 1: Add the private `findOrCreateBankAccountForPme()` helper and the two public methods**

Add this at the very end of `app/Services/ErpNextClient.php`, right before the final closing `}` of the class:

```php
    private function findOrCreateBankAccountForPme(User $pme, string $resolvedTreasuryAccount): string
    {
        $query = http_build_query([
            'filters' => json_encode([
                ['company', '=', $pme->erpnext_company_name],
                ['account', '=', $resolvedTreasuryAccount],
            ]),
            'fields' => json_encode(['name']),
            'limit_page_length' => 1,
        ]);

        $existing = $this->get('/api/resource/'.rawurlencode('Bank Account').'?'.$query);
        if (! empty($existing[0]['name'])) {
            return (string) $existing[0]['name'];
        }

        $bankQuery = http_build_query([
            'filters' => json_encode([['bank_name', '=', 'Banque interne']]),
            'fields' => json_encode(['name']),
            'limit_page_length' => 1,
        ]);
        $existingBank = $this->get('/api/resource/Bank?'.$bankQuery);
        if (empty($existingBank[0]['name'])) {
            $this->post('/api/resource/Bank', ['bank_name' => 'Banque interne']);
        }

        $created = $this->post('/api/resource/'.rawurlencode('Bank Account'), [
            'account_name' => 'Compte '.$resolvedTreasuryAccount,
            'bank' => 'Banque interne',
            'company' => $pme->erpnext_company_name,
            'account' => $resolvedTreasuryAccount,
            'is_company_account' => 1,
        ]);

        $bankAccountName = (string) ($created['name'] ?? '');
        if ($bankAccountName === '') {
            throw new ErpNextApiException('ERPNext n\'a pas renvoyé de nom de compte bancaire après création.');
        }

        return $bankAccountName;
    }

    /**
     * @return array<string, mixed>
     */
    public function createBankTransactionForPme(
        User $pme,
        string $treasuryAccountNumber,
        string $date,
        float $amount,
        string $direction,
        string $description,
        ?string $referenceNumber = null
    ): array {
        $resolvedTreasuryAccount = $this->findAccountByNumber($pme->erpnext_company_name, $treasuryAccountNumber);
        $bankAccount = $this->findOrCreateBankAccountForPme($pme, $resolvedTreasuryAccount);

        $payload = [
            'date' => $date,
            'bank_account' => $bankAccount,
            'deposit' => $direction === 'deposit' ? $amount : 0,
            'withdrawal' => $direction === 'withdrawal' ? $amount : 0,
            'description' => $description,
            'currency' => 'XOF',
        ];

        if (! empty($referenceNumber)) {
            $payload['reference_number'] = $referenceNumber;
        }

        $created = $this->post('/api/resource/'.rawurlencode('Bank Transaction'), $payload);
        if (empty($created['name'])) {
            throw new ErpNextApiException('ERPNext n\'a pas renvoyé de nom de transaction bancaire après création.');
        }

        return $created;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBankTransactionsForCompany(string $company): array
    {
        $query = http_build_query([
            'filters' => json_encode([['company', '=', $company]]),
            'fields' => json_encode(['name', 'status', 'bank_account', 'date', 'deposit', 'withdrawal', 'description', 'unallocated_amount']),
            'limit_page_length' => 0,
            'order_by' => 'date desc',
        ]);

        return $this->get('/api/resource/'.rawurlencode('Bank Transaction').'?'.$query);
    }
```

- [ ] **Step 2: Lint the file**

Run: `php -l app/Services/ErpNextClient.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Manual verification against the real ERPNext trial**

```bash
php artisan tinker --execute="
\$pme = App\Models\User::whereNotNull('erpnext_company_name')->first();
\$erpNext = new App\Services\ErpNextClient();

\$tx1 = \$erpNext->createBankTransactionForPme(\$pme, '5711', now()->toDateString(), 20000, 'deposit', 'Test releve depot');
echo 'tx1 name: ' . (\$tx1['name'] ?? 'N/A') . ' status: ' . (\$tx1['status'] ?? 'N/A') . PHP_EOL;

\$tx2 = \$erpNext->createBankTransactionForPme(\$pme, '5711', now()->toDateString(), 5000, 'withdrawal', 'Test releve retrait');
echo 'tx2 name: ' . (\$tx2['name'] ?? 'N/A') . ' status: ' . (\$tx2['status'] ?? 'N/A') . PHP_EOL;

\$list = \$erpNext->getBankTransactionsForCompany(\$pme->erpnext_company_name);
echo 'total transactions: ' . count(\$list) . PHP_EOL;
"
```
Expected: both transactions created with `status: Pending`; the second call does NOT create a new `Bank`/`Bank Account` (only the first does) — confirm by checking no error and that both transactions share the same `bank_account` value; `getBankTransactionsForCompany` returns at least these 2 rows (plus any from earlier manual testing this session).

- [ ] **Step 4: Commit**

```bash
git add app/Services/ErpNextClient.php
git commit -m "feat(erpnext-accounting): add Bank Transaction creation/listing to ErpNextClient

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 2: Controller methods, routes, and views

**Files:**
- Modify: `app/Http/Controllers/ErpNextAccountingTestController.php` (add 2 public methods at the end of the class; add 1 try/catch block + 1 variable pair inside the existing `show()` method)
- Modify: `routes/web.php` (add 2 routes inside the `erpnext-accounting-test` sub-group)
- Create: `resources/views/admin/erpnext-accounting-test/create-bank-transaction.blade.php`
- Modify: `resources/views/admin/erpnext-accounting-test/show.blade.php` (add a 6th tab)
- Modify: `resources/views/admin/erpnext-accounting-test/index.blade.php` (add 1 link)

**Interfaces:**
- Consumes: `ErpNextClient::createBankTransactionForPme()`/`getBankTransactionsForCompany()` (Task 1), `ErpNextClient::getChartOfAccountsForCompany()` (existing).
- Produces: routes `admin.erpnext-accounting-test.create-bank-transaction`, `admin.erpnext-accounting-test.store-bank-transaction`.

- [ ] **Step 1: Add the routes**

In `routes/web.php`, the `erpnext-accounting-test` sub-group currently reads:
```php
        Route::prefix('erpnext-accounting-test')->name('erpnext-accounting-test.')->group(function () {
            Route::get('/', [ErpNextAccountingTestController::class, 'index'])->name('index');
            Route::get('/show', [ErpNextAccountingTestController::class, 'show'])->name('show');
            Route::get('/create-entry', [ErpNextAccountingTestController::class, 'createEntry'])->name('create-entry');
            Route::post('/create-entry', [ErpNextAccountingTestController::class, 'storeEntry'])->name('store-entry');
        });
```

Change it to:
```php
        Route::prefix('erpnext-accounting-test')->name('erpnext-accounting-test.')->group(function () {
            Route::get('/', [ErpNextAccountingTestController::class, 'index'])->name('index');
            Route::get('/show', [ErpNextAccountingTestController::class, 'show'])->name('show');
            Route::get('/create-entry', [ErpNextAccountingTestController::class, 'createEntry'])->name('create-entry');
            Route::post('/create-entry', [ErpNextAccountingTestController::class, 'storeEntry'])->name('store-entry');
            Route::get('/create-bank-transaction', [ErpNextAccountingTestController::class, 'createBankTransaction'])->name('create-bank-transaction');
            Route::post('/create-bank-transaction', [ErpNextAccountingTestController::class, 'storeBankTransaction'])->name('store-bank-transaction');
        });
```

- [ ] **Step 2: Add the bank transaction list to `show()`**

In `app/Http/Controllers/ErpNextAccountingTestController.php`, the `show()` method currently ends with:
```php
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
```

Change to:
```php
        try {
            $profitAndLoss = $erpNext->getFinancialStatementForCompany($company, 'Profit and Loss Statement', $fromDate, $toDate);
        } catch (\Throwable $e) {
            $profitAndLossError = $e->getMessage();
        }

        $bankTransactions = null;
        $bankTransactionsError = null;

        try {
            $bankTransactions = $erpNext->getBankTransactionsForCompany($company);
        } catch (\Throwable $e) {
            $bankTransactionsError = $e->getMessage();
        }

        return view('admin.erpnext-accounting-test.show', compact(
            'pme', 'fromDate', 'toDate',
            'chartOfAccounts', 'chartOfAccountsError',
            'generalLedger', 'generalLedgerError',
            'trialBalance', 'trialBalanceError',
            'balanceSheet', 'balanceSheetError',
            'profitAndLoss', 'profitAndLossError',
            'bankTransactions', 'bankTransactionsError'
        ));
    }
```

Also update the early-return block (the "PME not provisioned" branch, currently near the top of `show()`) — it currently reads:
```php
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
```

Change to:
```php
        if (empty($company)) {
            $error = 'Cette PME n\'a pas de société ERPNext provisionnée (erpnext_company_name manquant).';
            $bankTransactions = null;

            return view('admin.erpnext-accounting-test.show', compact(
                'pme', 'fromDate', 'toDate',
                'chartOfAccounts', 'generalLedger', 'trialBalance', 'balanceSheet', 'profitAndLoss', 'bankTransactions'
            ) + [
                'chartOfAccountsError' => $error,
                'generalLedgerError' => $error,
                'trialBalanceError' => $error,
                'balanceSheetError' => $error,
                'profitAndLossError' => $error,
                'bankTransactionsError' => $error,
            ]);
        }
```

- [ ] **Step 3: Add the `createBankTransaction()`/`storeBankTransaction()` methods**

Add these at the very end of the class, right before the final closing `}`:

```php
    public function createBankTransaction(Request $request, ErpNextClient $erpNext): View
    {
        $pmes = User::whereNotNull('erpnext_company_name')->orderBy('company_name')->get();

        $accounts = [];
        $selectedUserId = $request->query('user_id');
        if ($selectedUserId) {
            $pme = User::find($selectedUserId);
            if ($pme && $pme->erpnext_company_name) {
                try {
                    $accounts = $erpNext->getChartOfAccountsForCompany($pme->erpnext_company_name);
                } catch (\Throwable) {
                    $accounts = [];
                }
            }
        }

        return view('admin.erpnext-accounting-test.create-bank-transaction', [
            'pmes' => $pmes,
            'accounts' => $accounts,
            'selectedUserId' => $selectedUserId,
        ]);
    }

    public function storeBankTransaction(Request $request, ErpNextClient $erpNext): View
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'account_number' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'direction' => ['required', 'in:deposit,withdrawal'],
            'description' => ['required', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
        ]);

        $pme = User::findOrFail($validated['user_id']);

        $result = null;
        $error = null;

        try {
            $result = $erpNext->createBankTransactionForPme(
                $pme,
                $validated['account_number'],
                $validated['date'],
                (float) $validated['amount'],
                $validated['direction'],
                $validated['description'],
                $validated['reference_number'] ?? null
            );
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('admin.erpnext-accounting-test.create-bank-transaction', [
            'pmes' => User::whereNotNull('erpnext_company_name')->orderBy('company_name')->get(),
            'accounts' => [],
            'selectedUserId' => $validated['user_id'],
            'result' => $result,
            'error' => $error,
        ]);
    }
```

- [ ] **Step 4: Write the create-bank-transaction view**

```blade
@extends('layouts.app')

@section('title', 'Nouvelle ligne de relevé ERPNext | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="container-fluid py-4">
    <h1 class="h3 mb-3">Nouvelle ligne de relevé bancaire ERPNext</h1>

    @if (isset($result) && $result)
        <div class="alert alert-success">
            Transaction créée : <strong>{{ $result['name'] ?? '' }}</strong> (statut : {{ $result['status'] ?? '' }})
        </div>
    @endif

    @if (isset($error) && $error)
        <div class="alert alert-danger">{{ $error }}</div>
    @endif

    <form method="GET" action="{{ route('admin.erpnext-accounting-test.create-bank-transaction') }}" class="mb-3">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="user_id_selector">PME</label>
                <select class="form-select" id="user_id_selector" name="user_id" onchange="this.form.submit()">
                    <option value="">— Choisir une PME —</option>
                    @foreach ($pmes as $pme)
                        <option value="{{ $pme->id }}" {{ (string) $selectedUserId === (string) $pme->id ? 'selected' : '' }}>{{ $pme->company_name ?? $pme->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>

    @if (! empty($accounts))
        <form method="POST" action="{{ route('admin.erpnext-accounting-test.store-bank-transaction') }}">
            @csrf
            <input type="hidden" name="user_id" value="{{ $selectedUserId }}">

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label" for="account_number">Compte de trésorerie</label>
                    <select class="form-select" id="account_number" name="account_number" required>
                        <option value="">— Compte —</option>
                        @foreach ($accounts as $account)
                            @if (! $account['is_group'] && $account['root_type'] === 'Asset')
                                <option value="{{ \Illuminate\Support\Str::before($account['account_name'], '-') }}">{{ $account['account_name'] }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="date">Date</label>
                    <input type="date" class="form-control" id="date" name="date" value="{{ now()->toDateString() }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="amount">Montant</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" id="amount" name="amount" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="direction">Sens</label>
                    <select class="form-select" id="direction" name="direction" required>
                        <option value="deposit">Dépôt</option>
                        <option value="withdrawal">Retrait</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="reference_number">Référence</label>
                    <input type="text" class="form-control" id="reference_number" name="reference_number">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="description">Description</label>
                <input type="text" class="form-control" id="description" name="description" required>
            </div>

            <button type="submit" class="btn btn-primary">Créer la ligne de relevé</button>
        </form>
    @elseif ($selectedUserId)
        <div class="alert alert-warning">Aucun compte trouvé pour cette PME (vérifiez qu'elle est bien provisionnée sur ERPNext).</div>
    @endif
</div>
@endsection
```

Save as `resources/views/admin/erpnext-accounting-test/create-bank-transaction.blade.php`.

- [ ] **Step 5: Add the 6th tab to `show.blade.php`**

In `resources/views/admin/erpnext-accounting-test/show.blade.php`, the tab nav currently ends with:
```blade
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-pl" type="button">Compte de résultat</button></li>
    </ul>
```

Change to:
```blade
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-pl" type="button">Compte de résultat</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-bank" type="button">Rapprochement bancaire</button></li>
    </ul>
```

And the tab content, which currently ends with the `#tab-pl` pane's closing `</div>` right before the outer `</div>` that closes `.tab-content`, currently:
```blade
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

Change to:
```blade
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

        <div class="tab-pane fade" id="tab-bank">
            @if ($bankTransactionsError)
                <div class="alert alert-danger">{{ $bankTransactionsError }}</div>
            @else
                <div class="mb-2">
                    <a href="{{ route('admin.erpnext-accounting-test.create-bank-transaction') }}" class="btn btn-outline-primary btn-sm">+ Nouvelle ligne de relevé</a>
                </div>
                <table class="table table-sm">
                    <thead><tr><th>Date</th><th>Compte bancaire</th><th>Dépôt</th><th>Retrait</th><th>Description</th><th>Statut</th><th>Montant non alloué</th></tr></thead>
                    <tbody>
                        @foreach ($bankTransactions as $tx)
                            <tr>
                                <td>{{ $tx['date'] }}</td>
                                <td>{{ $tx['bank_account'] }}</td>
                                <td>{{ number_format((float) $tx['deposit'], 0, ',', ' ') }}</td>
                                <td>{{ number_format((float) $tx['withdrawal'], 0, ',', ' ') }}</td>
                                <td>{{ $tx['description'] }}</td>
                                <td>
                                    @if ($tx['status'] === 'Reconciled')
                                        <span class="badge bg-success">{{ $tx['status'] }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $tx['status'] }}</span>
                                    @endif
                                </td>
                                <td>{{ number_format((float) $tx['unallocated_amount'], 0, ',', ' ') }}</td>
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

- [ ] **Step 6: Add the "Nouvelle ligne de relevé" link to the index view**

In `resources/views/admin/erpnext-accounting-test/index.blade.php`, the header currently reads:
```blade
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Comptabilité ERPNext — Test</h1>
        <a href="{{ route('admin.erpnext-accounting-test.create-entry') }}" class="btn btn-outline-primary">+ Nouvelle écriture</a>
    </div>
```

Change to:
```blade
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Comptabilité ERPNext — Test</h1>
        <div>
            <a href="{{ route('admin.erpnext-accounting-test.create-entry') }}" class="btn btn-outline-primary">+ Nouvelle écriture</a>
            <a href="{{ route('admin.erpnext-accounting-test.create-bank-transaction') }}" class="btn btn-outline-primary">+ Ligne de relevé</a>
        </div>
    </div>
```

- [ ] **Step 7: Lint the controller and routes**

Run:
```bash
php -l app/Http/Controllers/ErpNextAccountingTestController.php
php -l routes/web.php
```
Expected: `No syntax errors detected` for both.

- [ ] **Step 8: Manual end-to-end verification**

Run: `php artisan route:list --name=admin.erpnext-accounting-test`
Expected: 6 routes now listed.

In a browser (admin logged in): visit `/admin/erpnext-accounting-test/create-bank-transaction`, select "NotifyMails" (accounts dropdown populates), pick a treasury (Asset) account, fill amount/date/direction/description, submit.

Expected: success message with a real `ACC-BTN-...` name and status `Pending`. Then go to `/admin/erpnext-accounting-test`, select "NotifyMails", "Afficher", open the "Rapprochement bancaire" tab — the new line appears with its status badge and correct deposit/withdrawal column populated.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/ErpNextAccountingTestController.php routes/web.php resources/views/admin/erpnext-accounting-test/create-bank-transaction.blade.php resources/views/admin/erpnext-accounting-test/show.blade.php resources/views/admin/erpnext-accounting-test/index.blade.php
git commit -m "feat(erpnext-accounting): add Bank Transaction creation and reconciliation status tab

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```
