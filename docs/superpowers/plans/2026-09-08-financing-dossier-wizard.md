# Dossier de Financement — Socle + Étapes 1-2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Introduce `FinancingDossier`, a richer financing-application model matching the 12-step mockup, and ship a working server-rendered wizard for steps 1 (Demande de financement) and 2 (Entreprise, auto-filled from the PME's existing `User` record). Steps 3-12 appear in the step navigation as "à venir" (not yet built).

**Architecture:** One row per dossier in a new `financing_dossiers` table — scalar columns for the fields steps 1-2 actually use (filterable/searchable), one nullable JSON column per remaining step (steps 3-11) to avoid re-migrating on every follow-up sub-project. A new `FinancingDossierController` (separate from `FinancialAnalystController`, which stays focused on the portfolio/scoring/notes) owns creation and the step wizard: `store()` creates a prefilled draft and redirects into step 1; `showStep()`/`updateStep()` render and save one step at a time via full page navigation (no client-side SPA state — consistent with the rest of this Blade/Bootstrap codebase, unlike the JS-driven mockup). Server-rendered, not a port of the mockup's JavaScript.

**Tech Stack:** Laravel 13 / PHP 8.4, Blade, MySQL/PostgreSQL migrations — same conventions as the rest of the app.

## Global Constraints

- **Scope decision (deviation from the literal spec wording, flagged to the user at the end):** `FinancingDossier` is a **new, additional** model — it does **not** replace `InvestmentRequest` platform-wide. `AdminInvestmentRequestController`, `InvestorController`, `AdminController`, `AdminOpsCenterController`, and `admin/dashboard.blade.php` keep using `InvestmentRequest` completely unchanged. Only the Financial Analyst portal gets the new rich flow. This avoids breaking the existing PME-facing investment-request submission and the admin review screens, which are out of this sub-project's scope.
- The existing "Dossier(s) de financement" block on `financial-analyst/show.blade.php` (reading `InvestmentRequest`, with its accept/decline workflow) is **left exactly as-is** — a new, separate "Dossiers de financement (nouveau)" block is added alongside it for `FinancingDossier` records. Nothing already working regresses.
- Steps 3-12 of the wizard are visible in the step sidebar (so the full journey is legible) but not clickable — each shows a "Cette étape sera disponible dans une prochaine mise à jour" placeholder if reached directly by URL.
- A dossier only belongs to one PME (`user_id`) and one creating analyst (`analyst_user_id`); authorization reuses `ClientWorkspace::isAssignableClient()`, same pattern as the rest of `FinancialAnalystController`.
- Local PHPUnit cannot run (PHP 8.2 vs 8.4 required) — verification is `php -l` + Blade `compileString()` + manual review, consistent with the rest of this session.

---

### Task 1: Migration — `financing_dossiers` table

**Files:**
- Create: `database/migrations/2026_09_08_090000_create_financing_dossiers_table.php`

**Interfaces:**
- Produces: `financing_dossiers` table consumed by `FinancingDossier` model (Task 2).

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financing_dossiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('analyst_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reference', 32)->unique();

            // Workflow — mêmes valeurs et mêmes colonnes que investment_requests,
            // pour rester compatible avec le workflow déjà en place.
            $table->string('status', 32)->default('draft');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            // Étape 1 — Demande de financement
            $table->string('financing_type', 64)->nullable();
            $table->string('financing_purpose', 64)->nullable();
            $table->decimal('amount_requested', 15, 2)->nullable();
            $table->string('currency', 10)->default('XOF');
            $table->unsignedInteger('desired_term_months')->nullable();
            $table->unsignedInteger('grace_period_months')->nullable();
            $table->string('repayment_frequency', 32)->nullable();
            $table->decimal('promoter_contribution', 15, 2)->nullable();
            $table->date('desired_disbursement_date')->nullable();
            $table->json('financing_summary_data')->nullable();

            // Étape 2 — Entreprise
            $table->string('legal_name')->nullable();
            $table->string('trade_name')->nullable();
            $table->string('legal_form', 64)->nullable();
            $table->string('rccm_number', 64)->nullable();
            $table->string('taxpayer_number', 64)->nullable();
            $table->date('incorporation_date')->nullable();
            $table->string('registered_office')->nullable();
            $table->string('city_country')->nullable();
            $table->string('business_sector', 64)->nullable();
            $table->string('main_activity')->nullable();
            $table->unsignedInteger('employee_count')->nullable();
            $table->string('website')->nullable();
            $table->text('company_history')->nullable();
            $table->decimal('share_capital', 15, 2)->nullable();
            $table->string('major_shareholders')->nullable();
            $table->string('beneficial_owners')->nullable();
            $table->string('authorized_representative')->nullable();

            // Champs repris d'InvestmentRequest, utilisés aux étapes 10/12
            $table->text('attachments_commitment')->nullable();
            $table->boolean('certifies_accuracy')->default(false);
            $table->string('photo_path')->nullable();
            $table->string('identity_document_front_path')->nullable();
            $table->string('identity_document_back_path')->nullable();
            $table->string('identity_document_type', 32)->nullable();
            $table->string('identity_document_number', 64)->nullable();
            $table->date('identity_document_expires_at')->nullable();

            // Étapes 3-11 — une colonne JSON par étape, remplie dans les sous-projets suivants
            $table->json('promoters_data')->nullable();
            $table->json('project_data')->nullable();
            $table->json('market_data')->nullable();
            $table->json('historical_financials_data')->nullable();
            $table->json('forecast_data')->nullable();
            $table->json('financing_plan_data')->nullable();
            $table->json('collateral_data')->nullable();
            $table->json('documents_data')->nullable();
            $table->json('scoring_data')->nullable();
            $table->json('review_data')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financing_dossiers');
    }
};
```

- [ ] **Step 2: Verify syntax**

Run: `php -l database/migrations/2026_09_08_090000_create_financing_dossiers_table.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_09_08_090000_create_financing_dossiers_table.php
git commit -m "feat(analyst): add financing_dossiers table"
```

---

### Task 2: `FinancingDossier` model

**Files:**
- Create: `app/Models/FinancingDossier.php`

**Interfaces:**
- Consumes: `financing_dossiers` table (Task 1), `User` model.
- Produces: `FinancingDossier::createDraftFor(User $company, int $analystUserId): self` (creates a draft prefilled from the PME), `$dossier->company()`/`$dossier->analyst()`/`$dossier->reviewer()` relations, `FinancingDossier::STEPS` (ordered list of the 12 step definitions used by the wizard nav), `$dossier->prefilledFields(): array` (which of its fields came from auto-fill, for the UI badge).

- [ ] **Step 1: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FinancingDossier extends Model
{
    protected $fillable = [
        'user_id', 'analyst_user_id', 'reference', 'status',
        'reviewed_by', 'reviewed_at', 'review_note',
        'financing_type', 'financing_purpose', 'amount_requested', 'currency',
        'desired_term_months', 'grace_period_months', 'repayment_frequency',
        'promoter_contribution', 'desired_disbursement_date', 'financing_summary_data',
        'legal_name', 'trade_name', 'legal_form', 'rccm_number', 'taxpayer_number',
        'incorporation_date', 'registered_office', 'city_country', 'business_sector',
        'main_activity', 'employee_count', 'website', 'company_history',
        'share_capital', 'major_shareholders', 'beneficial_owners', 'authorized_representative',
        'attachments_commitment', 'certifies_accuracy', 'photo_path',
        'identity_document_front_path', 'identity_document_back_path',
        'identity_document_type', 'identity_document_number', 'identity_document_expires_at',
        'promoters_data', 'project_data', 'market_data', 'historical_financials_data',
        'forecast_data', 'financing_plan_data', 'collateral_data', 'documents_data',
        'scoring_data', 'review_data',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'desired_disbursement_date' => 'date',
        'incorporation_date' => 'date',
        'identity_document_expires_at' => 'date',
        'amount_requested' => 'decimal:2',
        'promoter_contribution' => 'decimal:2',
        'share_capital' => 'decimal:2',
        'certifies_accuracy' => 'boolean',
        'financing_summary_data' => 'array',
        'promoters_data' => 'array',
        'project_data' => 'array',
        'market_data' => 'array',
        'historical_financials_data' => 'array',
        'forecast_data' => 'array',
        'financing_plan_data' => 'array',
        'collateral_data' => 'array',
        'documents_data' => 'array',
        'scoring_data' => 'array',
        'review_data' => 'array',
    ];

    /**
     * Les 12 étapes du dossier, dans l'ordre — utilisées pour la navigation du
     * wizard. `key` sert dans l'URL (/etape/{key}). Seules 'demande' et
     * 'entreprise' sont fonctionnelles dans ce sous-projet ; les autres
     * s'affichent en "à venir".
     */
    public const STEPS = [
        ['key' => 'demande', 'label' => 'Demande de financement', 'icon' => 'file-text'],
        ['key' => 'entreprise', 'label' => 'Entreprise', 'icon' => 'building-2'],
        ['key' => 'promoteurs', 'label' => 'Promoteurs et dirigeants', 'icon' => 'users-round'],
        ['key' => 'projet', 'label' => 'Projet à financer', 'icon' => 'rocket'],
        ['key' => 'marche', 'label' => 'Marché et stratégie', 'icon' => 'chart-no-axes-combined'],
        ['key' => 'finances-historiques', 'label' => 'Finances historiques', 'icon' => 'file-spreadsheet'],
        ['key' => 'previsions', 'label' => 'Prévisions financières', 'icon' => 'chart-spline'],
        ['key' => 'plan-financement', 'label' => 'Plan de financement', 'icon' => 'scale'],
        ['key' => 'garanties', 'label' => 'Garanties et sûretés', 'icon' => 'shield-check'],
        ['key' => 'pieces', 'label' => 'Pièces justificatives', 'icon' => 'paperclip'],
        ['key' => 'scoring', 'label' => 'Scoring et analyse', 'icon' => 'gauge'],
        ['key' => 'validation', 'label' => 'Validation et édition', 'icon' => 'circle-check-big'],
    ];

    /**
     * Étapes déjà construites — les autres s'affichent "à venir" dans le wizard.
     */
    public const IMPLEMENTED_STEPS = ['demande', 'entreprise'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function analyst(): BelongsTo
    {
        return $this->belongsTo(User::class, 'analyst_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Crée un dossier en brouillon pour une PME, avec l'étape "Entreprise"
     * pré-remplie à partir de sa fiche existante.
     */
    public static function createDraftFor(User $company, int $analystUserId): self
    {
        return self::create([
            'user_id' => $company->id,
            'analyst_user_id' => $analystUserId,
            'reference' => 'DF-'.now()->format('Y').'-'.str_pad((string) (self::whereYear('created_at', now()->year)->count() + 1), 6, '0', STR_PAD_LEFT),
            'status' => 'draft',
            'legal_name' => $company->company_name,
            'trade_name' => $company->company_sigle,
            'rccm_number' => $company->rccm,
            'taxpayer_number' => $company->company_tax_id,
            'registered_office' => $company->address,
            'city_country' => $company->city ? $company->city.", Côte d'Ivoire" : null,
            'business_sector' => $company->sector,
            'main_activity' => $company->main_activity_description,
            'authorized_representative' => $company->contact_person_name,
        ]);
    }

    /**
     * Champs de l'étape Entreprise considérés comme pré-remplis automatiquement
     * (pour la pastille visuelle) — vrai si non vide juste après création,
     * évalué en comparant à la fiche PME actuelle.
     */
    public function prefilledFields(): array
    {
        $company = $this->company;
        if (! $company) {
            return [];
        }

        $map = [
            'legal_name' => $company->company_name,
            'trade_name' => $company->company_sigle,
            'rccm_number' => $company->rccm,
            'taxpayer_number' => $company->company_tax_id,
            'registered_office' => $company->address,
            'business_sector' => $company->sector,
            'main_activity' => $company->main_activity_description,
            'authorized_representative' => $company->contact_person_name,
        ];

        return array_keys(array_filter($map, fn ($sourceValue, $field) => $sourceValue !== null && $sourceValue === $this->{$field}, ARRAY_FILTER_USE_BOTH));
    }
}
```

- [ ] **Step 2: Verify syntax**

Run: `php -l app/Models/FinancingDossier.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/Models/FinancingDossier.php
git commit -m "feat(analyst): add FinancingDossier model with draft prefill from User"
```

---

### Task 3: `FinancingDossierController`

**Files:**
- Create: `app/Http/Controllers/FinancingDossierController.php`

**Interfaces:**
- Consumes: `FinancingDossier::createDraftFor()`, `FinancingDossier::STEPS`, `FinancingDossier::IMPLEMENTED_STEPS`, `ClientWorkspace::isAssignableClient()`.
- Produces: `store(Request, User $company)`, `showStep(FinancingDossier $dossier, string $step)`, `updateStep(Request, FinancingDossier $dossier, string $step)` — wired to routes in Task 4.

- [ ] **Step 1: Write the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Models\FinancingDossier;
use App\Models\User;
use App\Support\ClientWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancingDossierController extends Controller
{
    public function store(Request $request, User $company): RedirectResponse
    {
        if (! ClientWorkspace::isAssignableClient($company)) {
            abort(404);
        }

        $dossier = FinancingDossier::createDraftFor($company, $request->user()->id);

        return redirect()->route('analyst.financement.step', [$dossier, 'demande']);
    }

    public function showStep(FinancingDossier $dossier, string $step): View
    {
        $this->authorizeDossier($dossier);
        $this->ensureValidStep($step);

        return view('financial-analyst.financing-dossier.wizard', [
            'dossier' => $dossier,
            'step' => $step,
            'prefilledFields' => $dossier->prefilledFields(),
        ]);
    }

    public function updateStep(Request $request, FinancingDossier $dossier, string $step): RedirectResponse
    {
        $this->authorizeDossier($dossier);
        $this->ensureValidStep($step);

        if ($step === 'demande') {
            $validated = $request->validate([
                'financing_type' => ['required', 'string', 'max:64'],
                'financing_purpose' => ['required', 'string', 'max:64'],
                'amount_requested' => ['required', 'numeric', 'min:1'],
                'desired_term_months' => ['required', 'integer', 'min:1'],
                'grace_period_months' => ['nullable', 'integer', 'min:0'],
                'repayment_frequency' => ['required', 'string', 'max:32'],
                'promoter_contribution' => ['required', 'numeric', 'min:0'],
                'desired_disbursement_date' => ['nullable', 'date'],
                'financing_summary' => ['required', 'string'],
                'repayment_source' => ['required', 'string'],
            ]);

            $dossier->update([
                'financing_type' => $validated['financing_type'],
                'financing_purpose' => $validated['financing_purpose'],
                'amount_requested' => $validated['amount_requested'],
                'desired_term_months' => $validated['desired_term_months'],
                'grace_period_months' => $validated['grace_period_months'] ?? null,
                'repayment_frequency' => $validated['repayment_frequency'],
                'promoter_contribution' => $validated['promoter_contribution'],
                'desired_disbursement_date' => $validated['desired_disbursement_date'] ?? null,
                'financing_summary_data' => [
                    'financing_summary' => $validated['financing_summary'],
                    'repayment_source' => $validated['repayment_source'],
                ],
            ]);
        } elseif ($step === 'entreprise') {
            $validated = $request->validate([
                'legal_name' => ['required', 'string', 'max:255'],
                'trade_name' => ['nullable', 'string', 'max:255'],
                'legal_form' => ['required', 'string', 'max:64'],
                'rccm_number' => ['required', 'string', 'max:64'],
                'taxpayer_number' => ['required', 'string', 'max:64'],
                'incorporation_date' => ['required', 'date'],
                'registered_office' => ['required', 'string', 'max:255'],
                'city_country' => ['required', 'string', 'max:255'],
                'business_sector' => ['required', 'string', 'max:64'],
                'main_activity' => ['required', 'string', 'max:255'],
                'employee_count' => ['nullable', 'integer', 'min:0'],
                'website' => ['nullable', 'string', 'max:255'],
                'company_history' => ['required', 'string'],
                'share_capital' => ['required', 'numeric', 'min:0'],
                'major_shareholders' => ['required', 'string', 'max:255'],
                'beneficial_owners' => ['required', 'string', 'max:255'],
                'authorized_representative' => ['required', 'string', 'max:255'],
            ]);

            $dossier->update($validated);
        }

        return redirect()->route('analyst.financement.step', [$dossier, $this->nextStepKey($step)])
            ->with('status', 'Étape enregistrée.');
    }

    private function authorizeDossier(FinancingDossier $dossier): void
    {
        $company = $dossier->company;
        if (! $company || ! ClientWorkspace::isAssignableClient($company)) {
            abort(404);
        }
    }

    private function ensureValidStep(string $step): void
    {
        if (! in_array($step, array_column(FinancingDossier::STEPS, 'key'), true)) {
            abort(404);
        }
    }

    private function nextStepKey(string $step): string
    {
        $keys = array_column(FinancingDossier::STEPS, 'key');
        $index = array_search($step, $keys, true);

        return $keys[$index + 1] ?? $step;
    }
}
```

- [ ] **Step 2: Verify syntax**

Run: `php -l app/Http/Controllers/FinancingDossierController.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/FinancingDossierController.php
git commit -m "feat(analyst): add FinancingDossierController (create, wizard steps 1-2)"
```

---

### Task 4: Routes

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Add the routes**

In `routes/web.php`, inside the `Route::middleware('financial.analyst')->prefix('analyste')->name('analyst.')->group(...)` block (added earlier for the analyst portal), right after the `pme.open` route, add:

```php
        Route::post('/pme/{company}/financement', [FinancingDossierController::class, 'store'])->name('financement.store');
        Route::get('/financement/{dossier}/etape/{step}', [FinancingDossierController::class, 'showStep'])->name('financement.step');
        Route::post('/financement/{dossier}/etape/{step}', [FinancingDossierController::class, 'updateStep'])->name('financement.step.update');
```

Add the corresponding `use` statement near the other controller imports:

```php
use App\Http\Controllers\FinancingDossierController;
```

- [ ] **Step 2: Verify routes register**

Run: `php artisan route:list --name=analyst.financement`
Expected: three routes listed (`financement.store`, `financement.step`, `financement.step.update`), all pointing to `FinancingDossierController`.

- [ ] **Step 3: Commit**

```bash
git add routes/web.php
git commit -m "feat(analyst): route the financing dossier wizard"
```

---

### Task 5: Wizard view

**Files:**
- Create: `resources/views/financial-analyst/financing-dossier/wizard.blade.php`

**Interfaces:**
- Consumes: `$dossier` (FinancingDossier), `$step` (string key), `$prefilledFields` (array of field names), `FinancingDossier::STEPS`, `FinancingDossier::IMPLEMENTED_STEPS`.

- [ ] **Step 1: Write the view**

```blade
@extends('layouts.app')

@section('title', 'Dossier de financement '.$dossier->reference)
@section('page_title', 'Dossier de financement')

@section('content')
    @php($stepIndex = array_search($step, array_column(\App\Models\FinancingDossier::STEPS, 'key'), true))
    @php($stepCount = count(\App\Models\FinancingDossier::STEPS))
    @php($progressPct = (int) round((($stepIndex + 1) / $stepCount) * 100))

    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <div class="small text-muted">Dossiers de financement / {{ $dossier->reference }}</div>
                            <h5 class="mb-1">Demande de financement — {{ $dossier->company?->company_name ?: $dossier->company?->name }}</h5>
                        </div>
                        <a href="{{ route('analyst.pme.show', $dossier->company) }}" class="btn btn-outline-secondary btn-sm">← Retour à la fiche PME</a>
                    </div>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Avancement du dossier</span>
                            <strong>{{ $progressPct }} %</strong>
                        </div>
                        <div class="progress" style="height:6px;">
                            <div class="progress-bar" role="progressbar" style="width: {{ $progressPct }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-3">
            <div class="card">
                <div class="list-group list-group-flush">
                    @foreach(\App\Models\FinancingDossier::STEPS as $i => $s)
                        @php($implemented = in_array($s['key'], \App\Models\FinancingDossier::IMPLEMENTED_STEPS, true))
                        <a href="{{ $implemented ? route('analyst.financement.step', [$dossier, $s['key']]) : '#' }}"
                           class="list-group-item list-group-item-action d-flex align-items-center gap-2 {{ $s['key'] === $step ? 'active' : '' }} {{ ! $implemented ? 'disabled text-muted' : '' }}">
                            <span class="badge {{ $s['key'] === $step ? 'bg-light text-dark' : 'bg-secondary-subtle text-secondary-emphasis' }} rounded-pill">{{ $i + 1 }}</span>
                            <span class="flex-grow-1">{{ $s['label'] }}</span>
                            @if(! $implemented)
                                <span class="small">à venir</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    @if(! in_array($step, \App\Models\FinancingDossier::IMPLEMENTED_STEPS, true))
                        <div class="alert alert-info mb-0">Cette étape sera disponible dans une prochaine mise à jour.</div>
                    @else
                        <form action="{{ route('analyst.financement.step.update', [$dossier, $step]) }}" method="POST">
                            @csrf
                            @if($step === 'demande')
                                @include('financial-analyst.financing-dossier.step-demande')
                            @elseif($step === 'entreprise')
                                @include('financial-analyst.financing-dossier.step-entreprise')
                            @endif

                            <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                                @php($prevIndex = $stepIndex - 1)
                                @if($prevIndex >= 0 && in_array(\App\Models\FinancingDossier::STEPS[$prevIndex]['key'], \App\Models\FinancingDossier::IMPLEMENTED_STEPS, true))
                                    <a href="{{ route('analyst.financement.step', [$dossier, \App\Models\FinancingDossier::STEPS[$prevIndex]['key']]) }}" class="btn btn-outline-secondary">← Précédent</a>
                                @else
                                    <span></span>
                                @endif
                                <button type="submit" class="btn btn-primary">Enregistrer et continuer →</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
```

- [ ] **Step 2: Write the "Demande de financement" step partial**

Create `resources/views/financial-analyst/financing-dossier/step-demande.blade.php`:

```blade
<h6 class="mb-3">Identification de la demande</h6>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label class="form-label">Type de financement *</label>
        <select name="financing_type" class="form-select" required>
            <option value="">Sélectionner…</option>
            @foreach(['Crédit d’investissement','Crédit de trésorerie','Crédit-bail','Affacturage','Garantie bancaire','Prise de participation','Subvention'] as $opt)
                <option value="{{ $opt }}" {{ old('financing_type', $dossier->financing_type) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Objet principal *</label>
        <select name="financing_purpose" class="form-select" required>
            <option value="">Sélectionner…</option>
            @foreach(['Acquisition d’équipements','Besoin en fonds de roulement','Extension d’activité','Lancement de projet','Refinancement','Exécution d’un marché'] as $opt)
                <option value="{{ $opt }}" {{ old('financing_purpose', $dossier->financing_purpose) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Montant demandé (FCFA) *</label>
        <input type="number" step="0.01" min="0" name="amount_requested" class="form-control" value="{{ old('amount_requested', $dossier->amount_requested) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Durée souhaitée (mois) *</label>
        <input type="number" min="1" name="desired_term_months" class="form-control" value="{{ old('desired_term_months', $dossier->desired_term_months) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Différé souhaité (mois)</label>
        <input type="number" min="0" name="grace_period_months" class="form-control" value="{{ old('grace_period_months', $dossier->grace_period_months) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Périodicité de remboursement *</label>
        <select name="repayment_frequency" class="form-select" required>
            <option value="">Sélectionner…</option>
            @foreach(['Mensuelle','Trimestrielle','Semestrielle','In fine'] as $opt)
                <option value="{{ $opt }}" {{ old('repayment_frequency', $dossier->repayment_frequency) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Apport de l'entreprise (FCFA) *</label>
        <input type="number" step="0.01" min="0" name="promoter_contribution" class="form-control" value="{{ old('promoter_contribution', $dossier->promoter_contribution) }}" required>
        <div class="form-text">Montant déjà disponible et justifiable.</div>
    </div>
    <div class="col-md-3">
        <label class="form-label">Date souhaitée de décaissement</label>
        <input type="date" name="desired_disbursement_date" class="form-control" value="{{ old('desired_disbursement_date', optional($dossier->desired_disbursement_date)->toDateString()) }}">
    </div>
</div>

<h6 class="mb-3">Justification du besoin</h6>
<div class="row g-3">
    <div class="col-12">
        <label class="form-label">Résumé de la demande *</label>
        <textarea name="financing_summary" class="form-control" rows="3" required>{{ old('financing_summary', $dossier->financing_summary_data['financing_summary'] ?? '') }}</textarea>
        <div class="form-text">Présenter en quelques lignes le besoin, son urgence et les résultats attendus.</div>
    </div>
    <div class="col-12">
        <label class="form-label">Source principale de remboursement *</label>
        <textarea name="repayment_source" class="form-control" rows="3" required>{{ old('repayment_source', $dossier->financing_summary_data['repayment_source'] ?? '') }}</textarea>
        <div class="form-text">Préciser les flux qui serviront au paiement des échéances.</div>
    </div>
</div>
```

- [ ] **Step 3: Write the "Entreprise" step partial (with prefill badges)**

Create `resources/views/financial-analyst/financing-dossier/step-entreprise.blade.php`:

```blade
@php($badge = fn ($field) => in_array($field, $prefilledFields, true) ? '<span class="badge bg-info-subtle text-info-emphasis ms-1">pré-rempli</span>' : '')

<h6 class="mb-3">Identité juridique</h6>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label class="form-label">Raison sociale * {!! $badge('legal_name') !!}</label>
        <input type="text" name="legal_name" class="form-control" value="{{ old('legal_name', $dossier->legal_name) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Nom commercial {!! $badge('trade_name') !!}</label>
        <input type="text" name="trade_name" class="form-control" value="{{ old('trade_name', $dossier->trade_name) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Forme juridique *</label>
        <select name="legal_form" class="form-select" required>
            <option value="">Sélectionner…</option>
            @foreach(['Entreprise individuelle','SARL','SARLU','SA','SAS','SASU','Coopérative','Association','Autre'] as $opt)
                <option value="{{ $opt }}" {{ old('legal_form', $dossier->legal_form) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Numéro RCCM * {!! $badge('rccm_number') !!}</label>
        <input type="text" name="rccm_number" class="form-control" value="{{ old('rccm_number', $dossier->rccm_number) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Numéro de compte contribuable * {!! $badge('taxpayer_number') !!}</label>
        <input type="text" name="taxpayer_number" class="form-control" value="{{ old('taxpayer_number', $dossier->taxpayer_number) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Date de création *</label>
        <input type="date" name="incorporation_date" class="form-control" value="{{ old('incorporation_date', optional($dossier->incorporation_date)->toDateString()) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Adresse du siège * {!! $badge('registered_office') !!}</label>
        <input type="text" name="registered_office" class="form-control" value="{{ old('registered_office', $dossier->registered_office) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Ville et pays *</label>
        <input type="text" name="city_country" class="form-control" value="{{ old('city_country', $dossier->city_country) }}" required>
    </div>
</div>

<h6 class="mb-3">Activité et organisation</h6>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label class="form-label">Secteur d'activité * {!! $badge('business_sector') !!}</label>
        <select name="business_sector" class="form-select" required>
            <option value="">Sélectionner…</option>
            @foreach(['Agriculture','Commerce','Industrie','BTP','Transport','Services','Numérique','Santé','Éducation','Autre'] as $opt)
                <option value="{{ $opt }}" {{ old('business_sector', $dossier->business_sector) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Activité principale * {!! $badge('main_activity') !!}</label>
        <input type="text" name="main_activity" class="form-control" value="{{ old('main_activity', $dossier->main_activity) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Nombre de salariés</label>
        <input type="number" min="0" name="employee_count" class="form-control" value="{{ old('employee_count', $dossier->employee_count) }}">
    </div>
    <div class="col-md-8">
        <label class="form-label">Site internet</label>
        <input type="text" name="website" class="form-control" value="{{ old('website', $dossier->website) }}">
    </div>
    <div class="col-12">
        <label class="form-label">Historique et principales réalisations *</label>
        <textarea name="company_history" class="form-control" rows="3" required>{{ old('company_history', $dossier->company_history) }}</textarea>
    </div>
</div>

<h6 class="mb-3">Capital et gouvernance</h6>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Capital social (FCFA) *</label>
        <input type="number" step="0.01" min="0" name="share_capital" class="form-control" value="{{ old('share_capital', $dossier->share_capital) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Représentant légal * {!! $badge('authorized_representative') !!}</label>
        <input type="text" name="authorized_representative" class="form-control" value="{{ old('authorized_representative', $dossier->authorized_representative) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Principaux associés et pourcentages *</label>
        <input type="text" name="major_shareholders" class="form-control" value="{{ old('major_shareholders', $dossier->major_shareholders) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Bénéficiaires effectifs *</label>
        <input type="text" name="beneficial_owners" class="form-control" value="{{ old('beneficial_owners', $dossier->beneficial_owners) }}" required>
    </div>
</div>
```

- [ ] **Step 4: Verify all three views compile**

Run:
```bash
php artisan tinker --execute="
foreach ([
  'resources/views/financial-analyst/financing-dossier/wizard.blade.php',
  'resources/views/financial-analyst/financing-dossier/step-demande.blade.php',
  'resources/views/financial-analyst/financing-dossier/step-entreprise.blade.php',
] as \$f) {
    try { \Illuminate\Support\Facades\Blade::compileString(file_get_contents(\$f)); echo \$f.' OK'.PHP_EOL; }
    catch (\Throwable \$e) { echo \$f.' FAIL: '.\$e->getMessage().PHP_EOL; }
}
"
```
Expected: all three print `OK`.

- [ ] **Step 5: Commit**

```bash
git add resources/views/financial-analyst/financing-dossier/
git commit -m "feat(analyst): add the financing dossier wizard views (steps 1-2)"
```

---

### Task 6: Wire the new flow into the PME sheet

**Files:**
- Modify: `resources/views/financial-analyst/show.blade.php`

**Interfaces:**
- Consumes: `route('analyst.financement.store', $company)` (Task 4).

- [ ] **Step 1: Add a "Nouveau dossier de financement" button and a separate list block**

In `resources/views/financial-analyst/show.blade.php`, find the existing `<h6 class="card-title">Dossier(s) de financement</h6>` block (the one iterating `$investmentRequests`) and add, right after that card's closing `</div></div></div>` (end of the existing block), a new card:

```blade
    {{-- 5bis. Dossiers de financement (nouveau format, 12 étapes) --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="card-title mb-0">Dossiers de financement (nouveau format)</h6>
                        <form action="{{ route('analyst.financement.store', $company) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary">+ Nouveau dossier de financement</button>
                        </form>
                    </div>
                    @forelse($company->financingDossiers()->orderByDesc('created_at')->get() as $fd)
                        <div class="border-bottom py-2 d-flex justify-content-between align-items-center">
                            <div>
                                <a href="{{ route('analyst.financement.step', [$fd, 'demande']) }}" class="fw-semibold text-decoration-none">{{ $fd->reference }}</a>
                                <div class="small text-muted">{{ $fd->financing_purpose ?: 'Sans objet renseigné' }} — {{ number_format((float) $fd->amount_requested, 0, ',', ' ') }} FCFA</div>
                            </div>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $fd->status }}</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Aucun dossier de financement (nouveau format) pour cette entreprise.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
```

- [ ] **Step 2: Add the `financingDossiers()` relation to `User`**

In `app/Models/User.php`, right after the `investmentRequests()` relation added earlier this session, add:

```php
    public function financingDossiers(): HasMany
    {
        return $this->hasMany(FinancingDossier::class);
    }
```

- [ ] **Step 3: Verify**

Run: `php -l app/Models/User.php`
Expected: `No syntax errors detected`.

Run:
```bash
php artisan tinker --execute="echo \Illuminate\Support\Facades\Blade::compileString(file_get_contents('resources/views/financial-analyst/show.blade.php')) ? 'OK' : 'FAIL';"
```
Expected: `OK`.

- [ ] **Step 4: Commit**

```bash
git add resources/views/financial-analyst/show.blade.php app/Models/User.php
git commit -m "feat(analyst): link the new financing dossier wizard from the PME sheet"
```

---

## Post-implementation deploy checklist (new migration + new routes)

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

1. Depuis la fiche d'une PME ayant `company_name`/`rccm`/`sector`/`address` renseignés, cliquer "Nouveau dossier de financement" → redirigé vers l'étape 1, une référence `DF-2026-NNNNNN` est visible.
2. Remplir l'étape 1, cliquer "Enregistrer et continuer" → redirigé vers l'étape 2, avec les champs Raison sociale/RCCM/Secteur/Adresse/Représentant légal déjà remplis et marqués "pré-rempli".
3. Modifier un champ pré-rempli, enregistrer → la valeur modifiée est bien conservée (pas ré-écrasée).
4. Cliquer sur une étape 3 à 12 dans la barre latérale → lien désactivé, non cliquable.
5. Revenir sur la fiche PME → le nouveau dossier apparaît dans le bloc "Dossiers de financement (nouveau format)", l'ancien bloc "Dossier(s) de financement" (InvestmentRequest) est inchangé.
6. Créer un dossier pour une PME sans `sector`/`address` renseignés → étape 2 s'affiche vide sur ces champs, pas d'erreur.
