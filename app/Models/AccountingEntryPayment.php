<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingEntryPayment extends Model
{
    protected $fillable = [
        'accounting_entry_id',
        'user_id',
        'actor_user_id',
        'amount',
        'payment_date',
        'method',
        'reference',
        'treasury_transaction_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(AccountingEntry::class, 'accounting_entry_id');
    }

    public function treasuryTransaction(): BelongsTo
    {
        return $this->belongsTo(TreasuryTransaction::class);
    }
}
