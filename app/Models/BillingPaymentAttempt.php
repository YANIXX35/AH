<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingPaymentAttempt extends Model
{
    protected $fillable = [
        'billing_invoice_id',
        'payment_transaction_id',
        'attempted_at',
        'status',
        'provider',
        'error_message',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'billing_invoice_id');
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }
}
