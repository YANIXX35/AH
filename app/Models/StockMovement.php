<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'actor_user_id',
        'type',
        'quantity',
        'unit_cost',
        'quantity_after',
        'average_cost_after',
        'movement_date',
        'reason',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'quantity_after' => 'decimal:2',
        'average_cost_after' => 'decimal:2',
        'movement_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(StockProduct::class, 'product_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
