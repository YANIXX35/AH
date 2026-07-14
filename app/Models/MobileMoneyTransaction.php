<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileMoneyTransaction extends Model
{
    protected $fillable = [
        'statement_import_id',
        'user_id',
        'operator',
        'external_reference',
        'occurred_at',
        'amount',
        'direction',
        'counterparty_name',
        'counterparty_number',
        'raw_line',
        'status',
        'treasury_transaction_id',
        'accounting_entry_id',
        'matched_at',
    ];

    protected $casts = [
        'occurred_at' => 'date',
        'amount' => 'decimal:2',
        'matched_at' => 'datetime',
    ];

    public function statementImport(): BelongsTo
    {
        return $this->belongsTo(MobileMoneyStatementImport::class, 'statement_import_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function treasuryTransaction(): BelongsTo
    {
        return $this->belongsTo(TreasuryTransaction::class);
    }

    public function accountingEntry(): BelongsTo
    {
        return $this->belongsTo(AccountingEntry::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
