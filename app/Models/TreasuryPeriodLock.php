<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreasuryPeriodLock extends Model
{
    protected $fillable = [
        'user_id',
        'period_month',
        'locked_at',
        'locked_by',
        'validated_at',
        'validated_by',
        'notes',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
        'validated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
