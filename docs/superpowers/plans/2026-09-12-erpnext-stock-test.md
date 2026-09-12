# Stock ERPNext Test Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Connect PME360's Stock module to ERPNext's real inventory mechanism (Item, Stock Entry, Stock Reconciliation, Bin) in a new isolated admin test dashboard `/admin/erpnext-stock-test`, following exactly the same pattern as the 5 prior ERPNext test sub-projects.

**Architecture:** Extend `ErpNextClient` (`app/Services/ErpNextClient.php`) with 3 new public methods reusing the existing private HTTP helpers (`get`/`post`/`put`) and the existing `findOrCreateItem()`/`findAccountByNumber()` helpers. Extend `provisionCompanyForPme()` to also set `stock_adjustment_account` on the Company. Add a new `ErpNextStockTestController` + 3 Blade views + routes + 1 sidebar link, mirroring `ErpNextAccountingTestController` exactly.

**Tech Stack:** Laravel 13 / PHP 8.4, Blade + Bootstrap, ERPNext REST API (`/api/resource/...`), no new PHP packages.

## Global Constraints

- All stock operations in this sub-project use the PME's existing warehouse `$pme->erpnext_warehouse` ("Magasin principal - {abbr}") — never a warehouse selector in any form, and never ERPNext's default warehouses (Stores/Work In Progress/Finished Goods/Goods In Transit), which lack an `account` field and fail Stock Entry submission.
- `ajustement` maps to `Stock Reconciliation` (absolute quantity), not `Stock Entry` (relative movement).
- Every created transactional document (`Stock Entry`, `Stock Reconciliation`) must be explicitly submitted via a `PUT` with `docstatus: 1` — Frappe never auto-submits.
- Do not modify `StockController`, `StockService`, `StockProduct`, or `StockMovement` (local PME360 Stock module stays untouched).
- Company needs `stock_adjustment_account` set (class 603x SYSCOHADA account, e.g. `6031-...`) before any Stock Entry can submit — resolved via the existing `findAccountByNumber($company, '6031')` pattern.
- Doctype names containing spaces (`Stock Entry`, `Stock Reconciliation`) must be `rawurlencode()`d in every API path, exactly as done for `Journal Entry`/`Bank Transaction` elsewhere in `ErpNextClient`.

---

### Task 1: Extend `provisionCompanyForPme()` to set `stock_adjustment_account`

**Files:**
- Modify: `app/Services/ErpNextClient.php:234-286` (inside `provisionCompanyForPme()`)

**Interfaces:**
- Consumes: existing private `findAccountByNumber(string $company, string $accountNumber): string` (already defined at `ErpNextClient.php:341`), existing private `put(string $path, array $payload): array`.
- Produces: no new public interface — `provisionCompanyForPme()` keeps its exact existing return shape `array{company, warehouse, tax_template, income_account}`. Later tasks rely on the Company already having `stock_adjustment_account` set whenever they operate on a PME provisioned after this change.

- [ ] **Step 1: Read the current method to confirm exact line numbers before editing**

Run: view `app/Services/ErpNextClient.php` lines 210-287 (already read in full above — the account resolution block is at lines 234-236, and the final `round_off_account` PUT is at lines 277-279).

- [ ] **Step 2: Add stock account resolution next to the existing account resolution block**

In `provisionCompanyForPme()`, change:

```php
        $incomeAccount = $this->findAccountByNumber($resolvedCompanyName, '7061');
        $taxAccount = $this->findAccountByNumber($resolvedCompanyName, '4431');
        $stockAccount = $this->findAccountByNumber($resolvedCompanyName, '3111');
```

to:

```php
        $incomeAccount = $this->findAccountByNumber($resolvedCompanyName, '7061');
        $taxAccount = $this->findAccountByNumber($resolvedCompanyName, '4431');
        $stockAccount = $this->findAccountByNumber($resolvedCompanyName, '3111');
        $stockAdjustmentAccount = $this->findAccountByNumber($resolvedCompanyName, '6031');
```

- [ ] **Step 3: Set the new field alongside the existing `round_off_account` PUT**

Change:

```php
        $this->put('/api/resource/Company/'.rawurlencode($resolvedCompanyName), [
            'round_off_account' => $incomeAccount,
        ]);
```

to:

```php
        $this->put('/api/resource/Company/'.rawurlencode($resolvedCompanyName), [
            'round_off_account' => $incomeAccount,
            'stock_adjustment_account' => $stockAdjustmentAccount,
        ]);
```

- [ ] **Step 4: Manual verification (no automated test suite runs locally — PHP 8.2 installed vs 8.4 required, same constraint as the rest of this project)**

Run in `php artisan tinker` against a PME that has NOT yet been provisioned (or a fresh test user), or re-run provisioning for an existing one:

```php
$erpNext = app(\App\Services\ErpNextClient::class);
$pme = \App\Models\User::find(69); // NotifyMails, already provisioned
$result = $erpNext->provisionCompanyForPme($pme);
$company = (new \ReflectionClass($erpNext))->getMethod('get');
$company->setAccessible(true);
$data = $company->invoke($erpNext, '/api/resource/Company/'.rawurlencode($result['company']));
echo $data['stock_adjustment_account'];
```

Expected: prints a class 603x account name (e.g. `6031-Variations des stocks de marchandises - NOT69`), no exception thrown.

- [ ] **Step 5: Commit**

```bash
git add app/Services/ErpNextClient.php
git commit -m "feat(erpnext-stock): set stock_adjustment_account during company provisioning

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 2: Add `createStockMovementForPme()` to `ErpNextClient`

**Files:**
- Modify: `app/Services/ErpNextClient.php` (add new public method after `reconcileBankTransactionForPme()`, i.e. after line 778, before the closing `}` of the class)

**Interfaces:**
- Consumes: existing `findOrCreateItem(string $description): string` (line 362), existing private `post()`/`put()`.
- Produces: `public function createStockMovementForPme(User $pme, string $itemDescription, float $quantity, float $unitRate, string $direction): array` — `$direction` is `'in'` or `'out'`. Returns the submitted `Stock Entry` document array (has at least `name`, `docstatus`). Used by Task 4 (controller).

- [ ] **Step 1: Add the method**

Insert into `app/Services/ErpNextClient.php`, immediately before the final closing `}` of the class (after `reconcileBankTransactionForPme()`):

```php
    /**
     * @return array<string, mixed>
     */
    public function createStockMovementForPme(User $pme, string $itemDescription, float $quantity, float $unitRate, string $direction): array
    {
        $itemCode = $this->findOrCreateItem($itemDescription);

        $item = [
            'item_code' => $itemCode,
            'qty' => $quantity,
            'basic_rate' => $unitRate,
        ];

        if ($direction === 'in') {
            $item['t_warehouse'] = $pme->erpnext_warehouse;
        } else {
            $item['s_warehouse'] = $pme->erpnext_warehouse;
        }

        $created = $this->post('/api/resource/'.rawurlencode('Stock Entry'), [
            'stock_entry_type' => $direction === 'in' ? 'Material Receipt' : 'Material Issue',
            'company' => $pme->erpnext_company_name,
            'items' => [$item],
        ]);

        $stockEntryName = (string) ($created['name'] ?? '');
        if ($stockEntryName === '') {
            throw new ErpNextApiException('ERPNext n\'a pas renvoyé de nom de mouvement de stock après création.');
        }

        return $this->put('/api/resource/'.rawurlencode('Stock Entry').'/'.rawurlencode($stockEntryName), [
            'docstatus' => 1,
        ]);
    }
```

- [ ] **Step 2: Manual verification via tinker**

```bash
php artisan tinker
```

```php
$erpNext = app(\App\Services\ErpNextClient::class);
$pme = \App\Models\User::find(69);
$result = $erpNext->createStockMovementForPme($pme, 'Produit test plan stock', 10, 5000, 'in');
echo $result['docstatus']; // expect 1
echo $result['name'];
```

Expected: no exception, `docstatus` prints `1`.

- [ ] **Step 3: Commit**

```bash
git add app/Services/ErpNextClient.php
git commit -m "feat(erpnext-stock): add createStockMovementForPme for stock entry/exit

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 3: Add `adjustStockForPme()` and `getStockLevelsForCompany()` to `ErpNextClient`

**Files:**
- Modify: `app/Services/ErpNextClient.php` (add both methods after `createStockMovementForPme()` from Task 2)

**Interfaces:**
- Consumes: existing `findOrCreateItem()`, private `post()`/`put()`/`get()`.
- Produces:
  - `public function adjustStockForPme(User $pme, string $itemDescription, float $newQuantity): array` — creates+submits a `Stock Reconciliation`, returns the submitted document array.
  - `public function getStockLevelsForCompany(User $pme): array` — returns `array<int, array{item_code: string, warehouse: string, actual_qty: float}>` from `Bin`, filtered on the PME's warehouse. Used by Task 4 (controller) to show stock levels.

- [ ] **Step 1: Add `adjustStockForPme()`**

```php
    /**
     * @return array<string, mixed>
     */
    public function adjustStockForPme(User $pme, string $itemDescription, float $newQuantity): array
    {
        $itemCode = $this->findOrCreateItem($itemDescription);

        $created = $this->post('/api/resource/'.rawurlencode('Stock Reconciliation'), [
            'company' => $pme->erpnext_company_name,
            'purpose' => 'Stock Reconciliation',
            'items' => [
                [
                    'item_code' => $itemCode,
                    'warehouse' => $pme->erpnext_warehouse,
                    'qty' => $newQuantity,
                ],
            ],
        ]);

        $stockReconciliationName = (string) ($created['name'] ?? '');
        if ($stockReconciliationName === '') {
            throw new ErpNextApiException('ERPNext n\'a pas renvoyé de nom d\'ajustement de stock après création.');
        }

        return $this->put('/api/resource/'.rawurlencode('Stock Reconciliation').'/'.rawurlencode($stockReconciliationName), [
            'docstatus' => 1,
        ]);
    }
```

- [ ] **Step 2: Add `getStockLevelsForCompany()`**

```php
    /**
     * @return array<int, array{item_code: string, warehouse: string, actual_qty: float}>
     */
    public function getStockLevelsForCompany(User $pme): array
    {
        $query = http_build_query([
            'filters' => json_encode([['warehouse', '=', $pme->erpnext_warehouse]]),
            'fields' => json_encode(['item_code', 'warehouse', 'actual_qty']),
            'limit_page_length' => 0,
            'order_by' => 'item_code asc',
        ]);

        $rows = $this->get('/api/resource/Bin?'.$query);

        return array_map(fn ($row) => [
            'item_code' => (string) $row['item_code'],
            'warehouse' => (string) $row['warehouse'],
            'actual_qty' => (float) $row['actual_qty'],
        ], $rows);
    }
```

- [ ] **Step 3: Manual verification via tinker**

```php
$erpNext = app(\App\Services\ErpNextClient::class);
$pme = \App\Models\User::find(69);
$erpNext->adjustStockForPme($pme, 'Produit test plan stock', 20);
$levels = $erpNext->getStockLevelsForCompany($pme);
collect($levels)->firstWhere('item_code', 'produit-test-plan-stock');
```

Expected: no exception; the matching row shows `actual_qty` exactly `20.0` (not `30`, confirming it's an absolute set, not a cumulative add on top of the 10 units created in Task 2).

- [ ] **Step 4: Commit**

```bash
git add app/Services/ErpNextClient.php
git commit -m "feat(erpnext-stock): add adjustStockForPme and getStockLevelsForCompany

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 4: `ErpNextStockTestController` + routes

**Files:**
- Create: `app/Http/Controllers/ErpNextStockTestController.php`
- Modify: `routes/web.php` (add `use` import near line 19-20, add route group near line 551-560)

**Interfaces:**
- Consumes: `ErpNextClient::getStockLevelsForCompany(User $pme): array`, `createStockMovementForPme(User $pme, string $itemDescription, float $quantity, float $unitRate, string $direction): array`, `adjustStockForPme(User $pme, string $itemDescription, float $newQuantity): array` (all from Tasks 2-3).
- Produces: routes `admin.erpnext-stock-test.index` (GET `/admin/erpnext-stock-test`), `admin.erpnext-stock-test.show` (GET `/admin/erpnext-stock-test/show`), `admin.erpnext-stock-test.create-movement` (GET `/admin/erpnext-stock-test/create-movement`), `admin.erpnext-stock-test.store-movement` (POST same path). Views consumed in Task 5: `admin.erpnext-stock-test.index`, `admin.erpnext-stock-test.show`, `admin.erpnext-stock-test.create-movement`.

- [ ] **Step 1: Create the controller**

Create `app/Http/Controllers/ErpNextStockTestController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ErpNextClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ErpNextStockTestController extends Controller
{
    public function index(): View
    {
        $pmes = User::whereNotNull('erpnext_company_name')->orderBy('company_name')->get();

        return view('admin.erpnext-stock-test.index', compact('pmes'));
    }

    public function show(Request $request, ErpNextClient $erpNext): View
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $pme = User::findOrFail($validated['user_id']);

        $stockLevels = null;
        $stockLevelsError = null;

        if (empty($pme->erpnext_company_name) || empty($pme->erpnext_warehouse)) {
            $stockLevelsError = 'Cette PME n\'a pas de société/entrepôt ERPNext provisionné.';
        } else {
            try {
                $stockLevels = $erpNext->getStockLevelsForCompany($pme);
            } catch (\Throwable $e) {
                $stockLevelsError = $e->getMessage();
            }
        }

        return view('admin.erpnext-stock-test.show', compact('pme', 'stockLevels', 'stockLevelsError'));
    }

    public function createMovement(Request $request): View
    {
        $pmes = User::whereNotNull('erpnext_company_name')->orderBy('company_name')->get();
        $selectedUserId = $request->query('user_id');

        return view('admin.erpnext-stock-test.create-movement', [
            'pmes' => $pmes,
            'selectedUserId' => $selectedUserId,
        ]);
    }

    public function storeMovement(Request $request, ErpNextClient $erpNext): View
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'item_description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_rate' => ['required_if:movement_type,in,out', 'nullable', 'numeric', 'min:0'],
            'movement_type' => ['required', 'in:in,out,ajustement'],
        ]);

        $pme = User::findOrFail($validated['user_id']);

        $result = null;
        $error = null;

        try {
            if ($validated['movement_type'] === 'ajustement') {
                $result = $erpNext->adjustStockForPme(
                    $pme,
                    $validated['item_description'],
                    (float) $validated['quantity']
                );
            } else {
                $result = $erpNext->createStockMovementForPme(
                    $pme,
                    $validated['item_description'],
                    (float) $validated['quantity'],
                    (float) ($validated['unit_rate'] ?? 0),
                    $validated['movement_type']
                );
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('admin.erpnext-stock-test.create-movement', [
            'pmes' => User::whereNotNull('erpnext_company_name')->orderBy('company_name')->get(),
            'selectedUserId' => $validated['user_id'],
            'result' => $result,
            'error' => $error,
        ]);
    }
}
```

- [ ] **Step 2: Register the routes**

In `routes/web.php`, add the import next to the existing ERPNext controller imports (near line 19):

```php
use App\Http\Controllers\ErpNextStockTestController;
```

Then add a new route group right after the `erpnext-accounting-test` group (after line 560, before the closing `});` of the admin group):

```php
        Route::prefix('erpnext-stock-test')->name('erpnext-stock-test.')->group(function () {
            Route::get('/', [ErpNextStockTestController::class, 'index'])->name('index');
            Route::get('/show', [ErpNextStockTestController::class, 'show'])->name('show');
            Route::get('/create-movement', [ErpNextStockTestController::class, 'createMovement'])->name('create-movement');
            Route::post('/create-movement', [ErpNextStockTestController::class, 'storeMovement'])->name('store-movement');
        });
```

- [ ] **Step 3: Verify routes are registered**

Run: `php artisan route:list --name=erpnext-stock-test`
Expected: 4 rows listing `admin.erpnext-stock-test.index`, `.show`, `.create-movement` (GET), `.store-movement` (POST).

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/ErpNextStockTestController.php routes/web.php
git commit -m "feat(erpnext-stock): add ErpNextStockTestController and routes

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 5: Blade views for the Stock test dashboard

**Files:**
- Create: `resources/views/admin/erpnext-stock-test/index.blade.php`
- Create: `resources/views/admin/erpnext-stock-test/show.blade.php`
- Create: `resources/views/admin/erpnext-stock-test/create-movement.blade.php`

**Interfaces:**
- Consumes: variables passed by `ErpNextStockTestController` from Task 4 — `index` gets `$pmes`; `show` gets `$pme`, `$stockLevels` (nullable array of `{item_code, warehouse, actual_qty}`), `$stockLevelsError` (nullable string); `create-movement` gets `$pmes`, `$selectedUserId`, and optionally `$result`/`$error`.
- Produces: nothing consumed further — final UI layer.

- [ ] **Step 1: Create `index.blade.php`**

```blade
@extends('layouts.app')

@section('title', 'Stock ERPNext Test | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Stock ERPNext — Test</h1>
        <div>
            <a href="{{ route('admin.erpnext-stock-test.create-movement') }}" class="btn btn-outline-primary">+ Nouveau mouvement</a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.erpnext-stock-test.show') }}">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label" for="user_id">PME (provisionnée sur ERPNext)</label>
                <select class="form-select" id="user_id" name="user_id" required>
                    <option value="">— Choisir une PME —</option>
                    @foreach ($pmes as $pme)
                        <option value="{{ $pme->id }}">{{ $pme->company_name ?? $pme->name }} ({{ $pme->erpnext_company_name }})</option>
                    @endforeach
                </select>
                @if ($pmes->isEmpty())
                    <div class="form-text text-danger">Aucune PME provisionnée trouvée (erpnext_company_name vide pour tout le monde).</div>
                @endif
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Afficher</button>
            </div>
        </div>
    </form>
</div>
@endsection
```

- [ ] **Step 2: Create `show.blade.php`**

```blade
@extends('layouts.app')

@section('title', 'Stock ERPNext Test | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">{{ $pme->company_name ?? $pme->name }} — Stock</h1>
        <a href="{{ route('admin.erpnext-stock-test.index') }}">← Retour</a>
    </div>

    @if ($stockLevelsError)
        <div class="alert alert-danger">{{ $stockLevelsError }}</div>
    @else
        <table class="table table-sm">
            <thead><tr><th>Article</th><th>Entrepôt</th><th>Quantité en stock</th></tr></thead>
            <tbody>
                @forelse ($stockLevels as $row)
                    <tr>
                        <td>{{ $row['item_code'] }}</td>
                        <td>{{ $row['warehouse'] }}</td>
                        <td>{{ number_format($row['actual_qty'], 2, ',', ' ') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">Aucun article en stock pour cette PME.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif
</div>
@endsection
```

- [ ] **Step 3: Create `create-movement.blade.php`**

```blade
@extends('layouts.app')

@section('title', 'Nouveau mouvement de stock ERPNext | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="container-fluid py-4">
    <h1 class="h3 mb-3">Nouveau mouvement de stock ERPNext</h1>

    @if (isset($result) && $result)
        <div class="alert alert-success">
            Mouvement enregistré : <strong>{{ $result['name'] ?? '' }}</strong> (docstatus : {{ $result['docstatus'] ?? '' }})
        </div>
    @endif

    @if (isset($error) && $error)
        <div class="alert alert-danger">{{ $error }}</div>
    @endif

    <form method="GET" action="{{ route('admin.erpnext-stock-test.create-movement') }}" class="mb-3">
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

    @if ($selectedUserId)
        <form method="POST" action="{{ route('admin.erpnext-stock-test.store-movement') }}">
            @csrf
            <input type="hidden" name="user_id" value="{{ $selectedUserId }}">

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label" for="item_description">Article</label>
                    <input type="text" class="form-control" id="item_description" name="item_description" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="movement_type">Sens</label>
                    <select class="form-select" id="movement_type" name="movement_type" required>
                        <option value="in">Entrée</option>
                        <option value="out">Sortie</option>
                        <option value="ajustement">Ajustement (quantité absolue)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="quantity">Quantité</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" id="quantity" name="quantity" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="unit_rate">Coût unitaire</label>
                    <input type="number" step="0.01" min="0" class="form-control" id="unit_rate" name="unit_rate">
                    <div class="form-text">Non utilisé pour un ajustement.</div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Enregistrer le mouvement</button>
        </form>
    @endif
</div>
@endsection
```

- [ ] **Step 4: Manual verification (browser, local dev server)**

1. `php artisan serve` (or existing local setup), log in as admin.
2. Visit `/admin/erpnext-stock-test` → PME dropdown populated, submit → lands on `/admin/erpnext-stock-test/show?user_id=...` showing a stock table (empty or with rows from earlier tinker tests).
3. Visit `/admin/erpnext-stock-test/create-movement`, pick a PME, submit "Entrée" of 5 units of a new article at 1000 XOF → success alert with a `Stock Entry` name and `docstatus: 1`.
4. Go back to the PME's `show` page → the new article now appears with quantity `5`.
5. Submit "Sortie" of 2 units of the same article → success; `show` page now shows quantity `3`.
6. Submit "Ajustement" to `50` for the same article → success; `show` page shows quantity `50` exactly (not `53`).

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/erpnext-stock-test
git commit -m "feat(erpnext-stock): add Stock ERPNext test dashboard views

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 6: Sidebar link

**Files:**
- Modify: `resources/views/layouts/partials/sidebar.blade.php:367-368` (add a new `<li>` right after the existing "Comptabilité ERPNext Test" entry)

**Interfaces:**
- Consumes: route `admin.erpnext-stock-test.index` from Task 4.
- Produces: nothing further downstream.

- [ ] **Step 1: Add the sidebar entry**

In `resources/views/layouts/partials/sidebar.blade.php`, change:

```blade
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.erpnext-accounting-test.*') ? 'active' : '' }}" href="{{ route('admin.erpnext-accounting-test.index') }}">Comptabilité ERPNext Test</a>
                            </li>
```

to:

```blade
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.erpnext-accounting-test.*') ? 'active' : '' }}" href="{{ route('admin.erpnext-accounting-test.index') }}">Comptabilité ERPNext Test</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.erpnext-stock-test.*') ? 'active' : '' }}" href="{{ route('admin.erpnext-stock-test.index') }}">Stock ERPNext Test</a>
                            </li>
```

- [ ] **Step 2: Manual verification**

Load any admin page, confirm the "Stock ERPNext Test" link appears in the sidebar under "Comptabilité ERPNext Test" and navigates correctly; confirm it highlights as active when on any `/admin/erpnext-stock-test*` page.

- [ ] **Step 3: Commit**

```bash
git add resources/views/layouts/partials/sidebar.blade.php
git commit -m "feat(erpnext-stock): add Stock ERPNext Test sidebar link

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 7: Deploy to production (LWS) and verify live

**Files:** none (deployment only)

**Interfaces:** none — end-to-end verification task.

- [ ] **Step 1: Push all commits**

```bash
git push origin master
```

- [ ] **Step 2: Deploy on the LWS server via SSH**

```bash
bash deploy.sh
```

Expected output ends with `=== Déploiement terminé ===` (this script already handles `git pull`, migrations, cache clearing, and OPcache purge across all PHP-FPM workers — see `deploy.sh` at repo root).

- [ ] **Step 3: Live verification for the real PME "NotifyMails #69" (user id 69)**

1. On the live site, log in as admin, visit `/admin/erpnext-stock-test`, select NotifyMails, confirm `show` page loads without error (existing test items from earlier tinker sessions may already appear, e.g. `produit-test-stock`).
2. Create a movement: entrée of 7 units of a brand-new article (e.g. "Article production test") at 2000 XOF → confirm success message with a real `MAT-STE-...` name.
3. Confirm the `show` page reflects the new quantity.
4. Log into the real ERPNext trial (`https://sitiame-erp-essai.z.frappe.cloud`) and open the created `Stock Entry` directly to confirm it is `Submitted` (docstatus 1) with the correct warehouse "Magasin principal - NOT69".

- [ ] **Step 4: Provisioning regression check**

Confirm Task 1's change doesn't break registration: sign up a brand-new test PME through the public registration form (or Commercial/Accountant creation flow) in production, then check in ERPNext that its Company has `stock_adjustment_account` set automatically (no manual tinker call needed this time).

No commit for this task (deployment/verification only).

---

## Self-Review Notes

**Spec coverage:** All 3 spec architecture items covered — `provisionCompanyForPme()` extension (Task 1), `createStockMovementForPme`/`adjustStockForPme`/`getStockLevelsForCompany` (Tasks 2-3), new dashboard (Tasks 4-6). Spec's 4 manual tests are folded into Task 5 Step 4 and Task 7 Step 3. Spec explicitly excludes touching local `StockController`/`StockService` — no task does.

**Placeholder scan:** No TBD/TODO; every step has literal code or literal commands.

**Type consistency:** `createStockMovementForPme(User $pme, string $itemDescription, float $quantity, float $unitRate, string $direction)` used identically in Task 2 definition and Task 4 controller call. `adjustStockForPme(User $pme, string $itemDescription, float $newQuantity)` and `getStockLevelsForCompany(User $pme)` likewise consistent between Task 3 definition and Task 4 usage (note: spec originally sketched `getStockLevelsForCompany(string $company)`, changed to `User $pme` here so it can filter directly by `$pme->erpnext_warehouse` without a second lookup — simpler and avoids an extra Company round-trip). View variable names (`$stockLevels`, `$stockLevelsError`, `$result`, `$error`, `$selectedUserId`) consistent between Task 4 controller and Task 5 views.
