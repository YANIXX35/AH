<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
