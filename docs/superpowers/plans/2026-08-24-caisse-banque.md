# Caisse Banque (statut payé/impayé des écritures) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a payment-status layer (Impayé / Partiellement payé / Payé) to `AccountingEntry`, let users record one or more payments against an entry (each one auto-creating a linked `TreasuryTransaction`), and expose it through a new "Caisse Banque" screen attached to the entry-creation module.

**Architecture:** Two new columns on `accounting_entries` (`payment_status`, `amount_paid`) cache the current state; a new `accounting_entry_payments` table stores the individual payment records that drive that cache. A new `AccountingController::caisseBanque()` action lists entries with filters (reusing the existing Journal filter pattern), and `AccountingController::storeEntryPayment()` records a payment, updates the cache, and creates a `TreasuryTransaction` when the entry's accounts indicate a clear cash direction (411 Clients debit → encaissement, 401 Fournisseurs credit → décaissement).

**Tech Stack:** Laravel 13 / PHP 8.4, Blade, MySQL/PostgreSQL migrations, no JS framework — plain Blade forms and Bootstrap.

## Global Constraints

- Every new entry (from any of the 3 creation paths — manual form, OCR auto-validated, OCR manually validated) gets a `payment_status` on creation, no exceptions (per approved spec).
- An entry whose debit or credit already touches a treasury-class account (first significant digit `5`) is created as `paid` immediately — no payment expected.
- A payment amount can never push `amount_paid` above `amount` (server-side validation, reject with an explicit error otherwise).
- Reuse the existing `-subtle`/`-emphasis` Bootstrap badge convention already used in `resources/views/accounting/documents.blade.php` for status badges.
- Reuse the existing GET-form-with-auto-submitting-`<select>` filter pattern already used on the Journal screen (`resources/views/accounting.blade.php:1621-1655`, fixed to auto-submit `document_type` in commit `adc6221`).
- Local PHPUnit cannot run on this machine (PHP 8.2 installed vs 8.4 required) — verification is `php -l` + Blade `compileString()` + manual review, consistent with the rest of this session's work.

---

### Task 1: Migrations — payment columns + payments table

**Files:**
- Create: `database/migrations/2026_08_24_090000_add_payment_status_to_accounting_entries_table.php`
- Create: `database/migrations/2026_08_24_090100_create_accounting_entry_payments_table.php`

**Interfaces:**
- Produces: `accounting_entries.payment_status` (string, default `unpaid`), `accounting_entries.amount_paid` (decimal 15,2, default 0); `accounting_entry_payments` table with columns `id, accounting_entry_id, user_id, actor_user_id, amount, payment_date, method, reference, treasury_transaction_id, created_at, updated_at`.

- [ ] **Step 1: Write the payment-status migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_entries', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('unpaid')->after('amount');
            $table->decimal('amount_paid', 15, 2)->default(0)->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_entries', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'amount_paid']);
        });
    }
};
```

- [ ] **Step 2: Write the accounting_entry_payments migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_entry_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_entry_id')->constrained('accounting_entries')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('method', 30)->default('autre');
            $table->string('reference')->nullable();
            $table->foreignId('treasury_transaction_id')->nullable()->constrained('treasury_transactions')->nullOnDelete();
            $table->timestamps();

            $table->index(['accounting_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_entry_payments');
    }
};
```

- [ ] **Step 3: Verify migration syntax**

Run: `php -l database/migrations/2026_08_24_090000_add_payment_status_to_accounting_entries_table.php && php -l database/migrations/2026_08_24_090100_create_accounting_entry_payments_table.php`
Expected: `No syntax errors detected` for both files. (Cannot run `php artisan migrate` locally — no local DB connection this session; migrations run on next production deploy via the normal `git reset --hard` + manual `php artisan migrate` step.)

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_24_090000_add_payment_status_to_accounting_entries_table.php database/migrations/2026_08_24_090100_create_accounting_entry_payments_table.php
git commit -m "feat(accounting): add payment-status columns and payments table"
```

---

### Task 2: Models — AccountingEntryPayment + AccountingEntry helpers

**Files:**
- Create: `app/Models/AccountingEntryPayment.php`
- Modify: `app/Models/AccountingEntry.php`

**Interfaces:**
- Consumes: `accounting_entries.debit_account`/`credit_account` (existing strings), `accounting_entry_payments` table (Task 1).
- Produces: `AccountingEntry::defaultPaymentState(string $debitAccount, string $creditAccount, float $amount): array` returning `['payment_status' => string, 'amount_paid' => float]` — used by all 3 entry-creation call sites (Task 3). `AccountingEntry::payments(): HasMany`. `AccountingEntry::recalculatePaymentStatus(): void` — recomputes and saves `payment_status`/`amount_paid` from the `payments` relation. `AccountingEntry::inferPaymentMovementType(): ?string` returning `'encaissement'`, `'decaissement'`, or `null`.

- [ ] **Step 1: Create the AccountingEntryPayment model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingEntryPayment extends Model
{
    protected $fillable = [
        'accounting_entry_id',
        'user_id',
        'actor_user_id',
        'amount',
        'payment_date',
        'method',
        'reference',
        'treasury_transaction_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(AccountingEntry::class, 'accounting_entry_id');
    }

    public function treasuryTransaction(): BelongsTo
    {
        return $this->belongsTo(TreasuryTransaction::class);
    }
}
```

- [ ] **Step 2: Add the payments relation and payment-state helpers to AccountingEntry**

In `app/Models/AccountingEntry.php`, add `'payment_status'` and `'amount_paid'` to `$fillable`, add `'amount_paid' => 'decimal:2'` to `$casts`, then add these methods (after the existing `document()` method, before `getOcrBadge()`):

```php
    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AccountingEntryPayment::class);
    }

    /**
     * Statut de règlement à donner à une écriture au moment de sa création.
     * Une écriture dont le débit OU le crédit touche déjà un compte de
     * trésorerie (classe 5 : 512 Banque, 571 Caisse...) a vu son règlement
     * se produire au moment même de la saisie — typiquement un
     * "Justificatif" (627/512) ou un "Reçu" (512/411) — donc aucun paiement
     * futur n'est attendu et elle démarre directement payée. Toute autre
     * écriture (typiquement Achat 607/401 ou Vente 411/701, avec un compte
     * tiers mais pas encore de mouvement de trésorerie) démarre impayée.
     *
     * @return array{payment_status: string, amount_paid: float}
     */
    public static function defaultPaymentState(?string $debitAccount, ?string $creditAccount, float $amount): array
    {
        if (self::isClassFiveAccount($debitAccount) || self::isClassFiveAccount($creditAccount)) {
            return ['payment_status' => 'paid', 'amount_paid' => $amount];
        }

        return ['payment_status' => 'unpaid', 'amount_paid' => 0.0];
    }

    public static function isClassFiveAccount(?string $account): bool
    {
        $normalized = ltrim(trim((string) $account), '0');

        return $normalized !== '' && str_starts_with($normalized, '5');
    }

    /**
     * Sens du mouvement de trésorerie qu'un paiement sur cette écriture doit
     * générer. Se base sur la numérotation OHADA : un débit 411 (Clients)
     * signifie qu'un tiers nous doit de l'argent — l'encaisser augmente la
     * trésorerie. Un crédit 401 (Fournisseurs) signifie que nous devons de
     * l'argent à un tiers — le payer diminue la trésorerie. Toute autre
     * combinaison (pas de compte 401/411 reconnu) ne génère pas de
     * mouvement automatique : le paiement reste enregistré, seul le lien
     * Trésorerie est absent.
     */
    public function inferPaymentMovementType(): ?string
    {
        $debit = ltrim(trim((string) $this->debit_account), '0');
        $credit = ltrim(trim((string) $this->credit_account), '0');

        if (str_starts_with($debit, '411')) {
            return 'encaissement';
        }

        if (str_starts_with($credit, '401')) {
            return 'decaissement';
        }

        return null;
    }

    /**
     * Recalcule payment_status/amount_paid à partir de la somme des
     * paiements liés, et sauvegarde. À appeler après chaque création (ou
     * suppression) de paiement.
     */
    public function recalculatePaymentStatus(): void
    {
        $paid = (float) $this->payments()->sum('amount');
        $total = (float) $this->amount;

        $status = 'unpaid';
        if ($paid > 0 && $paid < $total) {
            $status = 'partial';
        } elseif ($paid >= $total && $total > 0) {
            $status = 'paid';
        }

        $this->forceFill(['amount_paid' => $paid, 'payment_status' => $status])->save();
    }

    public function paymentStatusBadge(): array
    {
        return match ($this->payment_status) {
            'paid' => ['class' => 'bg-success-subtle text-success-emphasis', 'label' => 'Payé'],
            'partial' => ['class' => 'bg-warning-subtle text-warning-emphasis', 'label' => 'Partiel'],
            default => ['class' => 'bg-danger-subtle text-danger-emphasis', 'label' => 'Impayé'],
        };
    }
```

- [ ] **Step 2: Verify syntax**

Run: `php -l app/Models/AccountingEntryPayment.php && php -l app/Models/AccountingEntry.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 3: Commit**

```bash
git add app/Models/AccountingEntryPayment.php app/Models/AccountingEntry.php
git commit -m "feat(accounting): add AccountingEntryPayment model and payment-state helpers"
```

---

### Task 3: Apply the default payment status at entry creation

**Files:**
- Modify: `app/Http/Controllers/AccountingController.php` (`storeEntry`, `createEntryFromAutoValidatedDocument`)
- Modify: `app/Http/Controllers/AccountingDocumentController.php` (`createEntryFromDocument`)

**Interfaces:**
- Consumes: `AccountingEntry::defaultPaymentState()` (Task 2).

- [ ] **Step 1: `AccountingController::storeEntry()`**

Find the `$entryPayload = array_merge($data, $ocrData, [...]);` block (around line 165) and merge in the default payment state before the entry is created:

```php
        $paymentState = AccountingEntry::defaultPaymentState(
            $validated['debit_account'],
            $validated['credit_account'],
            (float) $validated['amount']
        );

        $entryPayload = array_merge($data, $ocrData, $paymentState, [
            'user_id' => $this->workspaceUserId(),
            'actor_user_id' => Auth::id(),
        ]);
```

- [ ] **Step 2: `AccountingController::createEntryFromAutoValidatedDocument()`**

In the `AccountingEntry::updateOrCreate([...], [...])` call (around line 3121), add the payment state to the second array:

```php
        $paymentState = AccountingEntry::defaultPaymentState($debitAccount, $creditAccount, $amount);

        $entry = AccountingEntry::updateOrCreate(
            ['document_id' => $document->id],
            [
                'user_id' => $this->workspaceUserId(),
                'actor_user_id' => Auth::id(),
                'date' => (string) ($data['invoice_date'] ?? now()->toDateString()),
                'document_type' => $type,
                'document_reference' => $data['invoice_number'] ?? null,
                'description' => '[OCR] '.((string) ($data['partner'] ?? 'Document')).' - '.((string) ($data['invoice_number'] ?? 'Sans référence')),
                'debit_account' => $debitAccount,
                'credit_account' => $creditAccount,
                'amount' => $amount,
                'attachment_path' => $document->stored_path,
                'ocr_status' => 'verified',
                'ocr_detected_amount' => $amount,
                'ocr_verified_at' => now(),
                'ocr_text' => $data['ocr_text'] ?? null,
                'payment_status' => $paymentState['payment_status'],
                'amount_paid' => $paymentState['amount_paid'],
            ]
        );
```

- [ ] **Step 3: `AccountingDocumentController::createEntryFromDocument()`**

In `app/Http/Controllers/AccountingDocumentController.php`, find the `AccountingEntry::updateOrCreate([...], [...])` call (around line 108) and add the payment state:

```php
        $paymentState = AccountingEntry::defaultPaymentState($accounts['debit'], $accounts['credit'], $amount);

        AccountingEntry::updateOrCreate(
            [
                'document_id' => $document->id,
            ],
            [
                'user_id' => $this->workspaceUserId(),
                'actor_user_id' => Auth::id(),
                'date' => $data['invoice_date'] ?? now()->toDateString(),
                'document_type' => $type,
                'document_reference' => $data['invoice_number'] ?? null,
                'description' => '[OCR] '.($data['partner'] ?? 'Document').' - '.($data['invoice_number'] ?? 'Sans référence'),
                'debit_account' => $accounts['debit'],
                'credit_account' => $accounts['credit'],
                'amount' => $amount,
                'payment_status' => $paymentState['payment_status'],
                'amount_paid' => $paymentState['amount_paid'],
            ]
        );
```

- [ ] **Step 4: Verify syntax**

Run: `php -l app/Http/Controllers/AccountingController.php && php -l app/Http/Controllers/AccountingDocumentController.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AccountingController.php app/Http/Controllers/AccountingDocumentController.php
git commit -m "feat(accounting): default new entries to paid/unpaid based on their accounts"
```

---

### Task 4: Caisse Banque listing + payment recording (controller)

**Files:**
- Modify: `app/Http/Controllers/AccountingController.php`

**Interfaces:**
- Consumes: `AccountingEntry::inferPaymentMovementType()`, `AccountingEntry::recalculatePaymentStatus()` (Task 2), `TreasuryAudit::log()` (existing service, same signature already used in `destroyDocument`).
- Produces: `AccountingController::caisseBanque(Request $request): View`, `AccountingController::storeEntryPayment(Request $request, AccountingEntry $entry): RedirectResponse` — wired to routes in Task 5.

- [ ] **Step 1: Add `caisseBanque()`**

Add this method to `app/Http/Controllers/AccountingController.php`, right after the `documents()` method:

```php
    public function caisseBanque(Request $request)
    {
        $statusFilter = trim((string) $request->query('payment_status', ''));
        $documentType = trim((string) $request->query('document_type', ''));
        $account = trim((string) $request->query('account', ''));
        $dateFrom = $request->query('date_from', '');
        $dateTo = $request->query('date_to', '');

        $entriesQuery = AccountingEntry::with(['payments' => fn ($q) => $q->orderByDesc('payment_date')])
            ->whereIn('user_id', $this->workspaceDataUserIds())
            ->when($statusFilter, fn ($query, $statusFilter) => $query->where('payment_status', $statusFilter))
            ->when($documentType, fn ($query, $documentType) => $query->where('document_type', $documentType))
            ->when($account, function ($query, $account) {
                $query->where(function ($query) use ($account) {
                    $query->where('debit_account', 'like', "%{$account}%")
                        ->orWhere('credit_account', 'like', "%{$account}%");
                });
            })
            ->when($dateFrom, fn ($query, $dateFrom) => $query->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn ($query, $dateTo) => $query->whereDate('date', '<=', $dateTo))
            ->orderByDesc('date')
            ->orderByDesc('id');

        $entries = $entriesQuery->get();

        return view('accounting.caisse-banque', [
            'entries' => $entries,
            'statusFilter' => $statusFilter,
            'documentType' => $documentType,
            'account' => $account,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'totalUnpaid' => $entries->where('payment_status', 'unpaid')->sum(fn ($e) => (float) $e->amount - (float) $e->amount_paid),
            'totalPartial' => $entries->where('payment_status', 'partial')->sum(fn ($e) => (float) $e->amount - (float) $e->amount_paid),
            'totalPaid' => $entries->where('payment_status', 'paid')->sum('amount'),
        ]);
    }

    public function storeEntryPayment(Request $request, AccountingEntry $entry)
    {
        if (! $this->workspaceOwnsDataUserId((int) $entry->user_id)) {
            abort(403);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'method' => ['required', 'string', 'in:mobile_money,banque,especes,autre'],
            'reference' => ['nullable', 'string', 'max:255'],
            'bank_account' => ['nullable', 'string', 'max:100'],
        ]);

        $remaining = (float) $entry->amount - (float) $entry->amount_paid;
        if ((float) $validated['amount'] > $remaining + 0.01) {
            return back()->withErrors([
                'amount' => sprintf('Le montant dépasse le solde dû (%s FCFA).', number_format($remaining, 2, ',', ' ')),
            ])->withInput();
        }

        $treasuryTransaction = null;
        $movementType = $entry->inferPaymentMovementType();
        if ($movementType !== null) {
            $treasuryTransaction = TreasuryTransaction::create([
                'user_id' => $entry->user_id,
                'actor_user_id' => Auth::id(),
                'type' => $movementType,
                'transaction_type' => 'mouvement_bancaire',
                'payment_provider' => 'Caisse Banque',
                'payment_module' => 'accounting_entry_payment',
                'amount' => $validated['amount'],
                'description' => '[Règlement] '.$entry->description,
                'transaction_date' => $validated['payment_date'],
                'reference' => $validated['reference'] ?? null,
                'bank_account' => $validated['bank_account'] ?: '512 Banque',
                'status' => 'effectue',
                'notes' => 'Généré automatiquement depuis le règlement de l\'écriture #'.$entry->id,
            ]);
        }

        $payment = $entry->payments()->create([
            'user_id' => $entry->user_id,
            'actor_user_id' => Auth::id(),
            'amount' => $validated['amount'],
            'payment_date' => $validated['payment_date'],
            'method' => $validated['method'],
            'reference' => $validated['reference'] ?? null,
            'treasury_transaction_id' => $treasuryTransaction?->id,
        ]);

        $entry->recalculatePaymentStatus();

        TreasuryAudit::log($entry->user_id, 'accounting.entry.payment_recorded', $entry, [
            'payment_id' => $payment->id,
            'amount' => $validated['amount'],
            'new_status' => $entry->fresh()->payment_status,
        ]);

        return redirect()->route('accounting.caisse-banque')->with('status', 'Paiement enregistré.');
    }
```

- [ ] **Step 2: Add the missing `use` statements**

At the top of `app/Http/Controllers/AccountingController.php`, ensure these are present (add any missing):

```php
use App\Models\TreasuryTransaction;
use App\Services\TreasuryAudit;
```

(`AccountingEntry`, `Auth`, `Request` are already imported — verify by checking the existing `use` block at the top of the file before adding duplicates.)

- [ ] **Step 3: Verify syntax**

Run: `php -l app/Http/Controllers/AccountingController.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/AccountingController.php
git commit -m "feat(accounting): add Caisse Banque listing and payment recording"
```

---

### Task 5: Routes

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Add the two routes**

In `routes/web.php`, right after the line `Route::delete('/accounting/entries/{entry}', [AccountingController::class, 'destroyEntry'])->name('accounting.entries.destroy');` (line 553), add:

```php
        Route::get('/accounting/caisse-banque', [AccountingController::class, 'caisseBanque'])->name('accounting.caisse-banque');
        Route::post('/accounting/entries/{entry}/payments', [AccountingController::class, 'storeEntryPayment'])->name('accounting.entries.payments.store');
```

- [ ] **Step 2: Verify routes register**

Run: `php artisan route:list --name=accounting.caisse-banque` and `php artisan route:list --name=accounting.entries.payments.store`
Expected: each command shows exactly one matching route pointing to `AccountingController@caisseBanque` / `AccountingController@storeEntryPayment`.

- [ ] **Step 3: Commit**

```bash
git add routes/web.php
git commit -m "feat(accounting): route the Caisse Banque screen and payment endpoint"
```

---

### Task 6: Caisse Banque view

**Files:**
- Create: `resources/views/accounting/caisse-banque.blade.php`

**Interfaces:**
- Consumes: `$entries` (Collection of `AccountingEntry`, each with `payments` eager-loaded), `$statusFilter`, `$documentType`, `$account`, `$dateFrom`, `$dateTo`, `$totalUnpaid`, `$totalPartial`, `$totalPaid` (all from Task 4's `caisseBanque()`).

- [ ] **Step 1: Write the view**

```blade
@extends('layouts.app')

@section('title', 'Caisse Banque | Sitiame Capital')
@section('page_title', 'Caisse Banque')

@section('content')
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-1">Caisse Banque</h5>
                    <p class="text-muted mb-0">Suivi des règlements de vos écritures comptables — qui a payé, qui reste à payer.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-danger-subtle h-100">
                <div class="card-body">
                    <div class="text-danger small fw-semibold text-uppercase">Total impayé</div>
                    <div class="fs-4 fw-bold">{{ number_format($totalUnpaid, 0, ',', ' ') }} FCFA</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-warning-subtle h-100">
                <div class="card-body">
                    <div class="text-warning small fw-semibold text-uppercase">Total partiellement payé</div>
                    <div class="fs-4 fw-bold">{{ number_format($totalPartial, 0, ',', ' ') }} FCFA</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-success-subtle h-100">
                <div class="card-body">
                    <div class="text-success small fw-semibold text-uppercase">Total payé</div>
                    <div class="fs-4 fw-bold">{{ number_format($totalPaid, 0, ',', ' ') }} FCFA</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('accounting.caisse-banque') }}" method="GET" class="row g-2 align-items-end mb-4">
                <div class="col-auto">
                    <label class="small text-muted d-block">Statut</label>
                    <select name="payment_status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Tous</option>
                        <option value="unpaid" {{ $statusFilter === 'unpaid' ? 'selected' : '' }}>Impayé</option>
                        <option value="partial" {{ $statusFilter === 'partial' ? 'selected' : '' }}>Partiel</option>
                        <option value="paid" {{ $statusFilter === 'paid' ? 'selected' : '' }}>Payé</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="small text-muted d-block">Type</label>
                    <select name="document_type" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Tous</option>
                        <option value="Vente" {{ $documentType === 'Vente' ? 'selected' : '' }}>Vente</option>
                        <option value="Achat" {{ $documentType === 'Achat' ? 'selected' : '' }}>Achat</option>
                        <option value="Reçu" {{ $documentType === 'Reçu' ? 'selected' : '' }}>Reçu</option>
                        <option value="Justificatif" {{ $documentType === 'Justificatif' ? 'selected' : '' }}>Justificatif</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="small text-muted d-block">Compte</label>
                    <input type="text" name="account" class="form-control form-control-sm" placeholder="Débit / Crédit" value="{{ $account }}">
                </div>
                <div class="col-auto">
                    <label class="small text-muted d-block">Du</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
                </div>
                <div class="col-auto">
                    <label class="small text-muted d-block">Au</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Filtrer</button>
                    <a href="{{ route('accounting.caisse-banque') }}" class="btn btn-sm btn-outline-secondary">Effacer</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Tiers</th>
                            <th class="text-end">Montant</th>
                            <th class="text-end">Réglé</th>
                            <th class="text-end">Solde dû</th>
                            <th>Statut</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $entry)
                            @php($badge = $entry->paymentStatusBadge())
                            @php($due = (float) $entry->amount - (float) $entry->amount_paid)
                            <tr>
                                <td>{{ $entry->date?->format('d/m/Y') }}</td>
                                <td>{{ $entry->description }}</td>
                                <td class="small text-muted">{{ $entry->debit_account }} / {{ $entry->credit_account }}</td>
                                <td class="text-end">{{ number_format((float) $entry->amount, 0, ',', ' ') }}</td>
                                <td class="text-end">{{ number_format((float) $entry->amount_paid, 0, ',', ' ') }}</td>
                                <td class="text-end fw-semibold">{{ number_format($due, 0, ',', ' ') }}</td>
                                <td><span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span></td>
                                <td>
                                    @if($entry->payment_status !== 'paid')
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#payForm{{ $entry->id }}">
                                            Enregistrer un paiement
                                        </button>
                                    @endif
                                    @if($entry->payments->isNotEmpty())
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#payHistory{{ $entry->id }}">
                                            Voir les paiements ({{ $entry->payments->count() }})
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @if($entry->payment_status !== 'paid')
                                <tr class="collapse" id="payForm{{ $entry->id }}">
                                    <td colspan="8" class="bg-light">
                                        <form action="{{ route('accounting.entries.payments.store', $entry) }}" method="POST" class="row g-2 align-items-end py-2">
                                            @csrf
                                            <div class="col-auto">
                                                <label class="small text-muted d-block">Montant (solde dû : {{ number_format($due, 0, ',', ' ') }})</label>
                                                <input type="number" step="0.01" name="amount" max="{{ $due }}" class="form-control form-control-sm" required>
                                            </div>
                                            <div class="col-auto">
                                                <label class="small text-muted d-block">Date</label>
                                                <input type="date" name="payment_date" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required>
                                            </div>
                                            <div class="col-auto">
                                                <label class="small text-muted d-block">Méthode</label>
                                                <select name="method" class="form-select form-select-sm" required>
                                                    <option value="mobile_money">Mobile Money</option>
                                                    <option value="banque">Banque</option>
                                                    <option value="especes">Espèces</option>
                                                    <option value="autre">Autre</option>
                                                </select>
                                            </div>
                                            <div class="col-auto">
                                                <label class="small text-muted d-block">Référence</label>
                                                <input type="text" name="reference" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-auto">
                                                <label class="small text-muted d-block">Compte de trésorerie</label>
                                                <input type="text" name="bank_account" class="form-control form-control-sm" value="512 Banque">
                                            </div>
                                            <div class="col-auto">
                                                <button type="submit" class="btn btn-sm btn-success">Valider le paiement</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                            @if($entry->payments->isNotEmpty())
                                <tr class="collapse" id="payHistory{{ $entry->id }}">
                                    <td colspan="8" class="bg-light">
                                        <table class="table table-sm mb-0">
                                            <thead><tr><th>Date</th><th>Montant</th><th>Méthode</th><th>Référence</th></tr></thead>
                                            <tbody>
                                                @foreach($entry->payments as $payment)
                                                    <tr>
                                                        <td>{{ $payment->payment_date?->format('d/m/Y') }}</td>
                                                        <td>{{ number_format((float) $payment->amount, 0, ',', ' ') }} FCFA</td>
                                                        <td>{{ $payment->method }}</td>
                                                        <td>{{ $payment->reference ?: '—' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">Aucune écriture ne correspond à ce filtre.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
```

- [ ] **Step 2: Verify Blade compiles**

Run: `php artisan tinker --execute="echo \Illuminate\Support\Facades\Blade::compileString(file_get_contents('resources/views/accounting/caisse-banque.blade.php')) ? 'blade OK' : 'blade FAIL';"`
Expected: `blade OK`.

- [ ] **Step 3: Commit**

```bash
git add resources/views/accounting/caisse-banque.blade.php
git commit -m "feat(accounting): add the Caisse Banque view"
```

---

### Task 7: Wire it into the existing navigation

**Files:**
- Modify: `resources/views/accounting.blade.php`
- Modify: `resources/views/layouts/partials/sidebar.blade.php`

**Interfaces:**
- Consumes: `route('accounting.caisse-banque')` (Task 5).

- [ ] **Step 1: Add the shortcut card**

In `resources/views/accounting.blade.php`, right after the "Plan comptable OHADA" card block (ends at line 753, just before the closing `</div>` of the shortcut-cards row at line 754), insert:

```blade
        <div class="col-6 col-md-4 col-lg-3">
            <a href="{{ route('accounting.caisse-banque') }}" class="text-decoration-none text-reset">
                <div class="card summary-card h-100 border shadow-sm">
                    <div class="card-body py-3">
                        <h6 class="card-title text-primary mb-1">Caisse Banque</h6>
                        <p class="text-muted small mb-0">Suivi payé / impayé de vos écritures.</p>
                    </div>
                </div>
            </a>
        </div>
```

- [ ] **Step 2: Add the sidebar sub-menu entry**

In `resources/views/layouts/partials/sidebar.blade.php`, inside the "Comptabilité" collapse block, right after the "Plan comptable OHADA" `<li>` (the one linking to `route('accounting.plan')`, in the `sidebar-item-category` "Saisie des données" group — search for `accounting.plan` to locate it), insert:

```blade
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('accounting.caisse-banque') ? 'active' : '' }}" href="{{ route('accounting.caisse-banque') }}">
                                    <span class="icon-wrapper">💰</span>
                                    <span class="align-middle">Caisse Banque</span>
                                </a>
                            </li>
```

- [ ] **Step 3: Verify both files compile**

Run: `php artisan tinker --execute="echo \Illuminate\Support\Facades\Blade::compileString(file_get_contents('resources/views/accounting.blade.php')) ? 'accounting OK' : 'FAIL'; echo PHP_EOL; echo \Illuminate\Support\Facades\Blade::compileString(file_get_contents('resources/views/layouts/partials/sidebar.blade.php')) ? 'sidebar OK' : 'FAIL';"`
Expected: `accounting OK` and `sidebar OK`.

- [ ] **Step 4: Commit**

```bash
git add resources/views/accounting.blade.php resources/views/layouts/partials/sidebar.blade.php
git commit -m "feat(accounting): link Caisse Banque from the engine screen and sidebar"
```

---

## Post-implementation deploy checklist (new migrations + new route)

```bash
git fetch origin
git reset --hard origin/master
php artisan migrate
php artisan view:clear
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan route:cache
```

## Manual verification scenarios (production, per spec)

1. Créer une écriture "Achat" (607/401) manuellement → doit apparaître Impayé dans Caisse Banque.
2. Créer une écriture "Justificatif" (627/512) manuellement → doit apparaître Payé, sans bouton "Enregistrer un paiement".
3. Enregistrer un paiement partiel sur l'écriture Achat → statut passe à Partiel, un mouvement de Trésorerie "décaissement" apparaît dans `/treasury/tracking`.
4. Enregistrer un second paiement qui solde le reste → statut passe à Payé.
5. Tenter un paiement supérieur au solde dû → rejeté avec message d'erreur, rien n'est créé.
6. Filtrer par statut Impayé / Partiel / Payé → seules les lignes correspondantes s'affichent.
