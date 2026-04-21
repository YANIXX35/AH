<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreasuryTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'actor_user_id',
        'type',
        'transaction_type',
        'payment_module',
        'payment_provider',
        'mobile_method',
        'mobile_number',
        'card_network',
        'card_last4',
        'bank_name',
        'bank_reference',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'stripe_payment_channel',
        'stripe_bank_scheme',
        'stripe_charge_id',
        'stripe_payout_id',
        'stripe_status',
        'stripe_failure_reason',
        'stripe_paid_at',
        'stripe_last_event_id',
        'amount',
        'description',
        'transaction_date',
        'reference',
        'bank_account',
        'status',
        'notes',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'stripe_paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relation avec l'utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Scope : encaissements
     */
    public function scopeEncaissements($query)
    {
        return $query->where('type', 'encaissement');
    }

    /**
     * Scope : décaissements
     */
    public function scopeDecaissements($query)
    {
        return $query->where('type', 'decaissement');
    }

    /**
     * Scope : transactions effectuées
     */
    public function scopeEffectuees($query)
    {
        return $query->where('status', 'effectue');
    }

    /**
     * Scope : transactions planifiées
     */
    public function scopePlanifiees($query)
    {
        return $query->where('status', 'planifie');
    }

    /**
     * Scope : par date
     */
    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('transaction_date', [$start, $end]);
    }
}
