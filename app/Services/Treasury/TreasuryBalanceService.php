<?php

namespace App\Services\Treasury;

use App\Models\TreasuryTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TreasuryBalanceService
{
    /**
     * @param  array<int, int>  $userIds
     * @return array<string, mixed>
     */
    public function build(array $userIds, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $monthStart = $dateFrom
            ? Carbon::parse($dateFrom)->startOfDay()
            : now()->startOfMonth();
        $monthEnd = $dateTo
            ? Carbon::parse($dateTo)->endOfDay()
            : now()->endOfMonth();

        if ($monthStart->greaterThan($monthEnd)) {
            [$monthStart, $monthEnd] = [$monthEnd->copy()->startOfDay(), $monthStart->copy()->endOfDay()];
        }

        $encaissementsEffectues = TreasuryTransaction::whereIn('user_id', $userIds)
            ->encaissements()
            ->effectuees()
            ->sum('amount');
        $decaissementsEffectues = TreasuryTransaction::whereIn('user_id', $userIds)
            ->decaissements()
            ->effectuees()
            ->sum('amount');
        $soldeActuel = $encaissementsEffectues - $decaissementsEffectues;

        // Trésorerie réelle (PRD 4.4) : ne compte que les fonds dont la date de valeur
        // est déjà passée — distinct de $soldeActuel, qui compte tout ce qui est marqué
        // "effectué" même si un instrument bancaire (chèque, virement) n'est pas encore
        // crédité en banque.
        $encaissementsReels = (float) TreasuryTransaction::whereIn('user_id', $userIds)
            ->encaissements()
            ->cleared()
            ->sum('amount');
        $decaissementsReels = (float) TreasuryTransaction::whereIn('user_id', $userIds)
            ->decaissements()
            ->cleared()
            ->sum('amount');
        $soldeActuelReel = $encaissementsReels - $decaissementsReels;

        $soldeOuverture = TreasuryTransaction::whereIn('user_id', $userIds)
            ->effectuees()
            ->whereDate('transaction_date', '<', $monthStart)
            ->sum(DB::raw("CASE WHEN type = 'encaissement' THEN amount ELSE -amount END"));

        $monthTransactions = TreasuryTransaction::whereIn('user_id', $userIds)
            ->effectuees()
            ->byDateRange($monthStart, $monthEnd)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $monthAllTransactions = TreasuryTransaction::whereIn('user_id', $userIds)
            ->byDateRange($monthStart, $monthEnd)
            ->get(['type', 'status', 'amount']);

        $monthEncaissementsEffectues = (float) $monthAllTransactions
            ->where('type', 'encaissement')
            ->where('status', 'effectue')
            ->sum('amount');
        $monthDecaissementsEffectues = (float) $monthAllTransactions
            ->where('type', 'decaissement')
            ->where('status', 'effectue')
            ->sum('amount');
        $monthEncaissementsPlanifies = (float) $monthAllTransactions
            ->where('type', 'encaissement')
            ->where('status', 'planifie')
            ->sum('amount');
        $monthDecaissementsPlanifies = (float) $monthAllTransactions
            ->where('type', 'decaissement')
            ->where('status', 'planifie')
            ->sum('amount');

        $totalOperationsMois = $monthAllTransactions->count();
        $operationsEffectuees = $monthAllTransactions->where('status', 'effectue')->count();
        $tauxExecution = $totalOperationsMois > 0
            ? round(($operationsEffectuees / $totalOperationsMois) * 100, 1)
            : 0.0;

        $tauxCouvertureDecaissements = $monthDecaissementsEffectues > 0
            ? round(($monthEncaissementsEffectues / $monthDecaissementsEffectues) * 100, 1)
            : null;
        $consommationNetteMois = max(0, $monthDecaissementsEffectues - $monthEncaissementsEffectues);
        $joursDansMois = max(1, (int) $monthStart->daysInMonth);
        $autonomieJours = $consommationNetteMois > 0
            ? round($soldeActuel / ($consommationNetteMois / $joursDansMois), 1)
            : null;

        $projectionFinMois = $soldeActuel + $monthEncaissementsPlanifies - $monthDecaissementsPlanifies;
        $dailyBalances = $this->calculateDailyBalances($userIds, $monthStart, $monthEnd, $soldeOuverture);

        return [
            'soldeActuel' => $soldeActuel,
            'soldeActuelReel' => $soldeActuelReel,
            'encaissementsEffectues' => $encaissementsEffectues,
            'decaissementsEffectues' => $decaissementsEffectues,
            'encaissementsReels' => $encaissementsReels,
            'decaissementsReels' => $decaissementsReels,
            'soldeOuverture' => $soldeOuverture,
            'monthTransactions' => $monthTransactions,
            'dailyBalances' => $dailyBalances,
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
            'dateFrom' => $monthStart->toDateString(),
            'dateTo' => $monthEnd->toDateString(),
            'perfIndicators' => [
                'tauxExecution' => $tauxExecution,
                'tauxCouvertureDecaissements' => $tauxCouvertureDecaissements,
                'autonomieJours' => $autonomieJours,
                'projectionFinMois' => $projectionFinMois,
                'encaissementsPlanifies' => $monthEncaissementsPlanifies,
                'decaissementsPlanifies' => $monthDecaissementsPlanifies,
            ],
        ];
    }

    /**
     * @param  array<int, int>  $userIds
     * @return array<string, float>
     */
    private function calculateDailyBalances(array $userIds, Carbon $start, Carbon $end, float $initialBalance = 0.0): array
    {
        $balance = $initialBalance;
        $dailyBalances = [];

        for ($date = $start->copy(); $date <= $end; $date->addDay()) {
            $dayTransactions = TreasuryTransaction::whereIn('user_id', $userIds)
                ->effectuees()
                ->whereDate('transaction_date', $date)
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get();

            foreach ($dayTransactions as $transaction) {
                if ($transaction->type === 'encaissement') {
                    $balance += (float) $transaction->amount;
                } else {
                    $balance -= (float) $transaction->amount;
                }
            }

            $dailyBalances[$date->format('Y-m-d')] = $balance;
        }

        return $dailyBalances;
    }
}
