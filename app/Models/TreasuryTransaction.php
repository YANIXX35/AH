<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TreasuryTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'payment_transaction_id',
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
        'value_date',
        'reference',
        'bank_account',
        'status',
        'notes',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'value_date' => 'date',
        'amount' => 'decimal:2',
        'stripe_paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Garantit que transaction_date retourne toujours un objet Carbon ou null
     */
    protected function transactionDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value) : null,
        );
    }

    /**
     * Date à laquelle les fonds sont effectivement disponibles — `value_date` si
     * renseignée, sinon `transaction_date` (comportement historique, avant PRD 4.4).
     */
    public function getEffectiveValueDateAttribute(): ?Carbon
    {
        return $this->value_date ?? $this->transaction_date;
    }

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

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function mobileMoneyTransaction(): HasOne
    {
        return $this->hasOne(MobileMoneyTransaction::class);
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

    /**
     * Scope : fonds réellement disponibles à la date donnée (PRD 4.4) — transactions
     * effectuées dont la date de valeur (ou, à défaut, la date de transaction) est
     * déjà passée. Distinct de scopeEffectuees(), qui inclut aussi les instruments
     * bancaires marqués "effectué" mais pas encore crédités en banque.
     */
    public function scopeCleared($query, $asOf = null)
    {
        $asOf = $asOf ? Carbon::parse($asOf) : now();

        return $query->effectuees()
            ->where(function ($q) use ($asOf) {
                $q->where(function ($q2) use ($asOf) {
                    $q2->whereNotNull('value_date')->where('value_date', '<=', $asOf->toDateString());
                })->orWhere(function ($q2) use ($asOf) {
                    $q2->whereNull('value_date')->where('transaction_date', '<=', $asOf->toDateString());
                });
            });
    }
}
