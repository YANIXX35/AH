# Stock ERPNext Live Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every real stock movement recorded by a PME through `/stock` automatically syncs to ERPNext in the background (Stock Entry for entrée/sortie, Stock Reconciliation for ajustement), mirroring exactly how Invoicing already syncs via `SyncInvoiceToErpNext`.

**Architecture:** Add a `stock_movement_erpnext_syncs` tracking table + `StockMovementErpNextSync` model (copy of `invoice_erpnext_syncs`/`InvoiceErpNextSync`). Add a new queued Job `SyncStockMovementToErpNext` that maps a `StockMovement` to the right `ErpNextClient` call. Dispatch that job from `StockService::recordMovement()` right after its `DB::transaction()` commits — same position `InvoiceService::createInvoice()` uses for `SyncInvoiceToErpNext::dispatch($invoice)`.

**Tech Stack:** Laravel 13 / PHP 8.4, MySQL, existing `ErpNextClient` (`app/Services/ErpNextClient.php`) — no new PHP packages.

## Global Constraints

- The job must never let an exception propagate — production runs `QUEUE_CONNECTION=sync`, so an uncaught exception here would break the real stock movement request for a real user. Every `\Throwable` is caught and recorded as `failed`, exactly like `SyncInvoiceToErpNext::handle()`.
- If the PME has no `erpnext_company_name` or `erpnext_warehouse`, mark the sync `failed` with a clear message and return — never attempt the API call.
- `ajustement` always syncs via `Stock Reconciliation` using `$movement->quantity_after` (the absolute resulting stock level already computed locally) — never the signed delta stored in `$movement->quantity`.
- `entree`/`sortie` sync via `Stock Entry` using `abs($movement->quantity)` as the quantity.
- Do not modify `StockController`, any `stock.*` Blade view, `StockProduct`, or the CUMP calculation inside `StockService::recordMovement()` — only add a dispatch call after the existing transaction.
- Do not modify `ErpNextClient` — `createStockMovementForPme()` and `adjustStockForPme()` already exist and are already verified against the real ERPNext trial.

---

### Task 1: `stock_movement_erpnext_syncs` migration + `StockMovementErpNextSync` model

**Files:**
- Create: `database/migrations/2026_09_13_000000_create_stock_movement_erpnext_syncs_table.php`
- Create: `app/Models/StockMovementErpNextSync.php`

**Interfaces:**
- Consumes: nothing (new table, no dependency on other tasks).
- Produces: `StockMovementErpNextSync` Eloquent model with fillable `stock_movement_id`, `status`, `erpnext_document_type`, `erpnext_document_name`, `last_error`, `last_synced_at`, `raw_response`; relation `movement(): BelongsTo` to `StockMovement`. Used by Task 2 (the Job) via `StockMovementErpNextSync::firstOrCreate(['stock_movement_id' => $movement->id], ['status' => 'pending'])`.

- [ ] **Step 1: Create the migration**

Create `database/migrations/2026_09_13_000000_create_stock_movement_erpnext_syncs_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movement_erpnext_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_movement_id')->unique()->constrained('stock_movements')->cascadeOnDelete();
            $table->string('status', 16)->default('pending');
            $table->string('erpnext_document_type')->nullable();
            $table->string('erpnext_document_name')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movement_erpnext_syncs');
    }
};
```

- [ ] **Step 2: Run the migration locally**

Run: `php artisan migrate`
Expected: output includes `2026_09_13_000000_create_stock_movement_erpnext_syncs_table ... DONE`.

- [ ] **Step 3: Create the model**

Create `app/Models/StockMovementErpNextSync.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovementErpNextSync extends Model
{
    protected $table = 'stock_movement_erpnext_syncs';

    protected $fillable = [
        'stock_movement_id',
        'status',
        'erpnext_document_type',
        'erpnext_document_name',
        'last_error',
        'last_synced_at',
        'raw_response',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }
}
```

- [ ] **Step 4: Verify via tinker**

```bash
php artisan tinker --execute="
\$sync = \App\Models\StockMovementErpNextSync::create(['stock_movement_id' => 999999, 'status' => 'pending']);
echo 'created id: '.\$sync->id.PHP_EOL;
\$sync->delete();
echo 'deleted ok';
"
```

Expected: no exception (the FK constraint won't fire here because SQLite/MySQL FK checks are typically deferred to real inserts referencing rows — if this fails with a foreign key error, use an existing real `stock_movement_id` from `StockMovement::first()->id` instead and delete after).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_09_13_000000_create_stock_movement_erpnext_syncs_table.php app/Models/StockMovementErpNextSync.php
git commit -m "feat(erpnext-stock): add stock_movement_erpnext_syncs table and model

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 2: Add `user()` relation to `StockMovement`

**Files:**
- Modify: `app/Models/StockMovement.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `StockMovement::user(): BelongsTo` → `User`. Used by Task 3 (the Job) as `$this->movement->user`, mirroring `Invoice::user()` used by `SyncInvoiceToErpNext` as `$this->invoice->user`.

- [ ] **Step 1: Add the relation**

In `app/Models/StockMovement.php`, change:

```php
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
```

to:

```php
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 2: Verify via tinker**

```bash
php artisan tinker --execute="
\$movement = \App\Models\StockMovement::first();
if (\$movement) { echo \$movement->user?->id ?? 'user relation returned null'; } else { echo 'no stock movements exist locally yet, relation added without runtime check'; }
"
```

Expected: prints a user id (or the fallback message if no local stock movement exists yet — either outcome is fine, this step just confirms no exception is thrown).

- [ ] **Step 3: Commit**

```bash
git add app/Models/StockMovement.php
git commit -m "feat(erpnext-stock): add user() relation to StockMovement

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 3: `SyncStockMovementToErpNext` Job

**Files:**
- Create: `app/Jobs/SyncStockMovementToErpNext.php`

**Interfaces:**
- Consumes: `StockMovementErpNextSync` (Task 1), `StockMovement::user()` (Task 2), `StockMovement::product()` (existing, `app/Models/StockMovement.php:32-35`), `ErpNextClient::createStockMovementForPme(User $pme, string $itemDescription, float $quantity, float $unitRate, string $direction): array` and `ErpNextClient::adjustStockForPme(User $pme, string $itemDescription, float $newQuantity): array` (both already implemented in `app/Services/ErpNextClient.php`), `ErpNextClient::enabled(): bool`.
- Produces: `SyncStockMovementToErpNext::dispatch(StockMovement $movement)` — a queueable job. Used by Task 4 (`StockService::recordMovement()`).

- [ ] **Step 1: Create the Job**

Create `app/Jobs/SyncStockMovementToErpNext.php`:

```php
<?php

namespace App\Jobs;

use App\Models\StockMovement;
use App\Models\StockMovementErpNextSync;
use App\Services\ErpNextClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncStockMovementToErpNext implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public StockMovement $movement)
    {
    }

    public function handle(ErpNextClient $erpNext): void
    {
        $sync = StockMovementErpNextSync::firstOrCreate(
            ['stock_movement_id' => $this->movement->id],
            ['status' => 'pending']
        );

        if ($sync->status === 'synced') {
            return;
        }

        if (! $erpNext->enabled()) {
            $sync->update(['status' => 'failed', 'last_error' => 'ERPNext non configuré.']);

            return;
        }

        $pme = $this->movement->user;

        if (empty($pme) || empty($pme->erpnext_company_name) || empty($pme->erpnext_warehouse)) {
            $sync->update([
                'status' => 'failed',
                'last_error' => 'PME non provisionnée sur ERPNext (erpnext_company_name/erpnext_warehouse manquant).',
            ]);

            return;
        }

        $product = $this->movement->product;

        try {
            if ($this->movement->type === 'ajustement') {
                $response = $erpNext->adjustStockForPme(
                    $pme,
                    $product->name,
                    (float) $this->movement->quantity_after
                );
                $documentType = 'Stock Reconciliation';
            } else {
                $direction = $this->movement->type === 'entree' ? 'in' : 'out';
                $unitRate = (float) ($this->movement->unit_cost ?? $this->movement->average_cost_after ?? 0);

                $response = $erpNext->createStockMovementForPme(
                    $pme,
                    $product->name,
                    abs((float) $this->movement->quantity),
                    $unitRate,
                    $direction
                );
                $documentType = 'Stock Entry';
            }

            $sync->update([
                'status' => 'synced',
                'erpnext_document_type' => $documentType,
                'erpnext_document_name' => $response['name'] ?? null,
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

- [ ] **Step 2: Verify via tinker against the real ERPNext trial**

Use a locally provisioned test PME (id 15, "Test Inscription E2E ... #15", already provisioned in earlier sub-projects) and a real local stock product/movement:

```bash
php artisan tinker --execute="
\$pme = \App\Models\User::find(15);
\$product = \App\Models\StockProduct::firstOrCreate(
    ['user_id' => \$pme->id, 'name' => 'Produit sync job test'],
    ['unit' => 'unité', 'quantity_on_hand' => 0, 'average_cost' => 0, 'sale_price' => 0, 'is_active' => true]
);
\$movement = \App\Models\StockMovement::create([
    'product_id' => \$product->id,
    'user_id' => \$pme->id,
    'actor_user_id' => \$pme->id,
    'type' => 'entree',
    'quantity' => 8,
    'unit_cost' => 2500,
    'quantity_after' => 8,
    'average_cost_after' => 2500,
    'movement_date' => now()->toDateString(),
]);
\App\Jobs\SyncStockMovementToErpNext::dispatchSync(\$movement);
\$sync = \App\Models\StockMovementErpNextSync::where('stock_movement_id', \$movement->id)->first();
echo 'status: '.\$sync->status.PHP_EOL;
echo 'doc: '.\$sync->erpnext_document_type.' '.\$sync->erpnext_document_name.PHP_EOL;
echo 'error: '.(\$sync->last_error ?? 'none').PHP_EOL;
"
```

Expected: `status: synced`, `doc: Stock Entry MAT-STE-2026-0000X`, `error: none`. (`dispatchSync()` runs the job immediately in-process without needing a queue worker — appropriate for this manual verification step.)

- [ ] **Step 3: Commit**

```bash
git add app/Jobs/SyncStockMovementToErpNext.php
git commit -m "feat(erpnext-stock): add SyncStockMovementToErpNext job

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 4: Dispatch the job from `StockService::recordMovement()`

**Files:**
- Modify: `app/Domain/Inventory/StockService.php:110-191`

**Interfaces:**
- Consumes: `SyncStockMovementToErpNext::dispatch(StockMovement $movement)` (Task 3).
- Produces: no new public interface — `recordMovement()` keeps its exact existing signature and return type (`StockMovement`). `StockController::storeMovement()` (`app/Http/Controllers/StockController.php:161-194`) needs no changes.

- [ ] **Step 1: Add the import**

In `app/Domain/Inventory/StockService.php`, change:

```php
namespace App\Domain\Inventory;

use App\Models\StockMovement;
use App\Models\StockProduct;
use App\Services\TreasuryAudit;
use Illuminate\Support\Facades\DB;
```

to:

```php
namespace App\Domain\Inventory;

use App\Jobs\SyncStockMovementToErpNext;
use App\Models\StockMovement;
use App\Models\StockProduct;
use App\Services\TreasuryAudit;
use Illuminate\Support\Facades\DB;
```

- [ ] **Step 2: Dispatch after the transaction commits**

In `recordMovement()`, change the method body from:

```php
        return DB::transaction(function () use ($product, $type, $quantity, $unitCost, $date, $reason, $notes, $actorUserId) {
            $locked = StockProduct::where('id', $product->id)->lockForUpdate()->firstOrFail();

            // ... (unchanged body) ...

            return $movement;
        });
    }
```

to:

```php
        $movement = DB::transaction(function () use ($product, $type, $quantity, $unitCost, $date, $reason, $notes, $actorUserId) {
            $locked = StockProduct::where('id', $product->id)->lockForUpdate()->firstOrFail();

            // ... (unchanged body) ...

            return $movement;
        });

        SyncStockMovementToErpNext::dispatch($movement);

        return $movement;
    }
```

(Only the outer wrapping changes — every line already inside the closure, from `$locked = StockProduct::where(...)` through the inner `return $movement;`, stays byte-for-byte identical. Do not touch the CUMP calculation, the `StockMovement::create()` call, `TreasuryAudit::log()`, or the inner `$locked->update()`.)

- [ ] **Step 3: Verify existing local behavior is unchanged**

Run: `php artisan tinker --execute="
\$product = \App\Models\StockProduct::firstOrCreate(['user_id' => 15, 'name' => 'Produit dispatch test'], ['unit' => 'unité', 'quantity_on_hand' => 0, 'average_cost' => 0, 'sale_price' => 0, 'is_active' => true]);
\$service = app(\App\Domain\Inventory\StockService::class);
\$movement = \$service->recordMovement(\$product, 'entree', 6, 1500, now(), null, null, 15);
echo 'movement id: '.\$movement->id.PHP_EOL;
echo 'product qty now: '.\$product->fresh()->quantity_on_hand.PHP_EOL;
sleep(2);
\$sync = \App\Models\StockMovementErpNextSync::where('stock_movement_id', \$movement->id)->first();
echo 'sync status: '.(\$sync->status ?? 'NO SYNC ROW').PHP_EOL;
"`

Expected: `movement id: <n>`, `product qty now: 6`, and — since local `QUEUE_CONNECTION` may be `database` rather than `sync` — either `sync status: synced` (if queue worker is running) or `sync status: pending` (if not; this is expected and fine, it only proves the row was created and the local movement itself succeeded regardless of queue processing, matching the "never blocks the local write" requirement from the spec).

- [ ] **Step 4: Commit**

```bash
git add app/Domain/Inventory/StockService.php
git commit -m "feat(erpnext-stock): dispatch SyncStockMovementToErpNext after recordMovement

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 5: Deploy to production (LWS) and verify live against a real PME

**Files:** none (deployment + verification only)

**Interfaces:** none — end-to-end verification task, covering the 5 manual tests listed in the spec.

- [ ] **Step 1: Push all commits**

```bash
git push origin master
```

- [ ] **Step 2: Deploy on the LWS server via SSH**

```bash
bash deploy.sh
```

Expected: output ends with `=== Déploiement terminé ===`. This runs `php artisan migrate --force`, which creates `stock_movement_erpnext_syncs` in production, plus the standard cache/OPcache purge.

- [ ] **Step 3: Live verification for the real PME "NotifyMails #69" (user id 69, already provisioned with a warehouse and stock_adjustment_account)**

1. Log in as NotifyMails (or use its workspace), go to `/stock`, create a product, e.g. "Article sync live".
2. Record an **entrée** of 10 units at 3000 XOF on the product page.
3. Wait a few seconds (production runs `QUEUE_CONNECTION=sync`, so the sync job runs inline during the request — no waiting should actually be needed).
4. Open ERPNext (`https://sitiame-erp-essai.z.frappe.cloud`), search for the newest `Stock Entry`, confirm it is `Submitted`, company "NotifyMails #69", item matching "Article sync live", warehouse "Magasin principal - NOT69", 10 units.
5. Back on PME360, record a **sortie** of 4 units on the same product → confirm a new `Stock Entry` (Material Issue) in ERPNext, and that `Bin.actual_qty` for that item now reads 6 (verifiable via `/admin/erpnext-stock-test/show?user_id=69` from the earlier sub-project, or directly in ERPNext's `Stock Balance` report).
6. Record an **ajustement** of -1 (local result: 5) → confirm a `Stock Reconciliation` submitted in ERPNext with `qty: 5` (not `-1`), and `Bin.actual_qty` reads exactly 5.
7. Pick a PME that is NOT provisioned on ERPNext (or temporarily use one with no `erpnext_warehouse`), record any movement, confirm: the movement still saves locally without error, and (via `php artisan tinker` on the server, or a quick DB query) the corresponding `stock_movement_erpnext_syncs` row shows `status: failed` with the expected message.

No commit for this task (deployment/verification only).

---

## Self-Review Notes

**Spec coverage:** All decisions from the spec are covered — dispatch point (Task 4), entrée/sortie → `createStockMovementForPme` with `direction`/`abs(quantity)` (Task 3), ajustement → `adjustStockForPme` with `quantity_after` (Task 3), unit cost fallback `unit_cost ?? average_cost_after ?? 0` (Task 3), tracking table matching `invoice_erpnext_syncs` shape (Task 1), resilience/guard clauses matching `SyncInvoiceToErpNext` (Task 3), all 5 manual tests from the spec folded into Task 5 Step 3. Explicitly out-of-scope items from the spec (no sync on product creation, no admin UI, no reverse sync, no retroactive sync) are respected — no task touches `StockService::createProduct()` or adds any new UI.

**Placeholder scan:** No TBD/TODO; every step has literal code or literal commands.

**Type consistency:** `SyncStockMovementToErpNext::__construct(public StockMovement $movement)` in Task 3 matches `SyncStockMovementToErpNext::dispatch($movement)` call in Task 4, where `$movement` is the `StockMovement` returned by the (unchanged) transaction closure. `StockMovementErpNextSync` fillable fields defined in Task 1 (`stock_movement_id`, `status`, `erpnext_document_type`, `erpnext_document_name`, `last_error`, `last_synced_at`, `raw_response`) match exactly the fields written in Task 3's `$sync->update([...])` calls. `StockMovement::user()` added in Task 2 is consumed as `$this->movement->user` in Task 3 Step 1.
