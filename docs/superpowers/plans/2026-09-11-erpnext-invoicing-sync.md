# Branchement du module Facturation sur ERPNext Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Push every PME360 invoice's create/payment/cancel lifecycle to that PME's own ERPNext Company via three queued Jobs, submitting the Sales Invoice so it feeds real ERPNext financial reports, without ever touching the existing local invoicing logic (totals, PDF, local `AccountingEntry`).

**Architecture:** `InvoiceService` dispatches one Job per lifecycle event, right after each existing local operation completes. Each Job calls new methods on the existing `ErpNextClient` (customer dedup via a new mapping table, Sales Invoice creation+submission, Payment Entry, cancellation). A per-invoice `InvoiceErpNextSync` row tracks status so payment/cancellation Jobs know whether — and where — to sync.

**Tech Stack:** Laravel 13, PHP 8.4, MySQL, Laravel queues (`ShouldQueue`, database driver), the existing `ErpNextClient` (`app/Services/ErpNextClient.php`).

## Global Constraints

- No change to `InvoiceController`, `Invoice`, `InvoiceItem`, `InvoicePayment`, invoicing views, or local `AccountingEntry`/`PlanComptableAccount` — spec "Fichiers concernés — Ne pas toucher".
- ERPNext sync must never fail or block a local invoicing operation — spec "Décisions verrouillées".
- v1 scope is create + payment + cancel only; `updateInvoice()` is explicitly out of scope — spec "Décisions verrouillées".
- The ERPNext Sales Invoice is submitted (`docstatus: 1`) right after creation so it posts real ledger entries — spec "Décisions verrouillées".
- Cancellation uses ERPNext's native cancel (`docstatus: 2`) — spec "Décisions verrouillées".
- Payment uses ERPNext's native "Payment Entry" doctype — spec "Décisions verrouillées".
- Eloquent's default table-name/foreign-key guessing splits "ErpNext" into "erp_next" (discovered and fixed twice already in this codebase, on `ErpNextTestInvoice` and its `items()` relation) — every new model touching "ErpNext" in its class name MUST declare `protected $table` explicitly.
- No automated PHPUnit run is possible on this machine (PHP 8.2 installed vs 8.4 required) — every task's verification step is manual/tinker-based, per the established pattern in this session.

---

## Task 1: Database schema

**Files:**
- Create: `database/migrations/2026_09_11_110000_create_invoice_erpnext_customers_table.php`
- Create: `database/migrations/2026_09_11_110100_create_invoice_erpnext_syncs_table.php`

**Interfaces:**
- Produces: `invoice_erpnext_customers` (`id, user_id, dedup_key, erpnext_customer_name, timestamps`, unique on `(user_id, dedup_key)`) and `invoice_erpnext_syncs` (`id, invoice_id [unique], status, erpnext_invoice_name, erpnext_customer_name, last_error, last_synced_at, raw_response, timestamps`) — consumed by Task 2's models.

- [ ] **Step 1: Create the customer-dedup migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_erpnext_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('dedup_key');
            $table->string('erpnext_customer_name');
            $table->timestamps();

            $table->unique(['user_id', 'dedup_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_erpnext_customers');
    }
};
```

Save as `database/migrations/2026_09_11_110000_create_invoice_erpnext_customers_table.php`.

- [ ] **Step 2: Create the sync-tracking migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_erpnext_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->unique()->constrained('invoices')->cascadeOnDelete();
            $table->string('status', 16)->default('pending');
            $table->string('erpnext_invoice_name')->nullable();
            $table->string('erpnext_customer_name')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_erpnext_syncs');
    }
};
```

Save as `database/migrations/2026_09_11_110100_create_invoice_erpnext_syncs_table.php`.

Note: `erpnext_customer_name` is stored directly on the sync row (not just derivable via the dedup table) so the payment/cancellation Jobs (Task 4) can read everything they need from one row, without recomputing the dedup key or re-querying ERPNext.

- [ ] **Step 3: Run the migrations**

Run: `php artisan migrate`
Expected: both new migrations listed as `Migrated`, no errors.

- [ ] **Step 4: Manual verification**

Run: `php artisan tinker --execute="echo Schema::hasTable('invoice_erpnext_customers') && Schema::hasTable('invoice_erpnext_syncs') ? 'ok' : 'fail';"`
Expected: prints `ok`.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_09_11_110000_create_invoice_erpnext_customers_table.php database/migrations/2026_09_11_110100_create_invoice_erpnext_syncs_table.php
git commit -m "feat(erpnext-invoicing): add invoice_erpnext_customers and invoice_erpnext_syncs tables

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 2: Eloquent models

**Files:**
- Create: `app/Models/InvoiceErpNextCustomer.php`
- Create: `app/Models/InvoiceErpNextSync.php`

**Interfaces:**
- Consumes: tables from Task 1.
- Produces: `InvoiceErpNextCustomer` (fillable `user_id, dedup_key, erpnext_customer_name`), `InvoiceErpNextSync` (fillable `invoice_id, status, erpnext_invoice_name, erpnext_customer_name, last_error, last_synced_at, raw_response`; relation `invoice(): BelongsTo`) — consumed by Task 3 (`ErpNextClient`) and Task 4 (Jobs).

- [ ] **Step 1: Create `InvoiceErpNextCustomer`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceErpNextCustomer extends Model
{
    protected $table = 'invoice_erpnext_customers';

    protected $fillable = [
        'user_id',
        'dedup_key',
        'erpnext_customer_name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

Save as `app/Models/InvoiceErpNextCustomer.php`. The explicit `$table` is required — Eloquent's default guess for this class name would incorrectly split "ErpNext" into "erp_next" (see Global Constraints).

- [ ] **Step 2: Create `InvoiceErpNextSync`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceErpNextSync extends Model
{
    protected $table = 'invoice_erpnext_syncs';

    protected $fillable = [
        'invoice_id',
        'status',
        'erpnext_invoice_name',
        'erpnext_customer_name',
        'last_error',
        'last_synced_at',
        'raw_response',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
```

Save as `app/Models/InvoiceErpNextSync.php`.

- [ ] **Step 3: Manual verification**

Run: `php artisan tinker --execute="echo class_exists(App\Models\InvoiceErpNextCustomer::class) && class_exists(App\Models\InvoiceErpNextSync::class) ? 'ok' : 'fail';"`
Expected: prints `ok`.

- [ ] **Step 4: Commit**

```bash
git add app/Models/InvoiceErpNextCustomer.php app/Models/InvoiceErpNextSync.php
git commit -m "feat(erpnext-invoicing): add InvoiceErpNextCustomer and InvoiceErpNextSync models

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 3: Extend `ErpNextClient`

**Files:**
- Modify: `app/Services/ErpNextClient.php` (add 1 import, 1 private helper, 4 public methods, 1 private helper)

**Interfaces:**
- Consumes: existing private `get()`/`post()` (lines 38-78), `findOrCreateItem()` (lines 201-222), `ErpNextApiException`, `InvoiceErpNextCustomer` model (Task 2), `App\Models\Invoice`/`App\Models\InvoicePayment`.
- Produces:
  - `ErpNextClient::findOrCreateCustomerForPme(User $pme, string $clientName, ?string $clientTaxId): string`
  - `ErpNextClient::createAndSubmitSalesInvoiceForPme(User $pme, string $erpNextCustomerName, Invoice $invoice): array`
  - `ErpNextClient::recordPaymentForPme(User $pme, string $erpNextInvoiceName, string $erpNextCustomerName, InvoicePayment $payment): array`
  - `ErpNextClient::cancelSalesInvoiceForPme(string $erpNextInvoiceName): void`
  All consumed by Task 4's Jobs.

- [ ] **Step 1: Add imports**

In `app/Services/ErpNextClient.php`, lines 1-9 currently read:
```php
<?php

namespace App\Services;

use App\Exceptions\ErpNextApiException;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
```

Change to:
```php
<?php

namespace App\Services;

use App\Exceptions\ErpNextApiException;
use App\Models\Invoice;
use App\Models\InvoiceErpNextCustomer;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
```

- [ ] **Step 2: Add a private `put()` helper**

Add this method right after the existing `post()` method (i.e., right after line 78's closing `}`, before `extractErrorMessage()`):

```php
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function put(string $path, array $payload): array
    {
        try {
            $response = Http::withHeaders(['Authorization' => $this->authHeader()])
                ->timeout($this->timeout())
                ->put($this->baseUrl().$path, $payload);
        } catch (\Throwable $exception) {
            throw new ErpNextApiException('ERPNext injoignable: '.$exception->getMessage());
        }

        if ($response->failed()) {
            throw new ErpNextApiException($this->extractErrorMessage($response));
        }

        return (array) ($response->json('data') ?? []);
    }
```

- [ ] **Step 3: Add `findOrCreateCustomerForPme()` and its dedup-key helper**

Add these two methods right after `findOrCreateCustomer()` (i.e., after line 117's closing `}`, before `provisionCompanyForPme()`):

```php
    public function findOrCreateCustomerForPme(User $pme, string $clientName, ?string $clientTaxId): string
    {
        $dedupKey = $this->customerDedupKey($clientName, $clientTaxId);

        $mapping = InvoiceErpNextCustomer::where('user_id', $pme->id)
            ->where('dedup_key', $dedupKey)
            ->first();

        if ($mapping) {
            $existing = $this->get('/api/resource/Customer/'.rawurlencode($mapping->erpnext_customer_name));
            if (! empty($existing)) {
                return $mapping->erpnext_customer_name;
            }
        }

        $created = $this->post('/api/resource/Customer', [
            'customer_name' => $clientName,
            'company' => $pme->erpnext_company_name,
            'customer_group' => 'Commercial',
            'territory' => 'Ivory Coast',
        ]);

        $erpNextCustomerName = (string) ($created['name'] ?? '');
        if ($erpNextCustomerName === '') {
            throw new ErpNextApiException('ERPNext n\'a pas renvoyé de nom de client après création.');
        }

        InvoiceErpNextCustomer::updateOrCreate(
            ['user_id' => $pme->id, 'dedup_key' => $dedupKey],
            ['erpnext_customer_name' => $erpNextCustomerName]
        );

        return $erpNextCustomerName;
    }

    private function customerDedupKey(string $clientName, ?string $clientTaxId): string
    {
        $source = $clientTaxId ?: $clientName;

        return Str::of($source)->lower()->squish()->value();
    }
```

- [ ] **Step 4: Add `createAndSubmitSalesInvoiceForPme()`**

Add this method right after `provisionCompanyForPme()`'s closing `}` (i.e., after what is currently line 178, before `findAccountByNumber()`):

```php
    /**
     * @return array<string, mixed>
     */
    public function createAndSubmitSalesInvoiceForPme(User $pme, string $erpNextCustomerName, Invoice $invoice): array
    {
        $items = [];
        foreach ($invoice->items as $line) {
            $itemCode = $this->findOrCreateItem($line->description);
            $items[] = [
                'item_code' => $itemCode,
                'qty' => (float) $line->quantity,
                'rate' => (float) $line->unit_price,
                'warehouse' => $pme->erpnext_warehouse,
                'income_account' => $pme->erpnext_income_account,
            ];
        }

        $payload = [
            'company' => $pme->erpnext_company_name,
            'customer' => $erpNextCustomerName,
            'items' => $items,
            'posting_date' => $invoice->issue_date->format('Y-m-d'),
            'due_date' => $invoice->due_date->format('Y-m-d'),
        ];

        if ((float) $invoice->tax_rate > 0 && ! empty($pme->erpnext_tax_template)) {
            $payload['taxes_and_charges'] = $pme->erpnext_tax_template;

            $template = $this->get('/api/resource/'.rawurlencode('Sales Taxes and Charges Template').'/'.rawurlencode($pme->erpnext_tax_template));
            if (! empty($template['taxes'])) {
                $payload['taxes'] = array_map(fn ($row) => [
                    'charge_type' => $row['charge_type'] ?? 'On Net Total',
                    'account_head' => $row['account_head'],
                    'description' => $row['description'] ?? $row['account_head'],
                    'rate' => $row['rate'] ?? 0,
                ], $template['taxes']);
            }
        }

        $created = $this->post('/api/resource/Sales Invoice', $payload);
        $erpNextInvoiceName = (string) ($created['name'] ?? '');
        if ($erpNextInvoiceName === '') {
            throw new ErpNextApiException('ERPNext n\'a pas renvoyé de nom de facture après création.');
        }

        $submitted = $this->put('/api/resource/Sales Invoice/'.rawurlencode($erpNextInvoiceName), [
            'docstatus' => 1,
        ]);

        return $submitted ?: $created;
    }
```

- [ ] **Step 5: Add `recordPaymentForPme()` and `cancelSalesInvoiceForPme()`**

Add these two methods at the very end of the class, right before the final closing `}` of `ErpNextClient` (i.e., after `createSalesInvoice()`'s closing `}`, which is currently the last method):

```php
    /**
     * @return array<string, mixed>
     */
    public function recordPaymentForPme(User $pme, string $erpNextInvoiceName, string $erpNextCustomerName, InvoicePayment $payment): array
    {
        $created = $this->post('/api/resource/Payment Entry', [
            'payment_type' => 'Receive',
            'company' => $pme->erpnext_company_name,
            'party_type' => 'Customer',
            'party' => $erpNextCustomerName,
            'paid_amount' => (float) $payment->amount,
            'received_amount' => (float) $payment->amount,
            'posting_date' => $payment->paid_at->format('Y-m-d'),
            'references' => [
                [
                    'reference_doctype' => 'Sales Invoice',
                    'reference_name' => $erpNextInvoiceName,
                    'allocated_amount' => (float) $payment->amount,
                ],
            ],
        ]);

        $erpNextPaymentName = (string) ($created['name'] ?? '');
        if ($erpNextPaymentName === '') {
            throw new ErpNextApiException('ERPNext n\'a pas renvoyé de nom d\'écriture de paiement après création.');
        }

        return $this->put('/api/resource/Payment Entry/'.rawurlencode($erpNextPaymentName), [
            'docstatus' => 1,
        ]);
    }

    public function cancelSalesInvoiceForPme(string $erpNextInvoiceName): void
    {
        $this->put('/api/resource/Sales Invoice/'.rawurlencode($erpNextInvoiceName), [
            'docstatus' => 2,
        ]);
    }
```

- [ ] **Step 6: Lint the file**

Run: `php -l app/Services/ErpNextClient.php`
Expected: `No syntax errors detected`

- [ ] **Step 7: Manual verification against the real ERPNext trial**

This requires a PME already provisioned by the sub-project-1 work (a `User` with `erpnext_company_name` set — e.g. user id 15 or 7 from the provisioning tests earlier this session) and at least one `Invoice` belonging to that PME with at least one `InvoiceItem`. If none exists, create one via `App\Domain\Invoicing\InvoiceService` directly in tinker first:

```bash
php artisan tinker --execute="
\$pme = App\Models\User::whereNotNull('erpnext_company_name')->first();
if (!\$pme) { echo 'No provisioned PME found — run Task 4 of the provisioning plan first.'; exit; }

\$service = new App\Domain\Invoicing\InvoiceService();
\$invoice = \$service->createInvoice(
    \$pme->id, \$pme->id, 'Client Test ERPNext', null, null, null,
    new DateTime('now'), new DateTime('+30 days'),
    [['description' => 'Prestation test sync', 'quantity' => 1, 'unit_price' => 50000]],
    18.0
);

\$erpNext = new App\Services\ErpNextClient();
\$customerName = \$erpNext->findOrCreateCustomerForPme(\$pme, \$invoice->client_name, \$invoice->client_tax_id);
\$response = \$erpNext->createAndSubmitSalesInvoiceForPme(\$pme, \$customerName, \$invoice);
echo 'name: ' . (\$response['name'] ?? 'N/A') . PHP_EOL;
echo 'docstatus: ' . (\$response['docstatus'] ?? 'N/A') . PHP_EOL;
echo 'grand_total: ' . (\$response['grand_total'] ?? 'N/A') . PHP_EOL;
"
```
Expected: prints a real `ACC-SINV-...` name, `docstatus: 1`, and a non-zero `grand_total` matching `18%` tax applied on `50000`.

- [ ] **Step 8: Commit**

```bash
git add app/Services/ErpNextClient.php
git commit -m "feat(erpnext-invoicing): add per-PME Customer/Invoice/Payment/Cancel methods to ErpNextClient

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 4: Three sync Jobs

**Files:**
- Create: `app/Jobs/SyncInvoiceToErpNext.php`
- Create: `app/Jobs/SyncInvoicePaymentToErpNext.php`
- Create: `app/Jobs/SyncInvoiceCancellationToErpNext.php`

**Interfaces:**
- Consumes: `ErpNextClient::findOrCreateCustomerForPme()`/`createAndSubmitSalesInvoiceForPme()`/`recordPaymentForPme()`/`cancelSalesInvoiceForPme()` (Task 3), `InvoiceErpNextSync` (Task 2), `Invoice`/`InvoicePayment` (existing).
- Produces: `SyncInvoiceToErpNext::dispatch(Invoice $invoice)`, `SyncInvoicePaymentToErpNext::dispatch(InvoicePayment $payment)`, `SyncInvoiceCancellationToErpNext::dispatch(Invoice $invoice)` — consumed by Task 5's `InvoiceService` changes.

- [ ] **Step 1: Write `SyncInvoiceToErpNext`**

```php
<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\InvoiceErpNextSync;
use App\Services\ErpNextClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncInvoiceToErpNext implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Invoice $invoice)
    {
    }

    public function handle(ErpNextClient $erpNext): void
    {
        $sync = InvoiceErpNextSync::firstOrCreate(
            ['invoice_id' => $this->invoice->id],
            ['status' => 'pending']
        );

        if ($sync->status === 'synced') {
            return;
        }

        if (! $erpNext->enabled()) {
            $sync->update(['status' => 'failed', 'last_error' => 'ERPNext non configuré.']);

            return;
        }

        $pme = $this->invoice->user;

        if (empty($pme->erpnext_company_name)) {
            $sync->update([
                'status' => 'failed',
                'last_error' => 'PME non provisionnée sur ERPNext (erpnext_company_name manquant).',
            ]);

            return;
        }

        try {
            $erpNextCustomerName = $erpNext->findOrCreateCustomerForPme(
                $pme,
                $this->invoice->client_name,
                $this->invoice->client_tax_id
            );
            $response = $erpNext->createAndSubmitSalesInvoiceForPme($pme, $erpNextCustomerName, $this->invoice);

            $sync->update([
                'status' => 'synced',
                'erpnext_invoice_name' => $response['name'] ?? null,
                'erpnext_customer_name' => $erpNextCustomerName,
                'last_error' => null,
                'last_synced_at' => now(),
                'raw_response' => $response,
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

Save as `app/Jobs/SyncInvoiceToErpNext.php`. Note: unlike `ProvisionErpNextCompanyForPme` (sub-project 1), this Job catches its own exceptions and records them on the `InvoiceErpNextSync` row instead of letting the Job land in `failed_jobs` — per the spec, an individual invoice's sync failure must stay retriable by re-dispatching, without needing `php artisan queue:retry`.

- [ ] **Step 2: Write `SyncInvoicePaymentToErpNext`**

```php
<?php

namespace App\Jobs;

use App\Models\InvoiceErpNextSync;
use App\Models\InvoicePayment;
use App\Services\ErpNextClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncInvoicePaymentToErpNext implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public InvoicePayment $payment)
    {
    }

    public function handle(ErpNextClient $erpNext): void
    {
        if (! $erpNext->enabled()) {
            return;
        }

        $sync = InvoiceErpNextSync::where('invoice_id', $this->payment->invoice_id)
            ->where('status', 'synced')
            ->first();

        if (! $sync || empty($sync->erpnext_invoice_name) || empty($sync->erpnext_customer_name)) {
            Log::warning('Paiement non synchronisé vers ERPNext : facture #'.$this->payment->invoice_id.' jamais synchronisée.');

            return;
        }

        $pme = $this->payment->invoice->user;

        try {
            $erpNext->recordPaymentForPme($pme, $sync->erpnext_invoice_name, $sync->erpnext_customer_name, $this->payment);
        } catch (\Throwable $exception) {
            Log::warning('Échec de synchronisation du paiement #'.$this->payment->id.' vers ERPNext: '.$exception->getMessage());
        }
    }
}
```

Save as `app/Jobs/SyncInvoicePaymentToErpNext.php`.

- [ ] **Step 3: Write `SyncInvoiceCancellationToErpNext`**

```php
<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\InvoiceErpNextSync;
use App\Services\ErpNextClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncInvoiceCancellationToErpNext implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Invoice $invoice)
    {
    }

    public function handle(ErpNextClient $erpNext): void
    {
        if (! $erpNext->enabled()) {
            return;
        }

        $sync = InvoiceErpNextSync::where('invoice_id', $this->invoice->id)
            ->where('status', 'synced')
            ->first();

        if (! $sync || empty($sync->erpnext_invoice_name)) {
            Log::warning('Annulation non synchronisée vers ERPNext : facture #'.$this->invoice->id.' jamais synchronisée.');

            return;
        }

        try {
            $erpNext->cancelSalesInvoiceForPme($sync->erpnext_invoice_name);
        } catch (\Throwable $exception) {
            Log::warning('Échec de synchronisation de l\'annulation de la facture #'.$this->invoice->id.' vers ERPNext: '.$exception->getMessage());
        }
    }
}
```

Save as `app/Jobs/SyncInvoiceCancellationToErpNext.php`.

- [ ] **Step 4: Lint all three files**

Run:
```bash
php -l app/Jobs/SyncInvoiceToErpNext.php
php -l app/Jobs/SyncInvoicePaymentToErpNext.php
php -l app/Jobs/SyncInvoiceCancellationToErpNext.php
```
Expected: `No syntax errors detected` for each.

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/SyncInvoiceToErpNext.php app/Jobs/SyncInvoicePaymentToErpNext.php app/Jobs/SyncInvoiceCancellationToErpNext.php
git commit -m "feat(erpnext-invoicing): add SyncInvoiceToErpNext/Payment/Cancellation jobs

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 5: Wire the Jobs into `InvoiceService` and verify end-to-end

**Files:**
- Modify: `app/Domain/Invoicing/InvoiceService.php`

**Interfaces:**
- Consumes: `SyncInvoiceToErpNext::dispatch()`, `SyncInvoicePaymentToErpNext::dispatch()`, `SyncInvoiceCancellationToErpNext::dispatch()` (Task 4).

- [ ] **Step 1: Add imports**

In `app/Domain/Invoicing/InvoiceService.php`, lines 5-12 currently read:
```php
use App\Models\AccountingEntry;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceNumberCounter;
use App\Models\InvoicePayment;
use App\Services\TreasuryAudit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
```

Change to:
```php
use App\Jobs\SyncInvoiceCancellationToErpNext;
use App\Jobs\SyncInvoicePaymentToErpNext;
use App\Jobs\SyncInvoiceToErpNext;
use App\Models\AccountingEntry;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceNumberCounter;
use App\Models\InvoicePayment;
use App\Services\TreasuryAudit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
```

- [ ] **Step 2: Dispatch after invoice creation, outside the transaction**

In the same file, `createInvoice()` currently ends (lines 36-90) with the `DB::transaction(...)` call being the method's `return` statement directly. The transaction's closure ends with `return $invoice->fresh('items');` (line 88), and the method itself has no code after the transaction call (lines 89-90 are just the closing `}` of the closure and of the method).

Change the method so the transaction result is captured, the Job is dispatched **after** the transaction (so the queue worker — even with `QUEUE_CONNECTION=sync` locally — never sees the invoice before it's actually committed), then returned:

Replace:
```php
        return DB::transaction(function () use (
            $workspaceUserId, $actorUserId, $clientName, $clientContact, $clientAddress,
            $clientTaxId, $issueDate, $dueDate, $items, $taxRate, $notes, $currency
        ) {
```
with:
```php
        $invoice = DB::transaction(function () use (
            $workspaceUserId, $actorUserId, $clientName, $clientContact, $clientAddress,
            $clientTaxId, $issueDate, $dueDate, $items, $taxRate, $notes, $currency
        ) {
```

And replace the method's closing (currently):
```php
            return $invoice->fresh('items');
        });
    }
```
with:
```php
            return $invoice->fresh('items');
        });

        SyncInvoiceToErpNext::dispatch($invoice);

        return $invoice;
    }
```

- [ ] **Step 3: Dispatch after payment recording, outside the transaction**

In the same file, `recordPayment()` (currently lines 95-153) has the same shape — the whole method body is `return DB::transaction(function () use (...) { ... return $payment; });`.

Replace:
```php
    public function recordPayment(Invoice $invoice, array $data, int $actorUserId): InvoicePayment
    {
        return DB::transaction(function () use ($invoice, $data, $actorUserId) {
```
with:
```php
    public function recordPayment(Invoice $invoice, array $data, int $actorUserId): InvoicePayment
    {
        $payment = DB::transaction(function () use ($invoice, $data, $actorUserId) {
```

And replace the method's closing (currently):
```php
            return $payment;
        });
    }
```
with:
```php
            return $payment;
        });

        SyncInvoicePaymentToErpNext::dispatch($payment);

        return $payment;
    }
```

- [ ] **Step 4: Dispatch after cancellation**

`cancelInvoice()` (currently lines 226-243) is not wrapped in a transaction. Its current end reads:
```php
        TreasuryAudit::log($invoice->user_id, 'invoicing.invoice.cancelled', $invoice, [
            'invoice_number' => $invoice->invoice_number,
            'reason' => $reason,
            'actor_user_id' => $actorUserId,
        ]);
    }
```

Change to:
```php
        TreasuryAudit::log($invoice->user_id, 'invoicing.invoice.cancelled', $invoice, [
            'invoice_number' => $invoice->invoice_number,
            'reason' => $reason,
            'actor_user_id' => $actorUserId,
        ]);

        SyncInvoiceCancellationToErpNext::dispatch($invoice);
    }
```

- [ ] **Step 5: Lint the file**

Run: `php -l app/Domain/Invoicing/InvoiceService.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Manual end-to-end verification — full lifecycle**

Using the same provisioned PME as Task 3 Step 7:

```bash
php artisan tinker --execute="
\$pme = App\Models\User::whereNotNull('erpnext_company_name')->first();
\$service = new App\Domain\Invoicing\InvoiceService();

\$invoice = \$service->createInvoice(
    \$pme->id, \$pme->id, 'Client Cycle Complet', null, null, null,
    new DateTime('now'), new DateTime('+30 days'),
    [['description' => 'Prestation cycle complet', 'quantity' => 2, 'unit_price' => 25000]],
    18.0
);
echo 'invoice id: ' . \$invoice->id . PHP_EOL;
"
```

Then process the queue and check the sync row:
```bash
php artisan queue:work --stop-when-empty
php artisan tinker --execute="
\$sync = App\Models\InvoiceErpNextSync::latest('id')->first();
echo 'status: ' . \$sync->status . PHP_EOL;
echo 'erpnext_invoice_name: ' . \$sync->erpnext_invoice_name . PHP_EOL;
"
```
Expected: `status: synced`, a real `ACC-SINV-...` name.

- [ ] **Step 7: Manual verification — payment sync**

```bash
php artisan tinker --execute="
\$invoice = App\Models\Invoice::latest('id')->first();
\$service = new App\Domain\Invoicing\InvoiceService();
\$service->recordPayment(\$invoice, [
    'amount' => 25000,
    'paid_at' => new DateTime('now'),
    'treasury_transaction_id' => null,
    'method' => 'cash',
    'reference' => null,
    'treasury_account_code' => null,
    'notes' => null,
], \$invoice->user_id);
echo 'payment recorded';
"
php artisan queue:work --stop-when-empty
```
Expected: no crash locally; in ERPNext (`Accounting → Payments → Payment Entry`), confirm a submitted Payment Entry exists referencing the Sales Invoice from Step 6, and that the Sales Invoice's `outstanding_amount` decreased by 25000.

- [ ] **Step 8: Manual verification — cancellation sync (on a fresh unpaid invoice)**

```bash
php artisan tinker --execute="
\$pme = App\Models\User::whereNotNull('erpnext_company_name')->first();
\$service = new App\Domain\Invoicing\InvoiceService();
\$invoice = \$service->createInvoice(
    \$pme->id, \$pme->id, 'Client A Annuler', null, null, null,
    new DateTime('now'), new DateTime('+30 days'),
    [['description' => 'Prestation a annuler', 'quantity' => 1, 'unit_price' => 10000]],
    18.0
);
echo 'invoice id: ' . \$invoice->id . PHP_EOL;
"
php artisan queue:work --stop-when-empty
php artisan tinker --execute="
\$invoice = App\Models\Invoice::latest('id')->first();
\$service = new App\Domain\Invoicing\InvoiceService();
\$service->cancelInvoice(\$invoice, 'Test annulation ERPNext', \$invoice->user_id);
echo 'cancelled locally';
"
php artisan queue:work --stop-when-empty
```
Expected: in ERPNext, the corresponding Sales Invoice shows `docstatus: 2` (cancelled), and its GL entries are reversed (visible via `Accounting → General Ledger` filtered on that invoice — no net balance remains from it).

- [ ] **Step 9: Manual verification — graceful failure for a non-provisioned PME**

```bash
php artisan tinker --execute="
\$pme = App\Models\User::factory()->create(['company_name' => 'PME Non Provisionnee']);
\$service = new App\Domain\Invoicing\InvoiceService();
\$invoice = \$service->createInvoice(
    \$pme->id, \$pme->id, 'Client Test', null, null, null,
    new DateTime('now'), new DateTime('+30 days'),
    [['description' => 'Test', 'quantity' => 1, 'unit_price' => 1000]],
    0
);
echo 'invoice created locally, id: ' . \$invoice->id . PHP_EOL;
"
php artisan queue:work --stop-when-empty
php artisan tinker --execute="
\$sync = App\Models\InvoiceErpNextSync::latest('id')->first();
echo 'status: ' . \$sync->status . PHP_EOL;
echo 'last_error: ' . \$sync->last_error . PHP_EOL;
"
```
Expected: the local invoice exists and works normally (PDF/accounting entries unaffected — not re-verified here since that logic is untouched), `status: failed`, `last_error` mentions the PME is not provisioned on ERPNext — no exception thrown, no `failed_jobs` entry (this Job catches its own errors per Task 4 Step 1's design).

- [ ] **Step 10: Commit**

```bash
git add app/Domain/Invoicing/InvoiceService.php
git commit -m "feat(erpnext-invoicing): dispatch ERPNext sync on invoice create/payment/cancel

Verified end-to-end against the real ERPNext trial: full create -> pay
-> cancel lifecycle correctly reflected as a submitted Sales Invoice,
a submitted Payment Entry reducing outstanding_amount, and a native
ERPNext cancellation reversing ledger entries. Also verified graceful
failure (status=failed, no exception) for a PME never provisioned by
the sub-project-1 work.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```
