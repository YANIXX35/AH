<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialPayout extends Model
{
    protected $fillable = [
        'commercial_user_id',
        'validated_by_user_id',
        'receipt_number',
        'amount',
        'balance_at_payment',
        'previously_paid_total',
        'note',
        'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_at_payment' => 'integer',
            'previously_paid_total' => 'integer',
        ];
    }

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commercial_user_id');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by_user_id');
    }
}
