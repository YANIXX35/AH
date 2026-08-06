<?php

namespace App\Services;

use App\Models\CommercialPayout;
use App\Models\CommissionReceiptNumberCounter;
use App\Models\SubscriptionHistory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

class CommercialCommissionService
{
    public const SIGNUP_BONUS_TIER1 = 10000;

    public const SIGNUP_BONUS_TIER2 = 7000;

    public const RENEWAL_BONUS = 1500;

    public const TIER1_SLOTS = 3;

    /**
     * Calcule le solde d'un commercial :
     *
     * - Commission d'ajout : 10 000 F pour chacun des 3 premiers clients à avoir
     *   réellement payé leur abonnement (CinetPay, après la fin de leur essai gratuit),
     *   7 000 F pour chaque client payant suivant. Le rang qui détermine le palier suit
     *   l'ordre du PREMIER PAIEMENT de chaque client, pas l'ordre dans lequel le
     *   commercial les a ajoutés. Un client jamais payé rapporte 0 F et n'occupe aucun
     *   rang (il ne bloque donc jamais le tarif à 10 000 F pour un autre client payant).
     * - Commission de renouvellement : 1 500 F pour chaque paiement CinetPay réel d'un
     *   client apporté par ce commercial (le tout premier paiement compte à la fois pour
     *   débloquer la commission d'ajout et pour son propre 1 500 F).
     *
     * @return array{rows: Collection<int, array<string, mixed>>, totalBalance: int, totalSignupEarnings: int, totalRenewalEarnings: int, totalClients: int}
     */
    public function calculateBalance(User $commercial): array
    {
        try {
            $clients = ($commercial->is_platform_admin ?? false)
                ? User::query()->clients()->orderBy('created_at')->get()
                : $commercial->createdClients()->orderBy('created_at')->get();
        } catch (\Throwable $e) {
            $clients = collect();
        }

        $renewalsByClient = SubscriptionHistory::query()
            ->whereIn('user_id', $clients->pluck('id'))
            ->where('source', 'like', 'cinetpay%')
            ->orderBy('starts_at')
            ->get()
            ->groupBy('user_id');

        // Rang « palier » : uniquement parmi les clients ayant payé au moins une fois,
        // dans l'ordre de leur premier paiement (pas l'ordre d'ajout).
        $paymentRankByClientId = $clients
            ->filter(fn (User $client) => $renewalsByClient->has($client->id))
            ->sortBy(fn (User $client) => $renewalsByClient->get($client->id)->first()->starts_at)
            ->values()
            ->mapWithKeys(fn (User $client, int $index) => [$client->id => $index + 1]);

        $rows = collect();
        $totalSignupEarnings = 0;
        $totalRenewalEarnings = 0;

        foreach ($clients as $client) {
            $renewals = $renewalsByClient->get($client->id, collect());
            $renewalCount = $renewals->count();
            $renewalEarnings = $renewalCount * self::RENEWAL_BONUS;

            $paymentRank = $paymentRankByClientId->get($client->id);
            $hasPaid = $paymentRank !== null;
            $signupBonus = $hasPaid
                ? ($paymentRank <= self::TIER1_SLOTS ? self::SIGNUP_BONUS_TIER1 : self::SIGNUP_BONUS_TIER2)
                : 0;

            $totalSignupEarnings += $signupBonus;
            $totalRenewalEarnings += $renewalEarnings;

            $rows->push([
                'client' => $client,
                'rank' => $paymentRank,
                'has_paid' => $hasPaid,
                'signup_bonus' => $signupBonus,
                'renewal_count' => $renewalCount,
                'renewal_earnings' => $renewalEarnings,
                'subtotal' => $signupBonus + $renewalEarnings,
                'last_renewal_at' => $renewals->last()?->starts_at,
            ]);
        }

        return [
            'rows' => $rows->sortByDesc(fn (array $row) => $row['client']->created_at)->values(),
            'totalBalance' => $totalSignupEarnings + $totalRenewalEarnings,
            'totalSignupEarnings' => $totalSignupEarnings,
            'totalRenewalEarnings' => $totalRenewalEarnings,
            'totalClients' => $clients->count(),
        ];
    }

    public function totalPaid(User $commercial): int
    {
        return (int) CommercialPayout::query()
            ->where('commercial_user_id', $commercial->id)
            ->sum('amount');
    }

    /**
     * Numéro de reçu séquentiel par commercial et par année, ex. REC-2026-000001.
     * Doit être appelé à l'intérieur d'une DB::transaction() par l'appelant pour que
     * le lockForUpdate() garantisse l'absence de doublon/trou sous concurrence
     * (même schéma que InvoiceService::allocateInvoiceNumber).
     */
    public function allocateReceiptNumber(int $commercialUserId, \DateTimeInterface $date): string
    {
        $year = (int) $date->format('Y');

        try {
            CommissionReceiptNumberCounter::firstOrCreate(
                ['user_id' => $commercialUserId, 'year' => $year],
                ['last_number' => 0]
            );
        } catch (QueryException) {
            // Créé entre-temps par une requête concurrente : la lecture verrouillée ci-dessous la trouvera.
        }

        $counter = CommissionReceiptNumberCounter::where('user_id', $commercialUserId)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        $next = $counter->last_number + 1;
        $counter->update(['last_number' => $next]);

        return sprintf('REC-%d-%06d', $year, $next);
    }
}
