# Vrai pointage bancaire ERPNext (v2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an admin match a Pending Bank Transaction to an existing ERPNext voucher (Journal Entry/Payment Entry/Sales Invoice) from the accounting test dashboard, correctly appending to the transaction's existing `payment_entries` table and forcing `status: Reconciled` once fully allocated.

**Architecture:** One new `ErpNextClient` method implementing the read-modify-write cycle discovered empirically, wired into the existing `ErpNextAccountingTestController` with a new "Pointer" link per Pending row and a small reconciliation form, following the same pattern as the Journal Entry and Bank Transaction creation forms already built.

**Tech Stack:** Laravel 13, PHP 8.4, the existing `ErpNextClient` (`app/Services/ErpNextClient.php`).

## Global Constraints

- `payment_entries` is a full-array field replaced on every `PUT`, not an appendable list server-side — the method MUST `GET` the current array first and append to it, or prior allocations are silently lost (verified empirically) — spec "Découverte technique vérifiée empiriquement".
- ERPNext does not auto-flip `status` to `Reconciled` when `unallocated_amount` reaches 0 via the raw REST API — a second explicit `PUT` with `{"status": "Reconciled"}` is required — spec "Découverte technique vérifiée empiriquement".
- Voucher selection is free text (type via dropdown, exact document name via text input) — no exhaustive voucher picker in this v1 — spec "Décisions verrouillées".
- No automated PHPUnit run is possible on this machine (PHP 8.2 installed vs 8.4 required) — every task's verification step is manual/tinker-based, per the established pattern in this session.

---

## Task 1: `ErpNextClient::reconcileBankTransactionForPme()`

**Files:**
- Modify: `app/Services/ErpNextClient.php` (add 1 public method at the end of the class, after the current final `}`)

**Interfaces:**
- Consumes: existing private `get()`/`put()` helpers.
- Produces: `ErpNextClient::reconcileBankTransactionForPme(string $bankTransactionName, string $voucherType, string $voucherName, float $allocatedAmount): array`. Throws `ErpNextApiException` if ERPNext rejects the allocation (e.g. amount exceeds `unallocated_amount`, voucher not found). Consumed by Task 2's controller.

- [ ] **Step 1: Add the method**

Add this at the very end of `app/Services/ErpNextClient.php`, right before the final closing `}` of the class:

```php
    /**
     * @return array<string, mixed>
     */
    public function reconcileBankTransactionForPme(
        string $bankTransactionName,
        string $voucherType,
        string $voucherName,
        float $allocatedAmount
    ): array {
        $current = $this->get('/api/resource/'.rawurlencode('Bank Transaction').'/'.rawurlencode($bankTransactionName));

        $paymentEntries = (array) ($current['payment_entries'] ?? []);
        $paymentEntries[] = [
            'payment_document' => $voucherType,
            'payment_entry' => $voucherName,
            'allocated_amount' => $allocatedAmount,
        ];

        $updated = $this->put('/api/resource/'.rawurlencode('Bank Transaction').'/'.rawurlencode($bankTransactionName), [
            'payment_entries' => $paymentEntries,
        ]);

        if ((float) ($updated['unallocated_amount'] ?? 0) <= 0) {
            $updated = $this->put('/api/resource/'.rawurlencode('Bank Transaction').'/'.rawurlencode($bankTransactionName), [
                'status' => 'Reconciled',
            ]);
        }

        return $updated;
    }
```

- [ ] **Step 2: Lint the file**

Run: `php -l app/Services/ErpNextClient.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Manual verification against the real ERPNext trial**

Use an existing Pending Bank Transaction and a real voucher name from this session (adjust names to whatever exists in your environment — check via `php artisan tinker --execute="print_r((new App\Services\ErpNextClient())->getBankTransactionsForCompany('NotifyMails #69'));"` first to find one with `unallocated_amount > 0`):

```bash
php artisan tinker --execute="
\$erpNext = new App\Services\ErpNextClient();

\$result1 = \$erpNext->reconcileBankTransactionForPme('ACC-BTN-2026-00004', 'Journal Entry', 'ACC-JV-2026-00004', 50000);
echo 'after partial allocation - status: ' . \$result1['status'] . ' unallocated: ' . \$result1['unallocated_amount'] . PHP_EOL;

\$result2 = \$erpNext->reconcileBankTransactionForPme('ACC-BTN-2026-00004', 'Journal Entry', 'ACC-JV-2026-00005', 50000);
echo 'after full allocation - status: ' . \$result2['status'] . ' unallocated: ' . \$result2['unallocated_amount'] . PHP_EOL;
print_r(array_map(fn (\$p) => \$p['payment_entry'], \$result2['payment_entries']));
"
```
Expected: after the first call, `unallocated_amount` decreased by 50000 and `status` is still `Pending`; after the second call, `unallocated_amount` is `0` and `status` is `Reconciled`; the final `payment_entries` list contains BOTH voucher names (proving the read-append-write cycle preserved the first allocation instead of overwriting it).

- [ ] **Step 4: Commit**

```bash
git add app/Services/ErpNextClient.php
git commit -m "feat(erpnext-accounting): add reconcileBankTransactionForPme to ErpNextClient

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 2: Controller methods, routes, and view

**Files:**
- Modify: `app/Http/Controllers/ErpNextAccountingTestController.php` (add 2 public methods at the end of the class)
- Modify: `routes/web.php` (add 2 routes inside the `erpnext-accounting-test` sub-group)
- Create: `resources/views/admin/erpnext-accounting-test/reconcile-bank-transaction.blade.php`
- Modify: `resources/views/admin/erpnext-accounting-test/show.blade.php` (add a "Pointer" link per Pending row in the existing "Rapprochement bancaire" tab)

**Interfaces:**
- Consumes: `ErpNextClient::reconcileBankTransactionForPme()` (Task 1).
- Produces: routes `admin.erpnext-accounting-test.reconcile-bank-transaction`, `admin.erpnext-accounting-test.store-reconcile-bank-transaction`.

- [ ] **Step 1: Add the routes**

In `routes/web.php`, the `erpnext-accounting-test` sub-group currently ends with:
```php
            Route::get('/create-bank-transaction', [ErpNextAccountingTestController::class, 'createBankTransaction'])->name('create-bank-transaction');
            Route::post('/create-bank-transaction', [ErpNextAccountingTestController::class, 'storeBankTransaction'])->name('store-bank-transaction');
        });
```

Change it to:
```php
            Route::get('/create-bank-transaction', [ErpNextAccountingTestController::class, 'createBankTransaction'])->name('create-bank-transaction');
            Route::post('/create-bank-transaction', [ErpNextAccountingTestController::class, 'storeBankTransaction'])->name('store-bank-transaction');
            Route::get('/reconcile-bank-transaction', [ErpNextAccountingTestController::class, 'reconcileBankTransaction'])->name('reconcile-bank-transaction');
            Route::post('/reconcile-bank-transaction', [ErpNextAccountingTestController::class, 'storeReconcileBankTransaction'])->name('store-reconcile-bank-transaction');
        });
```

- [ ] **Step 2: Add the controller methods**

In `app/Http/Controllers/ErpNextAccountingTestController.php`, add these two public methods at the very end of the class, right before the final closing `}`:

```php
    public function reconcileBankTransaction(Request $request): View
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'bank_transaction_name' => ['required', 'string', 'max:255'],
            'unallocated_amount' => ['required', 'numeric'],
        ]);

        return view('admin.erpnext-accounting-test.reconcile-bank-transaction', [
            'userId' => $validated['user_id'],
            'bankTransactionName' => $validated['bank_transaction_name'],
            'unallocatedAmount' => $validated['unallocated_amount'],
        ]);
    }

    public function storeReconcileBankTransaction(Request $request, ErpNextClient $erpNext): View
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'bank_transaction_name' => ['required', 'string', 'max:255'],
            'voucher_type' => ['required', 'in:Journal Entry,Payment Entry,Sales Invoice'],
            'voucher_name' => ['required', 'string', 'max:255'],
            'allocated_amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $result = null;
        $error = null;

        try {
            $result = $erpNext->reconcileBankTransactionForPme(
                $validated['bank_transaction_name'],
                $validated['voucher_type'],
                $validated['voucher_name'],
                (float) $validated['allocated_amount']
            );
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('admin.erpnext-accounting-test.reconcile-bank-transaction', [
            'userId' => $validated['user_id'],
            'bankTransactionName' => $validated['bank_transaction_name'],
            'unallocatedAmount' => $result['unallocated_amount'] ?? $validated['allocated_amount'],
            'result' => $result,
            'error' => $error,
        ]);
    }
```

- [ ] **Step 3: Write the reconcile-bank-transaction view**

```blade
@extends('layouts.app')

@section('title', 'Pointer une transaction ERPNext | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="container-fluid py-4">
    <h1 class="h3 mb-3">Pointer la transaction {{ $bankTransactionName }}</h1>

    @if (isset($result) && $result)
        <div class="alert alert-success">
            Pointage enregistré. Statut : <strong>{{ $result['status'] ?? '' }}</strong> — Montant non alloué restant : <strong>{{ number_format((float) ($result['unallocated_amount'] ?? 0), 0, ',', ' ') }} XOF</strong>
        </div>
    @endif

    @if (isset($error) && $error)
        <div class="alert alert-danger">{{ $error }}</div>
    @endif

    <form method="POST" action="{{ route('admin.erpnext-accounting-test.store-reconcile-bank-transaction') }}">
        @csrf
        <input type="hidden" name="user_id" value="{{ $userId }}">
        <input type="hidden" name="bank_transaction_name" value="{{ $bankTransactionName }}">

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label" for="voucher_type">Type de pièce</label>
                <select class="form-select" id="voucher_type" name="voucher_type" required>
                    <option value="Journal Entry">Journal Entry</option>
                    <option value="Payment Entry">Payment Entry</option>
                    <option value="Sales Invoice">Sales Invoice</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="voucher_name">Nom exact de la pièce</label>
                <input type="text" class="form-control" id="voucher_name" name="voucher_name" placeholder="ex: ACC-JV-2026-00004" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="allocated_amount">Montant à allouer</label>
                <input type="number" step="0.01" min="0.01" class="form-control" id="allocated_amount" name="allocated_amount" value="{{ $unallocatedAmount }}" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Enregistrer le pointage</button>
    </form>
</div>
@endsection
```

Save as `resources/views/admin/erpnext-accounting-test/reconcile-bank-transaction.blade.php`.

- [ ] **Step 4: Add the "Pointer" link per Pending row**

In `resources/views/admin/erpnext-accounting-test/show.blade.php`, the "Rapprochement bancaire" tab's table row currently reads:
```blade
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
```

Add an "Actions" column. First change the header row right above it:
```blade
                    <thead><tr><th>Date</th><th>Compte bancaire</th><th>Dépôt</th><th>Retrait</th><th>Description</th><th>Statut</th><th>Montant non alloué</th></tr></thead>
```
to:
```blade
                    <thead><tr><th>Date</th><th>Compte bancaire</th><th>Dépôt</th><th>Retrait</th><th>Description</th><th>Statut</th><th>Montant non alloué</th><th></th></tr></thead>
```

Then change the row body to:
```blade
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
                                <td>
                                    @if ($tx['status'] !== 'Reconciled' && (float) $tx['unallocated_amount'] > 0)
                                        <a href="{{ route('admin.erpnext-accounting-test.reconcile-bank-transaction', ['user_id' => $pme->id, 'bank_transaction_name' => $tx['name'], 'unallocated_amount' => $tx['unallocated_amount']]) }}">Pointer</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
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
Expected: 8 routes now listed.

In a browser (admin logged in): go to `/admin/erpnext-accounting-test`, select a PME with a Pending bank transaction, "Afficher", open "Rapprochement bancaire", click "Pointer" next to a Pending row.

Expected: the reconciliation form pre-fills the remaining unallocated amount; enter a real voucher type/name from ERPNext and submit; success message shows the updated status/unallocated amount; going back to the "Rapprochement bancaire" tab confirms the row now reflects the reduced (or zeroed, with `Reconciled` badge) unallocated amount.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ErpNextAccountingTestController.php routes/web.php resources/views/admin/erpnext-accounting-test/reconcile-bank-transaction.blade.php resources/views/admin/erpnext-accounting-test/show.blade.php
git commit -m "feat(erpnext-accounting): add bank transaction reconciliation (matching) form

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```
