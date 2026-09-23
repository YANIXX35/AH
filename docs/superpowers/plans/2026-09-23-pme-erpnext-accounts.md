# PME ERPNext Accounts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every PME gets a real, sandboxed ERPNext login automatically at signup — restricted to their own Company's data and to 11 relevant modules — instead of no ERPNext access at all today.

**Architecture:** All new logic lives in PME360's `ErpNextClient::provisionCompanyForPme()` (PHP), calling ERPNext's existing generic REST API (`/api/resource/User`, `/api/resource/User Permission`) with credentials already configured — no new ERPNext-side endpoint. A new Frappe `Role` ("PME Client") and read permission grants on 3 `sitiame_core` doctypes are a one-time ERPNext-side setup (doctype JSON + a one-off Role-creation script, same convention already used all session for DB-level ERPNext customization).

**Tech Stack:** Laravel 13 / PHP 8.4 (PME360), Frappe/ERPNext v16 REST API (`sitiame_core`).

## Global Constraints

- Never give a PME account the `System Manager` role.
- `User Permission(allow="Company", for_value=<their company>)` is mandatory on every PME account — the single mechanism preventing cross-PME data leakage.
- Default password: `SITIAME2026!` (same constant convention as `ErpNextPmeRegistrationWebhookController::DEFAULT_PASSWORD`).
- Never reset the password of an already-existing account (staff or PME) — the retroactive backfill only ever creates brand-new ERPNext Users for PME `Company` records that don't have one yet.
- Failures creating the ERPNext User/User Permission must never block Company/Warehouse/Tax provisioning (existing resilience pattern: `try/catch` + `Log::warning`, non-fatal).
- The 11 allowed tiles: Financement, Scoring, Vente (Selling), Achat (Buying), Stock, Comptabilité (Accounting), Subcontracting, Abonnement, Production (Manufacturing), Projets (Projects), Actifs (Assets). Everything else on the Desk is hidden.

---

### Task 1: ERPNext — add "PME Client" read permission to the 3 custom doctypes

**Files:**
- Modify: `sitiame_core/sitiame_core/doctype/financing_dossier/financing_dossier.json`
- Modify: `sitiame_core/sitiame_core/doctype/credit_scoring_dossier/credit_scoring_dossier.json`
- Modify: `sitiame_core/sitiame_core/doctype/subscription_payment/subscription_payment.json`

Work in a local clone of `https://github.com/YANIXX35/sitiame_core`. All paths below are relative to the repo root.

**Interfaces:**
- Produces: a `Role` named `PME Client` with `read: 1` (only) on `Financing Dossier`, `Credit Scoring Dossier`, `Subscription Payment`. Consumed by Task 2 (the role is assigned to every new PME's ERPNext `User`).

- [ ] **Step 1: Add the permission row to each doctype's `permissions` array**

In `sitiame_core/sitiame_core/doctype/financing_dossier/financing_dossier.json`, the `"permissions"` array currently reads:

```json
 "permissions": [
  {
   "create": 1,
   "delete": 1,
   "email": 0,
   "print": 1,
   "read": 1,
   "report": 1,
   "role": "System Manager",
   "share": 0,
   "write": 1
  }
 ],
```

Change it to:

```json
 "permissions": [
  {
   "create": 1,
   "delete": 1,
   "email": 0,
   "print": 1,
   "read": 1,
   "report": 1,
   "role": "System Manager",
   "share": 0,
   "write": 1
  },
  {
   "create": 0,
   "delete": 0,
   "email": 0,
   "print": 1,
   "read": 1,
   "report": 1,
   "role": "PME Client",
   "share": 0,
   "write": 0
  }
 ],
```

Apply the exact same change (add the identical `PME Client` permission block, only `read`/`report`/`print` set) to `sitiame_core/sitiame_core/doctype/credit_scoring_dossier/credit_scoring_dossier.json` and `sitiame_core/sitiame_core/doctype/subscription_payment/subscription_payment.json` — each file's `permissions` array follows the same shape, just append the same new block.

- [ ] **Step 2: Verify all three files are valid JSON**

```bash
python -c "import json; [json.load(open(f)) for f in ['sitiame_core/sitiame_core/doctype/financing_dossier/financing_dossier.json','sitiame_core/sitiame_core/doctype/credit_scoring_dossier/credit_scoring_dossier.json','sitiame_core/sitiame_core/doctype/subscription_payment/subscription_payment.json']]"
```

Expected: no output (no parse errors).

- [ ] **Step 3: Commit**

```bash
git add sitiame_core/sitiame_core/doctype/financing_dossier/financing_dossier.json sitiame_core/sitiame_core/doctype/credit_scoring_dossier/credit_scoring_dossier.json sitiame_core/sitiame_core/doctype/subscription_payment/subscription_payment.json
git commit -m "feat(pme-accounts): grant PME Client role read-only access to their own dossiers"
```

---

### Task 2: ERPNext — deploy, migrate, and confirm the "PME Client" Role exists

**Files:** none (deployment + one-off verification only).

- [ ] **Step 1: Push and pull into both containers**

```bash
git push origin master
```

```bash
ssh -i ~/.ssh/id_ed25519_sitiame_vps root@31.207.36.253 '
set -e
docker exec sitiame-prod-backend-1 bash -c "cd /home/frappe/frappe-bench/apps/sitiame_core && git pull origin master"
docker exec sitiame-prod-frontend-1 bash -c "cd /home/frappe/frappe-bench/apps/sitiame_core && git pull origin master"
'
```

- [ ] **Step 2: Migrate (doctype permissions changed) and clear cache**

```bash
ssh -i ~/.ssh/id_ed25519_sitiame_vps root@31.207.36.253 '
docker exec sitiame-prod-backend-1 bench --site erp.sitiame-capital.com migrate
docker exec sitiame-prod-backend-1 bench --site erp.sitiame-capital.com clear-cache
'
```

`bench migrate` auto-creates any `Role` referenced in a doctype's permissions that doesn't already exist — this is how `PME Client` gets created, no separate script needed. Verify it explicitly anyway:

- [ ] **Step 3: Verify the Role and permissions landed correctly**

Write locally, then deploy via the established one-off pattern (`scp` to `/tmp/`, `docker cp` into `sitiame-prod-backend-1:/home/frappe/frappe-bench/apps/sitiame_core/sitiame_core/tmp_verify_pme_role.py`, `bench --site erp.sitiame-capital.com execute sitiame_core.tmp_verify_pme_role.run`, then `rm -f` the temp file in the container):

```python
import frappe


def run():
	role_exists = frappe.db.exists("Role", "PME Client")
	perms = {}
	for dt in ["Financing Dossier", "Credit Scoring Dossier", "Subscription Payment"]:
		row = frappe.db.get_value(
			"Custom DocPerm", {"parent": dt, "role": "PME Client"}, ["read", "write", "create"], as_dict=True
		) or frappe.db.get_value(
			"DocPerm", {"parent": dt, "role": "PME Client"}, ["read", "write", "create"], as_dict=True
		)
		perms[dt] = row
	return {"role_exists": role_exists, "perms": perms}
```

Expected: `role_exists` truthy, and each doctype shows `{"read": 1, "write": 0, "create": 0}` for `PME Client`.

---

### Task 3: PME360 — extend `provisionCompanyForPme()` to create the ERPNext User + User Permission

**Files:**
- Modify: `app/Services/ErpNextClient.php:252-331` (the `provisionCompanyForPme` method and its return, plus two new private helpers)

**Interfaces:**
- Consumes: `$this->post(string $path, array $payload): array` and `$this->get(string $path): array` (existing private helpers, `ErpNextClient.php:41-80`), `User::$email`, `User::$name`.
- Produces: `provisionCompanyForPme()` now also creates an ERPNext `User` (role `PME Client` + the standard staff bundle) and a `User Permission` scoping it to the PME's `Company`. Never throws on failure of this part (wrapped in its own `try/catch`), so Task 4's tests must assert Company provisioning still succeeds even when the User/User Permission calls fail.

- [ ] **Step 1: Add the two new private helpers**

Add these two methods to `app/Services/ErpNextClient.php`, right after `provisionCompanyForPme()` (i.e. immediately before `createAndSubmitSalesInvoiceForPme`, around line 332):

```php
    private const PME_DEFAULT_PASSWORD = 'SITIAME2026!';

    /**
     * Every module tile the PME account should NOT see is computed here,
     * not hardcoded: fetch every Desktop Icon label that currently exists
     * on ERPNext and subtract the 11 allowed ones, so a future tile added
     * to the Desk stays hidden by default for PME accounts without a code
     * change here.
     *
     * @return array<int, string>
     */
    private function hiddenDesktopIconLabelsForPme(): array
    {
        // These are the DocType's raw `label` values as stored (verified
        // against the live DB), NOT the French display text: every native
        // ERPNext tile's label is still the untranslated English name
        // ("Selling", "Accounting", ...) -- only the /desk display goes
        // through __() at render time. The REST API returns the raw field,
        // so matching must use these exact strings, not the on-screen text.
        $allowed = [
            'Financement', 'Scoring', 'Selling', 'Buying', 'Stock',
            'Accounting', 'Subcontracting', 'Abonnement',
            'Manufacturing', 'Projects', 'Assets',
        ];

        $query = http_build_query([
            'fields' => json_encode(['label']),
            'limit_page_length' => 0,
        ]);

        try {
            $response = Http::withHeaders(['Authorization' => $this->authHeader()])
                ->timeout($this->timeout())
                ->get($this->baseUrl().'/api/resource/Desktop Icon?'.$query);
        } catch (\Throwable) {
            return [];
        }

        if ($response->failed()) {
            return [];
        }

        $labels = array_column((array) ($response->json('data') ?? []), 'label');

        return array_values(array_diff($labels, $allowed));
    }

    private function createPmeErpNextUser(User $pme, string $companyName): void
    {
        $existing = $this->get('/api/resource/User/'.rawurlencode($pme->email));
        if (! empty($existing)) {
            return;
        }

        $hiddenDesktopIcons = $this->hiddenDesktopIconLabelsForPme();
        $hiddenSidebarItems = ['erp-financial-ranking', 'Scoring 360 Settings'];

        $roles = array_map(fn (string $role) => ['role' => $role], [
            'PME Client',
            'Sales User', 'Sales Manager',
            'Purchase Manager', 'Purchase Master Manager',
            'Stock Manager', 'Stock User', 'Item Manager',
            'Accounts Manager',
        ]);

        $this->post('/api/resource/User', [
            'email' => $pme->email,
            'first_name' => $pme->name ?: $pme->email,
            'send_welcome_email' => 0,
            'enabled' => 1,
            'user_type' => 'System User',
            'new_password' => self::PME_DEFAULT_PASSWORD,
            'roles' => $roles,
            'sitiame_hidden_desktop_icons' => json_encode($hiddenDesktopIcons),
            'sitiame_hidden_sidebar_items' => json_encode($hiddenSidebarItems),
        ]);

        $this->post('/api/resource/User Permission', [
            'user' => $pme->email,
            'allow' => 'Company',
            'for_value' => $companyName,
        ]);
    }
```

- [ ] **Step 2: Call it at the end of `provisionCompanyForPme()`**

In `app/Services/ErpNextClient.php`, replace the current `return` statement of `provisionCompanyForPme()` (lines 325-330):

```php
        return [
            'company' => $resolvedCompanyName,
            'warehouse' => $warehouseName,
            'tax_template' => $taxTemplateName,
            'income_account' => $incomeAccount,
        ];
    }
```

with:

```php
        try {
            $this->createPmeErpNextUser($pme, $resolvedCompanyName);
        } catch (\Throwable $exception) {
            Log::warning('Échec de la création du compte ERPNext pour la PME #'.$pme->id.': '.$exception->getMessage());
        }

        return [
            'company' => $resolvedCompanyName,
            'warehouse' => $warehouseName,
            'tax_template' => $taxTemplateName,
            'income_account' => $incomeAccount,
        ];
    }
```

Add the `Log` facade import if not already present — check the top of the file:

```bash
grep -n "^use Illuminate\\\\Support\\\\Facades\\\\Log;" app/Services/ErpNextClient.php
```

If that prints nothing, add `use Illuminate\Support\Facades\Log;` alongside the existing `use Illuminate\Support\Facades\Http;` import (line 12).

- [ ] **Step 3: Verify the file has no syntax errors**

```bash
docker run --rm -v "C:\Users\yaniss\Desktop\application:/app" -w /app php:8.4-cli-alpine php -l app/Services/ErpNextClient.php
```

Expected: `No syntax errors detected in app/Services/ErpNextClient.php`.

- [ ] **Step 4: Commit**

```bash
git add app/Services/ErpNextClient.php
git commit -m "feat(pme-accounts): create a sandboxed ERPNext login for every provisioned PME"
```

---

### Task 4: PME360 — test the new provisioning behavior

**Files:**
- Modify: `tests/Unit/ErpNextClientTest.php` (add two new test methods after `test_provision_company_for_pme_resolves_accounts_by_number_not_name`, i.e. after line 205)

**Interfaces:**
- Consumes: `ErpNextClient::provisionCompanyForPme()` (Task 3), the existing `makePme()` helper and `Http::fake` pattern already used in this file (lines 156-205).

- [ ] **Step 1: Write the two failing tests**

Insert these two methods into `tests/Unit/ErpNextClientTest.php`, right after `test_provision_company_for_pme_resolves_accounts_by_number_not_name` (after the closing `}` on line 205, before `test_find_account_by_number_throws_when_account_missing`):

```php
    public function test_provision_company_for_pme_creates_erpnext_user_and_user_permission(): void
    {
        Http::fake([
            'https://erp.test.local/api/resource/Company/*' => function ($request) {
                return $request->method() === 'PUT'
                    ? Http::response(['data' => ['name' => 'Test SARL #1']], 200)
                    : Http::response([], 404);
            },
            'https://erp.test.local/api/resource/Company' => Http::response(['data' => ['name' => 'Test SARL #1']], 200),
            'https://erp.test.local/api/resource/Account?*' => Http::response(['data' => [['name' => 'X']]], 200),
            'https://erp.test.local/api/resource/Warehouse/*' => Http::response([], 404),
            'https://erp.test.local/api/resource/Warehouse' => Http::response(['data' => ['name' => 'Magasin principal - TES1']], 200),
            'https://erp.test.local/api/resource/Sales%20Taxes%20and%20Charges%20Template/*' => Http::response([], 404),
            'https://erp.test.local/api/resource/Sales%20Taxes%20and%20Charges%20Template' => Http::response(['data' => ['name' => 'TVA 18% - TES1']], 200),
            'https://erp.test.local/api/resource/Desktop%20Icon?*' => Http::response(['data' => [
                ['label' => 'Financement'], ['label' => 'Scoring'], ['label' => 'Organisation'], ['label' => 'RH et Paie'],
            ]], 200),
            'https://erp.test.local/api/resource/User/*' => Http::response([], 404),
            'https://erp.test.local/api/resource/User' => Http::response(['data' => ['name' => 'pme@test.local']], 200),
            'https://erp.test.local/api/resource/User%20Permission' => Http::response(['data' => ['name' => 'UP-1']], 200),
        ]);

        $pme = $this->makePme(['email' => 'pme@test.local', 'erpnext_company_name' => null]);
        $client = new ErpNextClient;

        $client->provisionCompanyForPme($pme);

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://erp.test.local/api/resource/User') {
                return false;
            }
            $body = $request->data();

            return $body['email'] === 'pme@test.local'
                && $body['new_password'] === 'SITIAME2026!'
                && in_array(['role' => 'PME Client'], $body['roles'], true)
                && ! in_array(['role' => 'System Manager'], $body['roles'], true)
                && str_contains($body['sitiame_hidden_desktop_icons'], 'Organisation')
                && ! str_contains($body['sitiame_hidden_desktop_icons'], 'Financement');
        });

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://erp.test.local/api/resource/User%20Permission') {
                return false;
            }
            $body = $request->data();

            return $body['user'] === 'pme@test.local'
                && $body['allow'] === 'Company'
                && $body['for_value'] === 'Test SARL #1';
        });
    }

    public function test_provision_company_for_pme_still_returns_company_when_user_creation_fails(): void
    {
        Http::fake([
            'https://erp.test.local/api/resource/Company/*' => function ($request) {
                return $request->method() === 'PUT'
                    ? Http::response(['data' => ['name' => 'Test SARL #1']], 200)
                    : Http::response([], 404);
            },
            'https://erp.test.local/api/resource/Company' => Http::response(['data' => ['name' => 'Test SARL #1']], 200),
            'https://erp.test.local/api/resource/Account?*' => Http::response(['data' => [['name' => 'X']]], 200),
            'https://erp.test.local/api/resource/Warehouse/*' => Http::response([], 404),
            'https://erp.test.local/api/resource/Warehouse' => Http::response(['data' => ['name' => 'Magasin principal - TES1']], 200),
            'https://erp.test.local/api/resource/Sales%20Taxes%20and%20Charges%20Template/*' => Http::response([], 404),
            'https://erp.test.local/api/resource/Sales%20Taxes%20and%20Charges%20Template' => Http::response(['data' => ['name' => 'TVA 18% - TES1']], 200),
            'https://erp.test.local/api/resource/Desktop%20Icon?*' => Http::response(['data' => []], 200),
            'https://erp.test.local/api/resource/User/*' => Http::response([], 404),
            'https://erp.test.local/api/resource/User' => Http::response(['exception' => 'DuplicateEntryError'], 409),
        ]);

        $pme = $this->makePme(['email' => 'pme@test.local', 'erpnext_company_name' => null]);
        $client = new ErpNextClient;

        // Must not throw, even though the User creation call fails (409).
        $result = $client->provisionCompanyForPme($pme);

        $this->assertSame('Test SARL #1', $result['company']);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
docker run --rm -v "C:\Users\yaniss\Desktop\application:/app" -w /app php:8.4-cli-alpine sh -c "vendor/bin/phpunit --filter test_provision_company_for_pme_creates_erpnext_user_and_user_permission tests/Unit/ErpNextClientTest.php"
```

Expected: FAIL (the `User`/`User Permission` requests are never sent yet — `Task 3` not implemented, or if implemented already, run this before Task 3's code exists to confirm red first if following strict TDD order; if Task 3 is already done, skip straight to Step 3).

- [ ] **Step 3: Run the tests to verify they pass**

```bash
docker run --rm -v "C:\Users\yaniss\Desktop\application:/app" -w /app php:8.4-cli-alpine sh -c "vendor/bin/phpunit tests/Unit/ErpNextClientTest.php"
```

Expected: all tests in the file pass (the two new ones plus every pre-existing one, confirming no regression).

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/ErpNextClientTest.php
git commit -m "test(pme-accounts): cover ERPNext user/permission creation and its failure resilience"
```

---

### Task 5: PME360 — retroactive backfill command for already-registered PME

**Files:**
- Create: `app/Console/Commands/ProvisionErpNextAccessForExistingPmes.php`

**Interfaces:**
- Consumes: `ErpNextClient::provisionCompanyForPme()` (Task 3) — re-runs it for every existing PME `User`. `provisionCompanyForPme()` already short-circuits Company creation when `erpnext_company_name` is already set (existing behavior, unaffected) and `createPmeErpNextUser()` (Task 3) already skips creation if the ERPNext `User` already exists (checked via `GET /api/resource/User/<email>`), so this command is safe to re-run.

- [ ] **Step 1: Write the command**

Create `app/Console/Commands/ProvisionErpNextAccessForExistingPmes.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ErpNextClient;
use Illuminate\Console\Command;

/**
 * One-off backfill: every PME registered before the automatic ERPNext
 * account creation existed already has a Company/Warehouse/Tax template
 * (erpnext_company_name set) but no ERPNext login. This replays the same
 * provisioning call, which now also creates the User/User Permission --
 * it never touches an account that already exists (staff or PME), and
 * never resets a password, since createPmeErpNextUser() only inserts a
 * new User when none exists yet for that email.
 */
class ProvisionErpNextAccessForExistingPmes extends Command
{
    protected $signature = 'pme:provision-erpnext-access';

    protected $description = 'Crée un compte ERPNext pour chaque PME déjà provisionnée qui n\'en a pas encore un';

    public function handle(ErpNextClient $erpNext): int
    {
        if (! $erpNext->enabled()) {
            $this->error('ERPNext non configuré.');

            return self::FAILURE;
        }

        $pmes = User::whereNotNull('erpnext_company_name')->get();
        $this->info("{$pmes->count()} PME(s) déjà provisionnée(s) à traiter.");

        $ok = 0;
        $failed = 0;

        foreach ($pmes as $pme) {
            try {
                $erpNext->provisionCompanyForPme($pme);
                $ok++;
                $this->line("OK: {$pme->email} ({$pme->erpnext_company_name})");
            } catch (\Throwable $exception) {
                $failed++;
                $this->warn("Échec: {$pme->email}: {$exception->getMessage()}");
            }
        }

        $this->info("Terminé : {$ok} succès, {$failed} échec(s).");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 2: Verify the file has no syntax errors**

```bash
docker run --rm -v "C:\Users\yaniss\Desktop\application:/app" -w /app php:8.4-cli-alpine php -l app/Console/Commands/ProvisionErpNextAccessForExistingPmes.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/Console/Commands/ProvisionErpNextAccessForExistingPmes.php
git commit -m "feat(pme-accounts): add retroactive backfill command for existing PMEs"
```

---

### Task 6: Deploy PME360 and run the backfill

**Files:** none (deployment only).

- [ ] **Step 1: Push and deploy**

```bash
git push origin master
```

Deploy per the established manual process (CI/CD is currently broken — see project memory): SSH into LWS and run `git pull && composer install --no-dev ... && php artisan config:cache && php artisan route:cache`.

- [ ] **Step 2: Confirm with the user before running the backfill**

The backfill creates real ERPNext logins for every already-registered PME and is not easily reversible in bulk (would require manually deleting each created User). Show the user the count of PMEs with `erpnext_company_name` set (`php artisan tinker --execute="echo App\Models\User::whereNotNull('erpnext_company_name')->count();"`) and get explicit go-ahead before running it, per this project's standing rule on hard-to-reverse actions.

- [ ] **Step 3: Run the backfill**

```bash
php artisan pme:provision-erpnext-access
```

- [ ] **Step 4: Manual verification in sandbox**

Pick one PME from the command's "OK" output, log into `https://erp.sitiame-capital.com` with that PME's email + `SITIAME2026!`, and confirm:
- Only the 11 allowed tiles appear on `/desk`.
- The Scoring sidebar shows only their own Credit Scoring Dossier link (not "Classement financier" or "Scoring 360 Settings").
- Any list view with a Company filter (e.g. Sales Invoice list) shows only that PME's own Company's records, not other PMEs'.
