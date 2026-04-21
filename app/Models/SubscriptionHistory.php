<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionHistory extends Model
{
    protected $fillable = [
        'user_id',
        'from_status',
        'to_status',
        'is_premium',
        'starts_at',
        'ends_at',
        'source',
        'note',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_premium' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
