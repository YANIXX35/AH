# Création manuelle d'écritures ERPNext Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add manual Journal Entry creation to ERPNext from the existing accounting test dashboard, mirroring ERPNext's real "New Journal Entry" fields (Entry Type, Posting Date, unlimited debit/credit lines with a live account picker, Reference Number/Date), submitted immediately so it feeds the Balance Sheet/General Ledger reports built in the previous sub-project.

**Architecture:** One new `ErpNextClient::createJournalEntryForPme()` method (reusing the existing private `findAccountByNumber()`), two new methods on the existing `ErpNextAccountingTestController`, and one new view with JS-driven dynamic rows (same pattern as `admin/erpnext-test/create.blade.php`'s invoice line rows, adapted to a live-populated account `<select>` instead of a free-text description).

**Tech Stack:** Laravel 13, PHP 8.4, the existing `ErpNextClient` (`app/Services/ErpNextClient.php`), vanilla JS for dynamic rows (no new dependency).

## Global Constraints

- Zero modification to `AccountingController::storeEntry()` or the local "Gestion des écritures" feature — spec "Ne pas toucher".
- Unlimited accounting lines (not fixed at 2) — spec "Décisions verrouillées".
- Account selection is a live dropdown from `ErpNextClient::getChartOfAccountsForCompany()` (already built in the previous sub-project), never free text — spec "Décisions verrouillées".
- The Journal Entry is submitted (`docstatus: 1`) immediately after creation — spec "Décisions verrouillées".
- Client-side (JS) debit/credit total matching is a UX aid only; ERPNext's own server-side validation is the real enforcement — spec "Décisions verrouillées".
- No automated PHPUnit run is possible on this machine (PHP 8.2 installed vs 8.4 required) — every task's verification step is manual/tinker-based, per the established pattern in this session.

---

## Task 1: `ErpNextClient::createJournalEntryForPme()`

**Files:**
- Modify: `app/Services/ErpNextClient.php` (add 1 public method at the end of the class, after line 597's closing `}`)

**Interfaces:**
- Consumes: existing private `findAccountByNumber(string $company, string $accountNumber): string` (already in the class, used by `provisionCompanyForPme()`), existing private `post()`/`put()` helpers, `App\Models\User`.
- Produces: `ErpNextClient::createJournalEntryForPme(User $pme, string $voucherType, string $postingDate, array $lines, ?string $referenceNumber = null, ?string $referenceDate = null): array` where `$lines` is `array<int, array{account_number: string, debit: float, credit: float}>`. Throws `ErpNextApiException` on failure (missing document name, or ERPNext validation error e.g. unbalanced entry — message passed through as-is). Consumed by Task 2's controller.

- [ ] **Step 1: Add the method**

Add this at the very end of `app/Services/ErpNextClient.php`, right before the final closing `}` of the class:

```php
    /**
     * @param  array<int, array{account_number: string, debit: float, credit: float}>  $lines
     * @return array<string, mixed>
     */
    public function createJournalEntryForPme(
        User $pme,
        string $voucherType,
        string $postingDate,
        array $lines,
        ?string $referenceNumber = null,
        ?string $referenceDate = null
    ): array {
        $accounts = [];
        foreach ($lines as $line) {
            $resolvedAccount = $this->findAccountByNumber($pme->erpnext_company_name, $line['account_number']);
            $accounts[] = [
                'account' => $resolvedAccount,
                'debit_in_account_currency' => (float) $line['debit'],
                'credit_in_account_currency' => (float) $line['credit'],
            ];
        }

        $payload = [
            'company' => $pme->erpnext_company_name,
            'voucher_type' => $voucherType,
            'posting_date' => $postingDate,
            'accounts' => $accounts,
        ];

        if (! empty($referenceNumber)) {
            $payload['cheque_no'] = $referenceNumber;
        }
        if (! empty($referenceDate)) {
            $payload['cheque_date'] = $referenceDate;
        }

        $created = $this->post('/api/resource/'.rawurlencode('Journal Entry'), $payload);
        $journalEntryName = (string) ($created['name'] ?? '');
        if ($journalEntryName === '') {
            throw new ErpNextApiException('ERPNext n\'a pas renvoyé de nom d\'écriture après création.');
        }

        return $this->put('/api/resource/'.rawurlencode('Journal Entry').'/'.rawurlencode($journalEntryName), [
            'docstatus' => 1,
        ]);
    }
```

- [ ] **Step 2: Lint the file**

Run: `php -l app/Services/ErpNextClient.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Manual verification — balanced entry succeeds**

Run (adjust the company/account numbers to real ones present in your ERPNext trial — `6` for a generic expense account and `5711` for the cash account already used in earlier sub-projects both exist under "NotifyMails #69"):

```bash
php artisan tinker --execute="
\$pme = App\Models\User::whereNotNull('erpnext_company_name')->first();
\$erpNext = new App\Services\ErpNextClient();
try {
    \$result = \$erpNext->createJournalEntryForPme(
        \$pme,
        'Journal Entry',
        now()->toDateString(),
        [
            ['account_number' => '6', 'debit' => 10000, 'credit' => 0],
            ['account_number' => '5711', 'debit' => 0, 'credit' => 10000],
        ]
    );
    echo 'name: ' . (\$result['name'] ?? 'N/A') . PHP_EOL;
    echo 'docstatus: ' . (\$result['docstatus'] ?? 'N/A') . PHP_EOL;
} catch (\Throwable \$e) {
    echo 'ERROR: ' . \$e->getMessage() . PHP_EOL;
}
"
```
Expected: a real `ACC-JV-...` name, `docstatus: 1`.

- [ ] **Step 4: Manual verification — unbalanced entry fails cleanly**

Run the same call but with `'debit' => 10000` on the first line and `'credit' => 5000` on the second (deliberately unbalanced).
Expected: an `ErpNextApiException` is thrown with a message from ERPNext about unequal debit/credit — no PHP fatal error, no orphaned Draft document left silently (a Draft may exist in ERPNext, which is fine per this v1's scope — cleanup of failed drafts is not handled, matching the "no retry" precedent set in earlier sub-projects).

- [ ] **Step 5: Commit**

```bash
git add app/Services/ErpNextClient.php
git commit -m "feat(erpnext-accounting): add createJournalEntryForPme to ErpNextClient

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 2: Controller methods, routes, and view

**Files:**
- Modify: `app/Http/Controllers/ErpNextAccountingTestController.php` (add 2 public methods at the end of the class, after line 96's closing `}`)
- Modify: `routes/web.php` (add 2 routes inside the existing `erpnext-accounting-test` sub-group)
- Create: `resources/views/admin/erpnext-accounting-test/create-entry.blade.php`
- Modify: `resources/views/admin/erpnext-accounting-test/index.blade.php` (add 1 link)

**Interfaces:**
- Consumes: `ErpNextClient::createJournalEntryForPme()` (Task 1), `ErpNextClient::getChartOfAccountsForCompany()` (already existing, added in the previous sub-project's Task 1).
- Produces: routes `admin.erpnext-accounting-test.create-entry`, `admin.erpnext-accounting-test.store-entry`.

- [ ] **Step 1: Add the routes**

In `routes/web.php`, find the `erpnext-accounting-test` sub-group added in the previous sub-project (currently reads):
```php
        Route::prefix('erpnext-accounting-test')->name('erpnext-accounting-test.')->group(function () {
            Route::get('/', [ErpNextAccountingTestController::class, 'index'])->name('index');
            Route::get('/show', [ErpNextAccountingTestController::class, 'show'])->name('show');
        });
```

Change it to:
```php
        Route::prefix('erpnext-accounting-test')->name('erpnext-accounting-test.')->group(function () {
            Route::get('/', [ErpNextAccountingTestController::class, 'index'])->name('index');
            Route::get('/show', [ErpNextAccountingTestController::class, 'show'])->name('show');
            Route::get('/create-entry', [ErpNextAccountingTestController::class, 'createEntry'])->name('create-entry');
            Route::post('/create-entry', [ErpNextAccountingTestController::class, 'storeEntry'])->name('store-entry');
        });
```

- [ ] **Step 2: Add the controller methods**

In `app/Http/Controllers/ErpNextAccountingTestController.php`, add these two public methods at the very end of the class, right before the final closing `}`:

```php
    public function createEntry(Request $request, ErpNextClient $erpNext): View
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

        return view('admin.erpnext-accounting-test.create-entry', [
            'pmes' => $pmes,
            'accounts' => $accounts,
            'selectedUserId' => $selectedUserId,
        ]);
    }

    public function storeEntry(Request $request, ErpNextClient $erpNext): View
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'voucher_type' => ['required', 'string', 'max:255'],
            'posting_date' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'reference_date' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_number' => ['required', 'string', 'max:255'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
        ]);

        $pme = User::findOrFail($validated['user_id']);

        $result = null;
        $error = null;

        try {
            $result = $erpNext->createJournalEntryForPme(
                $pme,
                $validated['voucher_type'],
                $validated['posting_date'],
                $validated['lines'],
                $validated['reference_number'] ?? null,
                $validated['reference_date'] ?? null
            );
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('admin.erpnext-accounting-test.create-entry', [
            'pmes' => User::whereNotNull('erpnext_company_name')->orderBy('company_name')->get(),
            'accounts' => [],
            'selectedUserId' => $validated['user_id'],
            'result' => $result,
            'error' => $error,
        ]);
    }
```

Add the `ErpNextClient` import if not already present at the top of the file — check first:
```bash
grep -n "^use App\\\\Services\\\\ErpNextClient;" app/Http/Controllers/ErpNextAccountingTestController.php
```
(It's already imported, used by `show()` — no change needed if this prints a match.)

- [ ] **Step 3: Write the create-entry view**

```blade
@extends('layouts.app')

@section('title', 'Nouvelle écriture ERPNext | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="container-fluid py-4">
    <h1 class="h3 mb-3">Nouvelle écriture ERPNext</h1>

    @if (isset($result) && $result)
        <div class="alert alert-success">
            Écriture créée et soumise : <strong>{{ $result['name'] ?? '' }}</strong> (statut : {{ $result['docstatus'] ?? '' }})
        </div>
    @endif

    @if (isset($error) && $error)
        <div class="alert alert-danger">{{ $error }}</div>
    @endif

    <form method="GET" action="{{ route('admin.erpnext-accounting-test.create-entry') }}" class="mb-3">
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
        <form method="POST" action="{{ route('admin.erpnext-accounting-test.store-entry') }}">
            @csrf
            <input type="hidden" name="user_id" value="{{ $selectedUserId }}">

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label" for="voucher_type">Type d'écriture</label>
                    <select class="form-select" id="voucher_type" name="voucher_type" required>
                        <option value="Journal Entry">Journal Entry</option>
                        <option value="Bank Entry">Bank Entry</option>
                        <option value="Cash Entry">Cash Entry</option>
                        <option value="Contra Entry">Contra Entry</option>
                        <option value="Opening Entry">Opening Entry</option>
                        <option value="Depreciation Entry">Depreciation Entry</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="posting_date">Date de saisie</label>
                    <input type="date" class="form-control" id="posting_date" name="posting_date" value="{{ now()->toDateString() }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="reference_number">Numéro de référence</label>
                    <input type="text" class="form-control" id="reference_number" name="reference_number">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="reference_date">Date de référence</label>
                    <input type="date" class="form-control" id="reference_date" name="reference_date">
                </div>
            </div>

            <label class="form-label">Lignes comptables</label>
            <div id="lines-container">
                <div class="row g-2 mb-2 line-row">
                    <div class="col-6">
                        <select class="form-select" name="lines[0][account_number]" required>
                            <option value="">— Compte —</option>
                            @foreach ($accounts as $account)
                                @if (! $account['is_group'])
                                    <option value="{{ \Illuminate\Support\Str::before($account['account_name'], '-') }}">{{ $account['account_name'] }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-3"><input type="number" step="0.01" min="0" class="form-control" name="lines[0][debit]" placeholder="Débit" value="0" required></div>
                    <div class="col-3"><input type="number" step="0.01" min="0" class="form-control" name="lines[0][credit]" placeholder="Crédit" value="0" required></div>
                </div>
                <div class="row g-2 mb-2 line-row">
                    <div class="col-6">
                        <select class="form-select" name="lines[1][account_number]" required>
                            <option value="">— Compte —</option>
                            @foreach ($accounts as $account)
                                @if (! $account['is_group'])
                                    <option value="{{ \Illuminate\Support\Str::before($account['account_name'], '-') }}">{{ $account['account_name'] }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-3"><input type="number" step="0.01" min="0" class="form-control" name="lines[1][debit]" placeholder="Débit" value="0" required></div>
                    <div class="col-3"><input type="number" step="0.01" min="0" class="form-control" name="lines[1][credit]" placeholder="Crédit" value="0" required></div>
                </div>
            </div>
            <button type="button" id="add-line" class="btn btn-outline-secondary btn-sm mb-3">+ Ajouter une ligne</button>

            <div class="row g-3 mb-3">
                <div class="col-md-3"><strong>Total Débit : </strong><span id="total-debit">0</span></div>
                <div class="col-md-3"><strong>Total Crédit : </strong><span id="total-credit">0</span></div>
            </div>

            <button type="submit" class="btn btn-primary">Créer et soumettre l'écriture</button>
        </form>

        <script>
            (function () {
                var accountsOptionsHtml = document.querySelector('#lines-container .line-row select').innerHTML;
                var container = document.getElementById('lines-container');
                var addButton = document.getElementById('add-line');
                var index = 2;

                addButton.addEventListener('click', function () {
                    var row = document.createElement('div');
                    row.className = 'row g-2 mb-2 line-row';
                    row.innerHTML =
                        '<div class="col-6"><select class="form-select" name="lines[' + index + '][account_number]" required>' + accountsOptionsHtml + '</select></div>' +
                        '<div class="col-3"><input type="number" step="0.01" min="0" class="form-control" name="lines[' + index + '][debit]" placeholder="Débit" value="0" required></div>' +
                        '<div class="col-3"><input type="number" step="0.01" min="0" class="form-control" name="lines[' + index + '][credit]" placeholder="Crédit" value="0" required></div>';
                    container.appendChild(row);
                    index += 1;
                    recomputeTotals();
                });

                function recomputeTotals() {
                    var totalDebit = 0;
                    var totalCredit = 0;
                    document.querySelectorAll('input[name*="[debit]"]').forEach(function (input) {
                        totalDebit += parseFloat(input.value) || 0;
                    });
                    document.querySelectorAll('input[name*="[credit]"]').forEach(function (input) {
                        totalCredit += parseFloat(input.value) || 0;
                    });
                    document.getElementById('total-debit').textContent = totalDebit.toFixed(2);
                    document.getElementById('total-credit').textContent = totalCredit.toFixed(2);
                }

                container.addEventListener('input', function (event) {
                    if (event.target.matches('input[name*="[debit]"], input[name*="[credit]"]')) {
                        recomputeTotals();
                    }
                });

                recomputeTotals();
            })();
        </script>
    @elseif ($selectedUserId)
        <div class="alert alert-warning">Aucun compte trouvé pour cette PME (vérifiez qu'elle est bien provisionnée sur ERPNext).</div>
    @endif
</div>
@endsection
```

Save as `resources/views/admin/erpnext-accounting-test/create-entry.blade.php`.

Note on the account `<option>` value: ERPNext account names follow the pattern `"<number>-<label> - <abbr>"` (e.g. `"6-Comptes de charges des activités ordinaires - NOT69"`), and `findAccountByNumber()` matches on `account_name LIKE '<number>-%'` — so extracting everything before the first `-` in `account_name` (via `Str::before($account['account_name'], '-')`) reliably reconstructs the account number to submit.

- [ ] **Step 4: Add the "Nouvelle écriture" link to the index view**

In `resources/views/admin/erpnext-accounting-test/index.blade.php`, the current `<h1>` line reads:
```blade
    <h1 class="h3 mb-3">Comptabilité ERPNext — Test</h1>
```

Change to:
```blade
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Comptabilité ERPNext — Test</h1>
        <a href="{{ route('admin.erpnext-accounting-test.create-entry') }}" class="btn btn-outline-primary">+ Nouvelle écriture</a>
    </div>
```

- [ ] **Step 5: Lint the controller and routes**

Run:
```bash
php -l app/Http/Controllers/ErpNextAccountingTestController.php
php -l routes/web.php
```
Expected: `No syntax errors detected` for both.

- [ ] **Step 6: Manual end-to-end verification**

Run: `php artisan route:list --name=admin.erpnext-accounting-test`
Expected: 4 routes now listed (`index`, `show`, `create-entry`, `store-entry`).

In a browser (admin logged in): visit `/admin/erpnext-accounting-test/create-entry`, select "NotifyMails" (page reloads with the account dropdown populated), fill in 2 balanced lines (e.g. a class-6 expense account debit 15000, the 5711 cash account credit 15000), submit.

Expected: success message with a real `ACC-JV-...` name and `docstatus: 1`. Then go back to `/admin/erpnext-accounting-test`, select "NotifyMails" again, "Afficher" — the "Grand livre" and "Balance générale" tabs from the previous sub-project should now include this new entry's two lines.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ErpNextAccountingTestController.php routes/web.php resources/views/admin/erpnext-accounting-test/create-entry.blade.php resources/views/admin/erpnext-accounting-test/index.blade.php
git commit -m "feat(erpnext-accounting): add manual Journal Entry creation form to the test dashboard

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```
