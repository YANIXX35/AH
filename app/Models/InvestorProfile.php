<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fiche « profil investisseur » : scores agrégés et instantané financier (étape levée de fonds).
 */
class InvestorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'risk_score',
        'performance_score',
        'profile_code',
        'profile_label',
        'profile_detail',
        'classement_code',
        'classement_libelle',
        'operational_breakdown',
        'financial_snapshot',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'risk_score' => 'decimal:2',
            'performance_score' => 'decimal:2',
            'operational_breakdown' => 'array',
            'financial_snapshot' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
