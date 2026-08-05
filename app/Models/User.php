<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'must_change_password',
    'company_name',
    'company_sigle',
    'company_tax_id',
    'company_logo',
    'sector',
    'rccm',
    'trade_register_file',
    'address',
    'city',
    'phone',
    'avatar',
    'timezone',
    'locale',
    'currency',
    'email_notifications',
    'weekly_digest',
    'marketing_emails',
    'two_factor_enabled',
    'is_premium',
    'premium_status',
    'premium_trial_ends_at',
    'premium_ends_at',
    'fiscal_year_end_date',
    'fiscal_year_duration_months',
    'accounting_period_from',
    'accounting_period_to',
    'accounts_effective_closing_date',
    'previous_fiscal_year_end',
    'previous_fiscal_year_duration_months',
    'company_directory_number',
    'social_security_number',
    'importer_code',
    'primary_activity_code',
    'company_designation',
    'company_fax',
    'postal_code',
    'po_box',
    'full_geographic_address',
    'main_activity_description',
    'production_capacity_utilization_pct',
    'contact_person_name',
    'contact_person_address',
    'contact_person_title',
    'accountant_type',
    'accountant_name',
    'accountant_address',
    'accountant_phone',
    'company_auditors',
    'certified_financial_statements',
    'approved_by_general_assembly',
    'financial_statements_signatory_name',
    'signatory_qualification',
    'financial_statements_signature_date',
    'company_bank_accounts',
    'is_platform_admin',
    'is_accountant',
    'role_key',
    'module_permissions',
    'kyc_status',
    'kyc_submitted_at',
    'kyc_validated_at',
    'kyc_validated_by_user_id',
    'kyc_rejection_reason',
    'account_suspended',
    'suspended_at',
    'suspended_reason',
    'auto_suspended_for_payment',
    'enterprise_license_id',
    'created_by_user_id',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'email_notifications' => 'boolean',
            'weekly_digest' => 'boolean',
            'marketing_emails' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'is_premium' => 'boolean',
            'premium_trial_ends_at' => 'datetime',
            'premium_ends_at' => 'datetime',
            'fiscal_year_end_date' => 'date',
            'accounting_period_from' => 'date',
            'accounting_period_to' => 'date',
            'accounts_effective_closing_date' => 'date',
            'previous_fiscal_year_end' => 'date',
            'financial_statements_signature_date' => 'date',
            'production_capacity_utilization_pct' => 'decimal:2',
            'company_auditors' => 'array',
            'company_bank_accounts' => 'array',
            'is_platform_admin' => 'boolean',
            'is_accountant' => 'boolean',
            'module_permissions' => 'array',
            'kyc_submitted_at' => 'datetime',
            'kyc_validated_at' => 'datetime',
            'account_suspended' => 'boolean',
            'suspended_at' => 'datetime',
            'auto_suspended_for_payment' => 'boolean',
        ];
    }

    /**
     * Accès au volet administrateur plateforme (équipe Sitiame / super-admin).
     */
    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    /**
     * Comptable habilité (cabinet) — accès /accountant et dossiers clients.
     */
    public function isAccountant(): bool
    {
        return (bool) ($this->is_accountant ?? false);
    }

    /**
     * Libellé du statut métier (administrateur, comptable ou entreprise).
     */
    public function accountRoleLabel(): string
    {
        if ($this->isPlatformAdmin()) {
            return 'Administrateur';
        }
        if ($this->isAccountant()) {
            return 'Comptable';
        }

        return 'Entreprise';
    }

    /**
     * Comptes « entreprise cliente » (ni admin plateforme, ni comptable cabinet).
     *
     * @param  Builder<User>  $query
     */
    public function scopeClients(Builder $query): Builder
    {
        return $query
            ->where(function ($q) {
                $q->where('is_platform_admin', false)->orWhereNull('is_platform_admin');
            })
            ->where(function ($q) {
                $q->where('is_accountant', false)->orWhereNull('is_accountant');
            });
    }

    /**
     * Comptes « clients » soumis aux offres Gratuit / Premium (hors équipe plateforme).
     */
    public function isSubjectToSubscriptionTiers(): bool
    {
        return ! $this->isPlatformAdmin();
    }

    /**
     * Premium encore valide (date de fin future ou absente).
     */
    public function hasActivePremiumPeriod(): bool
    {
        if (! $this->is_premium) {
            return false;
        }
        if ($this->premium_ends_at === null) {
            return true;
        }

        return $this->premium_ends_at->isFuture();
    }

    /**
     * Échéance Premium dans les N prochains jours (alerte admin).
     */
    public function premiumEndsWithinDays(int $days): bool
    {
        if ($this->isPlatformAdmin()) {
            return false;
        }
        if (! $this->is_premium || $this->premium_ends_at === null) {
            return false;
        }

        return $this->premium_ends_at->isFuture()
            && $this->premium_ends_at->lte(now()->addDays($days));
    }

    /**
     * Dernière fiche « profil investisseur » (scores + instantané financier).
     */
    public function investorProfile(): HasOne
    {
        return $this->hasOne(InvestorProfile::class);
    }

    public function enterpriseLicense(): BelongsTo
    {
        return $this->belongsTo(EnterpriseLicense::class, 'enterprise_license_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function createdClients(): HasMany
    {
        return $this->hasMany(User::class, 'created_by_user_id');
    }

    public function billingSubscriptions(): HasMany
    {
        return $this->hasMany(BillingSubscription::class);
    }

    public function billingInvoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class);
    }

    public function kycDocuments(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    public function canAccessModule(string $module): bool
    {
        if ($this->isPlatformAdmin()) {
            return true;
        }

        $permissions = (array) ($this->module_permissions ?? []);
        if (array_key_exists($module, $permissions)) {
            return (bool) $permissions[$module];
        }

        return match ((string) ($this->role_key ?? '')) {
            'manager' => in_array($module, ['dashboard', 'payments', 'investor', 'support', 'invoicing', 'stock'], true),
            'accountant' => in_array($module, ['dashboard', 'accounting', 'treasury', 'support', 'invoicing'], true),
            'analyst' => in_array($module, ['dashboard', 'investor', 'support'], true),
            'viewer' => in_array($module, ['dashboard'], true),
            'commercial' => in_array($module, ['dashboard', 'clients_management', 'support'], true),
            default => true,
        };
    }

    /**
     * Données entreprise / cadre à recopier lors de la création d’un collègue sous la même licence.
     *
     * @return array<string, mixed>
     */
    public function sharedProfileForNewTeammate(): array
    {
        $keys = [
            'company_name', 'company_sigle', 'company_tax_id', 'company_logo', 'sector', 'rccm', 'trade_register_file',
            'address', 'city', 'phone', 'postal_code', 'po_box', 'full_geographic_address',
            'timezone', 'locale', 'currency',
            'email_notifications', 'weekly_digest', 'marketing_emails', 'two_factor_enabled',
            'is_premium', 'premium_status', 'premium_trial_ends_at', 'premium_ends_at',
            'fiscal_year_end_date', 'fiscal_year_duration_months', 'accounting_period_from', 'accounting_period_to',
            'accounts_effective_closing_date', 'previous_fiscal_year_end', 'previous_fiscal_year_duration_months',
            'company_directory_number', 'social_security_number', 'importer_code', 'primary_activity_code',
            'company_designation', 'company_fax', 'main_activity_description', 'production_capacity_utilization_pct',
            'contact_person_name', 'contact_person_address', 'contact_person_title',
            'accountant_type', 'accountant_name', 'accountant_address', 'accountant_phone',
            'company_auditors', 'certified_financial_statements', 'approved_by_general_assembly',
            'financial_statements_signatory_name', 'signatory_qualification', 'financial_statements_signature_date',
            'company_bank_accounts', 'auto_suspended_for_payment',
        ];

        return $this->only($keys);
    }

    /**
     * Compte entreprise avec licence (hors admin / comptable).
     */
    public function canManageEnterpriseTeam(): bool
    {
        return ! $this->isPlatformAdmin()
            && ! $this->isAccountant()
            && $this->enterprise_license_id !== null;
    }
}
