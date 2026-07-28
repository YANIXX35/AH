<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'period_month',
        'payment_date',
        'payment_method',
        'payment_account',
        'total_gross',
        'total_cnps',
        'total_its',
        'total_net',
        'file_path',
        'status',
        'accounting_entry_id',
        'treasury_transaction_id',
        'synced_at',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'synced_at' => 'datetime',
        'total_gross' => 'decimal:2',
        'total_cnps' => 'decimal:2',
        'total_its' => 'decimal:2',
        'total_net' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function accountingEntry(): BelongsTo
    {
        return $this->belongsTo(AccountingEntry::class);
    }

    public function treasuryTransaction(): BelongsTo
    {
        return $this->belongsTo(TreasuryTransaction::class);
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            'bank_transfer' => 'Virement Bancaire',
            'wave' => 'Wave Mobile Money',
            'orange_money' => 'Orange Money',
            'mtn' => 'MTN Mobile Money',
            'check' => 'Chèque',
            'cash' => 'Caisse / Espèces',
            default => 'Virement Bancaire',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'synced' => 'Validé & Synchronisé',
            'cancelled' => 'Annulé',
            default => 'Brouillon',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'synced' => 'bg-success text-white',
            'cancelled' => 'bg-danger text-white',
            default => 'bg-warning text-dark',
        };
    }
}
