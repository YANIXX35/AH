# Invoicing ERPNext Webhook Ingestion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Invoices are created, paid, and cancelled exclusively in ERPNext's native UI (Sales Invoice / Payment Entry); ERPNext notifies PME360 via webhook on each event, and PME360 reuses its existing `InvoiceService` methods (preserving sequential invoice numbering and OHADA accounting entries) to mirror the result locally.

**Architecture:** Three ERPNext `Webhook` documents (`Sales Invoice` on `on_submit`/`on_cancel`, `Payment Entry` on `on_submit`) POST a minimal JSON payload to a new token-protected PME360 route. `ErpNextInvoicingWebhookController` re-reads the full document via `ErpNextClient::getDocument()` (already built for Stock), extracts the data `InvoiceService` needs, and calls `createInvoice()`/`recordPayment()`/`cancelInvoice()` with a new `$skipErpNextSync` flag to avoid looping the data back to ERPNext. The old PME360-side creation/payment/cancellation/edit/delete routes and views are removed.

**Tech Stack:** Laravel 13 / PHP 8.4, MySQL, existing `ErpNextClient` (`app/Services/ErpNextClient.php`) — no new PHP packages.

## Global Constraints

- Reuse `InvoiceService::createInvoice()`, `recordPayment()`, `cancelInvoice()` as-is — do not duplicate their invoice-numbering or accounting-entry logic anywhere else. Add exactly one new parameter, `bool $skipErpNextSync = false`, to each of the three, defaulting to today's behavior (dispatch the sync job) so every existing caller is unaffected.
- The webhook endpoint verifies the same static shared-secret header already built for Stock (`X-PME360-Webhook-Token`, `config('services.erpnext.webhook_token')`) — no new token needed, reuse the existing one.
- If the `company` in the payload matches no local PME, or the referenced local invoice cannot be resolved (for payment/cancel events), respond `200` and do nothing further — log a warning, never throw, exactly like the Stock webhook.
- Resolve the local `Invoice` for payment/cancel events via `InvoiceErpNextSync::where('erpnext_invoice_name', $docname)->first()?->invoice` — the same table already used for the PME360→ERPNext direction, now also queried in reverse.
- For a creation event, guard against double-processing: if `InvoiceErpNextSync::where('erpnext_invoice_name', $docname)->exists()`, skip (already processed).
- Client fields not reliably available from ERPNext (`client_contact`, `client_address`, `client_tax_id`) are passed as `null` — do not attempt to invent values.
- Tax rate is read from the first row of the Sales Invoice's `taxes` child table (`rate`), defaulting to `0` if absent.
- A Payment Entry's `references` child table may reference multiple invoices — call `recordPayment()` once per matching reference row, using that row's `allocated_amount`.
- `treasury_account_code` is omitted when calling `recordPayment()` from the webhook (no local treasury accounting entry is generated for ERPNext-originated payments) — this is an accepted, already-optional code path in `recordPayment()`, not a new special case.
- Do not modify `Invoice`, `InvoiceItem`, `InvoicePayment`, `InvoiceNumberCounter`, `createSaleAccountingEntries()`, or the existing `Sync*ToErpNext` jobs.
- Remove `InvoiceController::create()`, `store()`, `edit()`, `update()`, `storePayment()`, `cancel()`, `destroy()` and their routes; keep `index()`, `show()`, `downloadPdf()`, `export()`.

---

### Task 1: Add `$skipErpNextSync` to the three `InvoiceService` methods

**Files:**
- Modify: `app/Domain/Invoicing/InvoiceService.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `createInvoice(..., bool $skipErpNextSync = false): Invoice`, `recordPayment(Invoice $invoice, array $data, int $actorUserId, bool $skipErpNextSync = false): InvoicePayment`, `cancelInvoice(Invoice $invoice, string $reason, int $actorUserId, bool $skipErpNextSync = false): void` — all three keep every existing parameter in the same order, the new parameter is appended last with a default so no existing call site needs to change. Used by Task 2 (`ErpNextInvoicingWebhookController`), called with `true`.

- [ ] **Step 1: `createInvoice()`**

In `app/Domain/Invoicing/InvoiceService.php`, change the method signature and its final lines from:

```php
    public function createInvoice(
        int $workspaceUserId,
        ?int $actorUserId,
        string $clientName,
        ?string $clientContact,
        ?string $clientAddress,
        ?string $clientTaxId,
        \DateTimeInterface $issueDate,
        \DateTimeInterface $dueDate,
        array $items,
        float $taxRate = 0,
        ?string $notes = null,
        string $currency = 'XOF'
    ): Invoice {
```

to:

```php
    public function createInvoice(
        int $workspaceUserId,
        ?int $actorUserId,
        string $clientName,
        ?string $clientContact,
        ?string $clientAddress,
        ?string $clientTaxId,
        \DateTimeInterface $issueDate,
        \DateTimeInterface $dueDate,
        array $items,
        float $taxRate = 0,
        ?string $notes = null,
        string $currency = 'XOF',
        bool $skipErpNextSync = false
    ): Invoice {
```

and change:

```php
            return $invoice->fresh('items');
        });

        SyncInvoiceToErpNext::dispatch($invoice);

        return $invoice;
    }
```

to:

```php
            return $invoice->fresh('items');
        });

        if (! $skipErpNextSync) {
            SyncInvoiceToErpNext::dispatch($invoice);
        }

        return $invoice;
    }
```

- [ ] **Step 2: `recordPayment()`**

Change:

```php
    public function recordPayment(Invoice $invoice, array $data, int $actorUserId): InvoicePayment
    {
```

to:

```php
    public function recordPayment(Invoice $invoice, array $data, int $actorUserId, bool $skipErpNextSync = false): InvoicePayment
    {
```

and change:

```php
            return $payment;
        });

        SyncInvoicePaymentToErpNext::dispatch($payment);

        return $payment;
    }
```

to:

```php
            return $payment;
        });

        if (! $skipErpNextSync) {
            SyncInvoicePaymentToErpNext::dispatch($payment);
        }

        return $payment;
    }
```

- [ ] **Step 3: `cancelInvoice()`**

Change:

```php
    public function cancelInvoice(Invoice $invoice, string $reason, int $actorUserId): void
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

        SyncInvoiceCancellationToErpNext::dispatch($invoice);
    }
```

to:

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

- [ ] **Step 4: Lint**

Run: `php -l app/Domain/Invoicing/InvoiceService.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Verify existing behavior is unaffected**

```bash
php artisan tinker --execute="
\$pme = \App\Models\User::find(15);
\$invoice = app(\App\Domain\Invoicing\InvoiceService::class)->createInvoice(
    \$pme->id, \$pme->id, 'Client test plan skip param', null, null, null,
    now(), now()->addDays(30),
    [['description' => 'Ligne test', 'quantity' => 1, 'unit_price' => 5000]],
    18
);
echo 'invoice_number: '.\$invoice->invoice_number.PHP_EOL;
\$sync = \App\Models\InvoiceErpNextSync::where('invoice_id', \$invoice->id)->first();
echo 'sync row exists (job queued as usual): '.(\$sync ? 'yes' : 'no, expected until worker runs').PHP_EOL;
"
```

Expected: a real `FA-2026-XXXXXX` number (default call path unaffected — `$skipErpNextSync` defaults to `false`, so the job still gets dispatched as before).

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Invoicing/InvoiceService.php
git commit -m "feat(erpnext-invoicing): add skipErpNextSync flag to InvoiceService methods

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 2: `ErpNextInvoicingWebhookController` + route

**Files:**
- Create: `app/Http/Controllers/ErpNextInvoicingWebhookController.php`
- Modify: `routes/web.php` (add a route next to `webhooks.erpnext.stock-movement`)
- Modify: `bootstrap/app.php` (add the new route to the CSRF exemption list)

**Interfaces:**
- Consumes: `ErpNextClient::getDocument(string $doctype, string $name): array` (already exists), `InvoiceService::createInvoice(..., bool $skipErpNextSync = true)`, `recordPayment(..., bool $skipErpNextSync = true)`, `cancelInvoice(..., bool $skipErpNextSync = true)` (Task 1), `InvoiceErpNextSync` model (existing).
- Produces: route `POST /webhooks/erpnext/invoicing`.

- [ ] **Step 1: Create the controller**

Create `app/Http/Controllers/ErpNextInvoicingWebhookController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Domain\Invoicing\InvoiceService;
use App\Models\InvoiceErpNextSync;
use App\Models\User;
use App\Services\ErpNextClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ErpNextInvoicingWebhookController extends Controller
{
    public function handle(Request $request, ErpNextClient $erpNext, InvoiceService $invoiceService): JsonResponse
    {
        $expectedToken = trim((string) config('services.erpnext.webhook_token', ''));
        $providedToken = (string) $request->header('X-PME360-Webhook-Token', '');

        if ($expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            abort(403);
        }

        $doctype = (string) $request->input('doctype', '');
        $docname = (string) $request->input('name', '');
        $company = (string) $request->input('company', '');

        if ($doctype === '' || $docname === '' || $company === '') {
            return response()->json(['status' => 'ignored', 'reason' => 'payload incomplet'], 200);
        }

        $pme = User::where('erpnext_company_name', $company)->first();

        if (! $pme) {
            Log::warning('Webhook ERPNext Invoicing reçu pour une company sans PME locale correspondante.', [
                'company' => $company,
                'doctype' => $doctype,
                'name' => $docname,
            ]);

            return response()->json(['status' => 'ignored', 'reason' => 'PME introuvable'], 200);
        }

        try {
            $document = $erpNext->getDocument($doctype, $docname);
        } catch (\Throwable $exception) {
            Log::warning('Webhook ERPNext Invoicing: échec de relecture du document.', [
                'doctype' => $doctype,
                'name' => $docname,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['status' => 'error'], 200);
        }

        try {
            if ($doctype === 'Sales Invoice' && (int) ($document['docstatus'] ?? 0) === 2) {
                $this->handleCancellation($docname);
            } elseif ($doctype === 'Sales Invoice') {
                $this->handleCreation($pme, $docname, $document, $invoiceService);
            } elseif ($doctype === 'Payment Entry') {
                $this->handlePayment($docname, $document, $invoiceService);
            } else {
                return response()->json(['status' => 'ignored', 'reason' => 'doctype non géré'], 200);
            }
        } catch (\Throwable $exception) {
            Log::warning('Webhook ERPNext Invoicing: échec de traitement.', [
                'doctype' => $doctype,
                'name' => $docname,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['status' => 'error'], 200);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function handleCreation(User $pme, string $docname, array $document, InvoiceService $invoiceService): void
    {
        if (InvoiceErpNextSync::where('erpnext_invoice_name', $docname)->exists()) {
            return;
        }

        $items = [];
        foreach ((array) ($document['items'] ?? []) as $line) {
            $items[] = [
                'description' => (string) ($line['item_name'] ?? $line['item_code'] ?? 'Article'),
                'quantity' => (float) ($line['qty'] ?? 0),
                'unit_price' => (float) ($line['rate'] ?? 0),
            ];
        }

        if (empty($items)) {
            return;
        }

        $taxRate = (float) ($document['taxes'][0]['rate'] ?? 0);

        $invoice = $invoiceService->createInvoice(
            $pme->id,
            $pme->id,
            (string) ($document['customer_name'] ?? $document['customer'] ?? 'Client ERPNext'),
            null,
            null,
            null,
            Carbon::parse((string) ($document['posting_date'] ?? now()->toDateString())),
            Carbon::parse((string) ($document['due_date'] ?? now()->toDateString())),
            $items,
            $taxRate,
            null,
            (string) ($document['currency'] ?? 'XOF'),
            true
        );

        InvoiceErpNextSync::updateOrCreate(
            ['invoice_id' => $invoice->id],
            [
                'status' => 'synced',
                'erpnext_invoice_name' => $docname,
                'erpnext_customer_name' => (string) ($document['customer'] ?? ''),
                'last_synced_at' => now(),
            ]
        );
    }

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

    private function handleCancellation(string $docname): void
    {
        $sync = InvoiceErpNextSync::where('erpnext_invoice_name', $docname)->first();

        if (! $sync || ! $sync->invoice) {
            return;
        }

        app(InvoiceService::class)->cancelInvoice(
            $sync->invoice,
            'Annulée depuis ERPNext ('.$docname.')',
            $sync->invoice->user_id,
            true
        );
    }
}
```

- [ ] **Step 2: Add the route**

In `routes/web.php`, change:

```php
Route::post('/webhooks/erpnext/stock-movement', [\App\Http\Controllers\ErpNextStockWebhookController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('webhooks.erpnext.stock-movement');
```

to:

```php
Route::post('/webhooks/erpnext/stock-movement', [\App\Http\Controllers\ErpNextStockWebhookController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('webhooks.erpnext.stock-movement');
Route::post('/webhooks/erpnext/invoicing', [\App\Http\Controllers\ErpNextInvoicingWebhookController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('webhooks.erpnext.invoicing');
```

- [ ] **Step 3: Add the CSRF exemption**

In `bootstrap/app.php`, change:

```php
        $middleware->validateCsrfTokens(except: [
            'webhooks/erpnext/stock-movement',
        ]);
```

to:

```php
        $middleware->validateCsrfTokens(except: [
            'webhooks/erpnext/stock-movement',
            'webhooks/erpnext/invoicing',
        ]);
```

- [ ] **Step 4: Lint**

Run: `php -l app/Http/Controllers/ErpNextInvoicingWebhookController.php && php -l routes/web.php && php -l bootstrap/app.php`
Expected: `No syntax errors detected` for all three.

- [ ] **Step 5: Verify the creation path via tinker against the real ERPNext trial**

First, find a real submitted Sales Invoice for the local test PME (id 15) — reuse one from earlier this session, or create+submit a fresh one via the already-existing admin test dashboard `/admin/erpnext-test` (from the very first Invoicing sub-project) if none is handy. Then:

```bash
php artisan tinker --execute="
\$pme = \App\Models\User::find(15);
\$erpNext = app(\App\Services\ErpNextClient::class);
\$query = http_build_query(['filters' => json_encode([['company','=',\$pme->erpnext_company_name],['docstatus','=',1]]), 'fields' => json_encode(['name']), 'limit_page_length' => 1, 'order_by' => 'creation desc']);
\$get = new ReflectionMethod(\$erpNext, 'get');
\$get->setAccessible(true);
\$latest = \$get->invoke(\$erpNext, '/api/resource/'.rawurlencode('Sales Invoice').'?'.\$query);
echo 'SALES_INVOICE_NAME='.(\$latest[0]['name'] ?? 'NONE FOUND — create one first via /admin/erpnext-test').PHP_EOL;
"
```

Then, with a real name in hand:

```bash
php artisan tinker --execute="
\$pme = \App\Models\User::find(15);
\$request = \Illuminate\Http\Request::create('/webhooks/erpnext/invoicing', 'POST', [
    'doctype' => 'Sales Invoice',
    'name' => 'PASTE_SALES_INVOICE_NAME_HERE',
    'company' => \$pme->erpnext_company_name,
]);
\$request->headers->set('X-PME360-Webhook-Token', config('services.erpnext.webhook_token'));
\$controller = app(\App\Http\Controllers\ErpNextInvoicingWebhookController::class);
\$response = \$controller->handle(\$request, app(\App\Services\ErpNextClient::class), app(\App\Domain\Invoicing\InvoiceService::class));
echo \$response->getContent().PHP_EOL;
\$sync = \App\Models\InvoiceErpNextSync::where('erpnext_invoice_name', 'PASTE_SALES_INVOICE_NAME_HERE')->first();
echo 'local invoice_number: '.(\$sync->invoice->invoice_number ?? 'NOT CREATED').PHP_EOL;
"
```

Expected: `{"status":"ok"}`, and a real `FA-2026-XXXXXX` invoice number printed, correctly sequenced after the local PME's existing invoices.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ErpNextInvoicingWebhookController.php routes/web.php bootstrap/app.php
git commit -m "feat(erpnext-invoicing): add webhook endpoint to ingest ERPNext invoicing events

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 3: Remove local invoice creation/payment/cancellation/edit/delete

**Files:**
- Modify: `app/Http/Controllers/InvoiceController.php`
- Modify: `routes/web.php`
- Delete: `resources/views/invoicing/create.blade.php`
- Delete: `resources/views/invoicing/edit.blade.php`
- Modify: `resources/views/invoicing/show.blade.php`
- Modify: `resources/views/invoicing/index.blade.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `InvoiceController` keeps only `index()`, `show()`, `downloadPdf()`, `export()`, `invoiceLabel()` (still used internally... actually `invoiceLabel()` was only used by the removed methods — check before keeping) and `authorizeInvoice()`.

- [ ] **Step 1: Check whether `invoiceLabel()` is still needed**

Run: `grep -n "invoiceLabel" app/Http/Controllers/InvoiceController.php`
It is currently called only inside `update()` and `cancel()` and `destroy()` (all being removed in this task) — if that's confirmed, remove `invoiceLabel()` too in Step 2 below.

- [ ] **Step 2: Rewrite `InvoiceController.php`**

Replace the entire file content with:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesClientWorkspace;
use App\Models\Invoice;
use App\Support\Export\TabularDocumentExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    use UsesClientWorkspace;

    public function index(Request $request): View
    {
        $userIds = $this->workspaceDataUserIds();

        $invoices = Invoice::whereIn('user_id', $userIds)
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $totals = [
            'unpaid' => Invoice::whereIn('user_id', $userIds)->where('status', 'unpaid')->sum('total_amount'),
            'partially_paid' => Invoice::whereIn('user_id', $userIds)->where('status', 'partially_paid')->sum('total_amount'),
            'overdue' => Invoice::whereIn('user_id', $userIds)->whereIn('status', ['unpaid', 'partially_paid'])->where('due_date', '<', now())->count(),
        ];

        return view('invoicing.index', [
            'invoices' => $invoices,
            'totals' => $totals,
            'currentStatus' => $request->query('status'),
        ]);
    }

    public function show(Invoice $invoice): View
    {
        $this->authorizeInvoice($invoice);
        $invoice->load(['items', 'payments.treasuryTransaction']);

        return view('invoicing.show', ['invoice' => $invoice]);
    }

    public function downloadPdf(Invoice $invoice): StreamedResponse
    {
        $this->authorizeInvoice($invoice);
        $invoice->load(['items', 'user']);

        $pdf = Pdf::loadView('invoicing.pdf', [
            'invoice' => $invoice,
            'companyLogo' => \App\Support\CompanyLogo::toDataUri($invoice->user->company_logo),
        ]);
        $path = 'invoices/'.$invoice->invoice_number.'.pdf';
        Storage::disk('public')->put($path, $pdf->output());
        $invoice->update(['pdf_path' => $path]);

        return Storage::disk('public')->download($path, $invoice->invoice_number.'.pdf');
    }

    public function export(Invoice $invoice, string $format, TabularDocumentExporter $exporter)
    {
        $this->authorizeInvoice($invoice);

        if ($format === 'pdf') {
            return $this->downloadPdf($invoice);
        }

        $invoice->load(['items', 'user']);
        $filename = 'facture-'.$invoice->invoice_number;
        $title = 'Facture '.$invoice->invoice_number.' — '.$invoice->client_name;
        $logoPath = \App\Support\CompanyLogo::absolutePath($invoice->user->company_logo);

        $summary = [
            ['Émetteur', $invoice->user->company_name ?? $invoice->user->name ?? '-'],
            ['Client', $invoice->client_name],
            ['Date d\'émission', $invoice->issue_date->format('d/m/Y')],
            ['Échéance', $invoice->due_date->format('d/m/Y')],
            ['Statut', strtoupper((string) $invoice->status)],
            ['Devise', $invoice->currency],
            ['Sous-total', number_format((float) $invoice->subtotal, 0, ',', ' ').' '.$invoice->currency],
            ['TVA ('.$invoice->tax_rate.'%)', number_format((float) $invoice->tax_amount, 0, ',', ' ').' '.$invoice->currency],
            ['Total TTC', number_format((float) $invoice->total_amount, 0, ',', ' ').' '.$invoice->currency],
            ['Montant payé', number_format((float) $invoice->amount_paid, 0, ',', ' ').' '.$invoice->currency],
            ['Solde dû', number_format($invoice->balanceDue(), 0, ',', ' ').' '.$invoice->currency],
        ];

        $headers = ['Libellé', 'Quantité', 'Prix unitaire', 'Total'];
        $rows = $invoice->items->map(fn ($item) => [
            $item->description,
            $item->quantity,
            number_format((float) $item->unit_price, 0, ',', ' ').' '.$invoice->currency,
            number_format((float) $item->line_total, 0, ',', ' ').' '.$invoice->currency,
        ])->all();

        return match ($format) {
            'csv' => $exporter->csv($filename, $title, $summary, $headers, $rows),
            'xlsx' => $exporter->excel($filename, $title, $summary, $headers, $rows, $logoPath),
            'docx' => $exporter->word($filename, $title, $summary, $headers, $rows, $logoPath),
            default => abort(404),
        };
    }

    private function authorizeInvoice(Invoice $invoice): void
    {
        abort_unless(in_array($invoice->user_id, $this->workspaceDataUserIds(), true), 403);
    }
}
```

(Note: `show()` no longer computes `$treasuryAccounts`/`$unlinkedTreasuryTransactions` — those were only used by the now-removed payment form. `$invoiceService`/`$changeApproval` are no longer injected since nothing in this controller calls them anymore.)

- [ ] **Step 3: Remove the routes**

In `routes/web.php`, change:

```php
        Route::get('/invoicing', [InvoiceController::class, 'index'])->name('invoicing.index');
        Route::get('/invoicing/create', [InvoiceController::class, 'create'])->name('invoicing.create');
        Route::post('/invoicing', [InvoiceController::class, 'store'])->middleware('throttle:finance-write')->name('invoicing.store');
        Route::get('/invoicing/{invoice}', [InvoiceController::class, 'show'])->name('invoicing.show');
        Route::get('/invoicing/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoicing.edit');
        Route::put('/invoicing/{invoice}', [InvoiceController::class, 'update'])->middleware('throttle:finance-write')->name('invoicing.update');
        Route::post('/invoicing/{invoice}/payments', [InvoiceController::class, 'storePayment'])->middleware('throttle:finance-write')->name('invoicing.payments.store');
        Route::post('/invoicing/{invoice}/cancel', [InvoiceController::class, 'cancel'])->middleware('throttle:finance-write')->name('invoicing.cancel');
        Route::delete('/invoicing/{invoice}', [InvoiceController::class, 'destroy'])->middleware('throttle:finance-write')->name('invoicing.destroy');
        Route::get('/invoicing/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoicing.pdf');
```

to:

```php
        Route::get('/invoicing', [InvoiceController::class, 'index'])->name('invoicing.index');
        Route::get('/invoicing/{invoice}', [InvoiceController::class, 'show'])->name('invoicing.show');
        Route::get('/invoicing/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoicing.pdf');
```

(The `invoicing.export` route further below, and every other route in the file, stays untouched — only these specific lines change.)

- [ ] **Step 4: Delete the now-orphaned views**

```bash
rm resources/views/invoicing/create.blade.php
rm resources/views/invoicing/edit.blade.php
```

- [ ] **Step 5: Remove the "Nouvelle Facture" link from `index.blade.php`**

In `resources/views/invoicing/index.blade.php`, change:

```blade
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="{{ route('invoicing.create') }}" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold">
                    <i data-feather="plus" class="me-1" style="width:14px; height:14px;"></i> Nouvelle Facture
                </a>
            </div>
```

to:

```blade
            <div class="d-flex align-items-center gap-2 flex-wrap">
            </div>
```

- [ ] **Step 6: Remove the edit/delete buttons and payment/cancel forms from `show.blade.php`**

In `resources/views/invoicing/show.blade.php`, change:

```blade
        <div class="d-flex gap-2">
            @if ((float) $invoice->amount_paid <= 0 && $invoice->status !== 'cancelled')
                <a href="{{ route('invoicing.edit', $invoice) }}" class="btn btn-outline-primary btn-sm">Modifier</a>
                <form action="{{ route('invoicing.destroy', $invoice) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('Supprimer définitivement cette facture ? Cette action est irréversible.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">Supprimer</button>
                </form>
            @endif
            <select id="invoiceExportFormat" class="form-select form-select-sm d-inline-block" style="width:auto;" aria-label="Format de téléchargement">
```

to:

```blade
        <div class="d-flex gap-2">
            <select id="invoiceExportFormat" class="form-select form-select-sm d-inline-block" style="width:auto;" aria-label="Format de téléchargement">
```

Then change:

```blade
        <div class="col-12 col-xl-4">
            @if ($invoice->status !== 'paid' && $invoice->status !== 'cancelled')
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="mb-3">Enregistrer un encaissement</h5>
                        <form action="{{ route('invoicing.payments.store', $invoice) }}" method="POST" id="payment-form" data-balance-due="{{ $invoice->balanceDue() }}">
                            @csrf

                            @if ($unlinkedTreasuryTransactions->count() > 0)
                                <div class="mb-3">
                                    <label class="form-label">Mouvement de trésorerie existant (optionnel)</label>
                                    <select name="treasury_transaction_id" id="treasury_transaction_id" class="form-select">
                                        <option value="">— Encaissement manuel —</option>
                                        @foreach ($unlinkedTreasuryTransactions as $tx)
                                            <option value="{{ $tx->id }}" data-amount="{{ $tx->amount }}" data-date="{{ $tx->transaction_date->format('Y-m-d') }}">
                                                {{ $tx->transaction_date->format('d/m/Y') }} — {{ number_format((float) $tx->amount, 0, ',', ' ') }} FCFA — {{ $tx->description }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Inclut les mouvements confirmés par le rapprochement Mobile Money.</small>
                                </div>
                            @endif

                            <div class="mb-3">
                                <label class="form-label">Montant *</label>
                                <input type="number" step="0.01" min="0.01" max="{{ $invoice->balanceDue() }}" name="amount" id="payment_amount" class="form-control" required value="{{ old('amount', $invoice->balanceDue()) }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Date *</label>
                                <input type="date" name="paid_at" id="payment_date" class="form-control" required value="{{ old('paid_at', now()->toDateString()) }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Méthode</label>
                                <select name="method" id="payment_method" class="form-select">
                                    <option value="">— Sélectionner —</option>
                                    <option value="Wave">Wave</option>
                                    <option value="Orange Money">Orange Money</option>
                                    <option value="MTN Money">MTN Money</option>
                                    <option value="Moov Money">Moov Money</option>
                                    <option value="Virement bancaire">Virement bancaire</option>
                                    <option value="Chèque">Chèque</option>
                                    <option value="Espèces">Espèces</option>
                                    <option value="autre">Autre…</option>
                                </select>
                                <input type="text" id="payment_method_other" class="form-control mt-2 d-none" placeholder="Préciser la méthode">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Référence</label>
                                <input type="text" name="reference" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Compte de trésorerie (pour l'écriture comptable)</label>
                                <select name="treasury_account_code" class="form-select">
                                    <option value="">— Ne pas générer d'écriture —</option>
                                    @foreach ($treasuryAccounts as $account)
                                        <option value="{{ $account->prefix }}">{{ $account->prefix }} — {{ $account->label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <button type="submit" class="btn btn-success w-100">Enregistrer l'encaissement</button>
                        </form>
                    </div>
                </div>
            @endif

            @if ($invoice->status !== 'cancelled' && (float) $invoice->amount_paid <= 0)
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3">Annuler la facture</h5>
                        <form action="{{ route('invoicing.cancel', $invoice) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Motif *</label>
                                <input type="text" name="reason" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('Annuler cette facture ? Le numéro reste réservé (conformité e-invoicing).');">Annuler la facture</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
```

to:

```blade
        <div class="col-12 col-xl-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Facturation</h5>
                    <p class="text-muted small mb-0">Les factures (création, encaissement, annulation) se créent désormais directement dans ERPNext. Les changements apparaissent automatiquement ici une fois enregistrés là-bas.</p>
                </div>
            </div>
        </div>
    </div>
</div>
```

- [ ] **Step 7: Remove the now-orphaned first `<script>` block (payment/cancel form JS)**

Change:

```blade
<script>
(function () {
    const select = document.getElementById('treasury_transaction_id');
    if (!select) return;
    select.addEventListener('change', function () {
        const option = select.options[select.selectedIndex];
        const amount = option.getAttribute('data-amount');
        const date = option.getAttribute('data-date');
        if (amount) document.getElementById('payment_amount').value = amount;
        if (date) document.getElementById('payment_date').value = date;
    });
})();

(function () {
    const methodSelect = document.getElementById('payment_method');
    const otherInput = document.getElementById('payment_method_other');
    const form = document.getElementById('payment-form');
    if (!methodSelect || !otherInput || !form) return;

    methodSelect.addEventListener('change', function () {
        otherInput.classList.toggle('d-none', methodSelect.value !== 'autre');
    });

    form.addEventListener('submit', function () {
        if (methodSelect.value === 'autre') {
            const custom = otherInput.value.trim();
            const option = methodSelect.options[methodSelect.selectedIndex];
            option.value = custom;
        }
    });
})();

(function () {
    const form = document.getElementById('payment-form');
    const amountInput = document.getElementById('payment_amount');
    if (!form || !amountInput) return;

    form.addEventListener('submit', function (e) {
        const amount = parseFloat(amountInput.value) || 0;
        const balanceDue = parseFloat(form.dataset.balanceDue) || 0;
        const isFullSettlement = Math.abs(amount - balanceDue) < 0.01;
        const message = isFullSettlement
            ? 'Ce montant solde entièrement la facture : elle passera au statut "Payée". Confirmer l\'encaissement ?'
            : 'Confirmer l\'enregistrement de cet encaissement ?';
        if (!confirm(message)) {
            e.preventDefault();
        }
    });
})();
</script>
<script>
(function () {
    var formatSelect = document.getElementById('invoiceExportFormat');
```

to:

```blade
<script>
(function () {
    var formatSelect = document.getElementById('invoiceExportFormat');
```

- [ ] **Step 8: Lint and confirm balanced markup**

Run: `php -l app/Http/Controllers/InvoiceController.php && php -l routes/web.php`
Expected: `No syntax errors detected` for both.

Read `resources/views/invoicing/show.blade.php` back in full and confirm: the `<div class="row g-3">` (or equivalent wrapping row — check its exact class in the file) still has a matched set of column `<div>`s, and exactly one `<script>` block remains before `@endsection`.

- [ ] **Step 9: Confirm the removed routes are gone**

Run: `php artisan route:list --name=invoicing.create` and `php artisan route:list --name=invoicing.payments.store` and `php artisan route:list --name=invoicing.cancel`
Expected: no matching routes for all three.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/InvoiceController.php routes/web.php resources/views/invoicing/
git commit -m "feat(erpnext-invoicing): remove local invoice creation/payment/cancellation (ERPNext is now the entry point)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 4: Create the 3 ERPNext Webhooks via the API

**Files:** none (one-off API calls against the ERPNext trial)

**Interfaces:**
- Consumes: the `Webhook` doctype REST API, same pattern used for Stock (including the discovered requirement to pass an explicit `name` field).

- [ ] **Step 1: Create the `Sales Invoice` on_submit webhook**

```bash
php artisan tinker --execute="
\$erpNext = app(\App\Services\ErpNextClient::class);
\$post = new ReflectionMethod(\$erpNext, 'post');
\$post->setAccessible(true);
\$token = config('services.erpnext.webhook_token');
\$result = \$post->invoke(\$erpNext, '/api/resource/Webhook', [
    'name' => 'Sales Invoice soumise vers PME360',
    'webhook_doctype' => 'Sales Invoice',
    'webhook_docevent' => 'on_submit',
    'request_url' => 'https://sitiame-capital.com/webhooks/erpnext/invoicing',
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

- [ ] **Step 2: Create the `Sales Invoice` on_cancel webhook**

Same call, with `name` set to `'Sales Invoice annulée vers PME360'` and `webhook_docevent` changed to `on_cancel` (everything else identical).

- [ ] **Step 3: Create the `Payment Entry` on_submit webhook**

Same call, with `name` set to `'Payment Entry soumis vers PME360'` and `webhook_doctype` changed to `Payment Entry`, `webhook_docevent` back to `on_submit`.

- [ ] **Step 4: Verify all three exist and are enabled**

```bash
php artisan tinker --execute="
\$erpNext = app(\App\Services\ErpNextClient::class);
\$get = new ReflectionMethod(\$erpNext, 'get');
\$get->setAccessible(true);
\$query = http_build_query(['filters' => json_encode([['request_url','=','https://sitiame-capital.com/webhooks/erpnext/invoicing']]), 'fields' => json_encode(['name','webhook_doctype','webhook_docevent','enabled']), 'limit_page_length' => 0]);
\$rows = \$get->invoke(\$erpNext, '/api/resource/Webhook?'.\$query);
foreach (\$rows as \$r) { echo json_encode(\$r).PHP_EOL; }
"
```

Expected: 3 rows, all `enabled: 1`.

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

1. Confirm `/invoicing` no longer shows "Nouvelle Facture", and an existing invoice's page no longer shows "Modifier"/"Supprimer"/payment/cancel forms.
2. On ERPNext, create and submit a `Sales Invoice` for Company "NotifyMails #69" with at least one item and the standard 18% tax template.
3. Within a few seconds, refresh `/invoicing` on PME360 → confirm a new invoice appears with a correctly sequenced `FA-2026-XXXXXX` number, correct client/items/totals, and that the corresponding 411/701 entries appear in `/accounting`.
4. Create a `Payment Entry` against that Sales Invoice on ERPNext, submit it → confirm the PME360 invoice's status updates (partially_paid or paid) and the payment appears in its payment history.
5. Create and submit a second Sales Invoice (unpaid), then cancel it on ERPNext → confirm it shows as `annulée` on PME360.
6. Confirm the webhook security guard: `curl -X POST https://sitiame-capital.com/webhooks/erpnext/invoicing -H "Content-Type: application/json" -d '{"doctype":"Sales Invoice","name":"x","company":"x"}'` (no token) → expect `403`.

No commit for this task (deployment/verification only).

---

## Self-Review Notes

**Spec coverage:** All decisions covered — reuse of `InvoiceService` methods with the new `$skipErpNextSync` flag preserving numbering/accounting (Task 1), 3 webhooks to one route (Task 2, Task 4), company/invoice resolution with silent-200 guards (Task 2), tax-rate/item/date extraction rules from the spec (Task 2's `handleCreation()`), multi-reference Payment Entry handling (Task 2's `handlePayment()` loop), cancellation via `docstatus === 2` detection on the same `Sales Invoice` webhook rather than a separate doctype distinction issue (Task 2's `handle()` dispatch logic), idempotent creation guard via `InvoiceErpNextSync::exists()` (Task 2's `handleCreation()`), form/route removal scoped exactly to create/store/edit/update/storePayment/cancel/destroy while keeping index/show/pdf/export (Task 3). All 6 manual tests from the spec are folded into Task 5 Step 3.

**Placeholder scan:** No TBD/TODO; every step has literal code or literal commands. The `PASTE_SALES_INVOICE_NAME_HERE` markers in Task 2 Step 5 are the same kind of intentional execution-time hand-off already used and accepted in the Stock webhook plan (Task 5's `PASTE_..._HERE` markers there) — not vague placeholders for unwritten logic.

**Type consistency:** `createInvoice(..., string $currency = 'XOF', bool $skipErpNextSync = false)` (Task 1) is called with exactly 13 positional arguments ending in `true` from Task 2's `handleCreation()`. `recordPayment(Invoice $invoice, array $data, int $actorUserId, bool $skipErpNextSync = false)` (Task 1) is called with the same 4-argument shape from Task 2's `handlePayment()`. `cancelInvoice(Invoice $invoice, string $reason, int $actorUserId, bool $skipErpNextSync = false)` (Task 1) matches Task 2's `handleCancellation()` call exactly. `InvoiceErpNextSync` fillable fields (`invoice_id`, `status`, `erpnext_invoice_name`, `erpnext_customer_name`, `last_synced_at` — all pre-existing) match what Task 2's `handleCreation()` writes via `updateOrCreate()`.
