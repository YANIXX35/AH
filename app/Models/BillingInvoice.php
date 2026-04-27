<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingInvoice extends Model
{
    protected $fillable = [
        'user_id',
        'billing_subscription_id',
        'invoice_number',
        'status',
        'issued_at',
        'due_at',
        'paid_at',
        'subtotal',
        'tax_amount',
        'total_amount',
        'currency',
        'payment_provider',
        'payment_reference',
        'pdf_path',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(BillingSubscription::class, 'billing_subscription_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillingInvoiceItem::class, 'billing_invoice_id');
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(BillingPaymentAttempt::class, 'billing_invoice_id');
    }
}
