<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    protected $fillable = [
        'invoice_id',
        'actor_user_id',
        'treasury_transaction_id',
        'accounting_entry_id',
        'amount',
        'paid_at',
        'method',
        'reference',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function treasuryTransaction(): BelongsTo
    {
        return $this->belongsTo(TreasuryTransaction::class);
    }

    public function accountingEntry(): BelongsTo
    {
        return $this->belongsTo(AccountingEntry::class);
    }
}
