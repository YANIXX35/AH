# ERPNext Test Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an isolated, admin-only "ERPNext Test" dashboard that creates real Sales Invoices in ERPNext via its REST API (auto-provisioning Customer + Item, using a default warehouse and tax template) and stores a structured local copy in PME360's own database, without touching any existing dashboard, route, or model.

**Architecture:** A single new `ErpNextClient` service (config-driven, `Http` facade, same pattern as `OcrService`/`CinetPayService`) exposes `findOrCreateCustomer()`, `findOrCreateItem()`, and `createSalesInvoice()`. A new `Admin\ErpNextTestController` orchestrates the flow behind the existing `platform.admin` middleware, persisting a `pending` row before calling the API so failures are never silent, then updating it to `synced`/`failed`.

**Tech Stack:** Laravel 13, PHP 8.4, MySQL, Laravel `Http` facade (Guzzle under the hood), Blade views, vanilla JS for repeatable invoice-line rows (no new JS dependency).

## Global Constraints

- Zero modification to existing dashboards, routes, models, or views outside the additions listed below — spec section "Contrainte stricte".
- All new DB tables are prefixed `erpnext_test_` to mark them as isolated/experimental — spec "Données".
- Admin-only access via the existing `platform.admin` middleware alias (`app/Http/Middleware/EnsurePlatformAdmin.php`) — spec "Accès et routes".
- PME360 form stays simple (PME select + free-text lines); all ERPNext field parity (Item, Warehouse, Tax) is handled by the service layer, invisible to the user — spec "Principe directeur".
- No automated PHPUnit run is possible on this machine (PHP 8.2 installed vs 8.4 required by dependencies) — every task's verification step is manual, per spec "Tests".
- Currency is always XOF; `due_date` is fixed at `posting_date + 30 days` for this v1 (no due-date field in the form) — spec "Service: ErpNextClient".

---

## Task 1: Config, exception, and database schema

**Files:**
- Modify: `config/services.php` (add `erpnext` key)
- Modify: `.env.example` (add 7 ERPNext variables)
- Create: `app/Exceptions/ErpNextApiException.php`
- Create: `database/migrations/2026_09_11_090000_add_erpnext_customer_id_to_users_table.php`
- Create: `database/migrations/2026_09_11_090100_create_erpnext_test_invoices_table.php`
- Create: `database/migrations/2026_09_11_090200_create_erpnext_test_invoice_items_table.php`

**Interfaces:**
- Produces: `config('services.erpnext.*')` keys (`base_url`, `api_key`, `api_secret`, `timeout`, `default_warehouse`, `default_tax_template`, `default_item_group`), consumed by Task 2's `ErpNextClient`.
- Produces: `ErpNextApiException` class (extends `\RuntimeException`, no extra methods), consumed by Task 2 and Task 3.
- Produces: `users.erpnext_customer_id` column, `erpnext_test_invoices` table, `erpnext_test_invoice_items` table — consumed by Task 3's models and controller.

- [ ] **Step 1: Add the `erpnext` config block**

In `config/services.php`, add this array entry alongside the existing `cinetpay`/`ocr_space` entries (append at the end of the returned array, before the closing `];`):

```php
    'erpnext' => [
        'base_url' => env('ERPNEXT_BASE_URL'),
        'api_key' => env('ERPNEXT_API_KEY'),
        'api_secret' => env('ERPNEXT_API_SECRET'),
        'timeout' => env('ERPNEXT_TIMEOUT', 15),
        'default_warehouse' => env('ERPNEXT_DEFAULT_WAREHOUSE'),
        'default_tax_template' => env('ERPNEXT_DEFAULT_TAX_TEMPLATE'),
        'default_item_group' => env('ERPNEXT_DEFAULT_ITEM_GROUP', 'All Item Groups'),
    ],
```

- [ ] **Step 2: Add the ERPNext variables to `.env.example`**

Append to `.env.example`:

```
ERPNEXT_BASE_URL=
ERPNEXT_API_KEY=
ERPNEXT_API_SECRET=
ERPNEXT_TIMEOUT=15
ERPNEXT_DEFAULT_WAREHOUSE=
ERPNEXT_DEFAULT_TAX_TEMPLATE=
ERPNEXT_DEFAULT_ITEM_GROUP="All Item Groups"
```

- [ ] **Step 3: Create the exception class**

```php
<?php

namespace App\Exceptions;

class ErpNextApiException extends \RuntimeException
{
}
```

Save as `app/Exceptions/ErpNextApiException.php`.

- [ ] **Step 4: Create the `users.erpnext_customer_id` migration**

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
            $table->string('erpnext_customer_id')->nullable()->after('primary_activity_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('erpnext_customer_id');
        });
    }
};
```

Save as `database/migrations/2026_09_11_090000_add_erpnext_customer_id_to_users_table.php`.

- [ ] **Step 5: Create the `erpnext_test_invoices` migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erpnext_test_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('erpnext_invoice_name')->nullable();
            $table->string('status', 16)->default('pending');
            $table->text('error_message')->nullable();
            $table->decimal('total_before_tax', 15, 2)->nullable();
            $table->decimal('total_taxes', 15, 2)->nullable();
            $table->decimal('grand_total', 15, 2)->nullable();
            $table->decimal('outstanding_amount', 15, 2)->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erpnext_test_invoices');
    }
};
```

Save as `database/migrations/2026_09_11_090100_create_erpnext_test_invoices_table.php`.

- [ ] **Step 6: Create the `erpnext_test_invoice_items` migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erpnext_test_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('erpnext_test_invoice_id')->constrained('erpnext_test_invoices')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erpnext_test_invoice_items');
    }
};
```

Save as `database/migrations/2026_09_11_090200_create_erpnext_test_invoice_items_table.php`.

- [ ] **Step 7: Run the migrations**

Run: `php artisan migrate`
Expected: output lists the 3 new migrations as `Migrated` with no errors.

- [ ] **Step 8: Commit**

```bash
git add config/services.php .env.example app/Exceptions/ErpNextApiException.php database/migrations/2026_09_11_090000_add_erpnext_customer_id_to_users_table.php database/migrations/2026_09_11_090100_create_erpnext_test_invoices_table.php database/migrations/2026_09_11_090200_create_erpnext_test_invoice_items_table.php
git commit -m "feat(erpnext-test): add config, exception, and database schema

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 2: `ErpNextClient` service

**Files:**
- Create: `app/Services/ErpNextClient.php`
- Modify: `app/Models/User.php` (add `erpnext_customer_id` to `$fillable`)

**Interfaces:**
- Consumes: `config('services.erpnext.*')` from Task 1, `ErpNextApiException` from Task 1.
- Produces:
  - `ErpNextClient::enabled(): bool`
  - `ErpNextClient::findOrCreateCustomer(User $pme): string` — returns the ERPNext Customer name (id), and persists it onto `$pme->erpnext_customer_id` (saves the model).
  - `ErpNextClient::findOrCreateItem(string $description): string` — returns the ERPNext Item code.
  - `ErpNextClient::createSalesInvoice(User $pme, string $erpNextCustomerName, array $lines): array` — `$lines` is `[['description' => string, 'quantity' => float, 'unit_price' => float], ...]`; returns the decoded ERPNext Sales Invoice response array (must contain `name`, `total`, `total_taxes_and_charges`, `grand_total`, `outstanding_amount`).
  - Throws `ErpNextApiException` on any HTTP failure, timeout, or non-2xx ERPNext response (message = ERPNext's own error text when available, otherwise a generic description).
  - Consumed by Task 3's controller.

- [ ] **Step 1: Add `erpnext_customer_id` to `User`'s fillable array**

In `app/Models/User.php`, find the `protected $fillable = [` array (around line 17) and add `'erpnext_customer_id',` as a new entry, near the other company-identity fields like `company_tax_id`.

- [ ] **Step 2: Write `ErpNextClient` with the request/response helper**

```php
<?php

namespace App\Services;

use App\Exceptions\ErpNextApiException;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ErpNextClient
{
    public function enabled(): bool
    {
        return trim((string) config('services.erpnext.base_url', '')) !== ''
            && trim((string) config('services.erpnext.api_key', '')) !== ''
            && trim((string) config('services.erpnext.api_secret', '')) !== '';
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.erpnext.base_url', ''), '/');
    }

    private function authHeader(): string
    {
        return 'token '.config('services.erpnext.api_key').':'.config('services.erpnext.api_secret');
    }

    private function timeout(): int
    {
        return max(5, (int) config('services.erpnext.timeout', 15));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function get(string $path): array
    {
        try {
            $response = Http::withHeaders(['Authorization' => $this->authHeader()])
                ->timeout($this->timeout())
                ->get($this->baseUrl().$path);
        } catch (\Throwable $exception) {
            throw new ErpNextApiException('ERPNext injoignable: '.$exception->getMessage());
        }

        if ($response->status() === 404) {
            return [];
        }

        if ($response->failed()) {
            throw new ErpNextApiException($this->extractErrorMessage($response));
        }

        return (array) ($response->json('data') ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        try {
            $response = Http::withHeaders(['Authorization' => $this->authHeader()])
                ->timeout($this->timeout())
                ->post($this->baseUrl().$path, $payload);
        } catch (\Throwable $exception) {
            throw new ErpNextApiException('ERPNext injoignable: '.$exception->getMessage());
        }

        if ($response->failed()) {
            throw new ErpNextApiException($this->extractErrorMessage($response));
        }

        return (array) ($response->json('data') ?? []);
    }

    private function extractErrorMessage(\Illuminate\Http\Client\Response $response): string
    {
        $message = $response->json('exception') ?? $response->json('message') ?? $response->json('_server_messages');

        if (is_string($message) && $message !== '') {
            return $message;
        }

        return 'ERPNext a répondu avec une erreur HTTP '.$response->status().'.';
    }
}
```

Save as `app/Services/ErpNextClient.php`. This step only sets up the private HTTP helpers — the public methods are added in the next steps.

- [ ] **Step 3: Add `findOrCreateCustomer()`**

Add this public method to `ErpNextClient` (after `enabled()`):

```php
    public function findOrCreateCustomer(User $pme): string
    {
        if ($pme->erpnext_customer_id) {
            $existing = $this->get('/api/resource/Customer/'.rawurlencode($pme->erpnext_customer_id));
            if (! empty($existing)) {
                return $pme->erpnext_customer_id;
            }
        }

        $customerName = $pme->company_name ?: $pme->name;

        $created = $this->post('/api/resource/Customer', [
            'customer_name' => $customerName,
            'customer_group' => 'All Customer Groups',
            'territory' => 'All Territories',
        ]);

        $erpNextId = (string) ($created['name'] ?? '');
        if ($erpNextId === '') {
            throw new ErpNextApiException('ERPNext n\'a pas renvoyé de nom de client après création.');
        }

        $pme->erpnext_customer_id = $erpNextId;
        $pme->save();

        return $erpNextId;
    }
```

- [ ] **Step 4: Add `findOrCreateItem()`**

```php
    public function findOrCreateItem(string $description): string
    {
        $itemCode = Str::limit(Str::slug($description), 140, '');
        if ($itemCode === '') {
            $itemCode = 'article-'.Str::random(8);
        }

        $existing = $this->get('/api/resource/Item/'.rawurlencode($itemCode));
        if (! empty($existing)) {
            return $itemCode;
        }

        $created = $this->post('/api/resource/Item', [
            'item_code' => $itemCode,
            'item_name' => $description,
            'item_group' => config('services.erpnext.default_item_group', 'All Item Groups'),
            'stock_uom' => 'Unité',
            'is_stock_item' => 1,
        ]);

        return (string) ($created['item_code'] ?? $itemCode);
    }
```

- [ ] **Step 5: Add `createSalesInvoice()`**

```php
    /**
     * @param  array<int, array{description: string, quantity: float, unit_price: float}>  $lines
     * @return array<string, mixed>
     */
    public function createSalesInvoice(User $pme, string $erpNextCustomerName, array $lines): array
    {
        $items = [];
        foreach ($lines as $line) {
            $itemCode = $this->findOrCreateItem($line['description']);
            $items[] = [
                'item_code' => $itemCode,
                'qty' => $line['quantity'],
                'rate' => $line['unit_price'],
                'warehouse' => config('services.erpnext.default_warehouse'),
            ];
        }

        $payload = [
            'customer' => $erpNextCustomerName,
            'items' => $items,
            'posting_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ];

        $taxTemplate = config('services.erpnext.default_tax_template');
        if (! empty($taxTemplate)) {
            $payload['taxes_and_charges'] = $taxTemplate;
        }

        return $this->post('/api/resource/Sales Invoice', $payload);
    }
```

- [ ] **Step 6: Manual smoke test — config detection**

Run: `php artisan tinker --execute="echo (new App\Services\ErpNextClient())->enabled() ? 'enabled' : 'disabled';"`
Expected: prints `disabled` (since `.env` has no real ERPNext credentials yet at this point in the plan — this confirms the `enabled()` gate works before any real credentials exist).

- [ ] **Step 7: Commit**

```bash
git add app/Services/ErpNextClient.php app/Models/User.php
git commit -m "feat(erpnext-test): add ErpNextClient service (Customer/Item/Invoice provisioning)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 3: Eloquent models

**Files:**
- Create: `app/Models/ErpNextTestInvoice.php`
- Create: `app/Models/ErpNextTestInvoiceItem.php`

**Interfaces:**
- Consumes: `erpnext_test_invoices`/`erpnext_test_invoice_items` tables from Task 1, `App\Models\User`.
- Produces: `ErpNextTestInvoice` (relations `user()`, `createdBy()`, `items()`; fillable includes all non-timestamp columns), `ErpNextTestInvoiceItem` (relation `invoice()`; fillable includes all non-timestamp columns) — consumed by Task 4's controller and views.

- [ ] **Step 1: Create `ErpNextTestInvoice`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpNextTestInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'created_by_user_id',
        'erpnext_invoice_name',
        'status',
        'error_message',
        'total_before_tax',
        'total_taxes',
        'grand_total',
        'outstanding_amount',
        'raw_response',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'total_before_tax' => 'decimal:2',
        'total_taxes' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ErpNextTestInvoiceItem::class);
    }
}
```

Save as `app/Models/ErpNextTestInvoice.php`.

- [ ] **Step 2: Create `ErpNextTestInvoiceItem`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpNextTestInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'erpnext_test_invoice_id',
        'description',
        'quantity',
        'unit_price',
        'amount',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ErpNextTestInvoice::class, 'erpnext_test_invoice_id');
    }
}
```

Save as `app/Models/ErpNextTestInvoiceItem.php`.

- [ ] **Step 3: Manual smoke test**

Run: `php artisan tinker --execute="echo class_exists(App\Models\ErpNextTestInvoice::class) && class_exists(App\Models\ErpNextTestInvoiceItem::class) ? 'ok' : 'fail';"`
Expected: prints `ok`.

- [ ] **Step 4: Commit**

```bash
git add app/Models/ErpNextTestInvoice.php app/Models/ErpNextTestInvoiceItem.php
git commit -m "feat(erpnext-test): add ErpNextTestInvoice and ErpNextTestInvoiceItem models

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 4: Controller, routes, and views

**Files:**
- Create: `app/Http/Controllers/Admin/ErpNextTestController.php`
- Modify: `routes/web.php` (add `erpnext-test` sub-group inside the existing `admin.` group)
- Create: `resources/views/admin/erpnext-test/index.blade.php`
- Create: `resources/views/admin/erpnext-test/create.blade.php`
- Create: `resources/views/admin/erpnext-test/show.blade.php`
- Modify: `resources/views/layouts/partials/sidebar.blade.php` (add one admin nav link)

**Interfaces:**
- Consumes: `ErpNextClient` (Task 2), `ErpNextTestInvoice`/`ErpNextTestInvoiceItem` (Task 3), `User::scopeClients()` (`app/Models/User.php:181`, pre-existing).
- Produces: routes `admin.erpnext-test.index`, `admin.erpnext-test.create`, `admin.erpnext-test.store`, `admin.erpnext-test.show`.

- [ ] **Step 1: Locate the existing admin route group and add the sub-group**

Open `routes/web.php`, find the block starting at line 444 (`Route::middleware('platform.admin')->prefix('admin')->name('admin.')->group(function () {`). Add this sub-group as the **last** entry inside that closure, right before its closing `});`:

```php
    Route::prefix('erpnext-test')->name('erpnext-test.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\ErpNextTestController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Admin\ErpNextTestController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Admin\ErpNextTestController::class, 'store'])->name('store');
        Route::get('/{erpNextTestInvoice}', [\App\Http\Controllers\Admin\ErpNextTestController::class, 'show'])->name('show');
    });
```

- [ ] **Step 2: Write the controller**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ErpNextApiException;
use App\Http\Controllers\Controller;
use App\Models\ErpNextTestInvoice;
use App\Models\User;
use App\Services\ErpNextClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ErpNextTestController extends Controller
{
    public function index(): View
    {
        $invoices = ErpNextTestInvoice::with('user')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.erpnext-test.index', compact('invoices'));
    }

    public function create(): View
    {
        $pmes = User::clients()->orderBy('company_name')->get();

        return view('admin.erpnext-test.create', compact('pmes'));
    }

    public function store(Request $request, ErpNextClient $erpNext): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $pme = User::findOrFail($validated['user_id']);

        $invoice = ErpNextTestInvoice::create([
            'user_id' => $pme->id,
            'created_by_user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        foreach ($validated['lines'] as $line) {
            $invoice->items()->create([
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'amount' => $line['quantity'] * $line['unit_price'],
            ]);
        }

        try {
            $customerName = $erpNext->findOrCreateCustomer($pme);
            $response = $erpNext->createSalesInvoice($pme, $customerName, $validated['lines']);

            $invoice->update([
                'status' => 'synced',
                'erpnext_invoice_name' => $response['name'] ?? null,
                'total_before_tax' => $response['total'] ?? null,
                'total_taxes' => $response['total_taxes_and_charges'] ?? null,
                'grand_total' => $response['grand_total'] ?? null,
                'outstanding_amount' => $response['outstanding_amount'] ?? null,
                'raw_response' => $response,
            ]);
        } catch (ErpNextApiException|\Throwable $exception) {
            $invoice->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('admin.erpnext-test.show', $invoice);
    }

    public function show(ErpNextTestInvoice $erpNextTestInvoice): View
    {
        $erpNextTestInvoice->load(['user', 'createdBy', 'items']);

        return view('admin.erpnext-test.show', ['invoice' => $erpNextTestInvoice]);
    }
}
```

Save as `app/Http/Controllers/Admin/ErpNextTestController.php`.

- [ ] **Step 3: Write the index view**

```blade
@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">ERPNext Test — Factures</h1>
        <a href="{{ route('admin.erpnext-test.create') }}" class="btn btn-primary">Nouvelle facture test</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>PME</th>
                        <th>Statut</th>
                        <th>N° ERPNext</th>
                        <th>Total TTC</th>
                        <th>Créée le</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td>{{ $invoice->id }}</td>
                            <td>{{ $invoice->user?->company_name ?? $invoice->user?->name }}</td>
                            <td>
                                @if ($invoice->status === 'synced')
                                    <span class="badge bg-success">Synchronisée</span>
                                @elseif ($invoice->status === 'failed')
                                    <span class="badge bg-danger">Échec</span>
                                @else
                                    <span class="badge bg-secondary">En cours</span>
                                @endif
                            </td>
                            <td>{{ $invoice->erpnext_invoice_name ?? '—' }}</td>
                            <td>{{ $invoice->grand_total !== null ? number_format((float) $invoice->grand_total, 0, ',', ' ').' XOF' : '—' }}</td>
                            <td>{{ $invoice->created_at->format('d/m/Y H:i') }}</td>
                            <td><a href="{{ route('admin.erpnext-test.show', $invoice) }}">Voir</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucune facture test pour le moment.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
```

Save as `resources/views/admin/erpnext-test/index.blade.php`.

- [ ] **Step 4: Write the create view**

```blade
@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <h1 class="h3 mb-3">Nouvelle facture test ERPNext</h1>

    <form method="POST" action="{{ route('admin.erpnext-test.store') }}">
        @csrf

        <div class="mb-3">
            <label class="form-label" for="user_id">PME</label>
            <select class="form-select" id="user_id" name="user_id" required>
                <option value="">— Choisir une PME —</option>
                @foreach ($pmes as $pme)
                    <option value="{{ $pme->id }}">{{ $pme->company_name ?? $pme->name }}</option>
                @endforeach
            </select>
        </div>

        <label class="form-label">Lignes de facture</label>
        <div id="lines-container">
            <div class="row g-2 mb-2 line-row">
                <div class="col-6"><input type="text" class="form-control" name="lines[0][description]" placeholder="Désignation" required></div>
                <div class="col-2"><input type="number" step="0.01" min="0.01" class="form-control" name="lines[0][quantity]" placeholder="Qté" required></div>
                <div class="col-3"><input type="number" step="0.01" min="0" class="form-control" name="lines[0][unit_price]" placeholder="Prix unitaire (XOF)" required></div>
            </div>
        </div>
        <button type="button" id="add-line" class="btn btn-outline-secondary btn-sm mb-3">+ Ajouter une ligne</button>

        <div>
            <button type="submit" class="btn btn-primary">Créer la facture (ERPNext + copie locale)</button>
        </div>
    </form>
</div>

<script>
    (function () {
        var container = document.getElementById('lines-container');
        var addButton = document.getElementById('add-line');
        var index = 1;

        addButton.addEventListener('click', function () {
            var row = document.createElement('div');
            row.className = 'row g-2 mb-2 line-row';
            row.innerHTML =
                '<div class="col-6"><input type="text" class="form-control" name="lines[' + index + '][description]" placeholder="Désignation" required></div>' +
                '<div class="col-2"><input type="number" step="0.01" min="0.01" class="form-control" name="lines[' + index + '][quantity]" placeholder="Qté" required></div>' +
                '<div class="col-3"><input type="number" step="0.01" min="0" class="form-control" name="lines[' + index + '][unit_price]" placeholder="Prix unitaire (XOF)" required></div>';
            container.appendChild(row);
            index += 1;
        });
    })();
</script>
@endsection
```

Save as `resources/views/admin/erpnext-test/create.blade.php`.

- [ ] **Step 5: Write the show view**

```blade
@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Facture test #{{ $invoice->id }}</h1>
        <a href="{{ route('admin.erpnext-test.index') }}">← Retour à la liste</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <p><strong>PME :</strong> {{ $invoice->user?->company_name ?? $invoice->user?->name }}</p>
            <p><strong>Statut :</strong>
                @if ($invoice->status === 'synced')
                    <span class="badge bg-success">Synchronisée</span>
                @elseif ($invoice->status === 'failed')
                    <span class="badge bg-danger">Échec</span>
                @else
                    <span class="badge bg-secondary">En cours</span>
                @endif
            </p>

            @if ($invoice->status === 'failed')
                <div class="alert alert-danger">{{ $invoice->error_message }}</div>
            @endif

            @if ($invoice->status === 'synced')
                <p><strong>N° facture ERPNext :</strong> {{ $invoice->erpnext_invoice_name }}</p>
                <p><strong>Total avant taxes :</strong> {{ number_format((float) $invoice->total_before_tax, 0, ',', ' ') }} XOF</p>
                <p><strong>Total taxes :</strong> {{ number_format((float) $invoice->total_taxes, 0, ',', ' ') }} XOF</p>
                <p><strong>Total TTC :</strong> {{ number_format((float) $invoice->grand_total, 0, ',', ' ') }} XOF</p>
                <p><strong>Montant dû :</strong> {{ number_format((float) $invoice->outstanding_amount, 0, ',', ' ') }} XOF</p>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Lignes saisies dans PME360</div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Désignation</th><th>Quantité</th><th>Prix unitaire</th><th>Montant</th></tr></thead>
                <tbody>
                    @foreach ($invoice->items as $item)
                        <tr>
                            <td>{{ $item->description }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ number_format((float) $item->unit_price, 0, ',', ' ') }} XOF</td>
                            <td>{{ number_format((float) $item->amount, 0, ',', ' ') }} XOF</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($invoice->raw_response)
        <div class="card">
            <div class="card-header">Réponse brute ERPNext (debug)</div>
            <div class="card-body">
                <pre class="mb-0" style="white-space: pre-wrap;">{{ json_encode($invoice->raw_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </div>
    @endif
</div>
@endsection
```

Save as `resources/views/admin/erpnext-test/show.blade.php`.

- [ ] **Step 6: Add the sidebar link**

Open `resources/views/layouts/partials/sidebar.blade.php`, find the admin section (the block guarded by an admin-only check, near other `admin.*` links). Add this entry alongside the existing admin links:

```blade
<li class="sidebar-item">
    <a class="sidebar-link {{ request()->routeIs('admin.erpnext-test.*') ? 'active' : '' }}" href="{{ route('admin.erpnext-test.index') }}">
        <i class="align-middle" data-feather="package" style="width:14px;height:14px;"></i> <span class="align-middle">ERPNext Test</span>
    </a>
</li>
```

- [ ] **Step 7: Manual verification — routes registered**

Run: `php artisan route:list --name=admin.erpnext-test`
Expected: 4 rows listed (`index`, `create`, `store`, `show`) all under `admin.erpnext-test.*`, all with `platform.admin` in their middleware.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/ErpNextTestController.php routes/web.php resources/views/admin/erpnext-test resources/views/layouts/partials/sidebar.blade.php
git commit -m "feat(erpnext-test): add admin controller, routes, and views

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 5: Manual end-to-end verification (with real ERPNext credentials)

**Files:** None (configuration + manual testing only — no code changes).

**Interfaces:**
- Consumes: everything from Tasks 1–4.
- Produces: nothing new — this task validates the feature works against the real ERPNext trial site.

- [ ] **Step 1: Generate ERPNext API credentials**

On `https://sitiame-erp-essai.z.frappe.cloud`, go to user profile → "My Settings" → "API Access" → "Generate Keys". Copy the API Key and API Secret immediately (the secret is shown only once).

- [ ] **Step 2: Create the default Warehouse and Tax Template in ERPNext**

In ERPNext: `Stock → Warehouse → New`, create one (e.g. name it "Magasin Principal"). Then `Accounting → Taxes → Sales Taxes and Charges Template → New`, create one row referencing an existing TVA-collectée account (e.g. account 4431 from the chart of accounts), rate 18%. Note the exact full names ERPNext assigns to both (visible in their list view, typically `"Magasin Principal - SC"` and `"<template name> - SC"`).

- [ ] **Step 3: Fill in real `.env` values**

```
ERPNEXT_BASE_URL=https://sitiame-erp-essai.z.frappe.cloud
ERPNEXT_API_KEY=<the generated key>
ERPNEXT_API_SECRET=<the generated secret>
ERPNEXT_DEFAULT_WAREHOUSE=<exact warehouse name from Step 2>
ERPNEXT_DEFAULT_TAX_TEMPLATE=<exact tax template name from Step 2>
```

Run: `php artisan config:clear` (so the new `.env` values are picked up).

- [ ] **Step 4: Create a test invoice through the UI**

Log in to PME360 as a platform admin, navigate to `/admin/erpnext-test/create`, pick an existing PME, add 2 lines (e.g. "Prestation de conseil", qty 1, price 100000; "Frais de dossier", qty 1, price 25000), submit.

Expected: redirected to the detail page showing status "Synchronisée", a real `erpnext_invoice_name` (e.g. `ACC-SINV-2026-00XXX`), and non-null totals with `total_taxes` > 0.

- [ ] **Step 5: Cross-check in ERPNext directly**

In ERPNext, go to `Accounting → Invoicing → Sales Invoice`, open the invoice with the matching name from Step 4.

Expected: the Customer matches the PME's company name, both item lines are present with the correct warehouse, the Sales Taxes and Charges table shows the 18% line, and the Grand Total matches what PME360 displayed.

- [ ] **Step 6: Verify Customer/Item reuse on a second submission**

Submit a second test invoice for the **same PME** with a line using the **exact same description** as Step 4.

Expected: in ERPNext, `Selling → Customer` list still shows only one Customer for that PME (no duplicate), and `Stock → Item` list still shows only one Item for that description (no duplicate) — confirms `findOrCreateCustomer`/`findOrCreateItem` correctly reused the existing records.

- [ ] **Step 7: Verify failure handling**

Temporarily set `ERPNEXT_API_KEY=invalid` in `.env`, run `php artisan config:clear`, submit a new test invoice.

Expected: redirected to the detail page showing status "Échec" with a readable error message; in ERPNext, no new Sales Invoice was created for this attempt. Restore the correct `ERPNEXT_API_KEY` afterward and run `php artisan config:clear` again.
