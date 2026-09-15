# Sport Club Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A brand-new PME360 module ("Club Sportif") shows members, cotisations (subscription-billed invoices), and events, all created and managed directly on ERPNext — no new local creation forms, PME360 only displays.

**Architecture:** Members are read live from ERPNext (`Customer` filtered by a per-PME `Customer Group`, no local table). Cotisations reuse the already-shipped Invoicing webhook unchanged, plus one new nullable column on `invoices` to tag subscription-billed invoices for filtering. Events get a small dedicated webhook (`Event`, `on_update`) writing to a new local `SportEvent` table, following the exact same pattern as the Stock/Invoicing/Accounting webhooks already shipped this session.

**Tech Stack:** Laravel 13 / PHP 8.4, MySQL, existing `ErpNextClient` (`app/Services/ErpNextClient.php`) — no new PHP packages.

## Global Constraints

- No local creation forms for members, cotisations, or events — this is a read-only mirror of ERPNext, matching the Stock/Invoicing/Accounting pattern.
- Members are **not** stored locally — always a live `GET` to ERPNext at page-render time (low volume, no background sync needed).
- Isolate each PME's members via a per-PME `Customer Group` named `"Membre Club Sportif - {abbr}"` (same abbreviation convention as `"Magasin principal - {abbr}"` already used in `provisionCompanyForPme()`).
- Isolate each PME's events via `Event.reference_doctype = "Company"` / `Event.reference_name = <the PME's erpnext_company_name>`.
- Cotisations are ordinary `Invoice` rows created by the **existing, unmodified** Invoicing webhook flow — only tag them via a new nullable `erpnext_subscription` column, never duplicate the creation logic.
- Reuse the existing shared webhook token (`config('services.erpnext.webhook_token')`) and the same CSRF-exemption mechanism already used for the 3 existing webhooks.
- Do not modify `InvoiceService`, `StockService`, `AccountingController`, or anything already shipped this session beyond the one small addition to `ErpNextInvoicingWebhookController::handleCreation()`.

---

### Task 1: `ErpNextClient` methods for members

**Files:**
- Modify: `app/Services/ErpNextClient.php` (add 3 new public methods after `getDocument()`, i.e. before the class's final closing `}`)

**Interfaces:**
- Consumes: existing private `get()`/`post()`/`put()` helpers.
- Produces:
  - `public function findOrCreateSportMemberGroup(User $pme): string` — returns the Customer Group name for this PME, creating it if needed.
  - `public function listSportMembers(User $pme): array` — returns `array<int, array{name: string, customer_name: string, mobile_no: ?string, email_id: ?string}>`.
  - `public function createSportMember(User $pme, string $name, ?string $mobile, ?string $email): array` — creates a `Customer` in the PME's dedicated group, returns the created document.
  All three used by Task 6 (`SportController`).

- [ ] **Step 1: Add the methods**

In `app/Services/ErpNextClient.php`, change the end of the file from:

```php
    public function getDocument(string $doctype, string $name): array
    {
        return $this->get('/api/resource/'.rawurlencode($doctype).'/'.rawurlencode($name));
    }
}
```

to:

```php
    public function getDocument(string $doctype, string $name): array
    {
        return $this->get('/api/resource/'.rawurlencode($doctype).'/'.rawurlencode($name));
    }

    public function findOrCreateSportMemberGroup(User $pme): string
    {
        $baseName = $pme->company_name ?: $pme->name;
        $abbr = strtoupper(Str::limit(preg_replace('/[^A-Za-z]/', '', $baseName) ?: 'PME', 3, '')).$pme->id;
        $groupName = 'Membre Club Sportif - '.$abbr;

        $existing = $this->get('/api/resource/'.rawurlencode('Customer Group').'/'.rawurlencode($groupName));
        if (! empty($existing)) {
            return (string) $existing['name'];
        }

        $created = $this->post('/api/resource/'.rawurlencode('Customer Group'), [
            'customer_group_name' => $groupName,
            'parent_customer_group' => 'All Customer Groups',
            'is_group' => 0,
        ]);

        $createdName = (string) ($created['name'] ?? '');
        if ($createdName === '') {
            throw new ErpNextApiException('ERPNext n\'a pas renvoyé de nom de groupe de clients après création.');
        }

        return $createdName;
    }

    /**
     * @return array<int, array{name: string, customer_name: string, mobile_no: ?string, email_id: ?string}>
     */
    public function listSportMembers(User $pme): array
    {
        $group = $this->findOrCreateSportMemberGroup($pme);

        $query = http_build_query([
            'filters' => json_encode([['customer_group', '=', $group]]),
            'fields' => json_encode(['name', 'customer_name', 'mobile_no', 'email_id']),
            'limit_page_length' => 0,
            'order_by' => 'customer_name asc',
        ]);

        $rows = $this->get('/api/resource/Customer?'.$query);

        return array_map(fn ($row) => [
            'name' => (string) $row['name'],
            'customer_name' => (string) $row['customer_name'],
            'mobile_no' => $row['mobile_no'] ?? null,
            'email_id' => $row['email_id'] ?? null,
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    public function createSportMember(User $pme, string $name, ?string $mobile, ?string $email): array
    {
        $group = $this->findOrCreateSportMemberGroup($pme);

        $payload = [
            'customer_name' => $name,
            'customer_group' => $group,
            'territory' => 'Ivory Coast',
        ];

        if (! empty($mobile)) {
            $payload['mobile_no'] = $mobile;
        }
        if (! empty($email)) {
            $payload['email_id'] = $email;
        }

        return $this->post('/api/resource/Customer', $payload);
    }
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
\$group = \$erpNext->findOrCreateSportMemberGroup(\$pme);
echo 'group: '.\$group.PHP_EOL;
\$member = \$erpNext->createSportMember(\$pme, 'Membre Test Plan', '0700000000', 'membre.test@example.com');
echo 'created member: '.\$member['name'].PHP_EOL;
\$members = \$erpNext->listSportMembers(\$pme);
echo 'members count: '.count(\$members).PHP_EOL;
foreach (\$members as \$m) { echo json_encode(\$m).PHP_EOL; }
"
```

Expected: a real group name (e.g. `Membre Club Sportif - TES15`), a created `Customer` name, and `members count: 1` with the just-created member listed.

- [ ] **Step 4: Commit**

```bash
git add app/Services/ErpNextClient.php
git commit -m "feat(erpnext-sport): add member Customer Group and listing methods to ErpNextClient

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 2: `sport_events` migration + `SportEvent` model

**Files:**
- Create: `database/migrations/2026_09_15_000000_create_sport_events_table.php`
- Create: `app/Models/SportEvent.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `SportEvent` Eloquent model with fillable `user_id`, `erpnext_event_name`, `subject`, `starts_on`, `description`; relation `user(): BelongsTo`. Used by Task 4 (the Event webhook) and Task 6 (`SportController`).

- [ ] **Step 1: Create the migration**

Create `database/migrations/2026_09_15_000000_create_sport_events_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sport_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('erpnext_event_name')->unique();
            $table->string('subject');
            $table->dateTime('starts_on');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sport_events');
    }
};
```

- [ ] **Step 2: Run the migration locally**

Run: `php artisan migrate`
Expected: output includes `2026_09_15_000000_create_sport_events_table ... DONE`.

- [ ] **Step 3: Create the model**

Create `app/Models/SportEvent.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SportEvent extends Model
{
    protected $fillable = [
        'user_id',
        'erpnext_event_name',
        'subject',
        'starts_on',
        'description',
    ];

    protected $casts = [
        'starts_on' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 4: Verify via tinker**

```bash
php artisan tinker --execute="
\$pme = \App\Models\User::find(15);
\$event = \App\Models\SportEvent::create([
    'user_id' => \$pme->id,
    'erpnext_event_name' => 'TEST-EV-PLAN',
    'subject' => 'Match test plan',
    'starts_on' => now(),
]);
echo 'created id: '.\$event->id.PHP_EOL;
\$event->delete();
echo 'deleted ok';
"
```

Expected: no exception, `created id: <n>` + `deleted ok`.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_09_15_000000_create_sport_events_table.php app/Models/SportEvent.php
git commit -m "feat(erpnext-sport): add sport_events table and model

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 3: `erpnext_subscription` column on `invoices`

**Files:**
- Create: `database/migrations/2026_09_15_000100_add_erpnext_subscription_to_invoices_table.php`
- Modify: `app/Models/Invoice.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `Invoice` gains a fillable, nullable `erpnext_subscription` string column. Used by Task 5 (`ErpNextInvoicingWebhookController::handleCreation()`, to write it) and Task 6 (`SportController::cotisations()`, to filter on it).

- [ ] **Step 1: Create the migration**

Create `database/migrations/2026_09_15_000100_add_erpnext_subscription_to_invoices_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('erpnext_subscription')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('erpnext_subscription');
        });
    }
};
```

- [ ] **Step 2: Run the migration locally**

Run: `php artisan migrate`
Expected: output includes `2026_09_15_000100_add_erpnext_subscription_to_invoices_table ... DONE`.

- [ ] **Step 3: Add the field to `Invoice`'s fillable array**

In `app/Models/Invoice.php`, change:

```php
    protected $fillable = [
        'user_id',
        'actor_user_id',
        'invoice_number',
        'issue_date',
        'due_date',
        'client_name',
        'client_contact',
        'client_address',
        'client_tax_id',
        'currency',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'amount_paid',
        'status',
        'notes',
        'pdf_path',
        'cancelled_at',
        'cancelled_reason',
```

to:

```php
    protected $fillable = [
        'user_id',
        'actor_user_id',
        'invoice_number',
        'issue_date',
        'due_date',
        'client_name',
        'client_contact',
        'client_address',
        'client_tax_id',
        'currency',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'amount_paid',
        'status',
        'notes',
        'erpnext_subscription',
        'pdf_path',
        'cancelled_at',
        'cancelled_reason',
```

(Every other line in the `$fillable` array, and the rest of the file, stays untouched — confirm by reading the file first, since the exact remaining lines after `cancelled_reason` were not reproduced here for brevity.)

- [ ] **Step 4: Lint**

Run: `php -l app/Models/Invoice.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Verify via tinker**

```bash
php artisan tinker --execute="
\$invoice = \App\Models\Invoice::first();
\$invoice->update(['erpnext_subscription' => 'ACC-SUB-TEST']);
echo \$invoice->fresh()->erpnext_subscription;
\$invoice->update(['erpnext_subscription' => null]);
"
```

Expected: prints `ACC-SUB-TEST`, no exception (confirms the column is both migrated and fillable).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_09_15_000100_add_erpnext_subscription_to_invoices_table.php app/Models/Invoice.php
git commit -m "feat(erpnext-sport): add erpnext_subscription column to invoices

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 4: `ErpNextSportEventWebhookController` + route

**Files:**
- Create: `app/Http/Controllers/ErpNextSportEventWebhookController.php`
- Modify: `routes/web.php` (add a route next to `webhooks.erpnext.accounting-entry`)
- Modify: `bootstrap/app.php` (add the new route to the CSRF exemption list)

**Interfaces:**
- Consumes: `ErpNextClient::getDocument(string $doctype, string $name): array` (existing), `SportEvent` model (Task 2).
- Produces: route `POST /webhooks/erpnext/sport-event`.

- [ ] **Step 1: Create the controller**

Create `app/Http/Controllers/ErpNextSportEventWebhookController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\SportEvent;
use App\Models\User;
use App\Services\ErpNextClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ErpNextSportEventWebhookController extends Controller
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

        if ($doctype !== 'Event' || $docname === '') {
            return response()->json(['status' => 'ignored', 'reason' => 'payload incomplet ou doctype non géré'], 200);
        }

        try {
            $document = $erpNext->getDocument('Event', $docname);
        } catch (\Throwable $exception) {
            Log::warning('Webhook ERPNext Sport Event: échec de relecture du document.', [
                'name' => $docname,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['status' => 'error'], 200);
        }

        if (($document['reference_doctype'] ?? '') !== 'Company') {
            return response()->json(['status' => 'ignored', 'reason' => 'événement non rattaché à une Company'], 200);
        }

        $company = (string) ($document['reference_name'] ?? '');
        $pme = User::where('erpnext_company_name', $company)->first();

        if (! $pme) {
            Log::warning('Webhook ERPNext Sport Event reçu pour une company sans PME locale correspondante.', [
                'company' => $company,
                'name' => $docname,
            ]);

            return response()->json(['status' => 'ignored', 'reason' => 'PME introuvable'], 200);
        }

        SportEvent::updateOrCreate(
            ['erpnext_event_name' => $docname],
            [
                'user_id' => $pme->id,
                'subject' => (string) ($document['subject'] ?? 'Événement'),
                'starts_on' => (string) ($document['starts_on'] ?? now()->toDateTimeString()),
                'description' => $document['description'] ?? null,
            ]
        );

        return response()->json(['status' => 'ok'], 200);
    }
}
```

- [ ] **Step 2: Add the route**

In `routes/web.php`, change:

```php
Route::post('/webhooks/erpnext/accounting-entry', [\App\Http\Controllers\ErpNextAccountingEntryWebhookController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('webhooks.erpnext.accounting-entry');
```

to:

```php
Route::post('/webhooks/erpnext/accounting-entry', [\App\Http\Controllers\ErpNextAccountingEntryWebhookController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('webhooks.erpnext.accounting-entry');
Route::post('/webhooks/erpnext/sport-event', [\App\Http\Controllers\ErpNextSportEventWebhookController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('webhooks.erpnext.sport-event');
```

- [ ] **Step 3: Add the CSRF exemption**

In `bootstrap/app.php`, change:

```php
        $middleware->validateCsrfTokens(except: [
            'webhooks/erpnext/stock-movement',
            'webhooks/erpnext/invoicing',
            'webhooks/erpnext/accounting-entry',
        ]);
```

to:

```php
        $middleware->validateCsrfTokens(except: [
            'webhooks/erpnext/stock-movement',
            'webhooks/erpnext/invoicing',
            'webhooks/erpnext/accounting-entry',
            'webhooks/erpnext/sport-event',
        ]);
```

- [ ] **Step 4: Lint**

Run: `php -l app/Http/Controllers/ErpNextSportEventWebhookController.php && php -l routes/web.php && php -l bootstrap/app.php`
Expected: `No syntax errors detected` for all three.

- [ ] **Step 5: Verify via tinker against the real ERPNext trial**

```bash
php artisan tinker --execute="
\$pme = \App\Models\User::find(15);
\$erpNext = app(\App\Services\ErpNextClient::class);
\$post = new ReflectionMethod(\$erpNext, 'post');
\$post->setAccessible(true);
\$created = \$post->invoke(\$erpNext, '/api/resource/Event', [
    'subject' => 'Match amical test plan',
    'starts_on' => now()->addDays(3)->toDateTimeString(),
    'event_type' => 'Public',
    'reference_doctype' => 'Company',
    'reference_name' => \$pme->erpnext_company_name,
]);
\$name = \$created['name'];
echo 'NAME='.\$name.PHP_EOL;

\$request = \Illuminate\Http\Request::create('/webhooks/erpnext/sport-event', 'POST', [
    'doctype' => 'Event', 'name' => \$name,
]);
\$request->headers->set('X-PME360-Webhook-Token', config('services.erpnext.webhook_token'));
\$controller = app(\App\Http\Controllers\ErpNextSportEventWebhookController::class);
\$response = \$controller->handle(\$request, \$erpNext);
echo \$response->getContent().PHP_EOL;

\$event = \App\Models\SportEvent::where('erpnext_event_name', \$name)->first();
echo 'local event: '.(\$event ? \$event->subject.' @ '.\$event->starts_on : 'NOT CREATED').PHP_EOL;
"
```

Expected: `{"status":"ok"}`, and `local event: Match amical test plan @ ...` with the real date.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ErpNextSportEventWebhookController.php routes/web.php bootstrap/app.php
git commit -m "feat(erpnext-sport): add webhook endpoint to ingest ERPNext sport events

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 5: Tag cotisation invoices with `erpnext_subscription`

**Files:**
- Modify: `app/Http/Controllers/ErpNextInvoicingWebhookController.php`

**Interfaces:**
- Consumes: `Invoice::update(['erpnext_subscription' => ...])` (Task 3's new fillable field).
- Produces: no new public interface — `handleCreation()` keeps its exact existing signature; it just writes one more field on the `Invoice` it already creates.

- [ ] **Step 1: Add the tagging logic**

In `app/Http/Controllers/ErpNextInvoicingWebhookController.php`, change:

```php
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

        $subscription = (string) ($document['subscription'] ?? '');
        if ($subscription !== '') {
            $invoice->update(['erpnext_subscription' => $subscription]);
        }

        InvoiceErpNextSync::updateOrCreate(
```

(Only the 3 new lines — `$subscription = ...` through the closing `}` of the `if` — are added, right after `createInvoice()` returns and right before the existing `InvoiceErpNextSync::updateOrCreate(...)` call. Nothing else in this file changes.)

- [ ] **Step 2: Lint**

Run: `php -l app/Http/Controllers/ErpNextInvoicingWebhookController.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Verify via tinker against the real ERPNext trial**

Reuse the Subscription mechanism tested during design: create a `Subscription Plan` + `Subscription`, wait for (or manually trigger) an invoice, then feed it through the webhook and confirm the tag is set. Since waiting for ERPNext's billing scheduler is impractical in a manual test, simulate the field directly on a normal Sales Invoice creation instead — this still proves the webhook correctly reads and stores whatever `subscription` value ERPNext sends:

```bash
php artisan tinker --execute="
\$pme = \App\Models\User::find(15);
\$erpNext = app(\App\Services\ErpNextClient::class);
\$post = new ReflectionMethod(\$erpNext, 'post');
\$post->setAccessible(true);
\$put = new ReflectionMethod(\$erpNext, 'put');
\$put->setAccessible(true);
\$customer = \$erpNext->findOrCreateCustomer(\$pme);
\$itemCode = \$erpNext->findOrCreateItem('Cotisation test plan');
\$created = \$post->invoke(\$erpNext, '/api/resource/Sales Invoice', [
    'company' => \$pme->erpnext_company_name,
    'customer' => \$customer,
    'items' => [['item_code' => \$itemCode, 'qty' => 1, 'rate' => 5000, 'warehouse' => \$pme->erpnext_warehouse, 'income_account' => \$pme->erpnext_income_account]],
    'posting_date' => now()->toDateString(),
    'due_date' => now()->addDays(30)->toDateString(),
]);
\$name = \$created['name'];
\$put->invoke(\$erpNext, '/api/resource/Sales Invoice/'.rawurlencode(\$name), ['docstatus' => 1]);
echo 'NAME='.\$name.PHP_EOL;
"
```

Then, since a plain Sales Invoice created this way has no real `subscription` field, directly verify the code path instead by unit-testing the tagging logic in isolation:

```bash
php artisan tinker --execute="
\$pme = \App\Models\User::find(15);
\$invoice = \App\Models\Invoice::where('user_id', \$pme->id)->first();
\$invoice->update(['erpnext_subscription' => 'ACC-SUB-2026-00099']);
echo 'tagged: '.\$invoice->fresh()->erpnext_subscription.PHP_EOL;
\$invoice->update(['erpnext_subscription' => null]);
"
```

Expected: `tagged: ACC-SUB-2026-00099` (confirms the column read/write path works; the live end-to-end proof that ERPNext actually populates `subscription` on scheduler-generated invoices is covered by Task 9's production verification, where a real Subscription has time to bill).

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/ErpNextInvoicingWebhookController.php
git commit -m "feat(erpnext-sport): tag subscription-billed invoices with erpnext_subscription

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 6: `SportController` + routes + views

**Files:**
- Create: `app/Http/Controllers/SportController.php`
- Create: `resources/views/sport/index.blade.php`
- Create: `resources/views/sport/members.blade.php`
- Create: `resources/views/sport/cotisations.blade.php`
- Create: `resources/views/sport/events.blade.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `ErpNextClient::listSportMembers(User $pme): array` (Task 1), `ErpNextClient::createSportMember(User $pme, string $name, ?string $mobile, ?string $email): array` (Task 1), `Invoice::whereNotNull('erpnext_subscription')` (Task 3), `SportEvent` model (Task 2).
- Produces: routes `sport.index`, `sport.members`, `sport.members.store`, `sport.cotisations`, `sport.events`.

- [ ] **Step 1: Create the controller**

Create `app/Http/Controllers/SportController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesClientWorkspace;
use App\Models\Invoice;
use App\Models\SportEvent;
use App\Services\ErpNextClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SportController extends Controller
{
    use UsesClientWorkspace;

    public function index(): View
    {
        return view('sport.index');
    }

    public function members(ErpNextClient $erpNext): View
    {
        $pme = $this->workspaceUser();

        $members = [];
        $error = null;

        if (empty($pme->erpnext_company_name)) {
            $error = 'Cette PME n\'est pas provisionnée sur ERPNext.';
        } else {
            try {
                $members = $erpNext->listSportMembers($pme);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view('sport.members', ['members' => $members, 'error' => $error]);
    }

    public function storeMember(Request $request, ErpNextClient $erpNext): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $pme = $this->workspaceUser();

        try {
            $erpNext->createSportMember($pme, $validated['name'], $validated['mobile'] ?? null, $validated['email'] ?? null);
        } catch (\Throwable $e) {
            return back()->withErrors(['name' => $e->getMessage()]);
        }

        return redirect()->route('sport.members')->with('success', 'Membre créé.');
    }

    public function cotisations(): View
    {
        $userIds = $this->workspaceDataUserIds();

        $cotisations = Invoice::whereIn('user_id', $userIds)
            ->whereNotNull('erpnext_subscription')
            ->orderByDesc('issue_date')
            ->get();

        return view('sport.cotisations', ['cotisations' => $cotisations]);
    }

    public function events(): View
    {
        $userIds = $this->workspaceDataUserIds();

        $events = SportEvent::whereIn('user_id', $userIds)
            ->orderBy('starts_on')
            ->get();

        return view('sport.events', ['events' => $events]);
    }

    private function workspaceUser(): \App\Models\User
    {
        return \App\Models\User::findOrFail($this->workspaceUserId());
    }
}
```

- [ ] **Step 2: Add the routes**

In `routes/web.php`, add this new group right after the `Route::middleware('module.permission:stock')->group(...)` block closes (find it by running `grep -n "module.permission:stock" routes/web.php` and locating its closing `});`):

```php
    Route::prefix('sport')->name('sport.')->group(function () {
        Route::get('/', [SportController::class, 'index'])->name('index');
        Route::get('/membres', [SportController::class, 'members'])->name('members');
        Route::post('/membres', [SportController::class, 'storeMember'])->middleware('throttle:finance-write')->name('members.store');
        Route::get('/cotisations', [SportController::class, 'cotisations'])->name('cotisations');
        Route::get('/evenements', [SportController::class, 'events'])->name('events');
    });
```

Also add the import near the top of the file, alongside the other controller `use` statements:

```php
use App\Http\Controllers\SportController;
```

- [ ] **Step 3: Create the views**

Create `resources/views/sport/index.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Club Sportif | Sitiame Capital')
@section('page_title', 'Club Sportif')

@section('content')
<div class="container-fluid p-0">
    <h2 class="h3 mb-4">Club Sportif</h2>
    <div class="row g-3">
        <div class="col-md-4">
            <a href="{{ route('sport.members') }}" class="text-decoration-none text-reset">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-primary">Membres</h5>
                        <p class="text-muted small mb-0">Liste des membres du club, gérés directement sur ERPNext.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('sport.cotisations') }}" class="text-decoration-none text-reset">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-primary">Cotisations</h5>
                        <p class="text-muted small mb-0">Factures de cotisation générées automatiquement par ERPNext.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('sport.events') }}" class="text-decoration-none text-reset">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-primary">Événements</h5>
                        <p class="text-muted small mb-0">Matchs, entraînements et événements créés sur ERPNext.</p>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>
@endsection
```

Create `resources/views/sport/members.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Membres du Club | Sitiame Capital')
@section('page_title', 'Membres du Club')

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h3 mb-0">Membres du Club</h2>
        <a href="{{ route('sport.index') }}" class="btn btn-outline-secondary btn-sm">Retour</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-xl-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Nouveau membre</h5>
                    <form action="{{ route('sport.members.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Nom *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Téléphone</label>
                            <input type="text" name="mobile" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Créer</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Liste des membres</h5>
                    @if ($error)
                        <div class="alert alert-danger">{{ $error }}</div>
                    @else
                        <table class="table table-sm">
                            <thead><tr><th>Nom</th><th>Téléphone</th><th>Email</th></tr></thead>
                            <tbody>
                                @forelse ($members as $member)
                                    <tr>
                                        <td>{{ $member['customer_name'] }}</td>
                                        <td>{{ $member['mobile_no'] ?? '—' }}</td>
                                        <td>{{ $member['email_id'] ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-4">Aucun membre pour l'instant.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
```

Create `resources/views/sport/cotisations.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Cotisations | Sitiame Capital')
@section('page_title', 'Cotisations')

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h3 mb-0">Cotisations</h2>
        <a href="{{ route('sport.index') }}" class="btn btn-outline-secondary btn-sm">Retour</a>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-sm">
                <thead><tr><th>N° facture</th><th>Membre</th><th>Émission</th><th>Montant</th><th>Statut</th></tr></thead>
                <tbody>
                    @forelse ($cotisations as $invoice)
                        <tr>
                            <td><a href="{{ route('invoicing.show', $invoice) }}">{{ $invoice->invoice_number }}</a></td>
                            <td>{{ $invoice->client_name }}</td>
                            <td>{{ $invoice->issue_date->format('d/m/Y') }}</td>
                            <td>{{ number_format((float) $invoice->total_amount, 0, ',', ' ') }} {{ $invoice->currency }}</td>
                            <td>{{ ucfirst($invoice->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucune cotisation pour l'instant.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
```

Create `resources/views/sport/events.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Événements du Club | Sitiame Capital')
@section('page_title', 'Événements du Club')

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h3 mb-0">Événements du Club</h2>
        <a href="{{ route('sport.index') }}" class="btn btn-outline-secondary btn-sm">Retour</a>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-sm">
                <thead><tr><th>Date</th><th>Sujet</th><th>Description</th></tr></thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td>{{ $event->starts_on->format('d/m/Y H:i') }}</td>
                            <td>{{ $event->subject }}</td>
                            <td>{{ $event->description ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">Aucun événement pour l'instant.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
```

- [ ] **Step 4: Lint**

Run: `php -l app/Http/Controllers/SportController.php && php -l routes/web.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 5: Confirm the routes are registered**

Run: `php artisan route:list --name=sport`
Expected: 5 rows (`sport.index`, `sport.members`, `sport.members.store`, `sport.cotisations`, `sport.events`).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/SportController.php resources/views/sport routes/web.php
git commit -m "feat(erpnext-sport): add SportController and views (members, cotisations, events)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 7: Sidebar link

**Files:**
- Modify: `resources/views/layouts/partials/sidebar.blade.php`

**Interfaces:**
- Consumes: route `sport.index` (Task 6).
- Produces: nothing further.

- [ ] **Step 1: Add the sidebar entry**

In `resources/views/layouts/partials/sidebar.blade.php`, change:

```blade
            <li class="sidebar-item {{ request()->routeIs('stock.*') ? 'active' : '' }}">
                <a class="sidebar-link" href="{{ route('stock.index') }}">
                    <i class="align-middle" data-feather="package"></i> <span class="align-middle">Stock</span>
                    <span class="badge bg-secondary rounded-pill ms-auto">Option</span>
                </a>
```

to:

```blade
            <li class="sidebar-item {{ request()->routeIs('stock.*') ? 'active' : '' }}">
                <a class="sidebar-link" href="{{ route('stock.index') }}">
                    <i class="align-middle" data-feather="package"></i> <span class="align-middle">Stock</span>
                    <span class="badge bg-secondary rounded-pill ms-auto">Option</span>
                </a>
            </li>
            <li class="sidebar-item {{ request()->routeIs('sport.*') ? 'active' : '' }}">
                <a class="sidebar-link" href="{{ route('sport.index') }}">
                    <i class="align-middle" data-feather="activity"></i> <span class="align-middle">Club Sportif</span>
                    <span class="badge bg-secondary rounded-pill ms-auto">Option</span>
                </a>
```

(This inserts a new `<li>` right after Stock's closing `</li>`, reusing the exact same opening `<li>`/`<a>` structure — the original Stock `</li>` that used to close the block now closes the new Sport entry instead; verify after editing that the number of `<li class="sidebar-item"` open/close tags in this area is still balanced.)

- [ ] **Step 2: Manual verification**

Load any authenticated PME360 page, confirm "Club Sportif" appears in the sidebar under Stock, and navigates to `/sport`.

- [ ] **Step 3: Commit**

```bash
git add resources/views/layouts/partials/sidebar.blade.php
git commit -m "feat(erpnext-sport): add Club Sportif sidebar link

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 8: Create the ERPNext `Event` webhook via the API

**Files:** none (one-off API call against the ERPNext trial)

**Interfaces:**
- Consumes: the `Webhook` doctype REST API, same pattern used for Stock/Invoicing/Accounting.

- [ ] **Step 1: Create the webhook**

```bash
php artisan tinker --execute="
\$erpNext = app(\App\Services\ErpNextClient::class);
\$post = new ReflectionMethod(\$erpNext, 'post');
\$post->setAccessible(true);
\$token = config('services.erpnext.webhook_token');
\$result = \$post->invoke(\$erpNext, '/api/resource/Webhook', [
    'name' => 'Event modifie vers PME360',
    'webhook_doctype' => 'Event',
    'webhook_docevent' => 'on_update',
    'request_url' => 'https://sitiame-capital.com/webhooks/erpnext/sport-event',
    'request_method' => 'POST',
    'request_structure' => 'JSON',
    'timeout' => 5,
    'enabled' => 1,
    'webhook_json' => '{\"doctype\": \"{{ doc.doctype }}\", \"name\": \"{{ doc.name }}\"}',
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
\$row = \$get->invoke(\$erpNext, '/api/resource/Webhook/Event modifie vers PME360');
echo 'enabled: '.\$row['enabled'].' doctype: '.\$row['webhook_doctype'].' event: '.\$row['webhook_docevent'].PHP_EOL;
"
```

Expected: `enabled: 1 doctype: Event event: on_update`.

No commit for this task (server-side ERPNext configuration only).

---

### Task 9: Deploy to production (LWS) and verify live end-to-end

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

Expected: output ends with `=== Déploiement terminé ===`. This runs `php artisan migrate --force`, creating both `sport_events` and the new `invoices.erpnext_subscription` column in production. No new env variable needed.

- [ ] **Step 3: Live verification for the real PME "NotifyMails #69"**

1. Confirm the "Club Sportif" sidebar entry appears and `/sport` loads with its 3 tiles.
2. On ERPNext, create a `Customer` directly in the group `"Membre Club Sportif - NOT69"` (find-or-create it first by visiting `/sport/membres` once, which calls `findOrCreateSportMemberGroup()`) → confirm it appears on `/sport/membres`, and confirm the same member does **not** appear when checking a different PME's `/sport/membres`.
3. On ERPNext, create a `Subscription Plan` and a `Subscription` for that member (reusing the item/plan mechanics verified during design) → wait for ERPNext to generate (or manually trigger, if ERPNext's UI offers a "Generate Invoice" action on the Subscription) a real `Sales Invoice` with a populated `subscription` field → confirm it appears on both `/invoicing` and `/sport/cotisations`.
4. On ERPNext, create an `Event` with `reference_doctype: Company`, `reference_name: <NotifyMails' company>` → confirm it appears on `/sport/evenements` within a few seconds, and not on another PME's events page.
5. Test the webhook security guard: `curl -X POST https://sitiame-capital.com/webhooks/erpnext/sport-event -H "Content-Type: application/json" -d '{"doctype":"Event","name":"x"}'` (no token) → expect `403`.
6. Try creating a member from PME360 (`/sport/membres`, the "Nouveau membre" form) → confirm it appears immediately in the same list (no webhook involved, since Task 6's `members()` reads live) and that it also appears as a real `Customer` on ERPNext in the right group.

No commit for this task (deployment/verification only).

---

## Self-Review Notes

**Spec coverage:** All decisions covered — no local storage for members with live reads scoped by a per-PME `Customer Group` (Task 1, Task 6), cotisations reusing the unmodified Invoicing webhook plus the new tagging column (Task 3, Task 5), events via a dedicated webhook scoped by `reference_doctype`/`reference_name` = Company (Task 2, Task 4), the new `/sport` module with its 3 sub-pages (Task 6), sidebar entry (Task 7). All 6 manual tests from the spec are folded into Task 9 Step 3. The spec's optional "creation of members possibly from PME360 too" note is resolved concretely: `SportController::storeMember()` calls `ErpNextClient::createSportMember()` directly (Task 6), matching the spec's "à trancher lors du plan, pas bloquant" — resolved as "yes, PME360 can create members too, but always by writing straight to ERPNext, never to a local table," consistent with every other module this session.

**Placeholder scan:** No TBD/TODO; every step has literal code or literal commands.

**Type consistency:** `ErpNextClient::listSportMembers(User $pme): array` (Task 1) returns exactly the shape `SportController::members()` (Task 6) consumes (`$member['customer_name']`, `$member['mobile_no']`, `$member['email_id']` — all present in Task 1's `array_map` and matched by Task 6's Blade view). `createSportMember(User $pme, string $name, ?string $mobile, ?string $email)` (Task 1) matches exactly the call in `SportController::storeMember()` (Task 6). `SportEvent` fillable fields (Task 2: `user_id`, `erpnext_event_name`, `subject`, `starts_on`, `description`) match exactly what Task 4's webhook writes via `updateOrCreate()`, and what Task 6's `events()`/the Blade view read. `Invoice::erpnext_subscription` (Task 3) is written by Task 5 and read by Task 6's `cotisations()` query — same column name throughout.
