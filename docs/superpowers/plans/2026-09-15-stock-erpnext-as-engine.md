# Stock ERPNext-as-Engine Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When ERPNext is reachable and the PME is provisioned, `StockService::recordMovement()` gets its resulting quantity/valuation from ERPNext's own `Bin` (the authoritative engine) instead of computing CUMP locally; if ERPNext is unreachable, it falls back to the existing local CUMP calculation unchanged, and queues the existing background retry job.

**Architecture:** Add `ErpNextClient::getBinForItem()` to read a single item's authoritative `actual_qty`/`valuation_rate`. Restructure `StockService::recordMovement()` to attempt a synchronous ERPNext write+read before falling back to its existing local math — the existing CUMP formula, `StockMovement::create()` call, and `TreasuryAudit::log()` call are not rewritten, only made conditional.

**Tech Stack:** Laravel 13 / PHP 8.4, MySQL, existing `ErpNextClient` (`app/Services/ErpNextClient.php`) — no new PHP packages.

## Global Constraints

- Product creation (`StockService::createProduct()`) stays 100% local — this plan only touches `recordMovement()`.
- On success, use ERPNext's `Bin.actual_qty`/`Bin.valuation_rate` as the movement's `quantity_after`/`average_cost_after` — do not recompute them locally in that branch.
- On failure (ERPNext disabled, PME not provisioned, or any `\Throwable` from the API call), use the existing local CUMP formula byte-for-byte unchanged, and dispatch `SyncStockMovementToErpNext::dispatch($movement)` as a catch-up retry — exactly as today.
- On success, mark the `StockMovementErpNextSync` row `synced` directly (no dispatch) — never dispatch the background job after an already-successful synchronous write.
- Never let the local write fail because of ERPNext — any exception from the ERPNext call must be caught and treated as "fall back", not propagated to the caller.
- Do not modify `StockController`, any `stock.*` Blade view, `createProduct()`/`updateProduct()`/`deleteProduct()`, the `/admin/erpnext-stock-test` dashboard, or `SyncStockMovementToErpNext` itself.

---

### Task 1: `getBinForItem()` in `ErpNextClient`

**Files:**
- Modify: `app/Services/ErpNextClient.php` (add a new public method after `getStockLevelsForCompany()`)

**Interfaces:**
- Consumes: existing private `get()` helper.
- Produces: `public function getBinForItem(User $pme, string $itemCode): ?array` — returns `['actual_qty' => float, 'valuation_rate' => float]` or `null` if no `Bin` row exists for that item+warehouse. Used by Task 2 (`StockService::recordMovement()`).

- [ ] **Step 1: Add the method**

In `app/Services/ErpNextClient.php`, immediately after `getStockLevelsForCompany()` (which ends with the closing `}` right before `}` of the class in the current file — insert before that final class-closing brace):

```php
    /**
     * @return array{actual_qty: float, valuation_rate: float}|null
     */
    public function getBinForItem(User $pme, string $itemCode): ?array
    {
        $query = http_build_query([
            'filters' => json_encode([
                ['item_code', '=', $itemCode],
                ['warehouse', '=', $pme->erpnext_warehouse],
            ]),
            'fields' => json_encode(['actual_qty', 'valuation_rate']),
            'limit_page_length' => 1,
        ]);

        $rows = $this->get('/api/resource/Bin?'.$query);
        $row = $rows[0] ?? null;

        if ($row === null) {
            return null;
        }

        return [
            'actual_qty' => (float) $row['actual_qty'],
            'valuation_rate' => (float) $row['valuation_rate'],
        ];
    }
```

- [ ] **Step 2: Lint**

Run: `php -l app/Services/ErpNextClient.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Verify via tinker against the real ERPNext trial**

Use the locally provisioned test PME (id 15) and an item already mouvemented earlier this session ("bon", which should have a `Bin` row from prior tests):

```bash
php artisan tinker --execute="
\$pme = \App\Models\User::find(15);
\$erpNext = app(\App\Services\ErpNextClient::class);
\$itemCode = \$erpNext->findOrCreateItem('Produit sync job test');
\$bin = \$erpNext->getBinForItem(\$pme, \$itemCode);
echo \$bin === null ? 'NULL (no bin row)' : json_encode(\$bin);
echo PHP_EOL;
\$missing = \$erpNext->getBinForItem(\$pme, 'article-jamais-mouvemente-xyz');
echo \$missing === null ? 'NULL as expected for unknown item' : 'UNEXPECTED: '.json_encode(\$missing);
"
```

Expected: first call prints a JSON object with `actual_qty`/`valuation_rate` (a real number, from earlier sub-project tests on that item); second call prints `NULL as expected for unknown item`.

- [ ] **Step 4: Commit**

```bash
git add app/Services/ErpNextClient.php
git commit -m "feat(erpnext-stock): add getBinForItem to ErpNextClient

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 2: Restructure `StockService::recordMovement()` to try ERPNext first

**Files:**
- Modify: `app/Domain/Inventory/StockService.php`

**Interfaces:**
- Consumes: `ErpNextClient::enabled(): bool`, `ErpNextClient::findOrCreateItem(string $description): string`, `ErpNextClient::createStockMovementForPme(User $pme, string $itemDescription, float $quantity, float $unitRate, string $direction): array` (existing), `ErpNextClient::adjustStockForPme(User $pme, string $itemDescription, float $newQuantity): array` (existing), `ErpNextClient::getBinForItem(User $pme, string $itemCode): ?array` (Task 1), `StockMovementErpNextSync` model (existing, from the Stock live-sync sub-project), `SyncStockMovementToErpNext::dispatch(StockMovement $movement)` (existing).
- Produces: `StockService::recordMovement(...)` keeps its exact existing public signature and return type (`StockMovement`) — no caller changes needed (`StockController::storeMovement()` untouched).

- [ ] **Step 1: Inject `ErpNextClient` into the constructor**

In `app/Domain/Inventory/StockService.php`, change:

```php
namespace App\Domain\Inventory;

use App\Jobs\SyncStockMovementToErpNext;
use App\Models\StockMovement;
use App\Models\StockProduct;
use App\Services\TreasuryAudit;
use Illuminate\Support\Facades\DB;

/**
 * Module "Extensions" du cadrage produit : optionnel, activable par segment
 * (négoce/distribution), sans dépendance sur le cœur Trésorerie/Facturation.
 * Valorisation au CUMP (coût unitaire moyen pondéré), méthode standard OHADA.
 */
class StockService
{
```

to:

```php
namespace App\Domain\Inventory;

use App\Jobs\SyncStockMovementToErpNext;
use App\Models\StockMovement;
use App\Models\StockMovementErpNextSync;
use App\Models\StockProduct;
use App\Services\ErpNextClient;
use App\Services\TreasuryAudit;
use Illuminate\Support\Facades\DB;

/**
 * Module "Extensions" du cadrage produit : optionnel, activable par segment
 * (négoce/distribution), sans dépendance sur le cœur Trésorerie/Facturation.
 * Valorisation au CUMP (coût unitaire moyen pondéré), méthode standard OHADA
 * en repli si ERPNext est indisponible — sinon la quantité/valorisation
 * viennent directement d'ERPNext, qui fait foi (voir recordMovement()).
 */
class StockService
{
    public function __construct(private readonly ErpNextClient $erpNext)
    {
    }

```

- [ ] **Step 2: Add a private helper that attempts the ERPNext write and returns its authoritative numbers, or `null`**

Insert this new private method directly above `recordMovement()`:

```php
    /**
     * Tente d'enregistrer le mouvement sur ERPNext et d'en lire le résultat
     * faisant foi. Retourne null si ERPNext est indisponible, la PME non
     * provisionnée, ou en cas d'erreur — jamais d'exception : le mouvement
     * local ne doit jamais être bloqué par une panne ERPNext.
     *
     * @return array{actual_qty: float, valuation_rate: float}|null
     */
    private function tryRecordMovementOnErpNext(
        StockProduct $product,
        string $type,
        float $delta,
        ?float $unitCost
    ): ?array {
        if (! $this->erpNext->enabled()) {
            return null;
        }

        $pme = $product->user;

        if (empty($pme) || empty($pme->erpnext_company_name) || empty($pme->erpnext_warehouse)) {
            return null;
        }

        try {
            $itemCode = $this->erpNext->findOrCreateItem($product->name);

            if ($type === 'ajustement') {
                $newAbsoluteQty = round(((float) $product->quantity_on_hand) + $delta, 2);
                $newAbsoluteQty = max($newAbsoluteQty, 0);
                $this->erpNext->adjustStockForPme($pme, $product->name, $newAbsoluteQty);
            } else {
                $direction = $delta > 0 ? 'in' : 'out';
                $this->erpNext->createStockMovementForPme(
                    $pme,
                    $product->name,
                    abs($delta),
                    (float) ($unitCost ?? 0),
                    $direction
                );
            }

            return $this->erpNext->getBinForItem($pme, $itemCode);
        } catch (\Throwable) {
            return null;
        }
    }

```

- [ ] **Step 3: Restructure `recordMovement()`**

Change:

```php
        $movement = DB::transaction(function () use ($product, $type, $quantity, $unitCost, $date, $reason, $notes, $actorUserId) {
            $locked = StockProduct::where('id', $product->id)->lockForUpdate()->firstOrFail();

            $currentQty = (float) $locked->quantity_on_hand;
            $currentAvg = (float) $locked->average_cost;

            $delta = match ($type) {
                'entree' => abs($quantity),
                'sortie' => -abs($quantity),
                'ajustement' => $quantity,
            };

            if (abs($delta) < 0.001) {
                throw new \InvalidArgumentException('La quantité ne peut pas être nulle.');
            }

            $newQty = round($currentQty + $delta, 2);
            if ($newQty < -0.001) {
                throw new \InvalidArgumentException(sprintf(
                    'Stock insuffisant : %.2f disponible(s), mouvement de %.2f demandé.',
                    $currentQty,
                    $delta
                ));
            }
            $newQty = max($newQty, 0);

            if ($delta > 0) {
                if ($unitCost !== null) {
                    $newAvg = $newQty > 0
                        ? round((($currentQty * $currentAvg) + ($delta * $unitCost)) / $newQty, 2)
                        : 0.0;
                } else {
                    $newAvg = $currentAvg;
                }
            } else {
                $unitCost = $unitCost ?? $currentAvg;
                $newAvg = $currentAvg;
            }

            $movement = StockMovement::create([
                'product_id' => $product->id,
                'user_id' => $product->user_id,
                'actor_user_id' => $actorUserId,
                'type' => $type,
                'quantity' => $delta,
                'unit_cost' => $unitCost,
                'quantity_after' => $newQty,
                'average_cost_after' => $newAvg,
                'movement_date' => $date->format('Y-m-d'),
                'reason' => $reason,
                'notes' => $notes,
            ]);

            $locked->update([
                'quantity_on_hand' => $newQty,
                'average_cost' => $newAvg,
            ]);

            TreasuryAudit::log($product->user_id, 'stock.movement.recorded', $movement, [
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $delta,
                'quantity_after' => $newQty,
            ]);

            return $movement;
        });

        SyncStockMovementToErpNext::dispatch($movement);

        return $movement;
    }
```

to:

```php
        $delta = match ($type) {
            'entree' => abs($quantity),
            'sortie' => -abs($quantity),
            'ajustement' => $quantity,
        };

        if (abs($delta) < 0.001) {
            throw new \InvalidArgumentException('La quantité ne peut pas être nulle.');
        }

        $erpNextResult = $this->tryRecordMovementOnErpNext($product, $type, $delta, $unitCost);

        $movement = DB::transaction(function () use ($product, $type, $delta, $unitCost, $date, $reason, $notes, $actorUserId, $erpNextResult) {
            $locked = StockProduct::where('id', $product->id)->lockForUpdate()->firstOrFail();

            $currentQty = (float) $locked->quantity_on_hand;
            $currentAvg = (float) $locked->average_cost;

            $newQty = round($currentQty + $delta, 2);
            if ($newQty < -0.001) {
                throw new \InvalidArgumentException(sprintf(
                    'Stock insuffisant : %.2f disponible(s), mouvement de %.2f demandé.',
                    $currentQty,
                    $delta
                ));
            }
            $newQty = max($newQty, 0);

            $storedUnitCost = $unitCost;

            if ($erpNextResult !== null) {
                $newQty = $erpNextResult['actual_qty'];
                $newAvg = $erpNextResult['valuation_rate'];
                $storedUnitCost = $storedUnitCost ?? $currentAvg;
            } elseif ($delta > 0) {
                if ($unitCost !== null) {
                    $newAvg = $newQty > 0
                        ? round((($currentQty * $currentAvg) + ($delta * $unitCost)) / $newQty, 2)
                        : 0.0;
                } else {
                    $newAvg = $currentAvg;
                }
            } else {
                $storedUnitCost = $unitCost ?? $currentAvg;
                $newAvg = $currentAvg;
            }

            $movement = StockMovement::create([
                'product_id' => $product->id,
                'user_id' => $product->user_id,
                'actor_user_id' => $actorUserId,
                'type' => $type,
                'quantity' => $delta,
                'unit_cost' => $storedUnitCost,
                'quantity_after' => $newQty,
                'average_cost_after' => $newAvg,
                'movement_date' => $date->format('Y-m-d'),
                'reason' => $reason,
                'notes' => $notes,
            ]);

            $locked->update([
                'quantity_on_hand' => $newQty,
                'average_cost' => $newAvg,
            ]);

            TreasuryAudit::log($product->user_id, 'stock.movement.recorded', $movement, [
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $delta,
                'quantity_after' => $newQty,
                'erpnext_engine' => $erpNextResult !== null,
            ]);

            return $movement;
        });

        if ($erpNextResult !== null) {
            StockMovementErpNextSync::updateOrCreate(
                ['stock_movement_id' => $movement->id],
                ['status' => 'synced', 'last_synced_at' => now()]
            );
        } else {
            SyncStockMovementToErpNext::dispatch($movement);
        }

        return $movement;
    }
```

(The type-validation `if (! in_array($type, ['entree', 'sortie', 'ajustement'], true))` at the very top of the method, and the method's signature/docblock, are untouched — only the body from the `$delta = match(...)` line onward changes.)

- [ ] **Step 4: Lint**

Run: `php -l app/Domain/Inventory/StockService.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Verify the ERPNext-engine success path via tinker against the real trial**

```bash
php artisan tinker --execute="
\$pme = \App\Models\User::find(15);
\$product = \App\Models\StockProduct::firstOrCreate(
    ['user_id' => \$pme->id, 'name' => 'Produit moteur ERPNext test'],
    ['unit' => 'unite', 'quantity_on_hand' => 0, 'average_cost' => 0, 'sale_price' => 0, 'is_active' => true]
);
\$service = app(\App\Domain\Inventory\StockService::class);
\$movement = \$service->recordMovement(\$product, 'entree', 10, 2000, now(), null, null, \$pme->id);
echo 'quantity_after: '.\$movement->quantity_after.PHP_EOL;
echo 'average_cost_after: '.\$movement->average_cost_after.PHP_EOL;
\$sync = \App\Models\StockMovementErpNextSync::where('stock_movement_id', \$movement->id)->first();
echo 'sync status: '.(\$sync->status ?? 'NO ROW').PHP_EOL;
"
```

Expected: `quantity_after: 10` (ERPNext's `Bin.actual_qty`, since this is a brand-new item with no prior stock), `average_cost_after: 2000` (ERPNext's `Bin.valuation_rate`), `sync status: synced` — and no queued job needed (this ran synchronously, `dispatchSync` was not even involved — the sync row is written directly by `recordMovement()` itself).

- [ ] **Step 6: Verify the local-fallback path by simulating an ERPNext outage**

```bash
php artisan tinker --execute="
\$pme = \App\Models\User::find(15);
\$product = \App\Models\StockProduct::firstOrCreate(
    ['user_id' => \$pme->id, 'name' => 'Produit repli local test'],
    ['unit' => 'unite', 'quantity_on_hand' => 0, 'average_cost' => 0, 'sale_price' => 0, 'is_active' => true]
);
config(['services.erpnext.api_key' => '']);
\$service = app(\App\Domain\Inventory\StockService::class);
\$movement = \$service->recordMovement(\$product, 'entree', 5, 1000, now(), null, null, \$pme->id);
echo 'quantity_after: '.\$movement->quantity_after.PHP_EOL;
echo 'average_cost_after: '.\$movement->average_cost_after.PHP_EOL;
\$sync = \App\Models\StockMovementErpNextSync::where('stock_movement_id', \$movement->id)->first();
echo 'sync status: '.(\$sync->status ?? 'NO ROW (job queued, not yet processed)').PHP_EOL;
"
```

Expected: `quantity_after: 5`, `average_cost_after: 1000` (the old local CUMP formula, since `api_key` was blanked so `enabled()` returns false) — local write succeeds despite the simulated outage, and either no sync row yet (job queued, matches `QUEUE_CONNECTION=database` locally) or `pending`/`failed` if a worker already picked it up. Restart `php artisan tinker` afterward (the `config()` override in Step 6 only affects that one process) before running any further real ERPNext calls.

- [ ] **Step 7: Commit**

```bash
git add app/Domain/Inventory/StockService.php
git commit -m "feat(erpnext-stock): try ERPNext synchronously in recordMovement, fall back to local CUMP

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 3: Deploy to production (LWS) and verify live against a real PME

**Files:** none (deployment + verification only)

**Interfaces:** none — end-to-end verification task, covering the 4 manual tests listed in the spec.

- [ ] **Step 1: Push all commits**

```bash
git push origin master
```

- [ ] **Step 2: Deploy on the LWS server via SSH**

```bash
bash deploy.sh
```

Expected: output ends with `=== Déploiement terminé ===`.

- [ ] **Step 3: Live verification for the real PME "NotifyMails #69" (user id 69, already provisioned)**

1. On `/stock`, record an entrée on an existing or new product → confirm the success message appears (possibly after a few seconds — this is the expected latency trade-off from the spec) and the displayed quantity/CUMP match what ERPNext's `Bin` shows for that item (cross-check via `/admin/erpnext-stock-test/show?user_id=69` or directly in ERPNext).
2. Record a sortie and an ajustement on the same product → confirm both reflect ERPNext's authoritative numbers, not a locally recomputed CUMP.
3. Measure and note the real response time of a successful movement (informational, per the spec's Test 4 — no numeric target, just confirm it's noticeably slower than before but still acceptable, e.g. under ~10 seconds).
4. Confirm `stock_movement_erpnext_syncs` rows for these movements show `status: synced` immediately, with no corresponding job ever appearing in the queue (check via `php artisan queue:failed` showing nothing new, or simply that the sync row is already `synced` right after the request completes).

No commit for this task (deployment/verification only). A genuine ERPNext-outage test in production (Test 2/3 from the spec) is impractical to safely simulate on the live site — the local verification in Task 2 Step 6 already covers that path; skip it here unless a real outage happens to occur naturally.

---

## Self-Review Notes

**Spec coverage:** All decisions covered — scope limited to `recordMovement()` only (Task 2, `createProduct()` untouched), the 4-step sequence (Task 2 Step 3: not-provisioned → skip straight to fallback via the `tryRecordMovementOnErpNext()` guard clauses; synchronous ERPNext call; Bin re-read on success; local CUMP fallback on failure with job dispatch), the "mark synced directly, no redispatch on success" rule (Task 2 Step 3, the `if ($erpNextResult !== null)` branch after the transaction), the accepted retroactive-drift limitation (documented in the spec, not re-litigated in code), the latency trade-off (called out in Task 3 Step 3). All 4 manual tests from the spec are covered: Test 1 → Task 2 Step 5 + Task 3 Step 1; Test 2 → Task 2 Step 6; Test 3 → Task 2 Step 3 code itself (the `empty($pme->erpnext_company_name)` guard, exercised implicitly whenever a non-provisioned PME is used — not re-tested separately since it's the same guard clause already proven in every prior sub-project this session); Test 4 → Task 3 Step 3.

**Placeholder scan:** No TBD/TODO; every step has literal code or literal commands.

**Type consistency:** `tryRecordMovementOnErpNext(StockProduct $product, string $type, float $delta, ?float $unitCost): ?array` (Task 2 Step 2) is called with exactly those 4 arguments, in that order, in Task 2 Step 3's rewritten `recordMovement()` body. Its return shape `{actual_qty, valuation_rate}|null` matches `getBinForItem()`'s return shape from Task 1 exactly (it's literally that method's return value, passed through unchanged). `StockMovementErpNextSync` fillable fields (`stock_movement_id`, `status`, `last_synced_at`, ... — defined in the earlier Stock live-sync sub-project) match the fields written in Task 2 Step 3's `updateOrCreate()` call.
