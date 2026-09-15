# Accounting Entry ERPNext Webhook Ingestion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A 2-line Journal Entry created and submitted directly on ERPNext (by a human, not by PME360's own sync jobs) is automatically mirrored as a local `AccountingEntry`; PME360's manual entry-creation form is removed, matching the pattern already shipped for Stock and Invoicing.

**Architecture:** `ErpNextClient::createJournalEntryForPme()` is modified to tag every Journal Entry it creates with `user_remark: 'PME360_SYNC'`. A new ERPNext `Webhook` (`Journal Entry`, `on_submit`) POSTs to a new token-protected route; `ErpNextAccountingEntryWebhookController` re-reads the document, skips anything not shaped like a clean 2-line debit/credit pair or tagged `PME360_SYNC`, resolves the local plan-comptable account numbers, and creates a local `AccountingEntry`. The manual "Nouvelle écriture" form and its route are removed from `/accounting`.

**Tech Stack:** Laravel 13 / PHP 8.4, MySQL, existing `ErpNextClient` (`app/Services/ErpNextClient.php`) — no new PHP packages.

## Global Constraints

- Only ingest a Journal Entry whose `accounts` child table has **exactly 2 rows**, one with `debit_in_account_currency > 0` and `credit_in_account_currency == 0`, the other the exact inverse, with equal amounts — anything else (3+ lines, unbalanced) is ignored with a logged warning, never an error response.
- Skip any Journal Entry whose `user_remark` starts with `PME360_SYNC` — this is how PME360's own sync jobs (Payroll, the admin test dashboard) mark entries they created themselves, to prevent re-ingesting them as duplicates.
- Resolve each ERPNext account name (e.g. `"411-Clients - NOT69"`) to a local plan-comptable number via `Str::before($accountName, '-')`, then verify it exists in `PlanComptableAccount` for that PME (`where('user_id', $pme->id)->where('numero_compte', $code)`) — do **not** reuse the `ValidatesPlanComptableAccount` trait (it depends on an authenticated session's `workspaceUserId()`, which doesn't exist in a webhook request).
- The created `AccountingEntry` uses `document_type: 'ecriture_erpnext'`, `document_reference` = the ERPNext Journal Entry name, `description` = the entry's `user_remark` if present else a generated fallback, `date` = `posting_date`.
- Guard against double-processing: skip if `AccountingEntry::where('document_type', 'ecriture_erpnext')->where('document_reference', $docname)->exists()`.
- Reuse the existing shared webhook token (`config('services.erpnext.webhook_token')`) — no new token needed.
- Do not modify `AccountingEntry` (model), `AccountingDocumentController`/`createEntryFromDocument()` (the OCR pipeline, stays fully local), `editEntry`/`updateEntry`/`destroyEntry`/`storeEntryPayment`/any OCR-retry endpoint, or `bankReconciliation()` (a read-only report, out of scope).
- Only remove `AccountingController::storeEntry()` and its route (`accounting.entries.store`) — every other `AccountingController` method stays untouched.

---

### Task 1: Tag PME360-created Journal Entries with `user_remark: 'PME360_SYNC'`

**Files:**
- Modify: `app/Services/ErpNextClient.php` (inside `createJournalEntryForPme()`)

**Interfaces:**
- Consumes: nothing new.
- Produces: `createJournalEntryForPme()` keeps its exact existing signature and return shape — only the payload it sends to ERPNext gains one more field. Used by Task 2 (the webhook controller's skip-filter reads this field back from ERPNext).

- [ ] **Step 1: Add the marker to the payload**

In `app/Services/ErpNextClient.php`, inside `createJournalEntryForPme()`, change:

```php
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
```

to:

```php
        $payload = [
            'company' => $pme->erpnext_company_name,
            'voucher_type' => $voucherType,
            'posting_date' => $postingDate,
            'accounts' => $accounts,
            'user_remark' => 'PME360_SYNC',
        ];

        if (! empty($referenceNumber)) {
            $payload['cheque_no'] = $referenceNumber;
        }
        if (! empty($referenceDate)) {
            $payload['cheque_date'] = $referenceDate;
        }
```

- [ ] **Step 2: Lint**

Run: `php -l app/Services/ErpNextClient.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Verify via tinker against the real ERPNext trial**

```bash
php artisan tinker --execute="
\$pme = \App\Models\User::find(15);
\$erpNext = app(\App\Services\ErpNextClient::class);
\$result = \$erpNext->createJournalEntryForPme(
    \$pme, 'Journal Entry', now()->toDateString(),
    [['account_number' => '6011000', 'debit' => 1000, 'credit' => 0], ['account_number' => '401', 'debit' => 0, 'credit' => 1000]]
);
echo 'name: '.\$result['name'].PHP_EOL;
echo 'user_remark: '.(\$result['user_remark'] ?? 'MISSING').PHP_EOL;
"
```

Expected: `user_remark: PME360_SYNC` printed back from ERPNext's response, confirming the tag was saved.

- [ ] **Step 4: Commit**

```bash
git add app/Services/ErpNextClient.php
git commit -m "feat(erpnext-accounting): tag PME360-created Journal Entries with user_remark

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 2: `ErpNextAccountingEntryWebhookController` + route

**Files:**
- Create: `app/Http/Controllers/ErpNextAccountingEntryWebhookController.php`
- Modify: `routes/web.php` (add a route next to `webhooks.erpnext.invoicing`)
- Modify: `bootstrap/app.php` (add the new route to the CSRF exemption list)

**Interfaces:**
- Consumes: `ErpNextClient::getDocument(string $doctype, string $name): array` (already exists), `PlanComptableAccount` model (existing), `AccountingEntry` model (existing).
- Produces: route `POST /webhooks/erpnext/accounting-entry`.

- [ ] **Step 1: Create the controller**

Create `app/Http/Controllers/ErpNextAccountingEntryWebhookController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\AccountingEntry;
use App\Models\PlanComptableAccount;
use App\Models\User;
use App\Services\ErpNextClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ErpNextAccountingEntryWebhookController extends Controller
{
    public function handle(Request $request, ErpNextClient $erpNext): JsonResponse
    {
        $expectedToken = trim((string) config('services.erpnext.webhook_token', ''));
        $providedToken = (string) $request->header('X-PME360-Webhook-Token', '');

        if ($expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            abort(403);
        }

        $doctype = (string) $request->input('doctype', '');
        $docname = (string) $request->input('name', '');
        $company = (string) $request->input('company', '');

        if ($doctype !== 'Journal Entry' || $docname === '' || $company === '') {
            return response()->json(['status' => 'ignored', 'reason' => 'payload incomplet ou doctype non géré'], 200);
        }

        $pme = User::where('erpnext_company_name', $company)->first();

        if (! $pme) {
            Log::warning('Webhook ERPNext Accounting reçu pour une company sans PME locale correspondante.', [
                'company' => $company,
                'name' => $docname,
            ]);

            return response()->json(['status' => 'ignored', 'reason' => 'PME introuvable'], 200);
        }

        if (AccountingEntry::where('document_type', 'ecriture_erpnext')->where('document_reference', $docname)->exists()) {
            return response()->json(['status' => 'ignored', 'reason' => 'déjà traité'], 200);
        }

        try {
            $document = $erpNext->getDocument('Journal Entry', $docname);
        } catch (\Throwable $exception) {
            Log::warning('Webhook ERPNext Accounting: échec de relecture du document.', [
                'name' => $docname,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['status' => 'error'], 200);
        }

        if (Str::startsWith((string) ($document['user_remark'] ?? ''), 'PME360_SYNC')) {
            return response()->json(['status' => 'ignored', 'reason' => 'créée par PME360 elle-même'], 200);
        }

        $lines = (array) ($document['accounts'] ?? []);

        if (count($lines) !== 2) {
            return response()->json(['status' => 'ignored', 'reason' => 'pas exactement 2 lignes'], 200);
        }

        $debitLine = null;
        $creditLine = null;

        foreach ($lines as $line) {
            $debit = (float) ($line['debit_in_account_currency'] ?? 0);
            $credit = (float) ($line['credit_in_account_currency'] ?? 0);

            if ($debit > 0 && $credit == 0) {
                $debitLine = $line;
            } elseif ($credit > 0 && $debit == 0) {
                $creditLine = $line;
            }
        }

        if ($debitLine === null || $creditLine === null) {
            return response()->json(['status' => 'ignored', 'reason' => 'lignes non conformes (pas un débit/crédit propre)'], 200);
        }

        $amount = (float) $debitLine['debit_in_account_currency'];

        if (abs($amount - (float) $creditLine['credit_in_account_currency']) > 0.01) {
            return response()->json(['status' => 'ignored', 'reason' => 'débit et crédit ne correspondent pas'], 200);
        }

        $debitAccount = $this->resolveLocalAccount($pme, (string) $debitLine['account']);
        $creditAccount = $this->resolveLocalAccount($pme, (string) $creditLine['account']);

        if ($debitAccount === null || $creditAccount === null) {
            Log::warning('Webhook ERPNext Accounting: compte sans correspondance locale.', [
                'name' => $docname,
                'debit_account' => $debitLine['account'],
                'credit_account' => $creditLine['account'],
            ]);

            return response()->json(['status' => 'ignored', 'reason' => 'compte non trouvé dans le plan comptable local'], 200);
        }

        AccountingEntry::create([
            'user_id' => $pme->id,
            'actor_user_id' => $pme->id,
            'date' => (string) ($document['posting_date'] ?? now()->toDateString()),
            'document_type' => 'ecriture_erpnext',
            'document_reference' => $docname,
            'description' => (string) ($document['user_remark'] ?? ('Écriture ERPNext '.$docname)),
            'debit_account' => $debitAccount,
            'credit_account' => $creditAccount,
            'amount' => $amount,
        ]);

        return response()->json(['status' => 'ok'], 200);
    }

    private function resolveLocalAccount(User $pme, string $erpNextAccountName): ?string
    {
        $code = Str::before($erpNextAccountName, '-');

        $exists = PlanComptableAccount::where('user_id', $pme->id)
            ->where('numero_compte', $code)
            ->exists();

        return $exists ? $code : null;
    }
}
```

- [ ] **Step 2: Add the route**

In `routes/web.php`, change:

```php
Route::post('/webhooks/erpnext/invoicing', [\App\Http\Controllers\ErpNextInvoicingWebhookController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('webhooks.erpnext.invoicing');
```

to:

```php
Route::post('/webhooks/erpnext/invoicing', [\App\Http\Controllers\ErpNextInvoicingWebhookController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('webhooks.erpnext.invoicing');
Route::post('/webhooks/erpnext/accounting-entry', [\App\Http\Controllers\ErpNextAccountingEntryWebhookController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('webhooks.erpnext.accounting-entry');
```

- [ ] **Step 3: Add the CSRF exemption**

In `bootstrap/app.php`, change:

```php
        $middleware->validateCsrfTokens(except: [
            'webhooks/erpnext/stock-movement',
            'webhooks/erpnext/invoicing',
        ]);
```

to:

```php
        $middleware->validateCsrfTokens(except: [
            'webhooks/erpnext/stock-movement',
            'webhooks/erpnext/invoicing',
            'webhooks/erpnext/accounting-entry',
        ]);
```

- [ ] **Step 4: Lint**

Run: `php -l app/Http/Controllers/ErpNextAccountingEntryWebhookController.php && php -l routes/web.php && php -l bootstrap/app.php`
Expected: `No syntax errors detected` for all three.

- [ ] **Step 5: Verify the happy path via tinker against the real ERPNext trial**

Create a real, human-style (untagged) 2-line Journal Entry directly via the API to simulate what a person would create in the ERPNext UI:

```bash
php artisan tinker --execute="
\$pme = \App\Models\User::find(15);
\$erpNext = app(\App\Services\ErpNextClient::class);
\$post = new ReflectionMethod(\$erpNext, 'post');
\$post->setAccessible(true);
\$put = new ReflectionMethod(\$erpNext, 'put');
\$put->setAccessible(true);
\$get = new ReflectionMethod(\$erpNext, 'get');
\$get->setAccessible(true);
\$findAccount = new ReflectionMethod(\$erpNext, 'findAccountByNumber');
\$findAccount->setAccessible(true);
\$debitAcc = \$findAccount->invoke(\$erpNext, \$pme->erpnext_company_name, '6011000');
\$creditAcc = \$findAccount->invoke(\$erpNext, \$pme->erpnext_company_name, '401');
\$created = \$post->invoke(\$erpNext, '/api/resource/'.rawurlencode('Journal Entry'), [
    'company' => \$pme->erpnext_company_name,
    'voucher_type' => 'Journal Entry',
    'posting_date' => now()->toDateString(),
    'accounts' => [
        ['account' => \$debitAcc, 'debit_in_account_currency' => 5000, 'credit_in_account_currency' => 0],
        ['account' => \$creditAcc, 'debit_in_account_currency' => 0, 'credit_in_account_currency' => 5000],
    ],
]);
\$name = \$created['name'];
\$put->invoke(\$erpNext, '/api/resource/'.rawurlencode('Journal Entry').'/'.rawurlencode(\$name), ['docstatus' => 1]);
echo 'NAME='.\$name.PHP_EOL;

\$request = \Illuminate\Http\Request::create('/webhooks/erpnext/accounting-entry', 'POST', [
    'doctype' => 'Journal Entry', 'name' => \$name, 'company' => \$pme->erpnext_company_name,
]);
\$request->headers->set('X-PME360-Webhook-Token', config('services.erpnext.webhook_token'));
\$controller = app(\App\Http\Controllers\ErpNextAccountingEntryWebhookController::class);
\$response = \$controller->handle(\$request, \$erpNext);
echo \$response->getContent().PHP_EOL;

\$entry = \App\Models\AccountingEntry::where('document_reference', \$name)->first();
echo 'local entry: '.(\$entry ? \$entry->debit_account.'/'.\$entry->credit_account.' = '.\$entry->amount : 'NOT CREATED').PHP_EOL;
"
```

Expected: `{"status":"ok"}`, and `local entry: 6011000/401 = 5000.00` (or similar, showing the real resolved account codes and amount).

- [ ] **Step 6: Verify the PME360_SYNC skip filter**

```bash
php artisan tinker --execute="
\$pme = \App\Models\User::find(15);
\$erpNext = app(\App\Services\ErpNextClient::class);
\$result = \$erpNext->createJournalEntryForPme(
    \$pme, 'Journal Entry', now()->toDateString(),
    [['account_number' => '6011000', 'debit' => 2000, 'credit' => 0], ['account_number' => '401', 'debit' => 0, 'credit' => 2000]]
);
\$name = \$result['name'];

\$request = \Illuminate\Http\Request::create('/webhooks/erpnext/accounting-entry', 'POST', [
    'doctype' => 'Journal Entry', 'name' => \$name, 'company' => \$pme->erpnext_company_name,
]);
\$request->headers->set('X-PME360-Webhook-Token', config('services.erpnext.webhook_token'));
\$controller = app(\App\Http\Controllers\ErpNextAccountingEntryWebhookController::class);
\$response = \$controller->handle(\$request, \$erpNext);
echo \$response->getContent().PHP_EOL;
echo 'local entry exists (should NOT): '.(\App\Models\AccountingEntry::where('document_reference', \$name)->exists() ? 'YES - BUG' : 'no, correct').PHP_EOL;
"
```

Expected: `{"status":"ignored","reason":"créée par PME360 elle-même"}`, and `local entry exists (should NOT): no, correct`.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ErpNextAccountingEntryWebhookController.php routes/web.php bootstrap/app.php
git commit -m "feat(erpnext-accounting): add webhook endpoint to ingest manual ERPNext journal entries

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 3: Remove `AccountingController::storeEntry()` and the manual entry form

**Files:**
- Modify: `app/Http/Controllers/AccountingController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/accounting.blade.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing new — every other `AccountingController` method and route stays exactly as-is.

- [ ] **Step 1: Locate and remove `storeEntry()`**

Run: `grep -n "public function storeEntry" app/Http/Controllers/AccountingController.php`

Read that method's full body starting from that line (it runs roughly 105-200+ in the current file, ending at the next `public function` after it, e.g. `showEntry` or similar — read the file to find the exact closing `}` of `storeEntry()` before the next method declaration). Delete the entire method (from `public function storeEntry(Request $request)` through its matching closing `}`), leaving every method before and after it untouched.

- [ ] **Step 2: Remove the route**

In `routes/web.php`, delete this line:

```php
        Route::post('/accounting/entries', [AccountingController::class, 'storeEntry'])->name('accounting.entries.store');
```

- [ ] **Step 3: Replace the manual entry form in `accounting.blade.php`**

The form (and its dedicated read-only fallback message and the `<script>` block that only makes sense when the form exists) spans from the line containing `@if($canEditAccounting)` through the line containing the closing `</script>` right before the unconditional `<div class="row g-4 mb-4">` that contains "Écritures totales". Before editing, confirm the exact current line numbers (they may have shifted slightly since this plan was written) by running:

```bash
grep -n '@if($canEditAccounting)\|id="moteur-ecritures"\|Mode Consultation Uniquement\|Écritures totales\|createEntrySubmitBtn' resources/views/accounting.blade.php
```

This should show: the `@if($canEditAccounting)` line, the `id="moteur-ecritures"` line just after it, the "Mode Consultation Uniquement" line (inside the `@else` branch), a line for `createEntrySubmitBtn` (inside the `<script>` block, near the end of the section to delete), and the "Écritures totales" line (the first line that must survive, unconditional, right after the block being deleted).

Then delete every line from `@if($canEditAccounting)` (inclusive) through the closing `</script>` tag that immediately precedes the "Écritures totales" `<div class="row g-4 mb-4">` block (inclusive of the `</script>` line, exclusive of the "Écritures totales" line) — for example, using `sed` with the confirmed line numbers from the grep above (replace `START` and `END` with the real numbers found):

```bash
sed -i 'START,ENDd' resources/views/accounting.blade.php
```

Then insert the replacement content at that same location (immediately before the "Écritures totales" block):

```blade
    <div class="row g-4 mb-4">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4"><i data-feather="info" class="me-2" style="width: 20px; height: 20px;"></i>Écritures manuelles</h5>
                    <p class="text-muted mb-0">Les écritures manuelles (débit/crédit) se créent désormais directement dans ERPNext. Elles apparaissent automatiquement ici une fois enregistrées là-bas. Les documents scannés (factures, reçus) continuent d'être traités par l'OCR de PME360 comme avant.</p>
                </div>
            </div>
        </div>
    </div>
```

(This uses `Illuminate\Support\Str::before`-style plain Blade, no `@if`/`@else`/`@endif` needed anymore since the message is the same for everyone regardless of role.)

- [ ] **Step 4: Verify no leftover references**

Run: `grep -n "moteur-ecritures\|accounting.entries.store\|createEntrySubmitBtn\|calculateAmounts\|documentType\b" resources/views/accounting.blade.php`
Expected: no matches (if any appear, they are leftover fragments that must be removed — re-check the deletion boundaries from Step 3).

Run: `grep -rn "accounting.entries.store" resources/ routes/ app/`
Expected: no matches anywhere in the codebase (mirrors the same check already done for Stock's `stock.movements.store` and Invoicing's removed routes in earlier sub-projects).

- [ ] **Step 5: Lint**

Run: `php -l app/Http/Controllers/AccountingController.php && php -l routes/web.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 6: Confirm the removed route is gone**

Run: `php artisan route:list --name=accounting.entries.store`
Expected: no matching routes.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/AccountingController.php routes/web.php resources/views/accounting.blade.php
git commit -m "feat(erpnext-accounting): remove local manual journal entry creation (ERPNext is now the entry point)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 4: Create the ERPNext Webhook via the API

**Files:** none (one-off API call against the ERPNext trial)

**Interfaces:**
- Consumes: the `Webhook` doctype REST API, same pattern used for Stock/Invoicing (explicit `name` field required).

- [ ] **Step 1: Create the `Journal Entry` on_submit webhook**

```bash
php artisan tinker --execute="
\$erpNext = app(\App\Services\ErpNextClient::class);
\$post = new ReflectionMethod(\$erpNext, 'post');
\$post->setAccessible(true);
\$token = config('services.erpnext.webhook_token');
\$result = \$post->invoke(\$erpNext, '/api/resource/Webhook', [
    'name' => 'Journal Entry soumise vers PME360',
    'webhook_doctype' => 'Journal Entry',
    'webhook_docevent' => 'on_submit',
    'request_url' => 'https://sitiame-capital.com/webhooks/erpnext/accounting-entry',
    'request_method' => 'POST',
    'request_structure' => 'JSON',
    'timeout' => 5,
    'enabled' => 1,
    'webhook_json' => '{\"doctype\": \"{{ doc.doctype }}\", \"name\": \"{{ doc.name }}\", \"company\": \"{{ doc.company }}\"}',
    'webhook_headers' => [
        ['key' => 'X-PME360-Webhook-Token', 'value' => \$token],
        ['key' => 'Content-Type', 'value' => 'application/json'],
    ],
]);
echo 'created: '.\$result['name'].PHP_EOL;
"
```

- [ ] **Step 2: Verify it exists and is enabled**

```bash
php artisan tinker --execute="
\$erpNext = app(\App\Services\ErpNextClient::class);
\$get = new ReflectionMethod(\$erpNext, 'get');
\$get->setAccessible(true);
\$row = \$get->invoke(\$erpNext, '/api/resource/Webhook/Journal Entry soumise vers PME360');
echo 'enabled: '.\$row['enabled'].' doctype: '.\$row['webhook_doctype'].' event: '.\$row['webhook_docevent'].PHP_EOL;
"
```

Expected: `enabled: 1 doctype: Journal Entry event: on_submit`.

No commit for this task (server-side ERPNext configuration only).

---

### Task 5: Deploy to production (LWS) and verify live end-to-end

**Files:** none (deployment + verification only)

**Interfaces:** none — end-to-end verification task, covering the manual tests listed in the spec.

- [ ] **Step 1: Push all commits**

```bash
git push origin master
```

- [ ] **Step 2: Deploy on the LWS server via SSH**

```bash
bash deploy.sh
```

Expected: output ends with `=== Déploiement terminé ===`. No new env variable needed — the webhook token is already set from the Stock sub-project and reused here.

- [ ] **Step 3: Live verification for the real PME "NotifyMails #69"**

1. Confirm `/accounting` no longer shows the "Nouvelle écriture"/"Génération d'écritures" form, and shows the new informational message instead.
2. On ERPNext, create and submit a 2-line `Journal Entry` for Company "NotifyMails #69" using two real SYSCOHADA leaf accounts (not group accounts — reuse the lesson from the Payroll sub-project: verify each account's "Groupe ?" is "Non" first).
3. Within a few seconds, refresh `/accounting` on PME360 → confirm a new entry appears with `document_type: ecriture_erpnext`, correct accounts/amount.
4. Trigger a real Payroll sync (`SyncPayrollToErpNext`, e.g. by validating a payroll run for NotifyMails as usual) → confirm its 2 Journal Entries do **not** create duplicate `AccountingEntry` rows (there should be exactly the 2 entries `PayrollController::sync()` already creates locally itself — `facture_vente`/`paie`-style document types, not `ecriture_erpnext`).
5. Confirm the webhook security guard: `curl -X POST https://sitiame-capital.com/webhooks/erpnext/accounting-entry -H "Content-Type: application/json" -d '{"doctype":"Journal Entry","name":"x","company":"x"}'` (no token) → expect `403`.
6. Confirm that editing/deleting an existing `AccountingEntry` (any origin) still works normally on `/accounting`.

No commit for this task (deployment/verification only).

---

## Self-Review Notes

**Spec coverage:** All decisions covered — `user_remark` marker (Task 1), the webhook with its 2-line filter and account resolution (Task 2), the `PME360_SYNC` skip guard verified live against a real tagged entry (Task 2 Step 6), idempotent creation guard (Task 2's `handle()`, checked before re-reading the document), form/route removal scoped exactly to `storeEntry()`/`accounting.entries.store` while explicitly leaving `editEntry`/`updateEntry`/`destroyEntry`/`storeEntryPayment`/OCR endpoints untouched (Task 3), `bankReconciliation()` never touched anywhere in this plan. All 6 manual tests from the spec are folded into Task 5 Step 3.

**Placeholder scan:** No TBD/TODO; every step has literal code or literal commands. Task 3 Step 3's `sed -i 'START,ENDd'` with `START`/`END` placeholders is a deliberate, explicit hand-off — the plan cannot know the exact current line numbers of a 700+ line pre-existing Blade file without risking drift between writing and execution, so it instructs the one grep command needed to find them precisely at execution time, rather than guessing numbers that could silently delete the wrong content. This mirrors the `PASTE_..._HERE` execution-time hand-offs already accepted in the Stock and Invoicing webhook plans.

**Type consistency:** `resolveLocalAccount(User $pme, string $erpNextAccountName): ?string` (Task 2) matches its two call sites in the same file's `handle()` method exactly. `AccountingEntry::create([...])` field names (`user_id`, `actor_user_id`, `date`, `document_type`, `document_reference`, `description`, `debit_account`, `credit_account`, `amount`) match the model's existing `$fillable` array exactly (verified against `app/Models/AccountingEntry.php` during design). `getDocument('Journal Entry', $docname)` matches the existing method signature from `ErpNextClient.php` (built for the Stock sub-project, reused unchanged here).
