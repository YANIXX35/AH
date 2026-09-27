# Correction bugs comptables Facturation (paiement + annulation) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every encaissement synced from ERPNext produce a real treasury-side `AccountingEntry`, and make every invoice cancellation produce reversal (`annulation_facture`) entries for the sale/TVA lines it originally posted.

**Architecture:** Extract the existing, production-proven ERPNext-account→local-account resolution logic (`ErpNextAccountingEntryWebhookController::resolveLocalAccount()`) into a new public `ErpNextClient::resolveLocalAccountCode()` method, reused by `ErpNextInvoicingWebhookController::handlePayment()` to resolve a real `treasury_account_code` from ERPNext's `Payment Entry.paid_to` field instead of the current hardcoded `null`. Separately, add a `reverseSaleAccountingEntries()` private method to `InvoiceService`, called from `cancelInvoice()`, which is safe to add unconditionally because `cancelInvoice()` already refuses to run on any invoice with `amount_paid > 0`.

**Tech Stack:** Laravel 13, PHP 8.4 (production; local dev is PHP 8.2, PHPUnit cannot run locally — verify via `php artisan tinker` and manual browser/production testing).

## Global Constraints

- No change to the account codes `'411'`, `'701'`, `'4431'` themselves, or to how they're chosen — out of scope (see spec's "Hors périmètre").
- No multi-currency/exchange-rate handling — out of scope.
- Only `Payment Entry` documents with `payment_type === 'Receive'` are handled for treasury account resolution — `handlePayment()`'s existing loop already filters to `reference_doctype === 'Sales Invoice'`, so `Pay`-type (supplier payment) documents should never reach this code path in practice, but the check must be explicit, not assumed.
- `resolveLocalAccountCode()`'s extracted behavior must be byte-for-byte identical to the current `ErpNextAccountingEntryWebhookController::resolveLocalAccount()` — this logic is already verified working in production (confirmed live for `6011000`/`5711000`/`4111000` accounts), so this is a pure extract-method refactor, not a rewrite.

---

### Task 1: Extract `resolveLocalAccountCode()` into `ErpNextClient`

**Files:**
- Modify: `app/Services/ErpNextClient.php`
- Modify: `app/Http/Controllers/ErpNextAccountingEntryWebhookController.php`

**Interfaces:**
- Produces: `ErpNextClient::resolveLocalAccountCode(User $pme, string $erpNextAccountName): ?string` — resolves an ERPNext account name (e.g. `"5211 - Banque - ABC"`) to a local `PlanComptableAccount.numero_compte` string, or `null` if no match exists (tries the raw code before the first `-`, then that code padded with `'000'` if it's exactly 4 characters long). This is consumed by Task 2.

- [ ] **Step 1: Read the current implementation to copy exactly**

Confirm the exact current body (already read in this session, re-verify unchanged):

```bash
grep -n "private function resolveLocalAccount" -A 22 app/Http/Controllers/ErpNextAccountingEntryWebhookController.php
```

Expected output matches:
```php
    private function resolveLocalAccount(User $pme, string $erpNextAccountName): ?string
    {
        $code = Str::before($erpNextAccountName, '-');

        if (PlanComptableAccount::where('user_id', $pme->id)->where('numero_compte', $code)->exists()) {
            return $code;
        }

        // Le plan comptable local numérote certains comptes feuilles sur 7
        // chiffres (ex: 6011000) là où le plan importé sur ERPNext s'arrête
        // à 4 (6011) — écart de granularité constaté empiriquement, pas un
        // compte différent. On tente donc aussi le code local équivalent.
        if (strlen($code) === 4) {
            $paddedCode = $code.'000';

            if (PlanComptableAccount::where('user_id', $pme->id)->where('numero_compte', $paddedCode)->exists()) {
                return $paddedCode;
            }
        }

        return null;
    }
```

- [ ] **Step 2: Add `PlanComptableAccount` import and the new method to `ErpNextClient`**

In `app/Services/ErpNextClient.php`, find the import block (currently lines 5-13):

```php
use App\Exceptions\ErpNextApiException;
use App\Models\Invoice;
use App\Models\InvoiceErpNextCustomer;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
```

Replace with (adding `PlanComptableAccount`, alphabetically among the `App\Models` group):

```php
use App\Exceptions\ErpNextApiException;
use App\Models\Invoice;
use App\Models\InvoiceErpNextCustomer;
use App\Models\InvoicePayment;
use App\Models\PlanComptableAccount;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
```

Then find `getDocument()` (currently around line 939-942):

```php
    public function getDocument(string $doctype, string $name): array
    {
        return $this->get('/api/resource/'.rawurlencode($doctype).'/'.rawurlencode($name));
    }
```

Use the Edit tool to insert the new method immediately after it (before `findOrCreateSportMemberGroup`):

`old_string`:
```php
    public function getDocument(string $doctype, string $name): array
    {
        return $this->get('/api/resource/'.rawurlencode($doctype).'/'.rawurlencode($name));
    }

    public function findOrCreateSportMemberGroup(User $pme): string
```

`new_string`:
```php
    public function getDocument(string $doctype, string $name): array
    {
        return $this->get('/api/resource/'.rawurlencode($doctype).'/'.rawurlencode($name));
    }

    public function resolveLocalAccountCode(User $pme, string $erpNextAccountName): ?string
    {
        $code = Str::before($erpNextAccountName, '-');

        if (PlanComptableAccount::where('user_id', $pme->id)->where('numero_compte', $code)->exists()) {
            return $code;
        }

        // Le plan comptable local numérote certains comptes feuilles sur 7
        // chiffres (ex: 6011000) là où le plan importé sur ERPNext s'arrête
        // à 4 (6011) — écart de granularité constaté empiriquement, pas un
        // compte différent. On tente donc aussi le code local équivalent.
        if (strlen($code) === 4) {
            $paddedCode = $code.'000';

            if (PlanComptableAccount::where('user_id', $pme->id)->where('numero_compte', $paddedCode)->exists()) {
                return $paddedCode;
            }
        }

        return null;
    }

    public function findOrCreateSportMemberGroup(User $pme): string
```

- [ ] **Step 3: Lint-check `ErpNextClient.php`**

```bash
php -l app/Services/ErpNextClient.php
```
Expected: `No syntax errors detected in app/Services/ErpNextClient.php`

- [ ] **Step 4: Update `ErpNextAccountingEntryWebhookController` to use the shared method**

In `app/Http/Controllers/ErpNextAccountingEntryWebhookController.php`, find the two call sites (currently lines 93-94):

```php
        $debitAccount = $this->resolveLocalAccount($pme, (string) $debitLine['account']);
        $creditAccount = $this->resolveLocalAccount($pme, (string) $creditLine['account']);
```

Replace with:

```php
        $debitAccount = $erpNext->resolveLocalAccountCode($pme, (string) $debitLine['account']);
        $creditAccount = $erpNext->resolveLocalAccountCode($pme, (string) $creditLine['account']);
```

(`$erpNext` is already the `ErpNextClient $erpNext` parameter of the enclosing `handle(Request $request, ErpNextClient $erpNext)` method — no new parameter needed here.)

Then delete the now-unused private method. Find (currently lines 121-142):

```php

    private function resolveLocalAccount(User $pme, string $erpNextAccountName): ?string
    {
        $code = Str::before($erpNextAccountName, '-');

        if (PlanComptableAccount::where('user_id', $pme->id)->where('numero_compte', $code)->exists()) {
            return $code;
        }

        // Le plan comptable local numérote certains comptes feuilles sur 7
        // chiffres (ex: 6011000) là où le plan importé sur ERPNext s'arrête
        // à 4 (6011) — écart de granularité constaté empiriquement, pas un
        // compte différent. On tente donc aussi le code local équivalent.
        if (strlen($code) === 4) {
            $paddedCode = $code.'000';

            if (PlanComptableAccount::where('user_id', $pme->id)->where('numero_compte', $paddedCode)->exists()) {
                return $paddedCode;
            }
        }

        return null;
    }
}
```

Replace with just:
```php
}
```

(This removes the method and leaves the class's closing brace — the `use App\Models\PlanComptableAccount;` and `use Illuminate\Support\Str;` imports at the top of this controller become unused after this deletion; leave them in place. Removing unused imports is out of scope for this fix and adds no value — PHP does not error or warn on unused `use` statements.)

- [ ] **Step 5: Lint-check the controller**

```bash
php -l app/Http/Controllers/ErpNextAccountingEntryWebhookController.php
```
Expected: `No syntax errors detected`

- [ ] **Step 6: Regression-verify the extraction via tinker**

This reproduces the exact account-resolution check already validated live in production during the Accounting webhook sub-project, to confirm the extraction changed nothing:

```bash
php artisan tinker --execute="
\$pme = App\Models\User::where('erpnext_company_name', '!=', null)->first();
if (!\$pme) { echo 'NO PME WITH erpnext_company_name FOUND'; exit; }
\$client = new App\Services\ErpNextClient();
echo 'PME: ' . \$pme->company_name . PHP_EOL;
echo 'Resolve \"6011 - Achats - X\": ' . (\$client->resolveLocalAccountCode(\$pme, '6011 - Achats - X') ?? 'NULL') . PHP_EOL;
echo 'Resolve \"9999 - Inexistant - X\": ' . (\$client->resolveLocalAccountCode(\$pme, '9999 - Inexistant - X') ?? 'NULL') . PHP_EOL;
"
```
Expected: the first resolution returns either `6011` or `6011000` (whichever exists in that PME's local chart of accounts — matches what Task 1's spec confirmed empirically for this codebase), the second returns `NULL` (no such account exists).

- [ ] **Step 7: Commit**

```bash
git add app/Services/ErpNextClient.php app/Http/Controllers/ErpNextAccountingEntryWebhookController.php
git commit -m "$(cat <<'EOF'
refactor(erpnext): extract resolveLocalAccountCode into ErpNextClient

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Resolve a real treasury account for synced payments

**Files:**
- Modify: `app/Http/Controllers/ErpNextInvoicingWebhookController.php`

**Interfaces:**
- Consumes: `ErpNextClient::resolveLocalAccountCode(User $pme, string $erpNextAccountName): ?string` from Task 1.
- Produces: `handlePayment()` now passes a real (or `null`, with a logged warning) `treasury_account_code` to `InvoiceService::recordPayment()`.

- [ ] **Step 1: Update `handle()` to pass `ErpNextClient` and `$pme` into `handlePayment()`**

Find (currently line 62-63):

```php
            } elseif ($doctype === 'Payment Entry') {
                $this->handlePayment($docname, $document, $invoiceService);
```

Replace with:

```php
            } elseif ($doctype === 'Payment Entry') {
                $this->handlePayment($pme, $docname, $document, $invoiceService, $erpNext);
```

(`$erpNext` is already the `ErpNextClient $erpNext` parameter of the enclosing `handle(Request $request, ErpNextClient $erpNext, InvoiceService $invoiceService)` method; `$pme` is already resolved earlier in the same method at line 33.)

- [ ] **Step 2: Update `handlePayment()`'s signature and resolve the treasury account**

Find the current method (lines 136-167):

```php
    /**
     * @param  array<string, mixed>  $document
     */
    private function handlePayment(string $docname, array $document, InvoiceService $invoiceService): void
    {
        foreach ((array) ($document['references'] ?? []) as $reference) {
            if (($reference['reference_doctype'] ?? '') !== 'Sales Invoice') {
                continue;
            }

            $sync = InvoiceErpNextSync::where('erpnext_invoice_name', (string) ($reference['reference_name'] ?? ''))->first();

            if (! $sync || ! $sync->invoice) {
                continue;
            }

            $invoiceService->recordPayment(
                $sync->invoice,
                [
                    'amount' => (float) ($reference['allocated_amount'] ?? 0),
                    'paid_at' => Carbon::parse((string) ($document['posting_date'] ?? now()->toDateString())),
                    'method' => (string) ($document['mode_of_payment'] ?? null) ?: null,
                    'reference' => $docname,
                    'treasury_account_code' => null,
                    'treasury_transaction_id' => null,
                    'notes' => null,
                ],
                $sync->invoice->user_id,
                true
            );
        }
    }
```

Replace with:

```php
    /**
     * @param  array<string, mixed>  $document
     */
    private function handlePayment(User $pme, string $docname, array $document, InvoiceService $invoiceService, ErpNextClient $erpNext): void
    {
        $treasuryAccountCode = null;

        if (($document['payment_type'] ?? '') === 'Receive' && ! empty($document['paid_to'])) {
            $treasuryAccountCode = $erpNext->resolveLocalAccountCode($pme, (string) $document['paid_to']);

            if ($treasuryAccountCode === null) {
                Log::warning('Webhook ERPNext Invoicing: compte de trésorerie sans correspondance locale.', [
                    'name' => $docname,
                    'paid_to' => $document['paid_to'],
                ]);
            }
        }

        foreach ((array) ($document['references'] ?? []) as $reference) {
            if (($reference['reference_doctype'] ?? '') !== 'Sales Invoice') {
                continue;
            }

            $sync = InvoiceErpNextSync::where('erpnext_invoice_name', (string) ($reference['reference_name'] ?? ''))->first();

            if (! $sync || ! $sync->invoice) {
                continue;
            }

            $invoiceService->recordPayment(
                $sync->invoice,
                [
                    'amount' => (float) ($reference['allocated_amount'] ?? 0),
                    'paid_at' => Carbon::parse((string) ($document['posting_date'] ?? now()->toDateString())),
                    'method' => (string) ($document['mode_of_payment'] ?? null) ?: null,
                    'reference' => $docname,
                    'treasury_account_code' => $treasuryAccountCode,
                    'treasury_transaction_id' => null,
                    'notes' => null,
                ],
                $sync->invoice->user_id,
                true
            );
        }
    }
```

(`User` is already imported at the top of this file — line 7, `use App\Models\User;` — no new import needed for the type-hint. `ErpNextClient` is already imported — line 8.)

- [ ] **Step 3: Lint-check**

```bash
php -l app/Http/Controllers/ErpNextInvoicingWebhookController.php
```
Expected: `No syntax errors detected`

- [ ] **Step 4: Manual verification via Reflection (local)**

Since this method is `private` and driven by a real ERPNext webhook payload, verify with a hand-built `$document` array simulating what ERPNext's real `Payment Entry` webhook would send, invoked via Reflection (same technique already used successfully earlier this session for the Sport subscription-tagging feature):

```bash
php artisan tinker --execute="
\$pme = App\Models\User::where('erpnext_company_name', '!=', null)->first();
if (!\$pme) { echo 'NO PME FOUND'; exit; }

\$sync = App\Models\InvoiceErpNextSync::whereNotNull('erpnext_invoice_name')->with('invoice')->first();
if (!\$sync || !\$sync->invoice) { echo 'NO SYNCED INVOICE FOUND — cannot test end to end, but resolution logic already verified in Task 1'; exit; }

\$erpNext = new App\Services\ErpNextClient();
\$invoiceService = app(App\Domain\Invoicing\InvoiceService::class);
\$controller = new App\Http\Controllers\ErpNextInvoicingWebhookController();

\$document = [
    'payment_type' => 'Receive',
    'paid_to' => '5711 - Caisse - TEST',
    'posting_date' => now()->toDateString(),
    'mode_of_payment' => 'Cash',
    'references' => [
        ['reference_doctype' => 'Sales Invoice', 'reference_name' => \$sync->erpnext_invoice_name, 'allocated_amount' => 100],
    ],
];

\$countBefore = App\Models\AccountingEntry::where('document_type', 'encaissement_facture')->count();

\$method = new ReflectionMethod(\$controller, 'handlePayment');
\$method->setAccessible(true);
\$method->invoke(\$controller, \$pme, 'TEST-PAY-0001', \$document, \$invoiceService, \$erpNext);

\$countAfter = App\Models\AccountingEntry::where('document_type', 'encaissement_facture')->count();
echo 'AccountingEntry encaissement_facture before=' . \$countBefore . ' after=' . \$countAfter . PHP_EOL;
"
```
Expected: `after` is greater than `before` by 1 **if** `'5711'` (or its `000`-padded form) exists in that PME's local chart of accounts; otherwise `after === before` and a `Log::warning` line appears in `storage/logs/laravel.log` mentioning `compte de trésorerie sans correspondance locale` — both outcomes are correct behavior, confirm which one occurred and that it matches the account's actual presence (`App\Models\PlanComptableAccount::where('user_id', $pme->id)->where('numero_compte', '5711')->exists()`).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ErpNextInvoicingWebhookController.php
git commit -m "$(cat <<'EOF'
fix(invoicing): resolve real treasury account for synced ERPNext payments

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Reversal entries on invoice cancellation

**Files:**
- Modify: `app/Domain/Invoicing/InvoiceService.php`

**Interfaces:**
- Produces: `InvoiceService::cancelInvoice()` now creates `annulation_facture`-typed reversal `AccountingEntry` rows and is idempotent against being called twice on the same invoice.

- [ ] **Step 1: Add the idempotency guard and call the new reversal method**

Find the current method (lines 242-263):

```php
    public function cancelInvoice(Invoice $invoice, string $reason, int $actorUserId, bool $skipErpNextSync = false): void
    {
        if ($invoice->status === 'paid' || (float) $invoice->amount_paid > 0) {
            throw new \InvalidArgumentException('Facture déjà réglée (partiellement ou totalement) : impossible d\'annuler, établir un avoir.');
        }

        $invoice->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_reason' => $reason,
        ]);

        TreasuryAudit::log($invoice->user_id, 'invoicing.invoice.cancelled', $invoice, [
            'invoice_number' => $invoice->invoice_number,
            'reason' => $reason,
            'actor_user_id' => $actorUserId,
        ]);

        if (! $skipErpNextSync) {
            SyncInvoiceCancellationToErpNext::dispatch($invoice);
        }
    }
```

Replace with:

```php
    public function cancelInvoice(Invoice $invoice, string $reason, int $actorUserId, bool $skipErpNextSync = false): void
    {
        if ($invoice->status === 'cancelled') {
            return;
        }

        if ($invoice->status === 'paid' || (float) $invoice->amount_paid > 0) {
            throw new \InvalidArgumentException('Facture déjà réglée (partiellement ou totalement) : impossible d\'annuler, établir un avoir.');
        }

        $invoice->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_reason' => $reason,
        ]);

        $this->reverseSaleAccountingEntries($invoice, $actorUserId);

        TreasuryAudit::log($invoice->user_id, 'invoicing.invoice.cancelled', $invoice, [
            'invoice_number' => $invoice->invoice_number,
            'reason' => $reason,
            'actor_user_id' => $actorUserId,
        ]);

        if (! $skipErpNextSync) {
            SyncInvoiceCancellationToErpNext::dispatch($invoice);
        }
    }

    private function reverseSaleAccountingEntries(Invoice $invoice, int $actorUserId): void
    {
        $entries = AccountingEntry::where('document_type', 'facture_vente')
            ->where('document_reference', $invoice->invoice_number)
            ->get();

        foreach ($entries as $entry) {
            AccountingEntry::create([
                'user_id' => $invoice->user_id,
                'actor_user_id' => $actorUserId,
                'date' => now()->format('Y-m-d'),
                'document_type' => 'annulation_facture',
                'document_reference' => $invoice->invoice_number,
                'description' => 'Annulation facture '.$invoice->invoice_number.' — contrepassation',
                'debit_account' => $entry->credit_account,
                'credit_account' => $entry->debit_account,
                'amount' => $entry->amount,
            ]);
        }
    }
```

- [ ] **Step 2: Lint-check**

```bash
php -l app/Domain/Invoicing/InvoiceService.php
```
Expected: `No syntax errors detected`

- [ ] **Step 3: Manual verification via tinker (local)**

```bash
php artisan tinker --execute="
DB::beginTransaction();
try {
    \$pme = App\Models\User::first();
    \$service = app(App\Domain\Invoicing\InvoiceService::class);

    \$invoice = \$service->createInvoice(
        \$pme->id, \$pme->id, 'Client Test Annulation', null, null, null,
        now(), now()->addDays(30),
        [['description' => 'Article test', 'quantity' => 1, 'unit_price' => 10000]],
        18.0, null, 'XOF', true
    );

    echo 'Invoice created: ' . \$invoice->invoice_number . ' tax_amount=' . \$invoice->tax_amount . PHP_EOL;

    \$beforeCount = App\Models\AccountingEntry::where('document_reference', \$invoice->invoice_number)->count();
    echo 'AccountingEntry rows before cancel: ' . \$beforeCount . PHP_EOL;

    \$service->cancelInvoice(\$invoice, 'Test annulation', \$pme->id, true);

    \$afterCount = App\Models\AccountingEntry::where('document_reference', \$invoice->invoice_number)->count();
    \$reversals = App\Models\AccountingEntry::where('document_reference', \$invoice->invoice_number)->where('document_type', 'annulation_facture')->get();
    echo 'AccountingEntry rows after cancel: ' . \$afterCount . PHP_EOL;
    echo 'Reversal rows: ' . \$reversals->count() . PHP_EOL;
    foreach (\$reversals as \$r) {
        echo '  debit=' . \$r->debit_account . ' credit=' . \$r->credit_account . ' amount=' . \$r->amount . PHP_EOL;
    }

    // idempotency check
    \$service->cancelInvoice(\$invoice, 'Test annulation deux fois', \$pme->id, true);
    \$afterSecondCall = App\Models\AccountingEntry::where('document_reference', \$invoice->invoice_number)->count();
    echo 'AccountingEntry rows after calling cancelInvoice() a second time: ' . \$afterSecondCall . ' (must equal ' . \$afterCount . ')' . PHP_EOL;
} finally {
    DB::rollBack();
    echo 'Rolled back — no test data persisted.' . PHP_EOL;
}
"
```
Expected:
- `AccountingEntry rows before cancel: 2` (one `facture_vente` sale line debit 411/credit 701, one TVA line debit 411/credit 4431 — since tax rate 18% > 0).
- `AccountingEntry rows after cancel: 4` (the 2 originals + 2 reversals).
- `Reversal rows: 2`, each with debit/credit swapped relative to its matching original (e.g. one row `debit=701 credit=411`, one row `debit=4431 credit=411`).
- After the second `cancelInvoice()` call (idempotency check): row count unchanged (still 4), confirming the early-return guard works.

- [ ] **Step 4: Commit**

```bash
git add app/Domain/Invoicing/InvoiceService.php
git commit -m "$(cat <<'EOF'
fix(invoicing): reverse sale/tax accounting entries on invoice cancellation

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Deploy and live verification

**Files:** none (deployment + manual verification only).

- [ ] **Step 1: Push to GitHub**

```bash
git push origin master
```
Expected: push succeeds (remember: `deploy.sh` on the LWS server pulls from `origin/master` — a local-only commit does nothing until pushed, as already learned in the previous sub-project this session).

- [ ] **Step 2: Deploy on the LWS server**

The user runs `bash deploy.sh` directly on the LWS server via their own SSH session (this plan's execution session does not have SSH/credential access to the production server — confirmed blocked by the sandbox's credential-exploration guard in the previous sub-project). Wait for the user to confirm the deploy output shows the new commits fast-forwarded successfully.

- [ ] **Step 3: Live verification — payment treasury entry**

On a real PME in production (e.g. "NotifyMails #69" or another PME with `erpnext_company_name` set), record a real payment against a synced invoice directly in ERPNext (the same flow already used for prior live verifications in this session). Then check `/accounting/report/journal` or `/accounting/report/grand-livre` in PME360 and confirm a new `AccountingEntry` of type `encaissement_facture` appears with a real (non-empty) `debit_account` resolved from ERPNext's `paid_to`.

- [ ] **Step 4: Live verification — cancellation reversal**

Create a fresh test invoice directly in ERPNext for the same PME (unpaid — do not record any payment against it, since `cancelInvoice()` refuses invoices with `amount_paid > 0`), confirm it syncs into PME360, then cancel it in ERPNext. Confirm in `/accounting/report/journal` that two new `annulation_facture` entries appear, with debit/credit reversed relative to the original `facture_vente` entries, and that the original entries are still present (not deleted).

- [ ] **Step 5: Report back to the user**

Summarize what was verified live (which PME, which URLs, what was observed), matching the verification style used throughout this session.
