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
     * Calcule le solde d'un commercial : commission d'ajout de client (10 000 F pour
     * chacun des 3 premiers clients ajoutés à vie, 7 500 F pour chaque client suivant)
     * + 1 500 F pour chaque renouvellement d'abonnement réellement payé (CinetPay) par
     * un client apporté par ce commercial, après la fin de son essai gratuit.
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

        $rows = collect();
        $totalSignupEarnings = 0;
        $totalRenewalEarnings = 0;

        foreach ($clients as $index => $client) {
            $rank = $index + 1;
            $signupBonus = $rank <= self::TIER1_SLOTS ? self::SIGNUP_BONUS_TIER1 : self::SIGNUP_BONUS_TIER2;
            $renewals = $renewalsByClient->get($client->id, collect());
            $renewalCount = $renewals->count();
            $renewalEarnings = $renewalCount * self::RENEWAL_BONUS;

            $totalSignupEarnings += $signupBonus;
            $totalRenewalEarnings += $renewalEarnings;

            $rows->push([
                'client' => $client,
                'rank' => $rank,
                'signup_bonus' => $signupBonus,
                'renewal_count' => $renewalCount,
                'renewal_earnings' => $renewalEarnings,
                'subtotal' => $signupBonus + $renewalEarnings,
                'last_renewal_at' => $renewals->last()?->starts_at,
            ]);
        }

        return [
            'rows' => $rows->sortByDesc('rank')->values(),
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
