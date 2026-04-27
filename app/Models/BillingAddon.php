<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingAddon extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'price',
        'currency',
        'billing_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptionAddons(): HasMany
    {
        return $this->hasMany(BillingSubscriptionAddon::class);
    }
}
