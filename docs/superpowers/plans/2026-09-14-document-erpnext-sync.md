# Document ERPNext Sync (KYC + OCR) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every KYC document approved and every accounting document (OCR) validated in PME360 automatically uploads a copy to ERPNext as a private file attached to the PME's Company, mirroring the existing sync pattern (Invoicing, Stock, Payroll).

**Architecture:** Add a new multipart-upload capability to `ErpNextClient` (`uploadFileForPme()`), two tracking tables/models (one per document source), and two queued Jobs that call it. Dispatch `SyncKycDocumentsToErpNext` from `AdminComplianceKycController::approve()` and `SyncAccountingDocumentToErpNext` from `AccountingDocumentController::storeValidation()`, both right after the existing local writes.

**Tech Stack:** Laravel 13 / PHP 8.4, MySQL, Laravel's `Http` facade (`Http::attach()` for multipart) — no new PHP packages.

## Global Constraints

- Both jobs must never let an exception propagate — production runs `QUEUE_CONNECTION=sync`, so an uncaught exception would break the real KYC-approval or document-validation request for a real user. Every `\Throwable` is caught and recorded as `failed`, exactly like `SyncInvoiceToErpNext`/`SyncStockMovementToErpNext`/`SyncPayrollToErpNext`.
- If the PME has no `erpnext_company_name`, mark the sync `failed` with a clear message and return — never attempt the API call.
- Both document types attach to the PME's **Company** on ERPNext (`doctype: 'Company', docname: $pme->erpnext_company_name`) — never to a specific transactional document (Sales Invoice, Journal Entry). This is a deliberate simplification from the spec, not an oversight.
- ERPNext's `upload_file` endpoint is multipart/form-data, not JSON and not `asForm()` — it needs a dedicated new private helper in `ErpNextClient`, distinct from the existing `get()`/`post()`/`postForm()`/`put()`.
- Both local file sources are stored on Laravel's `public` disk (`Storage::disk('public')`) — confirmed for `KycDocument` (`AdminComplianceKycController.php:68`) and `AccountingDocument` (`AccountingController.php:2731`/`2982`).
- Do not modify `KycDocument`, `AccountingDocument`, `AccountingEntry`, the OCR pipeline (`OcrPipelineService`/`OcrService`), or any existing view — only add dispatch calls after the existing local writes in the two named controller methods.

---

### Task 1: `uploadFileForPme()` in `ErpNextClient`

**Files:**
- Modify: `app/Services/ErpNextClient.php` (add a new private `uploadFile()` helper near the other private HTTP helpers, and a new public `uploadFileForPme()` method — placement: anywhere after the existing `put()` method and before the first public business method, e.g. right after `extractErrorMessage()`)

**Interfaces:**
- Consumes: `Illuminate\Support\Facades\Storage` (new import), the existing `baseUrl()`, `authHeader()`, `timeout()`, `extractErrorMessage()` private helpers.
- Produces: `public function uploadFileForPme(User $pme, string $localDisk, string $storedPath, string $originalName): array` — returns the ERPNext `File` document array (`{name, file_name, file_url, attached_to_doctype, attached_to_name, ...}`). Used by Task 4 (`SyncKycDocumentsToErpNext`) and Task 5 (`SyncAccountingDocumentToErpNext`).

- [ ] **Step 1: Add the `Storage` import**

In `app/Services/ErpNextClient.php`, change:

```php
use App\Exceptions\ErpNextApiException;
use App\Models\Invoice;
use App\Models\InvoiceErpNextCustomer;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
```

to:

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

- [ ] **Step 2: Add the private multipart upload helper**

Insert right after the existing `private function put(...)` method (immediately before `extractErrorMessage()`):

```php
    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function uploadFile(string $path, string $fileContents, string $fileName, array $fields): array
    {
        try {
            $response = Http::withHeaders(['Authorization' => $this->authHeader()])
                ->timeout($this->timeout())
                ->attach('file', $fileContents, $fileName)
                ->post($this->baseUrl().$path, $fields);
        } catch (\Throwable $exception) {
            throw new ErpNextApiException('ERPNext injoignable: '.$exception->getMessage());
        }

        if ($response->failed()) {
            throw new ErpNextApiException($this->extractErrorMessage($response));
        }

        return (array) ($response->json('message') ?? []);
    }
```

- [ ] **Step 3: Add the public method**

Insert right after `extractErrorMessage()`, before `findOrCreateCustomer()`:

```php
    /**
     * @return array<string, mixed>
     */
    public function uploadFileForPme(User $pme, string $localDisk, string $storedPath, string $originalName): array
    {
        $fileContents = Storage::disk($localDisk)->get($storedPath);

        if ($fileContents === null) {
            throw new ErpNextApiException("Fichier introuvable sur le disque local: $storedPath");
        }

        return $this->uploadFile('/api/method/upload_file', $fileContents, $originalName, [
            'doctype' => 'Company',
            'docname' => $pme->erpnext_company_name,
            'is_private' => 1,
        ]);
    }
```

- [ ] **Step 4: Verify via tinker against the real ERPNext trial**

Use the locally provisioned test PME (id 15) and a small real local file:

```bash
php artisan tinker --execute="
\$pme = \App\Models\User::find(15);
\Illuminate\Support\Facades\Storage::disk('public')->put('test-plan-uploads/hello.txt', 'contenu de test plan');
\$erpNext = app(\App\Services\ErpNextClient::class);
\$result = \$erpNext->uploadFileForPme(\$pme, 'public', 'test-plan-uploads/hello.txt', 'hello.txt');
echo 'file name: '.\$result['name'].PHP_EOL;
echo 'attached_to: '.\$result['attached_to_doctype'].' '.\$result['attached_to_name'].PHP_EOL;
"
```

Expected: no exception, `file name: <hash>`, `attached_to: Company Test Inscription E2E 1789166589 #15`.

- [ ] **Step 5: Commit**

```bash
git add app/Services/ErpNextClient.php
git commit -m "feat(erpnext-documents): add uploadFileForPme to ErpNextClient

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 2: `kyc_document_erpnext_syncs` migration + `KycDocumentErpNextSync` model

**Files:**
- Create: `database/migrations/2026_09_14_000000_create_kyc_document_erpnext_syncs_table.php`
- Create: `app/Models/KycDocumentErpNextSync.php`

**Interfaces:**
- Consumes: nothing (new table, no dependency on other tasks).
- Produces: `KycDocumentErpNextSync` Eloquent model with fillable `kyc_document_id`, `status`, `erpnext_file_name`, `last_error`, `last_synced_at`, `raw_response`; relation `document(): BelongsTo` to `KycDocument`. Used by Task 4 (the KYC Job).

- [ ] **Step 1: Create the migration**

Create `database/migrations/2026_09_14_000000_create_kyc_document_erpnext_syncs_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_document_erpnext_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kyc_document_id')->unique()->constrained('kyc_documents')->cascadeOnDelete();
            $table->string('status', 16)->default('pending');
            $table->string('erpnext_file_name')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_document_erpnext_syncs');
    }
};
```

- [ ] **Step 2: Run the migration locally**

Run: `php artisan migrate`
Expected: output includes `2026_09_14_000000_create_kyc_document_erpnext_syncs_table ... DONE`.

(If this fails with a missing-table error for `kyc_documents`, confirm the real table name via `php artisan tinker --execute="echo (new App\Models\KycDocument)->getTable();"` and adjust `constrained('kyc_documents')` — but `KycDocument` follows the default Eloquent naming convention, matching every existing reference to it in the codebase.)

- [ ] **Step 3: Create the model**

Create `app/Models/KycDocumentErpNextSync.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycDocumentErpNextSync extends Model
{
    protected $table = 'kyc_document_erpnext_syncs';

    protected $fillable = [
        'kyc_document_id',
        'status',
        'erpnext_file_name',
        'last_error',
        'last_synced_at',
        'raw_response',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(KycDocument::class, 'kyc_document_id');
    }
}
```

Note the explicit `protected $table = 'kyc_document_erpnext_syncs';` — without it, Eloquent's `Str::snake()` convention would split "ErpNext" as "Erp_Next" and guess the wrong table name, the same pitfall already hit and fixed for `StockMovementErpNextSync` in an earlier sub-project.

- [ ] **Step 4: Verify via tinker**

```bash
php artisan tinker --execute="
\$doc = \App\Models\KycDocument::first();
if (\$doc) {
    \$sync = \App\Models\KycDocumentErpNextSync::create(['kyc_document_id' => \$doc->id, 'status' => 'pending']);
    echo 'created id: '.\$sync->id.PHP_EOL;
    \$sync->delete();
    echo 'deleted ok';
} else {
    echo 'no local kyc documents exist yet, model class loaded fine';
}
"
```

Expected: no exception; either `created id: <n>` + `deleted ok`, or the fallback message.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_09_14_000000_create_kyc_document_erpnext_syncs_table.php app/Models/KycDocumentErpNextSync.php
git commit -m "feat(erpnext-documents): add kyc_document_erpnext_syncs table and model

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 3: `accounting_document_erpnext_syncs` migration + `AccountingDocumentErpNextSync` model

**Files:**
- Create: `database/migrations/2026_09_14_000100_create_accounting_document_erpnext_syncs_table.php`
- Create: `app/Models/AccountingDocumentErpNextSync.php`

**Interfaces:**
- Consumes: nothing (new table, no dependency on other tasks).
- Produces: `AccountingDocumentErpNextSync` Eloquent model with fillable `accounting_document_id`, `status`, `erpnext_file_name`, `last_error`, `last_synced_at`, `raw_response`; relation `document(): BelongsTo` to `AccountingDocument`. Used by Task 5 (the OCR document Job).

- [ ] **Step 1: Create the migration**

Create `database/migrations/2026_09_14_000100_create_accounting_document_erpnext_syncs_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_document_erpnext_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_document_id')->unique()->constrained('accounting_documents')->cascadeOnDelete();
            $table->string('status', 16)->default('pending');
            $table->string('erpnext_file_name')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_document_erpnext_syncs');
    }
};
```

- [ ] **Step 2: Run the migration locally**

Run: `php artisan migrate`
Expected: output includes `2026_09_14_000100_create_accounting_document_erpnext_syncs_table ... DONE`.

- [ ] **Step 3: Create the model**

Create `app/Models/AccountingDocumentErpNextSync.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingDocumentErpNextSync extends Model
{
    protected $table = 'accounting_document_erpnext_syncs';

    protected $fillable = [
        'accounting_document_id',
        'status',
        'erpnext_file_name',
        'last_error',
        'last_synced_at',
        'raw_response',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class, 'accounting_document_id');
    }
}
```

- [ ] **Step 4: Verify via tinker**

```bash
php artisan tinker --execute="
\$doc = \App\Models\AccountingDocument::first();
if (\$doc) {
    \$sync = \App\Models\AccountingDocumentErpNextSync::create(['accounting_document_id' => \$doc->id, 'status' => 'pending']);
    echo 'created id: '.\$sync->id.PHP_EOL;
    \$sync->delete();
    echo 'deleted ok';
} else {
    echo 'no local accounting documents exist yet, model class loaded fine';
}
"
```

Expected: no exception; either `created id: <n>` + `deleted ok`, or the fallback message.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_09_14_000100_create_accounting_document_erpnext_syncs_table.php app/Models/AccountingDocumentErpNextSync.php
git commit -m "feat(erpnext-documents): add accounting_document_erpnext_syncs table and model

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 4: `SyncKycDocumentsToErpNext` Job

**Files:**
- Create: `app/Jobs/SyncKycDocumentsToErpNext.php`

**Interfaces:**
- Consumes: `KycDocumentErpNextSync` (Task 2), `User::kycDocuments(): HasMany` (already exists, `app/Models/User.php:269`), `ErpNextClient::uploadFileForPme(User $pme, string $localDisk, string $storedPath, string $originalName): array` (Task 1), `ErpNextClient::enabled(): bool`.
- Produces: `SyncKycDocumentsToErpNext::dispatch(User $pme)` — a queueable job. Used by Task 6 (`AdminComplianceKycController::approve()`).

- [ ] **Step 1: Create the Job**

Create `app/Jobs/SyncKycDocumentsToErpNext.php`:

```php
<?php

namespace App\Jobs;

use App\Models\KycDocumentErpNextSync;
use App\Models\User;
use App\Services\ErpNextClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncKycDocumentsToErpNext implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public User $pme)
    {
    }

    public function handle(ErpNextClient $erpNext): void
    {
        if (! $erpNext->enabled()) {
            return;
        }

        if (empty($this->pme->erpnext_company_name)) {
            return;
        }

        $documents = $this->pme->kycDocuments()->where('status', 'approved')->get();

        foreach ($documents as $document) {
            $sync = KycDocumentErpNextSync::firstOrCreate(
                ['kyc_document_id' => $document->id],
                ['status' => 'pending']
            );

            if ($sync->status === 'synced') {
                continue;
            }

            try {
                $response = $erpNext->uploadFileForPme(
                    $this->pme,
                    'public',
                    $document->stored_path,
                    $document->original_name
                );

                $sync->update([
                    'status' => 'synced',
                    'erpnext_file_name' => $response['name'] ?? null,
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
}
```

- [ ] **Step 2: Verify via tinker against the real ERPNext trial**

Use the locally provisioned test PME (id 15) and a real local `KycDocument`:

```bash
php artisan tinker --execute="
\$pme = \App\Models\User::find(15);
\Illuminate\Support\Facades\Storage::disk('public')->put('kyc-documents/test-plan-kyc.txt', 'contenu kyc test plan');
\$doc = \App\Models\KycDocument::create([
    'user_id' => \$pme->id,
    'document_type' => 'trade_register',
    'stored_path' => 'kyc-documents/test-plan-kyc.txt',
    'original_name' => 'test-plan-kyc.txt',
    'status' => 'approved',
]);
\App\Jobs\SyncKycDocumentsToErpNext::dispatchSync(\$pme);
\$sync = \App\Models\KycDocumentErpNextSync::where('kyc_document_id', \$doc->id)->first();
echo 'status: '.\$sync->status.PHP_EOL;
echo 'erpnext file: '.\$sync->erpnext_file_name.PHP_EOL;
echo 'error: '.(\$sync->last_error ?? 'none').PHP_EOL;
"
```

Expected: `status: synced`, `erpnext file: <a hash name>`, `error: none`. (`dispatchSync()` runs the job immediately in-process without needing a queue worker — appropriate for this manual verification step.)

- [ ] **Step 3: Commit**

```bash
git add app/Jobs/SyncKycDocumentsToErpNext.php
git commit -m "feat(erpnext-documents): add SyncKycDocumentsToErpNext job

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 5: `SyncAccountingDocumentToErpNext` Job

**Files:**
- Create: `app/Jobs/SyncAccountingDocumentToErpNext.php`

**Interfaces:**
- Consumes: `AccountingDocumentErpNextSync` (Task 3), `AccountingDocument::user(): BelongsTo` (already exists, `app/Models/AccountingDocument.php:32-35`), `ErpNextClient::uploadFileForPme(...)` (Task 1), `ErpNextClient::enabled(): bool`.
- Produces: `SyncAccountingDocumentToErpNext::dispatch(AccountingDocument $document)` — a queueable job. Used by Task 7 (`AccountingDocumentController::storeValidation()`).

- [ ] **Step 1: Create the Job**

Create `app/Jobs/SyncAccountingDocumentToErpNext.php`:

```php
<?php

namespace App\Jobs;

use App\Models\AccountingDocument;
use App\Models\AccountingDocumentErpNextSync;
use App\Services\ErpNextClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncAccountingDocumentToErpNext implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public AccountingDocument $document)
    {
    }

    public function handle(ErpNextClient $erpNext): void
    {
        $sync = AccountingDocumentErpNextSync::firstOrCreate(
            ['accounting_document_id' => $this->document->id],
            ['status' => 'pending']
        );

        if ($sync->status === 'synced') {
            return;
        }

        if (! $erpNext->enabled()) {
            $sync->update(['status' => 'failed', 'last_error' => 'ERPNext non configuré.']);

            return;
        }

        $pme = $this->document->user;

        if (empty($pme) || empty($pme->erpnext_company_name)) {
            $sync->update([
                'status' => 'failed',
                'last_error' => 'PME non provisionnée sur ERPNext (erpnext_company_name manquant).',
            ]);

            return;
        }

        try {
            $response = $erpNext->uploadFileForPme(
                $pme,
                'public',
                $this->document->stored_path,
                $this->document->original_name
            );

            $sync->update([
                'status' => 'synced',
                'erpnext_file_name' => $response['name'] ?? null,
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

```bash
php artisan tinker --execute="
\$pme = \App\Models\User::find(15);
\Illuminate\Support\Facades\Storage::disk('public')->put('accounting-documents/test-plan-ocr.txt', 'contenu document ocr test plan');
\$doc = \App\Models\AccountingDocument::create([
    'user_id' => \$pme->id,
    'original_name' => 'test-plan-ocr.txt',
    'stored_path' => 'accounting-documents/test-plan-ocr.txt',
    'document_type' => 'Achat',
    'status' => 'validated',
]);
\App\Jobs\SyncAccountingDocumentToErpNext::dispatchSync(\$doc);
\$sync = \App\Models\AccountingDocumentErpNextSync::where('accounting_document_id', \$doc->id)->first();
echo 'status: '.\$sync->status.PHP_EOL;
echo 'erpnext file: '.\$sync->erpnext_file_name.PHP_EOL;
echo 'error: '.(\$sync->last_error ?? 'none').PHP_EOL;
"
```

Expected: `status: synced`, `erpnext file: <a hash name>`, `error: none`.

- [ ] **Step 3: Commit**

```bash
git add app/Jobs/SyncAccountingDocumentToErpNext.php
git commit -m "feat(erpnext-documents): add SyncAccountingDocumentToErpNext job

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 6: Dispatch from `AdminComplianceKycController::approve()`

**Files:**
- Modify: `app/Http/Controllers/AdminComplianceKycController.php:100-130`

**Interfaces:**
- Consumes: `SyncKycDocumentsToErpNext::dispatch(User $pme)` (Task 4).
- Produces: no new public interface — `approve()` keeps its exact existing behavior and return type (`RedirectResponse`).

- [ ] **Step 1: Add the import**

At the top of `app/Http/Controllers/AdminComplianceKycController.php`, add (alongside the other `use App\...` imports already there):

```php
use App\Jobs\SyncKycDocumentsToErpNext;
```

- [ ] **Step 2: Dispatch after the bulk update**

In `approve()`, change:

```php
        KycDocument::query()
            ->where('user_id', $user->id)
            ->update([
                'status' => 'approved',
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $request->user()?->id,
                'review_note' => (string) $request->input('note', ''),
            ]);

        $this->auditTrail->log(
```

to:

```php
        KycDocument::query()
            ->where('user_id', $user->id)
            ->update([
                'status' => 'approved',
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $request->user()?->id,
                'review_note' => (string) $request->input('note', ''),
            ]);

        SyncKycDocumentsToErpNext::dispatch($user);

        $this->auditTrail->log(
```

(Only the `SyncKycDocumentsToErpNext::dispatch($user);` line is added; every line before and after it stays byte-for-byte identical — do not touch the `KycDocument::query()->update(...)` call or the `$this->auditTrail->log(...)` call.)

- [ ] **Step 3: Verify existing local behavior is unchanged, end-to-end through the real queue**

```bash
php artisan tinker --execute="
\$admin = \App\Models\User::where('is_platform_admin', true)->first() ?? \App\Models\User::first();
\Illuminate\Support\Facades\Auth::login(\$admin);
\$pme = \App\Models\User::find(15);
\App\Models\KycDocument::create([
    'user_id' => \$pme->id,
    'document_type' => 'nif_attestation',
    'stored_path' => 'kyc-documents/test-plan-kyc-controller.txt',
    'original_name' => 'test-plan-kyc-controller.txt',
    'status' => 'pending',
]);
\Illuminate\Support\Facades\Storage::disk('public')->put('kyc-documents/test-plan-kyc-controller.txt', 'contenu kyc controller test');
\$controller = app(\App\Http\Controllers\AdminComplianceKycController::class);
\$request = \Illuminate\Http\Request::create('/admin/compliance/kyc/'.\$pme->id.'/approve', 'POST');
\$request->setUserResolver(fn () => \$admin);
\$controller->approve(\$request, \$pme);
echo 'pme kyc_status: '.\$pme->fresh()->kyc_status.PHP_EOL;
"
```

Expected: `pme kyc_status: approved` (local behavior unaffected). Then process the queue once to confirm the dispatched job runs correctly:

Run: `php artisan queue:work --once --queue=default`
Expected: one line ending `App\Jobs\SyncKycDocumentsToErpNext ... DONE`.

```bash
php artisan tinker --execute="
\$doc = \App\Models\KycDocument::where('stored_path', 'kyc-documents/test-plan-kyc-controller.txt')->latest()->first();
\$sync = \App\Models\KycDocumentErpNextSync::where('kyc_document_id', \$doc->id)->first();
echo 'status: '.(\$sync->status ?? 'NO SYNC ROW').PHP_EOL;
echo 'erpnext file: '.(\$sync->erpnext_file_name ?? 'none').PHP_EOL;
"
```

Expected: `status: synced` with a real ERPNext file name.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/AdminComplianceKycController.php
git commit -m "feat(erpnext-documents): dispatch SyncKycDocumentsToErpNext after KYC approval

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 7: Dispatch from `AccountingDocumentController::storeValidation()`

**Files:**
- Modify: `app/Http/Controllers/AccountingDocumentController.php:27-88` (the `storeValidation()` method)

**Interfaces:**
- Consumes: `SyncAccountingDocumentToErpNext::dispatch(AccountingDocument $document)` (Task 5).
- Produces: no new public interface — `storeValidation()` keeps its exact existing behavior and return type.

- [ ] **Step 1: Add the import**

In `app/Http/Controllers/AccountingDocumentController.php`, change:

```php
use App\Http\Controllers\Concerns\UsesClientWorkspace;
use App\Http\Controllers\Concerns\ValidatesPlanComptableAccount;
use App\Models\AccountingDocument;
use App\Models\AccountingEntry;
use App\Models\TreasuryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
```

to:

```php
use App\Http\Controllers\Concerns\UsesClientWorkspace;
use App\Http\Controllers\Concerns\ValidatesPlanComptableAccount;
use App\Jobs\SyncAccountingDocumentToErpNext;
use App\Models\AccountingDocument;
use App\Models\AccountingEntry;
use App\Models\TreasuryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
```

- [ ] **Step 2: Dispatch after the entry is created from the document**

In `storeValidation()`, change:

```php
        $this->createEntryFromDocument($document);

        return redirect()->route('accounting.documents')->with('status', 'Document validé et écriture générée.');
    }
```

to:

```php
        $this->createEntryFromDocument($document);

        SyncAccountingDocumentToErpNext::dispatch($document);

        return redirect()->route('accounting.documents')->with('status', 'Document validé et écriture générée.');
    }
```

(Only the dispatch line is added; every line before and after stays byte-for-byte identical — do not touch `createEntryFromDocument()` or its internals.)

- [ ] **Step 3: Verify existing local behavior is unchanged, end-to-end through the real queue**

```bash
php artisan tinker --execute="
\$pme = \App\Models\User::find(15);
\Illuminate\Support\Facades\Auth::login(\$pme);
\Illuminate\Support\Facades\Storage::disk('public')->put('accounting-documents/test-plan-ocr-controller.txt', 'contenu ocr controller test');
\$doc = \App\Models\AccountingDocument::create([
    'user_id' => \$pme->id,
    'original_name' => 'test-plan-ocr-controller.txt',
    'stored_path' => 'accounting-documents/test-plan-ocr-controller.txt',
    'document_type' => 'Achat',
    'status' => 'pending_validation',
    'extracted_data' => [],
]);
\$controller = app(\App\Http\Controllers\AccountingDocumentController::class);
\$request = \Illuminate\Http\Request::create('/accounting/documents/'.\$doc->id.'/validate', 'POST', [
    'partner' => 'Fournisseur Test Plan',
    'invoice_date' => now()->toDateString(),
    'amount_ttc' => 10000,
    'currency' => 'XOF',
    'document_type' => 'Achat',
    'debit_account' => '607 Achats de marchandises',
    'credit_account' => '401 Fournisseurs',
]);
\$request->setUserResolver(fn () => \$pme);
\$controller->storeValidation(\$request, \$doc);
echo 'document status: '.\$doc->fresh()->status.PHP_EOL;
"
```

Expected: `document status: validated` (local behavior unaffected — an `AccountingEntry` is still created exactly as before). Then process the queue once:

Run: `php artisan queue:work --once --queue=default`
Expected: one line ending `App\Jobs\SyncAccountingDocumentToErpNext ... DONE`.

```bash
php artisan tinker --execute="
\$doc = \App\Models\AccountingDocument::where('stored_path', 'accounting-documents/test-plan-ocr-controller.txt')->latest()->first();
\$sync = \App\Models\AccountingDocumentErpNextSync::where('accounting_document_id', \$doc->id)->first();
echo 'status: '.(\$sync->status ?? 'NO SYNC ROW').PHP_EOL;
echo 'erpnext file: '.(\$sync->erpnext_file_name ?? 'none').PHP_EOL;
"
```

Expected: `status: synced` with a real ERPNext file name.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/AccountingDocumentController.php
git commit -m "feat(erpnext-documents): dispatch SyncAccountingDocumentToErpNext after document validation

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 8: Deploy to production (LWS) and verify live against a real PME

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

Expected: output ends with `=== Déploiement terminé ===`. This runs `php artisan migrate --force`, which creates both `kyc_document_erpnext_syncs` and `accounting_document_erpnext_syncs` in production, plus the standard cache/OPcache purge.

- [ ] **Step 3: Live verification for the real PME "NotifyMails #69" (user id 69, already provisioned)**

1. As NotifyMails, submit a KYC document (e.g. Registre de commerce) through the normal compliance flow.
2. As admin, approve it via `/admin/compliance/kyc` → confirm the existing success message ("KYC/KYB validé avec succès.") still appears.
3. Open ERPNext, search `File`, filter by `Attached To Name` = "NotifyMails #69" → confirm a new private file appears with the same original filename.
4. As NotifyMails, import and validate an accounting document (OCR) through `/accounting/documents` → confirm the existing success message ("Document validé et écriture générée.") still appears and the local `AccountingEntry` is still created as before.
5. Confirm on ERPNext another `File` appears attached to the same Company for that second document.
6. Re-approve the same KYC batch a second time (if the UI allows re-triggering `approve()`) → confirm no duplicate `File` is created in ERPNext for the already-synced document (the job's `if ($sync->status === 'synced') continue;` guard prevents it).
7. Repeat steps 1-2 and 4 for a PME that is NOT provisioned on ERPNext → confirm both flows still work normally locally, and (via `php artisan tinker` on the server) the corresponding sync rows show `status: failed` with the expected messages.

No commit for this task (deployment/verification only).

---

## Self-Review Notes

**Spec coverage:** All decisions from the spec are covered — the new multipart `uploadFileForPme()` method (Task 1), attachment target always the Company (Task 1, Task 4, Task 5), two tracking tables matching prior sub-projects' shape with the explicit `$table` fix (Task 2, Task 3), the two dispatch points (Task 6, Task 7), resilience/guard clauses matching `SyncStockMovementToErpNext`/`SyncPayrollToErpNext` (Task 4, Task 5), idempotence via per-document sync status inside the KYC job's loop (Task 4), all 4 manual tests from the spec folded into Task 8 Step 3 (expanded to 7 concrete checks). Explicitly out-of-scope items (no ERPNext-side file processing, no sync of `AccountingEntry` itself, no admin UI, no reverse sync) are respected — no task touches `AccountingEntry`, `OcrPipelineService`, or `OcrService`.

**Placeholder scan:** No TBD/TODO; every step has literal code or literal commands.

**Type consistency:** `SyncKycDocumentsToErpNext::__construct(public User $pme)` (Task 4) matches `SyncKycDocumentsToErpNext::dispatch($user)` call in Task 6, where `$user` is the same `User` instance already loaded in `approve()`. `SyncAccountingDocumentToErpNext::__construct(public AccountingDocument $document)` (Task 5) matches `SyncAccountingDocumentToErpNext::dispatch($document)` in Task 7. `uploadFileForPme(User $pme, string $localDisk, string $storedPath, string $originalName)` (Task 1) is called identically in both Task 4 and Task 5 with `'public'` as `$localDisk` and each model's own `stored_path`/`original_name` fields — matching the exact fillable field names defined on `KycDocument` and `AccountingDocument`. `KycDocumentErpNextSync`/`AccountingDocumentErpNextSync` fillable fields (Task 2, Task 3) match exactly what Task 4/Task 5's `$sync->update([...])` calls write.