<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mémorise une clôture mensuelle de dossier (repère métier, non verrouillage technique des écritures).
 */
class AccountingMonthClosure extends Model
{
    protected $fillable = [
        'user_id',
        'year_month',
        'closed_at',
        'closed_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }
}
