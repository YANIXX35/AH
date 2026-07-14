<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingQualityReview extends Model
{
    protected $fillable = [
        'user_id',
        'period_start',
        'period_end',
        'status',
        'method_version',
        'reliability_score_snapshot',
        'reviewed_at',
        'reviewed_by',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'reliability_score_snapshot' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isValidated(): bool
    {
        return $this->status === 'validated';
    }
}
