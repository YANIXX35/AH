<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Licence multi-utilisateurs entreprise (clé générée par l’administration plateforme).
 */
class EnterpriseLicense extends Model
{
    protected $fillable = [
        'license_key',
        'assigned_company_tax_id',
        'label',
        'max_seats',
        'notes',
        'created_by_user_id',
        'primary_workspace_user_id',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'max_seats' => 'integer',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Compte pivot pour plan comptable et nouvelles écritures (données partagées équipe).
     */
    public function primaryWorkspaceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_workspace_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'enterprise_license_id');
    }

    /**
     * Comptes entreprise actifs rattachés à la licence (hors admin / comptable / suspendus).
     */
    public function seatsUsed(): int
    {
        return $this->users()
            ->where('is_platform_admin', false)
            ->where('is_accountant', false)
            ->where('account_suspended', false)
            ->count();
    }

    public function hasAvailableSeat(): bool
    {
        return $this->isUsable() && $this->seatsUsed() < $this->max_seats;
    }

    /**
     * NIF entreprise auquel la licence est définitivement rattachée (une entreprise par licence).
     */
    public function isLockedToCompany(): bool
    {
        return $this->assigned_company_tax_id !== null && $this->assigned_company_tax_id !== '';
    }

    /**
     * Indique si le NIF utilisateur correspond à l’entreprise autorisée pour cette licence.
     */
    public function acceptsCompanyTaxId(?string $userCompanyTaxId): bool
    {
        $norm = static::normalizeCompanyTaxId($userCompanyTaxId);
        if (! $this->isLockedToCompany()) {
            return true;
        }

        return $norm !== '' && $norm === $this->assigned_company_tax_id;
    }

    /**
     * Normalise un NIF / identifiant fiscal (casse, espaces).
     */
    public static function normalizeCompanyTaxId(?string $input): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim((string) $input)));
    }

    public function isUsable(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Génère une clé unique du type SIT-XXXXXXXX-XXXXXXXX.
     */
    public static function generateUniqueKey(): string
    {
        for ($i = 0; $i < 20; $i++) {
            $key = 'SIT-'.strtoupper(bin2hex(random_bytes(4))).'-'.strtoupper(bin2hex(random_bytes(4)));
            if (! static::query()->where('license_key', $key)->exists()) {
                return $key;
            }
        }

        return 'SIT-'.strtoupper(Str::uuid()->toString());
    }

    /**
     * Normalise une saisie utilisateur (espaces, casse).
     */
    public static function normalizeKey(?string $input): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $input));
    }

    public static function findByNormalizedKey(string $normalized): ?self
    {
        return static::query()->where('license_key', $normalized)->first();
    }

    /**
     * Définit le compte principal (plus petit id entreprise sur cette licence) si absent ou incohérent.
     */
    public function syncPrimaryWorkspaceUser(): void
    {
        $minId = User::query()
            ->clients()
            ->where('enterprise_license_id', $this->id)
            ->min('id');

        if ($minId === null) {
            return;
        }

        if ((int) $this->primary_workspace_user_id !== (int) $minId) {
            $this->forceFill(['primary_workspace_user_id' => $minId])->save();
        }
    }
}
