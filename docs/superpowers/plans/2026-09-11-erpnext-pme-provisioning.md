# Provisionnement automatique ERPNext par PME Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** At PME registration, automatically provision a dedicated ERPNext Company (SYSCOHADA chart of accounts, default warehouse, 18% tax template) via a queued Laravel Job, without ever blocking or delaying the registration response.

**Architecture:** `RegisterController::store()` dispatches `ProvisionErpNextCompanyForPme` right after `User::create()`. The Job calls a new `ErpNextClient::provisionCompanyForPme()` method that creates the Company (with `chart_of_accounts: "Syscohada - Plan Comptable"`), a Warehouse, and a Sales Taxes and Charges Template scoped to that Company, then resolves the Company's own 4431/7061 accounts and saves all 4 identifiers onto the `User` row.

**Tech Stack:** Laravel 13, PHP 8.4, MySQL, Laravel queues (`ShouldQueue`, database driver — already used in production for OCR per existing `.env` convention), Laravel `Http` facade via the existing `ErpNextClient`.

## Global Constraints

- Registration must never fail or slow down because of ERPNext — spec "Décisions verrouillées".
- No automatic retry in v1; a failed Job stays in Laravel's standard `failed_jobs` table, retriable manually via `php artisan queue:retry` — spec "Décisions verrouillées".
- Must use `chart_of_accounts: "Syscohada - Plan Comptable"` explicitly — passing only `country` does NOT load this chart (verified empirically: produces the generic "Standard" chart instead) — spec "Découverte technique vérifiée empiriquement".
- The existing `/admin/erpnext-test` dashboard and its shared "Sitiame Capital" company must remain untouched and working — spec "Ce qui ne change pas".
- `erpnext_customer_id` (existing column) is NOT reused or removed by this work — spec "Remarque de cohérence".
- No automated PHPUnit run is possible on this machine (PHP 8.2 installed vs 8.4 required) — every task's verification step is manual/tinker-based, per the established pattern in this session.

---

## Task 1: Database schema for provisioning fields

**Files:**
- Create: `database/migrations/2026_09_11_100000_add_erpnext_provisioning_fields_to_users_table.php`
- Modify: `app/Models/User.php:55` (add 4 fields to the `#[Fillable([...])]` array, right after `'erpnext_customer_id',`)

**Interfaces:**
- Produces: `users.erpnext_company_name`, `users.erpnext_warehouse`, `users.erpnext_tax_template`, `users.erpnext_income_account` (all nullable strings) — consumed by Task 3 (`ErpNextClient::provisionCompanyForPme()` writes to them via the Job in Task 4).

- [ ] **Step 1: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('erpnext_company_name')->nullable()->after('erpnext_customer_id');
            $table->string('erpnext_warehouse')->nullable()->after('erpnext_company_name');
            $table->string('erpnext_tax_template')->nullable()->after('erpnext_warehouse');
            $table->string('erpnext_income_account')->nullable()->after('erpnext_tax_template');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'erpnext_company_name',
                'erpnext_warehouse',
                'erpnext_tax_template',
                'erpnext_income_account',
            ]);
        });
    }
};
```

Save as `database/migrations/2026_09_11_100000_add_erpnext_provisioning_fields_to_users_table.php`.

- [ ] **Step 2: Add the 4 fields to `User`'s fillable list**

In `app/Models/User.php`, line 55 currently reads:
```php
    'erpnext_customer_id',
```

Change it to:
```php
    'erpnext_customer_id',
    'erpnext_company_name',
    'erpnext_warehouse',
    'erpnext_tax_template',
    'erpnext_income_account',
```

- [ ] **Step 3: Run the migration**

Run: `php artisan migrate`
Expected: the new migration listed as `Migrated`, no errors.

- [ ] **Step 4: Manual verification**

Run: `php artisan tinker --execute="echo Schema::hasColumn('users', 'erpnext_company_name') && Schema::hasColumn('users', 'erpnext_income_account') ? 'ok' : 'fail';"`
Expected: prints `ok`.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_09_11_100000_add_erpnext_provisioning_fields_to_users_table.php app/Models/User.php
git commit -m "feat(erpnext-provisioning): add per-PME ERPNext identifiers to users table

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 2: `ErpNextClient::provisionCompanyForPme()`

**Files:**
- Modify: `app/Services/ErpNextClient.php` (add one public method; no changes to existing methods)

**Interfaces:**
- Consumes: the existing private `get(string $path): array` and `post(string $path, array $payload): array` helpers already defined in this class (lines 38-78), `ErpNextApiException` (already imported).
- Produces: `ErpNextClient::provisionCompanyForPme(User $pme): array` returning `['company' => string, 'warehouse' => string, 'tax_template' => string, 'income_account' => string]`. Throws `ErpNextApiException` if any sub-step fails. Consumed by Task 4's Job.

- [ ] **Step 1: Add the method**

Add this public method to `app/Services/ErpNextClient.php`, right after `findOrCreateCustomer()` (i.e., after line 117, before `findOrCreateItem()`):

```php
    /**
     * @return array{company: string, warehouse: string, tax_template: string, income_account: string}
     */
    public function provisionCompanyForPme(User $pme): array
    {
        $baseName = $pme->company_name ?: $pme->name;
        $companyName = trim($baseName).' #'.$pme->id;
        $abbr = strtoupper(Str::limit(preg_replace('/[^A-Za-z]/', '', $baseName) ?: 'PME', 3, '')).$pme->id;

        $company = $this->post('/api/resource/Company', [
            'company_name' => $companyName,
            'abbr' => $abbr,
            'default_currency' => 'XOF',
            'country' => 'Ivory Coast',
            'chart_of_accounts' => 'Syscohada - Plan Comptable',
        ]);

        $resolvedCompanyName = (string) ($company['name'] ?? '');
        if ($resolvedCompanyName === '') {
            throw new ErpNextApiException('ERPNext n\'a pas renvoyé de nom de société après création.');
        }

        $warehouse = $this->post('/api/resource/Warehouse', [
            'warehouse_name' => 'Magasin principal',
            'company' => $resolvedCompanyName,
        ]);
        $warehouseName = (string) ($warehouse['name'] ?? '');
        if ($warehouseName === '') {
            throw new ErpNextApiException('ERPNext n\'a pas renvoyé de nom d\'entrepôt après création.');
        }

        $incomeAccount = $this->findAccountByNumber($resolvedCompanyName, '7061');
        $taxAccount = $this->findAccountByNumber($resolvedCompanyName, '4431');

        $taxTemplate = $this->post('/api/resource/'.rawurlencode('Sales Taxes and Charges Template'), [
            'title' => 'TVA 18%',
            'company' => $resolvedCompanyName,
            'taxes' => [
                [
                    'charge_type' => 'On Net Total',
                    'account_head' => $taxAccount,
                    'rate' => 18,
                ],
            ],
        ]);
        $taxTemplateName = (string) ($taxTemplate['name'] ?? '');
        if ($taxTemplateName === '') {
            throw new ErpNextApiException('ERPNext n\'a pas renvoyé de nom de gabarit de TVA après création.');
        }

        return [
            'company' => $resolvedCompanyName,
            'warehouse' => $warehouseName,
            'tax_template' => $taxTemplateName,
            'income_account' => $incomeAccount,
        ];
    }

    private function findAccountByNumber(string $company, string $accountNumber): string
    {
        $query = http_build_query([
            'filters' => json_encode([
                ['company', '=', $company],
                ['account_number', '=', $accountNumber],
            ]),
            'fields' => json_encode(['name']),
            'limit_page_length' => 1,
        ]);

        $accounts = $this->get('/api/resource/Account?'.$query);
        $account = $accounts[0] ?? null;

        if (empty($account['name'])) {
            throw new ErpNextApiException("Compte $accountNumber introuvable pour la société $company.");
        }

        return (string) $account['name'];
    }
```

- [ ] **Step 2: Lint the file**

Run: `php -l app/Services/ErpNextClient.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Manual verification against the real ERPNext trial**

Run:
```bash
php artisan tinker --execute="
\$u = App\Models\User::whereNotNull('company_name')->first();
\$c = new App\Services\ErpNextClient();
try {
    \$result = \$c->provisionCompanyForPme(\$u);
    print_r(\$result);
} catch (\Throwable \$e) {
    echo 'ERROR: ' . \$e->getMessage() . PHP_EOL;
}
"
```
Expected: prints an array with 4 non-empty string values (`company`, `warehouse`, `tax_template`, `income_account`). Note: this creates a real Company in the ERPNext trial for this test user — acceptable in this trial environment per the spec's accepted risk, same as prior manual tests this session.

- [ ] **Step 4: Commit**

```bash
git add app/Services/ErpNextClient.php
git commit -m "feat(erpnext-provisioning): add provisionCompanyForPme to ErpNextClient

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 3: `ProvisionErpNextCompanyForPme` Job

**Files:**
- Create: `app/Jobs/ProvisionErpNextCompanyForPme.php`

**Interfaces:**
- Consumes: `ErpNextClient::provisionCompanyForPme(User $pme): array` (Task 2), `User` model with the 4 fillable fields from Task 1.
- Produces: `ProvisionErpNextCompanyForPme::dispatch(User $pme)` — consumed by Task 4's controller change.

- [ ] **Step 1: Write the Job**

```php
<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\ErpNextClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProvisionErpNextCompanyForPme implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public User $pme)
    {
    }

    public function handle(ErpNextClient $erpNext): void
    {
        if ($this->pme->erpnext_company_name) {
            return;
        }

        if (! $erpNext->enabled()) {
            Log::info('ERPNext non configuré : provisionnement ignoré pour la PME #'.$this->pme->id);

            return;
        }

        $result = $erpNext->provisionCompanyForPme($this->pme);

        $this->pme->update([
            'erpnext_company_name' => $result['company'],
            'erpnext_warehouse' => $result['warehouse'],
            'erpnext_tax_template' => $result['tax_template'],
            'erpnext_income_account' => $result['income_account'],
        ]);
    }
}
```

Save as `app/Jobs/ProvisionErpNextCompanyForPme.php`. Note: `handle()` deliberately lets any `ErpNextApiException`/`\Throwable` from `provisionCompanyForPme()` propagate uncaught — Laravel's queue worker catches it, marks the Job as failed, and records it in the `failed_jobs` table, per the spec's "no custom retry" decision.

- [ ] **Step 2: Lint the file**

Run: `php -l app/Jobs/ProvisionErpNextCompanyForPme.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Manual verification — idempotence guard and disabled-client path**

Run:
```bash
php artisan tinker --execute="
\$u = App\Models\User::factory()->make(['erpnext_company_name' => 'Already Set - PME']);
\$u->id = 999999;
\$job = new App\Jobs\ProvisionErpNextCompanyForPme(\$u);
\$job->handle(new App\Services\ErpNextClient());
echo 'no exception thrown (idempotence guard worked)';
"
```
Expected: prints `no exception thrown (idempotence guard worked)` — confirms the Job returns early without calling the API when `erpnext_company_name` is already set.

- [ ] **Step 4: Commit**

```bash
git add app/Jobs/ProvisionErpNextCompanyForPme.php
git commit -m "feat(erpnext-provisioning): add ProvisionErpNextCompanyForPme queued job

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 4: Wire the Job into registration

**Files:**
- Modify: `app/Http/Controllers/RegisterController.php` (add 1 import, 1 dispatch line)

**Interfaces:**
- Consumes: `ProvisionErpNextCompanyForPme::dispatch(User $pme)` (Task 3).

- [ ] **Step 1: Add the import**

In `app/Http/Controllers/RegisterController.php`, the imports currently read (lines 5-11):
```php
use App\Mail\AccountCreatedMail;
use App\Models\BillingPlan;
use App\Models\BillingSubscription;
use App\Models\EnterpriseLicense;
use App\Models\KycDocument;
use App\Models\PlanComptableAccount;
use App\Models\User;
```

Add `use App\Jobs\ProvisionErpNextCompanyForPme;` as a new line right after `use App\Mail\AccountCreatedMail;` (alphabetical grouping — `Jobs` before `Models`):
```php
use App\Jobs\ProvisionErpNextCompanyForPme;
use App\Mail\AccountCreatedMail;
use App\Models\BillingPlan;
```

- [ ] **Step 2: Dispatch the Job after registration**

In the same file, lines 111-119 currently read:
```php
            $created = User::create([
                ...$validated,
                'password' => Hash::make($validated['password']),
                'enterprise_license_id' => $enterpriseLicenseId,
                'kyc_status' => 'submitted',
                'kyc_submitted_at' => now(),
            ]);

            PlanComptableAccount::seedDefaultsFor($created->id);
```

Change to:
```php
            $created = User::create([
                ...$validated,
                'password' => Hash::make($validated['password']),
                'enterprise_license_id' => $enterpriseLicenseId,
                'kyc_status' => 'submitted',
                'kyc_submitted_at' => now(),
            ]);

            PlanComptableAccount::seedDefaultsFor($created->id);
            ProvisionErpNextCompanyForPme::dispatch($created);
```

- [ ] **Step 3: Lint the file**

Run: `php -l app/Http/Controllers/RegisterController.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Manual end-to-end verification**

Register a new test PME through PME360's actual registration form (or via the equivalent existing test route/fixture used elsewhere in this codebase for registration testing, if one exists — otherwise use the live `/register` form in a browser).

Immediately after submitting, confirm the registration completed normally (same redirect/behavior as before this change — no added delay, no new errors on screen).

Then run:
```bash
php artisan queue:work --once
```
Expected: processes the queued `ProvisionErpNextCompanyForPme` Job. If `QUEUE_CONNECTION=sync` locally (immediate execution, no queue to work), this step can be skipped — the Job already ran synchronously during registration in that case.

- [ ] **Step 5: Verify the 4 fields were populated**

Run:
```bash
php artisan tinker --execute="
\$u = App\Models\User::orderByDesc('id')->first();
echo 'company: ' . \$u->erpnext_company_name . PHP_EOL;
echo 'warehouse: ' . \$u->erpnext_warehouse . PHP_EOL;
echo 'tax_template: ' . \$u->erpnext_tax_template . PHP_EOL;
echo 'income_account: ' . \$u->erpnext_income_account . PHP_EOL;
"
```
Expected: all 4 fields non-empty, matching real ERPNext resource names (e.g. `company` ending in `#<id>`, `warehouse` ending in `- <ABBR>`).

- [ ] **Step 6: Verify registration is not blocked when ERPNext is unreachable**

Temporarily set `ERPNEXT_API_KEY=invalid` in `.env`, run `php artisan config:clear`, register a second test PME.

Expected: registration succeeds normally (same redirect as always). Then run `php artisan queue:failed` (or `php artisan queue:work --once` if using sync/immediate dispatch, watching for a logged exception instead of a crash) — expect to see the `ProvisionErpNextCompanyForPme` Job recorded as failed, with the ERPNext error message, while the new user account itself exists and can log in normally.

Restore the correct `ERPNEXT_API_KEY` afterward and run `php artisan config:clear` again.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/RegisterController.php
git commit -m "feat(erpnext-provisioning): dispatch ERPNext provisioning on PME registration

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```
