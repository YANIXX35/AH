# Payroll ERPNext Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When a PME validates a payroll run (`PayrollController::sync()`), automatically mirror it to ERPNext as two submitted Journal Entries (accrual + payment), exactly like Invoicing and Stock already sync in the background.

**Architecture:** Add a `payroll_erpnext_syncs` tracking table + `PayrollErpNextSync` model (copy of `InvoiceErpNextSync`/`StockMovementErpNextSync`). Add a new queued Job `SyncPayrollToErpNext` that calls the already-existing `ErpNextClient::createJournalEntryForPme()` twice. Dispatch that job from `PayrollController::sync()` right after its `DB::transaction()` commits.

**Tech Stack:** Laravel 13 / PHP 8.4, MySQL, existing `ErpNextClient` (`app/Services/ErpNextClient.php`) — no new PHP packages.

## Global Constraints

- The job must never let an exception propagate — production runs `QUEUE_CONNECTION=sync`, so an uncaught exception here would break the real payroll validation request for a real user. Every `\Throwable` is caught and recorded as `failed`, exactly like `SyncInvoiceToErpNext::handle()` and `SyncStockMovementToErpNext::handle()`.
- If the PME has no `erpnext_company_name`, mark the sync `failed` with a clear message and return — never attempt the API call.
- The accrual entry is Débit `6611` (leaf account "Appointements salaires et commissions" under the `661` group) / Crédit `422`, amount = `$payroll->total_gross` if `> 0`, else `$payroll->total_net` (same fallback `PayrollController::sync()` already uses for the local `AccountingEntry`, at `app/Http/Controllers/PayrollController.php:166`). Note: `661` itself is a Group Account on ERPNext's imported SYSCOHADA chart (`is_group: 1`) and cannot be posted to directly — verified live, `6611` is the correct leaf.
- The payment entry is Débit `422` / Crédit `5711`, amount = `$payroll->total_net`. The treasury credit account is always `5711` regardless of `payment_method` — same simplification already used by `ErpNextClient::recordPaymentForPme()` for Invoicing.
- Do not modify `PayrollRun`, `PayrollItem`, the gross/CNPS/ITS/net calculation in `PayrollController::store()`, or the local `AccountingEntry`/`TreasuryTransaction` creation in `sync()` — only add a dispatch call after the existing transaction.
- Do not modify `ErpNextClient` — `createJournalEntryForPme()` already exists and is already verified against the real ERPNext trial.

---

### Task 1: `payroll_erpnext_syncs` migration + `PayrollErpNextSync` model

**Files:**
- Create: `database/migrations/2026_09_13_010000_create_payroll_erpnext_syncs_table.php`
- Create: `app/Models/PayrollErpNextSync.php`

**Interfaces:**
- Consumes: nothing (new table, no dependency on other tasks).
- Produces: `PayrollErpNextSync` Eloquent model with fillable `payroll_run_id`, `status`, `erpnext_accrual_entry_name`, `erpnext_payment_entry_name`, `last_error`, `last_synced_at`, `raw_response`; relation `payroll(): BelongsTo` to `PayrollRun`. Used by Task 2 (the Job) via `PayrollErpNextSync::firstOrCreate(['payroll_run_id' => $payroll->id], ['status' => 'pending'])`.

- [ ] **Step 1: Create the migration**

Create `database/migrations/2026_09_13_010000_create_payroll_erpnext_syncs_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_erpnext_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->unique()->constrained('payroll_runs')->cascadeOnDelete();
            $table->string('status', 16)->default('pending');
            $table->string('erpnext_accrual_entry_name')->nullable();
            $table->string('erpnext_payment_entry_name')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_erpnext_syncs');
    }
};
```

- [ ] **Step 2: Run the migration locally**

Run: `php artisan migrate`
Expected: output includes `2026_09_13_010000_create_payroll_erpnext_syncs_table ... DONE`.

(If this fails with `Base table or view not found ... payroll_runs`, first check the actual table name via `php artisan tinker --execute="echo (new App\Models\PayrollRun)->getTable();"` and correct `constrained('payroll_runs')` to match — but `PayrollRun` follows the default Eloquent convention and every existing route/controller code already assumes the table is `payroll_runs`.)

- [ ] **Step 3: Create the model**

Create `app/Models/PayrollErpNextSync.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollErpNextSync extends Model
{
    protected $table = 'payroll_erpnext_syncs';

    protected $fillable = [
        'payroll_run_id',
        'status',
        'erpnext_accrual_entry_name',
        'erpnext_payment_entry_name',
        'last_error',
        'last_synced_at',
        'raw_response',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }
}
```

Note the explicit `protected $table = 'payroll_erpnext_syncs';` — without it, Eloquent's `Str::snake()` convention would split "ErpNext" as "Erp_Next" and guess the wrong table name (`payroll_erp_next_syncs`), the same pitfall already hit and fixed for `StockMovementErpNextSync` in the previous sub-project.

- [ ] **Step 4: Verify via tinker**

```bash
php artisan tinker --execute="
\$payroll = \App\Models\PayrollRun::first();
if (\$payroll) {
    \$sync = \App\Models\PayrollErpNextSync::create(['payroll_run_id' => \$payroll->id, 'status' => 'pending']);
    echo 'created id: '.\$sync->id.PHP_EOL;
    \$sync->delete();
    echo 'deleted ok';
} else {
    echo 'no local payroll runs exist yet, model class loaded and migrated fine';
}
"
```

Expected: no exception; either `created id: <n>` + `deleted ok`, or the fallback message if no `PayrollRun` rows exist locally yet.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_09_13_010000_create_payroll_erpnext_syncs_table.php app/Models/PayrollErpNextSync.php
git commit -m "feat(erpnext-payroll): add payroll_erpnext_syncs table and model

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 2: `SyncPayrollToErpNext` Job

**Files:**
- Create: `app/Jobs/SyncPayrollToErpNext.php`

**Interfaces:**
- Consumes: `PayrollErpNextSync` (Task 1), `PayrollRun::user(): BelongsTo` (already exists, `app/Models/PayrollRun.php:41-44`), `ErpNextClient::createJournalEntryForPme(User $pme, string $voucherType, string $postingDate, array $lines, ?string $referenceNumber = null, ?string $referenceDate = null): array` (already implemented in `app/Services/ErpNextClient.php:613-654`, where each line is `{account_number: string, debit: float, credit: float}`), `ErpNextClient::enabled(): bool`.
- Produces: `SyncPayrollToErpNext::dispatch(PayrollRun $payroll)` — a queueable job. Used by Task 3 (`PayrollController::sync()`).

- [ ] **Step 1: Create the Job**

Create `app/Jobs/SyncPayrollToErpNext.php`:

```php
<?php

namespace App\Jobs;

use App\Models\PayrollErpNextSync;
use App\Models\PayrollRun;
use App\Services\ErpNextClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncPayrollToErpNext implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public PayrollRun $payroll)
    {
    }

    public function handle(ErpNextClient $erpNext): void
    {
        $sync = PayrollErpNextSync::firstOrCreate(
            ['payroll_run_id' => $this->payroll->id],
            ['status' => 'pending']
        );

        if ($sync->status === 'synced') {
            return;
        }

        if (! $erpNext->enabled()) {
            $sync->update(['status' => 'failed', 'last_error' => 'ERPNext non configuré.']);

            return;
        }

        $pme = $this->payroll->user;

        if (empty($pme) || empty($pme->erpnext_company_name)) {
            $sync->update([
                'status' => 'failed',
                'last_error' => 'PME non provisionnée sur ERPNext (erpnext_company_name manquant).',
            ]);

            return;
        }

        $postingDate = $this->payroll->payment_date->format('Y-m-d');
        $grossAmount = (float) $this->payroll->total_gross > 0
            ? (float) $this->payroll->total_gross
            : (float) $this->payroll->total_net;

        try {
            $accrualResponse = $erpNext->createJournalEntryForPme(
                $pme,
                'Journal Entry',
                $postingDate,
                [
                    ['account_number' => '6611', 'debit' => $grossAmount, 'credit' => 0],
                    ['account_number' => '422', 'debit' => 0, 'credit' => $grossAmount],
                ]
            );

            $paymentResponse = $erpNext->createJournalEntryForPme(
                $pme,
                'Journal Entry',
                $postingDate,
                [
                    ['account_number' => '422', 'debit' => (float) $this->payroll->total_net, 'credit' => 0],
                    ['account_number' => '5711', 'debit' => 0, 'credit' => (float) $this->payroll->total_net],
                ]
            );

            $sync->update([
                'status' => 'synced',
                'erpnext_accrual_entry_name' => $accrualResponse['name'] ?? null,
                'erpnext_payment_entry_name' => $paymentResponse['name'] ?? null,
                'last_error' => null,
                'last_synced_at' => now(),
                'raw_response' => [
                    'accrual' => $accrualResponse,
                    'payment' => $paymentResponse,
                ],
            ]);
        } catch (\Throwable $exception) {
            $sync->update([
                'status' => 'failed',
                'last_error' => $exception->getMessage(),
            ]);
        }
    }
}
```

- [ ] **Step 2: Verify via tinker against the real ERPNext trial**

Use the locally provisioned test PME (id 15, "Test Inscription E2E ... #15") and a real local payroll run:

```bash
php artisan tinker --execute="
\$pme = \App\Models\User::find(15);
\$payroll = \App\Models\PayrollRun::create([
    'user_id' => \$pme->id,
    'title' => 'Paie test job sync',
    'period_month' => now()->format('Y-m'),
    'payment_date' => now()->toDateString(),
    'payment_method' => 'cash',
    'payment_account' => 'Compte Principal',
    'total_gross' => 150000,
    'total_cnps' => 10000,
    'total_its' => 5000,
    'total_net' => 135000,
    'status' => 'draft',
]);
\App\Jobs\SyncPayrollToErpNext::dispatchSync(\$payroll);
\$sync = \App\Models\PayrollErpNextSync::where('payroll_run_id', \$payroll->id)->first();
echo 'status: '.\$sync->status.PHP_EOL;
echo 'accrual: '.\$sync->erpnext_accrual_entry_name.PHP_EOL;
echo 'payment: '.\$sync->erpnext_payment_entry_name.PHP_EOL;
echo 'error: '.(\$sync->last_error ?? 'none').PHP_EOL;
"
```

Expected: `status: synced`, `accrual: ACC-JV-2026-0000X`, `payment: ACC-JV-2026-0000Y` (a different Journal Entry number), `error: none`. (`dispatchSync()` runs the job immediately in-process without needing a queue worker — appropriate for this manual verification step.)

- [ ] **Step 3: Commit**

```bash
git add app/Jobs/SyncPayrollToErpNext.php
git commit -m "feat(erpnext-payroll): add SyncPayrollToErpNext job

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 3: Dispatch the job from `PayrollController::sync()`

**Files:**
- Modify: `app/Http/Controllers/PayrollController.php:142-191`

**Interfaces:**
- Consumes: `SyncPayrollToErpNext::dispatch(PayrollRun $payroll)` (Task 2).
- Produces: no new public interface — `sync()` keeps its exact existing behavior and return type (`RedirectResponse`).

- [ ] **Step 1: Add the import**

In `app/Http/Controllers/PayrollController.php`, change:

```php
use App\Models\AccountingEntry;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\TreasuryTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
```

to:

```php
use App\Jobs\SyncPayrollToErpNext;
use App\Models\AccountingEntry;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\TreasuryTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
```

- [ ] **Step 2: Dispatch after the transaction commits**

In `sync()`, change:

```php
            $payroll->status = 'synced';
            $payroll->synced_at = now();
            $payroll->save();
        });

        return redirect()->back()->with('status', 'Lot de paie validé et synchronisé en Comptabilité & Trésorerie !');
    }
```

to:

```php
            $payroll->status = 'synced';
            $payroll->synced_at = now();
            $payroll->save();
        });

        SyncPayrollToErpNext::dispatch($payroll);

        return redirect()->back()->with('status', 'Lot de paie validé et synchronisé en Comptabilité & Trésorerie !');
    }
```

(Only these two lines are added; every line inside the `DB::transaction(...)` closure above stays byte-for-byte identical — do not touch the `AccountingEntry::create()` call, the `TreasuryTransaction::create()` call, or the `$payroll->save()`.)

- [ ] **Step 3: Verify existing local behavior is unchanged, end-to-end through the real queue**

Confirm local `QUEUE_CONNECTION`:

Run: `grep QUEUE_CONNECTION .env`
Expected: `QUEUE_CONNECTION=database` (or whatever this environment already uses — either way, the next steps work the same).

```bash
php artisan tinker --execute="
\$pme = \App\Models\User::find(15);
\$payroll = \App\Models\PayrollRun::create([
    'user_id' => \$pme->id,
    'title' => 'Paie test controller dispatch',
    'period_month' => now()->format('Y-m'),
    'payment_date' => now()->toDateString(),
    'payment_method' => 'bank_transfer',
    'payment_account' => 'Compte Principal',
    'total_gross' => 200000,
    'total_cnps' => 15000,
    'total_its' => 8000,
    'total_net' => 177000,
    'status' => 'draft',
]);
\$controller = app(\App\Http\Controllers\PayrollController::class);
\$request = \Illuminate\Http\Request::create('/payroll/'.\$payroll->id.'/sync', 'POST');
\$request->setUserResolver(fn () => \$pme);
\$controller->sync(\$payroll, \$request);
echo 'payroll status: '.\$payroll->fresh()->status.PHP_EOL;
\$sync = \App\Models\PayrollErpNextSync::where('payroll_run_id', \$payroll->id)->first();
echo 'sync row exists (queued): '.(\$sync ? 'yes, status='.\$sync->status : 'NO SYNC ROW').PHP_EOL;
"
```

Expected: `payroll status: synced` (local behavior unaffected), and either `sync row exists (queued): yes, status=pending` (job queued but not yet processed) or `NO SYNC ROW` if `QUEUE_CONNECTION` is not `sync` and no worker has run yet — both are fine at this point, they only confirm the local write succeeded regardless of ERPNext.

Then process the queue once to confirm the job itself completes correctly when picked up:

Run: `php artisan queue:work --once --queue=default`
Expected: one line ending `App\Jobs\SyncPayrollToErpNext ... DONE`.

```bash
php artisan tinker --execute="
\$sync = \App\Models\PayrollErpNextSync::latest()->first();
echo 'status: '.\$sync->status.PHP_EOL;
echo 'accrual: '.\$sync->erpnext_accrual_entry_name.PHP_EOL;
echo 'payment: '.\$sync->erpnext_payment_entry_name.PHP_EOL;
"
```

Expected: `status: synced` with two distinct `ACC-JV-...` names.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/PayrollController.php
git commit -m "feat(erpnext-payroll): dispatch SyncPayrollToErpNext after payroll sync()

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 4: Deploy to production (LWS) and verify live against a real PME

**Files:** none (deployment + verification only)

**Interfaces:** none — end-to-end verification task, covering the 4 manual tests listed in the spec.

- [ ] **Step 1: Push all commits**

```bash
git push origin master
```

- [ ] **Step 2: Deploy on the LWS server via SSH**

```bash
bash deploy.sh
```

Expected: output ends with `=== Déploiement terminé ===`. This runs `php artisan migrate --force`, which creates `payroll_erpnext_syncs` in production, plus the standard cache/OPcache purge.

- [ ] **Step 3: Live verification for the real PME "NotifyMails #69" (user id 69, already provisioned)**

1. Log in as NotifyMails, go to "Paiement des Salaires" → "Nouveau lot de paie", fill in at least one employee with a base salary, submit → confirm it saves as a draft (unchanged existing behavior).
2. Open the payroll run, click the sync/validate action → confirm the existing success message ("Lot de paie validé et synchronisé en Comptabilité & Trésorerie !") still appears.
3. Open ERPNext (`https://sitiame-erp-essai.z.frappe.cloud`), search `Journal Entry`, filter by Company "NotifyMails #69", confirm two new submitted entries appear: one 661/422 for the gross amount, one 422/5711 for the net amount, both dated the payroll's payment date.
4. Try clicking sync again on the same payroll run → confirm the existing local guard still shows "Ce lot de paie est déjà synchronisé !" and no duplicate Journal Entries are created in ERPNext.
5. Repeat step 1-2 for a PME that is NOT provisioned on ERPNext (or one missing `erpnext_company_name`) → confirm the payroll run still syncs locally without error, and (via `php artisan tinker` on the server) the corresponding `payroll_erpnext_syncs` row shows `status: failed` with the expected message.

No commit for this task (deployment/verification only).

---

## Self-Review Notes

**Spec coverage:** All decisions from the spec are covered — dispatch point in `sync()` (Task 3), the two Journal Entry legs with the exact account numbers and amount fallback (Task 2), the fixed `5711` treasury account simplification (Task 2), tracking table matching prior sub-projects' shape with the explicit `$table` fix (Task 1), resilience/guard clauses matching `SyncInvoiceToErpNext`/`SyncStockMovementToErpNext` (Task 2), all 4 manual tests from the spec folded into Task 4 Step 3. Explicitly out-of-scope items (no native HR/Payroll doctypes, no sync at draft creation, no admin UI, no reverse sync) are respected — no task touches `PayrollController::store()`, `PayrollRun`, or `PayrollItem`.

**Placeholder scan:** No TBD/TODO; every step has literal code or literal commands.

**Type consistency:** `SyncPayrollToErpNext::__construct(public PayrollRun $payroll)` in Task 2 matches `SyncPayrollToErpNext::dispatch($payroll)` call in Task 3, where `$payroll` is the same `PayrollRun` instance already loaded in `sync()`. `PayrollErpNextSync` fillable fields defined in Task 1 (`payroll_run_id`, `status`, `erpnext_accrual_entry_name`, `erpnext_payment_entry_name`, `last_error`, `last_synced_at`, `raw_response`) match exactly the fields written in Task 2's `$sync->update([...])` calls. `createJournalEntryForPme()`'s line shape `{account_number, debit, credit}` (Task 2) matches its existing signature in `ErpNextClient.php:613-654` exactly — no new parameters introduced.
