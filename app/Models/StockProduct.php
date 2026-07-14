<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockProduct extends Model
{
    protected $fillable = [
        'user_id',
        'actor_user_id',
        'sku',
        'name',
        'unit',
        'quantity_on_hand',
        'average_cost',
        'sale_price',
        'reorder_threshold',
        'is_active',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:2',
        'average_cost' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'reorder_threshold' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'product_id')->latest('movement_date')->latest('id');
    }

    public function stockValue(): float
    {
        return round((float) $this->quantity_on_hand * (float) $this->average_cost, 2);
    }

    public function isBelowThreshold(): bool
    {
        return $this->reorder_threshold !== null && (float) $this->quantity_on_hand < (float) $this->reorder_threshold;
    }
}
