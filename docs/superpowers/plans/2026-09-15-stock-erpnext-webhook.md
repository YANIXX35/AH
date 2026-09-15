# Stock ERPNext Webhook Ingestion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stock movements are created exclusively in ERPNext's native UI (Stock Entry / Stock Reconciliation); ERPNext notifies PME360 via webhook on submit, and PME360 updates its local `StockProduct`/`StockMovement` display to match ERPNext's authoritative `Bin` data.

**Architecture:** Two ERPNext `Webhook` documents (created via the API, `on_submit` on `Stock Entry` and `Stock Reconciliation`) POST a minimal JSON payload to a new unauthenticated-but-token-protected PME360 route. `ErpNextStockWebhookController` re-reads the full document and the authoritative `Bin` row via `ErpNextClient`, resolves the local PME/product by name, and writes a local `StockMovement` reflecting exactly what ERPNext already committed. The old PME360-side creation form and route are removed.

**Tech Stack:** Laravel 13 / PHP 8.4, MySQL, existing `ErpNextClient` (`app/Services/ErpNextClient.php`) — no new PHP packages.

## Global Constraints

- The webhook endpoint verifies a static shared-secret header (`X-PME360-Webhook-Token`, compared against `config('services.erpnext.webhook_token')`) — reject with 403 if missing/wrong, before doing anything else.
- If the `company` in the payload matches no local PME (`User::where('erpnext_company_name', ...)`), respond `200` and do nothing further (so ERPNext does not retry indefinitely) — log a warning, never throw.
- Resolve the local `StockProduct` by exact name match against ERPNext's `item_name` for each item line — reuses the same name-based convention already used by `findOrCreateItem($product->name)` in the other direction (no new mapping table).
- Read the resulting quantity/valuation from `ErpNextClient::getBinForItem()` (already exists) — never recompute anything locally.
- Movement type mapping: `stock_entry_type: "Material Receipt"` → `entree`; `"Material Issue"` → `sortie`; a `Stock Reconciliation` document → `ajustement`.
- Every webhook-created `StockMovement` gets `reason` set to `"Créé depuis ERPNext (".$docname.")"` for traceability.
- Do not modify `StockService::recordMovement()`, `createProduct()`/`updateProduct()`/`deleteProduct()`, or `/admin/erpnext-stock-test`.
- The route must sit **outside** the `auth`/CSRF-protected route groups (it's a server-to-server webhook, not a browser request) and carry a `throttle` middleware.

---

### Task 1: `getDocument()` generic reader in `ErpNextClient`

**Files:**
- Modify: `app/Services/ErpNextClient.php` (add a new public method after `getBinForItem()`, i.e. right before the class's final closing `}`)

**Interfaces:**
- Consumes: existing private `get()` helper.
- Produces: `public function getDocument(string $doctype, string $name): array` — returns the full document array (or `[]` if not found, matching `get()`'s existing 404-handling behavior). Used by Task 2 (`ErpNextStockWebhookController`).

- [ ] **Step 1: Add the method**

In `app/Services/ErpNextClient.php`, change the end of the file from:

```php
        return [
            'actual_qty' => (float) $row['actual_qty'],
            'valuation_rate' => (float) $row['valuation_rate'],
        ];
    }
}
```

to:

```php
        return [
            'actual_qty' => (float) $row['actual_qty'],
            'valuation_rate' => (float) $row['valuation_rate'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDocument(string $doctype, string $name): array
    {
        return $this->get('/api/resource/'.rawurlencode($doctype).'/'.rawurlencode($name));
    }
}
```

- [ ] **Step 2: Lint**

Run: `php -l app/Services/ErpNextClient.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Verify via tinker against the real ERPNext trial**

Reuse a Stock Entry created earlier this session (e.g. `MAT-STE-2026-00006`, or list one with `GET /api/resource/Stock Entry` first if that exact name is unavailable):

```bash
php artisan tinker --execute="
\$erpNext = app(\App\Services\ErpNextClient::class);
\$query = http_build_query(['fields' => json_encode(['name']), 'limit_page_length' => 1, 'order_by' => 'creation desc']);
\$get = new ReflectionMethod(\$erpNext, 'get');
\$get->setAccessible(true);
\$latest = \$get->invoke(\$erpNext, '/api/resource/'.rawurlencode('Stock Entry').'?'.\$query);
\$name = \$latest[0]['name'];
echo 'testing with: '.\$name.PHP_EOL;
\$doc = \$erpNext->getDocument('Stock Entry', \$name);
echo 'doctype: '.\$doc['doctype'].PHP_EOL;
echo 'company: '.\$doc['company'].PHP_EOL;
echo 'first item_name: '.(\$doc['items'][0]['item_name'] ?? 'MISSING').PHP_EOL;
"
```

Expected: `doctype: Stock Entry`, a real company name, and a real `item_name` printed for the first line item (confirms `item_name` is present on submitted Stock Entry item rows, as relied on by Task 2).

- [ ] **Step 4: Commit**

```bash
git add app/Services/ErpNextClient.php
git commit -m "feat(erpnext-stock): add getDocument generic reader to ErpNextClient

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 2: `ErpNextStockWebhookController` + route + config

**Files:**
- Create: `app/Http/Controllers/ErpNextStockWebhookController.php`
- Modify: `routes/web.php` (add a route outside the `auth` group, near the existing `internal.opcache-reset` route)
- Modify: `config/services.php` (add `webhook_token` under the existing `erpnext` array)

**Interfaces:**
- Consumes: `ErpNextClient::getDocument(string $doctype, string $name): array` (Task 1), `ErpNextClient::getBinForItem(User $pme, string $itemCode): ?array` (existing, from the ERPNext-as-engine sub-project), `User::where('erpnext_company_name', ...)`, `StockProduct::firstOrCreate(...)`, `StockMovement::create(...)`.
- Produces: route `POST /webhooks/erpnext/stock-movement` (no name needed — it's called by ERPNext, not linked to from any PME360 view).

- [ ] **Step 1: Add the config value**

In `config/services.php`, change:

```php
    'erpnext' => [
        'base_url' => env('ERPNEXT_BASE_URL'),
        'api_key' => env('ERPNEXT_API_KEY'),
        'api_secret' => env('ERPNEXT_API_SECRET'),
        'timeout' => env('ERPNEXT_TIMEOUT', 15),
        'default_warehouse' => env('ERPNEXT_DEFAULT_WAREHOUSE'),
        'default_tax_template' => env('ERPNEXT_DEFAULT_TAX_TEMPLATE'),
        'default_item_group' => env('ERPNEXT_DEFAULT_ITEM_GROUP', 'Services'),
        'default_income_account' => env('ERPNEXT_DEFAULT_INCOME_ACCOUNT'),
    ],
```

to:

```php
    'erpnext' => [
        'base_url' => env('ERPNEXT_BASE_URL'),
        'api_key' => env('ERPNEXT_API_KEY'),
        'api_secret' => env('ERPNEXT_API_SECRET'),
        'timeout' => env('ERPNEXT_TIMEOUT', 15),
        'default_warehouse' => env('ERPNEXT_DEFAULT_WAREHOUSE'),
        'default_tax_template' => env('ERPNEXT_DEFAULT_TAX_TEMPLATE'),
        'default_item_group' => env('ERPNEXT_DEFAULT_ITEM_GROUP', 'Services'),
        'default_income_account' => env('ERPNEXT_DEFAULT_INCOME_ACCOUNT'),
        'webhook_token' => env('ERPNEXT_WEBHOOK_TOKEN'),
    ],
```

- [ ] **Step 2: Generate and set the webhook token locally**

Run: `php -r "echo bin2hex(random_bytes(24)).PHP_EOL;"`

Copy the printed value into the local `.env` file as a new line: `ERPNEXT_WEBHOOK_TOKEN=<the generated value>`. Keep this value noted somewhere safe — it is needed again in Task 5 when configuring the ERPNext-side Webhook documents, and again for the production `.env` in Task 6.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/ErpNextStockWebhookController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use App\Models\StockProduct;
use App\Models\User;
use App\Services\ErpNextClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ErpNextStockWebhookController extends Controller
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

        if ($doctype === '' || $docname === '' || $company === '') {
            return response()->json(['status' => 'ignored', 'reason' => 'payload incomplet'], 200);
        }

        $pme = User::where('erpnext_company_name', $company)->first();

        if (! $pme) {
            Log::warning('Webhook ERPNext Stock reçu pour une company sans PME locale correspondante.', [
                'company' => $company,
                'doctype' => $doctype,
                'name' => $docname,
            ]);

            return response()->json(['status' => 'ignored', 'reason' => 'PME introuvable'], 200);
        }

        try {
            $document = $erpNext->getDocument($doctype, $docname);
        } catch (\Throwable $exception) {
            Log::warning('Webhook ERPNext Stock: échec de relecture du document.', [
                'doctype' => $doctype,
                'name' => $docname,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['status' => 'error'], 200);
        }

        $movementType = match (true) {
            $doctype === 'Stock Reconciliation' => 'ajustement',
            ($document['stock_entry_type'] ?? '') === 'Material Receipt' => 'entree',
            ($document['stock_entry_type'] ?? '') === 'Material Issue' => 'sortie',
            default => null,
        };

        if ($movementType === null) {
            return response()->json(['status' => 'ignored', 'reason' => 'type de document non géré'], 200);
        }

        foreach ((array) ($document['items'] ?? []) as $item) {
            $itemCode = (string) ($item['item_code'] ?? '');
            $itemName = (string) ($item['item_name'] ?? $itemCode);

            if ($itemCode === '') {
                continue;
            }

            $product = StockProduct::firstOrCreate(
                ['user_id' => $pme->id, 'name' => $itemName],
                ['unit' => 'unité', 'quantity_on_hand' => 0, 'average_cost' => 0, 'sale_price' => 0, 'is_active' => true]
            );

            $bin = $erpNext->getBinForItem($pme, $itemCode);

            if ($bin === null) {
                continue;
            }

            $previousQty = (float) $product->quantity_on_hand;

            StockMovement::create([
                'product_id' => $product->id,
                'user_id' => $pme->id,
                'actor_user_id' => $pme->id,
                'type' => $movementType,
                'quantity' => round($bin['actual_qty'] - $previousQty, 2),
                'unit_cost' => $bin['valuation_rate'],
                'quantity_after' => $bin['actual_qty'],
                'average_cost_after' => $bin['valuation_rate'],
                'movement_date' => now()->toDateString(),
                'reason' => 'Créé depuis ERPNext ('.$docname.')',
            ]);

            $product->update([
                'quantity_on_hand' => $bin['actual_qty'],
                'average_cost' => $bin['valuation_rate'],
            ]);
        }

        return response()->json(['status' => 'ok'], 200);
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, change:

```php
Route::get('/internal/opcache-reset', \App\Http\Controllers\OpcacheResetController::class)
    ->middleware('throttle:30,1')
    ->name('internal.opcache-reset');
```

to:

```php
Route::get('/internal/opcache-reset', \App\Http\Controllers\OpcacheResetController::class)
    ->middleware('throttle:30,1')
    ->name('internal.opcache-reset');
Route::post('/webhooks/erpnext/stock-movement', [\App\Http\Controllers\ErpNextStockWebhookController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('webhooks.erpnext.stock-movement');
```

- [ ] **Step 5: Confirm the route is CSRF-exempt**

Laravel's default `web` middleware group (which `VerifyCsrfToken` belongs to) applies to routes registered without an explicit different group. Run: `php artisan route:list --name=webhooks.erpnext.stock-movement` and check the `Middleware` column — if `VerifyCsrfToken` appears, this route needs to be added to the `except` array in `app/Http/Middleware/VerifyCsrfToken.php` (or equivalent CSRF exemption used elsewhere in this codebase — check how `/internal/opcache-reset` avoids CSRF issues, since it's a GET route CSRF doesn't apply to GETs, so that one doesn't prove anything either way). If CSRF blocks the POST, add this line inside that middleware's `$except` array:

```php
'webhooks/erpnext/stock-movement',
```

- [ ] **Step 6: Lint**

Run: `php -l app/Http/Controllers/ErpNextStockWebhookController.php`
Expected: `No syntax errors detected`.

- [ ] **Step 7: Verify locally with a simulated webhook call**

First, ensure PME id 15 (the local test PME used throughout this session) has at least one real Stock Entry already submitted on ERPNext from earlier testing (it does — `MAT-STE-2026-00006`/`00007` etc. from the ERPNext-as-engine sub-project). Simulate the webhook call directly against the local dev server:

```bash
php artisan tinker --execute="
\$pme = \App\Models\User::find(15);
echo 'company: '.\$pme->erpnext_company_name.PHP_EOL;
\$erpNext = app(\App\Services\ErpNextClient::class);
\$query = http_build_query(['filters' => json_encode([['company','=',\$pme->erpnext_company_name]]), 'fields' => json_encode(['name']), 'limit_page_length' => 1, 'order_by' => 'creation desc']);
\$get = new ReflectionMethod(\$erpNext, 'get');
\$get->setAccessible(true);
\$latest = \$get->invoke(\$erpNext, '/api/resource/'.rawurlencode('Stock Entry').'?'.\$query);
echo 'STOCK_ENTRY_NAME='.\$latest[0]['name'].PHP_EOL;
"
```

Note the printed `STOCK_ENTRY_NAME` and company, then call the controller directly (bypassing HTTP/CSRF entirely, since this only tests the controller logic — Step 5 already covers the real HTTP/CSRF concern):

```bash
php artisan tinker --execute="
\$request = \Illuminate\Http\Request::create('/webhooks/erpnext/stock-movement', 'POST', [
    'doctype' => 'Stock Entry',
    'name' => 'PASTE_STOCK_ENTRY_NAME_HERE',
    'company' => 'PASTE_COMPANY_NAME_HERE',
]);
\$request->headers->set('X-PME360-Webhook-Token', config('services.erpnext.webhook_token'));
\$controller = app(\App\Http\Controllers\ErpNextStockWebhookController::class);
\$response = \$controller->handle(\$request, app(\App\Services\ErpNextClient::class));
echo \$response->getContent().PHP_EOL;
"
```

Expected: `{"status":"ok"}`. Then confirm a new `StockMovement` was created:

```bash
php artisan tinker --execute="
\$m = \App\Models\StockMovement::latest()->first();
echo \$m->type.' | qty_after: '.\$m->quantity_after.' | reason: '.\$m->reason.PHP_EOL;
"
```

Expected: `entree` (or whatever type matches the tested Stock Entry), a real quantity, `reason` starting with `Créé depuis ERPNext (`.

Also verify the token check: rerun the same call with a wrong header value (`$request->headers->set('X-PME360-Webhook-Token', 'wrong');`) and confirm it throws a 403 (`AuthorizationException` via `abort(403)`).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/ErpNextStockWebhookController.php routes/web.php config/services.php
git commit -m "feat(erpnext-stock): add webhook endpoint to ingest ERPNext stock movements

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 3: Remove `StockController::storeMovement()` and its route

**Files:**
- Modify: `app/Http/Controllers/StockController.php:1-16` (imports) and `:161-194` (the `storeMovement()` method itself)
- Modify: `routes/web.php:692` (remove the `stock.movements.store` route)

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing new — `StockController` keeps every other method (`index`, `create`, `store`, `show`, `downloadPdf`, `export`, `edit`, `update`, `destroy`) exactly as-is.

- [ ] **Step 1: Remove the unused imports**

`Illuminate\Http\Request` and `Illuminate\Support\Carbon` are used only inside `storeMovement()` (verified: no other method in this controller references either). In `app/Http/Controllers/StockController.php`, change:

```php
use App\Domain\Inventory\StockService;
use App\Http\Controllers\Concerns\UsesClientWorkspace;
use App\Http\Requests\StockProductRequest;
use App\Models\StockProduct;
use App\Support\CompanyLogo;
use App\Support\Export\TabularDocumentExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
```

to:

```php
use App\Domain\Inventory\StockService;
use App\Http\Controllers\Concerns\UsesClientWorkspace;
use App\Http\Requests\StockProductRequest;
use App\Models\StockProduct;
use App\Support\CompanyLogo;
use App\Support\Export\TabularDocumentExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
```

- [ ] **Step 2: Remove the method**

Delete this entire method from `app/Http/Controllers/StockController.php`:

```php
    public function storeMovement(Request $request, StockProduct $product): RedirectResponse
    {
        $this->authorizeProduct($product);

        $validated = $request->validate([
            'type' => ['required', 'in:entree,sortie,ajustement'],
            'quantity' => ['required', 'numeric'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'movement_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (in_array($validated['type'], ['entree', 'sortie'], true) && (float) $validated['quantity'] <= 0) {
            return back()->withErrors(['quantity' => 'La quantité doit être positive pour une entrée ou une sortie.']);
        }

        try {
            $this->stockService->recordMovement(
                $product,
                $validated['type'],
                (float) $validated['quantity'],
                isset($validated['unit_cost']) ? (float) $validated['unit_cost'] : null,
                Carbon::parse($validated['movement_date']),
                $validated['reason'] ?? null,
                $validated['notes'] ?? null,
                auth()->id()
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        return back()->with('success', 'Mouvement enregistré.');
    }

```

(Leave `authorizeProduct()` — the private helper right after it — untouched; it's still used by every other method in the controller.)

- [ ] **Step 3: Remove the route**

In `routes/web.php`, delete this line:

```php
        Route::post('/stock/{product}/movements', [StockController::class, 'storeMovement'])->middleware('throttle:finance-write')->name('stock.movements.store');
```

- [ ] **Step 4: Lint**

Run: `php -l app/Http/Controllers/StockController.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Confirm the route is really gone**

Run: `php artisan route:list --name=stock.movements.store`
Expected: no rows (empty output / "No matching routes found").

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/StockController.php routes/web.php
git commit -m "feat(erpnext-stock): remove local stock movement creation (ERPNext is now the entry point)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 4: Remove the movement form from `stock/show.blade.php`

**Files:**
- Modify: `resources/views/stock/show.blade.php`

**Interfaces:**
- Consumes: nothing new — same `$product` variable already passed by `StockController::show()`.
- Produces: nothing new.

- [ ] **Step 1: Replace the "Enregistrer un mouvement" card with an informational message**

In `resources/views/stock/show.blade.php`, change:

```blade
    <div class="row g-3">
        <div class="col-12 col-xl-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Enregistrer un mouvement</h5>
                    <form action="{{ route('stock.movements.store', $product) }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Type *</label>
                            <select name="type" id="movement_type" class="form-select" required>
                                <option value="entree">Entrée (approvisionnement)</option>
                                <option value="sortie">Sortie (vente / consommation)</option>
                                <option value="ajustement">Ajustement (correction inventaire)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantité *</label>
                            <input type="number" step="0.01" name="quantity" class="form-control" required>
                            <small class="text-muted" id="quantity-help">Positive pour une entrée.</small>
                        </div>
                        <div class="mb-3" id="unit-cost-group">
                            <label class="form-label">Coût unitaire</label>
                            <input type="number" step="0.01" min="0" name="unit_cost" class="form-control">
                            <small class="text-muted">Requis pour une entrée (recalcule le CUMP).</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date *</label>
                            <input type="date" name="movement_date" class="form-control" required value="{{ now()->toDateString() }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Motif</label>
                            <input type="text" name="reason" class="form-control" placeholder="Réception fournisseur, vente, inventaire…">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Enregistrer</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
```

to:

```blade
    <div class="row g-3">
        <div class="col-12 col-xl-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Mouvements de stock</h5>
                    <p class="text-muted small mb-0">Les mouvements de stock (entrées, sorties, ajustements) se créent désormais directement dans ERPNext. Ils apparaissent automatiquement ici une fois enregistrés là-bas.</p>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
```

- [ ] **Step 2: Remove the now-unused JS block that controlled the deleted form**

Change:

```blade
<script>
(function () {
    const typeSelect = document.getElementById('movement_type');
    const help = document.getElementById('quantity-help');
    const unitCostGroup = document.getElementById('unit-cost-group');

    function update() {
        if (typeSelect.value === 'sortie') {
            help.textContent = 'Positive : quantité retirée du stock.';
            unitCostGroup.style.display = 'none';
        } else if (typeSelect.value === 'ajustement') {
            help.textContent = 'Positive pour augmenter le stock, négative pour le corriger à la baisse.';
            unitCostGroup.style.display = '';
        } else {
            help.textContent = 'Positive pour une entrée.';
            unitCostGroup.style.display = '';
        }
    }

    typeSelect.addEventListener('change', update);
    update();
})();
</script>
<script>
(function () {
    var formatSelect = document.getElementById('stockExportFormat');
```

to:

```blade
<script>
(function () {
    var formatSelect = document.getElementById('stockExportFormat');
```

(Only the first `<script>` block — the one referencing `movement_type`/`quantity-help`/`unit-cost-group` — is deleted. The second script, controlling the export-format dropdown, stays untouched.)

- [ ] **Step 3: Lint**

Run: `php -l resources/views/stock/show.blade.php`
Expected: `No syntax errors detected` (Blade files are valid PHP once compiled, but `php -l` on the raw `.blade.php` source still checks its literal PHP directives like `@php`/`{{ }}` won't be checked this way — instead just visually confirm no unmatched `<div>`/`<script>` tags were left by reading the file back).

- [ ] **Step 4: Read the file back to confirm balanced markup**

Read the full file and confirm: the `<div class="row g-3">` still has exactly two direct children columns (`col-12 col-xl-4` and `col-12 col-xl-8`), and there is exactly one `<script>` block remaining before `@endsection`.

- [ ] **Step 5: Commit**

```bash
git add resources/views/stock/show.blade.php
git commit -m "feat(erpnext-stock): remove movement creation form from stock product page

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 5: Create the 2 ERPNext Webhooks via the API

**Files:** none (one-off API calls against the ERPNext trial, documented here for reproducibility — not a Laravel artisan command, since this only ever needs to run once per ERPNext environment)

**Interfaces:**
- Consumes: the raw `Webhook` doctype REST API (`POST /api/resource/Webhook`), same low-level pattern already used elsewhere in this session (e.g. Task 1's verification, or the earlier Workspace experiments).

- [ ] **Step 1: Determine the public URL for the webhook route**

The production site is `https://sitiame-capital.com`, so the full webhook URL is `https://sitiame-capital.com/webhooks/erpnext/stock-movement`.

- [ ] **Step 2: Create the `Stock Entry` webhook**

```bash
php artisan tinker --execute="
\$erpNext = app(\App\Services\ErpNextClient::class);
\$post = new ReflectionMethod(\$erpNext, 'post');
\$post->setAccessible(true);
\$result = \$post->invoke(\$erpNext, '/api/resource/Webhook', [
    'webhook_doctype' => 'Stock Entry',
    'webhook_docevent' => 'on_submit',
    'request_url' => 'https://sitiame-capital.com/webhooks/erpnext/stock-movement',
    'request_method' => 'POST',
    'request_structure' => 'JSON',
    'timeout' => 5,
    'enabled' => 1,
    'webhook_json' => '{\"doctype\": \"{{ doc.doctype }}\", \"name\": \"{{ doc.name }}\", \"company\": \"{{ doc.company }}\"}',
    'webhook_headers' => [
        ['key' => 'X-PME360-Webhook-Token', 'value' => 'PASTE_THE_TOKEN_FROM_TASK_2_STEP_2_HERE'],
        ['key' => 'Content-Type', 'value' => 'application/json'],
    ],
]);
echo 'created: '.\$result['name'].PHP_EOL;
"
```

Expected: `created: Stock Entry Webhook to PME360` (or whatever auto-generated name ERPNext assigns — the exact `name` value doesn't matter, only that creation succeeds).

- [ ] **Step 3: Create the `Stock Reconciliation` webhook**

Same call, with `webhook_doctype` changed to `Stock Reconciliation` (everything else identical, same `request_url`, same token, same `webhook_json` template — the payload shape is the same for both doctypes since both have a `doctype`/`name`/`company` field).

- [ ] **Step 4: Verify both webhooks exist and are enabled**

```bash
php artisan tinker --execute="
\$erpNext = app(\App\Services\ErpNextClient::class);
\$get = new ReflectionMethod(\$erpNext, 'get');
\$get->setAccessible(true);
\$query = http_build_query(['filters' => json_encode([['webhook_doctype','in',['Stock Entry','Stock Reconciliation']]]), 'fields' => json_encode(['name','webhook_doctype','webhook_docevent','request_url','enabled']), 'limit_page_length' => 0]);
\$rows = \$get->invoke(\$erpNext, '/api/resource/Webhook?'.\$query);
foreach (\$rows as \$r) { echo json_encode(\$r).PHP_EOL; }
"
```

Expected: 2 rows, both `enabled: 1`, both `request_url: "https://sitiame-capital.com/webhooks/erpnext/stock-movement"`.

No commit for this task (server-side ERPNext configuration only, no repository files changed).

---

### Task 6: Deploy to production (LWS) and verify live end-to-end

**Files:** none (deployment + verification only)

**Interfaces:** none — end-to-end verification task, covering the manual tests listed in the spec.

- [ ] **Step 1: Push all commits**

```bash
git push origin master
```

- [ ] **Step 2: Add the production env variable, then deploy**

On the LWS SSH terminal, before running `deploy.sh`, add the same token generated in Task 2 Step 2 to the production `.env`:

```bash
echo "ERPNEXT_WEBHOOK_TOKEN=PASTE_THE_SAME_TOKEN_HERE" >> .env
bash deploy.sh
```

Expected: output ends with `=== Déploiement terminé ===`.

- [ ] **Step 3: Live verification for the real PME "NotifyMails #69" (already provisioned, and the webhook already targets production since `request_url` was set to the real domain in Task 5)**

1. Confirm `/stock` for NotifyMails now shows the informational message instead of a creation form.
2. Log into ERPNext (`https://sitiame-erp-essai.z.frappe.cloud`), create and submit a `Stock Entry` (Material Receipt) directly in the UI for Company "NotifyMails #69", any item, any warehouse (use "Magasin principal - NOT69" for consistency with everything else this session).
3. Within a few seconds, refresh `/stock` on PME360 → confirm the corresponding product now shows the correct quantity/CUMP, and its movement history includes a new row with `reason` starting with "Créé depuis ERPNext (".
4. Repeat with a `Stock Reconciliation` submitted directly in ERPNext → confirm it appears locally tagged `ajustement`, with the exact absolute quantity ERPNext set.
5. Test the security guard: `curl -X POST https://sitiame-capital.com/webhooks/erpnext/stock-movement -H "Content-Type: application/json" -d '{"doctype":"Stock Entry","name":"x","company":"x"}'` (no token header) → confirm a `403` response.
6. Test the unknown-company guard: submit a Stock Entry in ERPNext for a Company that has no matching PME360 `erpnext_company_name` (if one exists — e.g. "Sitiame Capital" if it was never linked to a PME360 `User` row) → confirm nothing breaks and the webhook responds `200`.

No commit for this task (deployment/verification only).

---

## Self-Review Notes

**Spec coverage:** All decisions covered — 2 Webhooks created via API with the specified `webhook_json` shape (Task 5), shared-token auth with 403 on mismatch (Task 2 Step 3 + Task 6 Step 3.5), silent-200 on unknown company (Task 2 Step 3 + Task 6 Step 3.6), item resolution by `item_name` (Task 2 Step 3), `Bin`-sourced authoritative numbers via the already-existing `getBinForItem()` (Task 2 Step 3), movement-type mapping table (Task 2 Step 3, the `match(true)` block), traceable `reason` field (Task 2 Step 3), form/route removal (Tasks 3-4), route placed outside `auth`/CSRF groups with `throttle` (Task 2 Steps 4-5). All 6 manual tests from the spec are folded into Task 6 Step 3 (webhook creation itself covered by Task 5 Step 4, form removal by Task 6 Step 3.1, the two ingestion tests by 3.2-3.4, the two guard tests by 3.5-3.6).

**Placeholder scan:** No TBD/TODO; every step has literal code or literal commands. The two `PASTE_..._HERE` markers in Task 5's commands are intentional and explicit hand-off points between tasks (the generated token from Task 2 Step 2 is genuinely only known at execution time, not something a plan can hardcode) — not vague placeholders for unwritten logic.

**Type consistency:** `ErpNextClient::getDocument(string $doctype, string $name): array` (Task 1) is called with those exact two string arguments in Task 2 Step 3's controller (`$erpNext->getDocument($doctype, $docname)`), where `$doctype`/`$docname` come straight from the validated request payload. `getBinForItem(User $pme, string $itemCode): ?array` (already existing, reused) is called identically to its existing usage elsewhere in the codebase. `StockMovement`/`StockProduct` fields written in Task 2 Step 3 (`type`, `quantity`, `unit_cost`, `quantity_after`, `average_cost_after`, `movement_date`, `reason` / `quantity_on_hand`, `average_cost`) match exactly the fillable fields already defined on those models (used identically by `StockService::recordMovement()`, unchanged elsewhere in this plan).
