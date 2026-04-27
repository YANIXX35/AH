<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingSubscriptionAddon extends Model
{
    protected $fillable = [
        'billing_subscription_id',
        'billing_addon_id',
        'quantity',
        'unit_price',
        'total_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(BillingSubscription::class, 'billing_subscription_id');
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(BillingAddon::class, 'billing_addon_id');
    }
}
