<?php

namespace App\Services;

use App\Models\AccountingDocument;
use App\Models\AccountingEntry;
use App\Models\TreasuryTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    /**
     * @param  array<int, int>  $userIds
     * @return array<string, mixed>
     */
    public function build(array $userIds, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth();
        $yearStart = $today->copy()->startOfYear();
        $yearEnd = $today->copy()->endOfYear();

        $entriesQuery = AccountingEntry::whereIn('user_id', $userIds);
        $documentsQuery = AccountingDocument::whereIn('user_id', $userIds);
        $treasuryQuery = TreasuryTransaction::whereIn('user_id', $userIds);

        $accountingEntriesCount = (clone $entriesQuery)->count();
        $accountingMonthlyAmount = (clone $entriesQuery)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $turnoverMonth = (float) AccountingEntry::whereIn('user_id', $userIds)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum(DB::raw("
                CASE
                    WHEN LTRIM(credit_account) LIKE '7%' THEN amount
                    WHEN LTRIM(debit_account) LIKE '7%' THEN -amount
                    ELSE 0
                END
            "));
        $turnoverYear = (float) AccountingEntry::whereIn('user_id', $userIds)
            ->whereBetween('date', [$yearStart, $yearEnd])
            ->sum(DB::raw("
                CASE
                    WHEN LTRIM(credit_account) LIKE '7%' THEN amount
                    WHEN LTRIM(debit_account) LIKE '7%' THEN -amount
                    ELSE 0
                END
            "));

        $turnoverChartLabels = [];
        $turnoverChartData = [];
        for ($i = 11; $i >= 0; $i--) {
            $mStart = $today->copy()->subMonths($i)->startOfMonth();
            $mEnd = $mStart->copy()->endOfMonth();
            $turnoverChartLabels[] = $mStart->locale('fr')->translatedFormat('M Y');
            $caMonth = (float) AccountingEntry::whereIn('user_id', $userIds)
                ->whereBetween('date', [$mStart->toDateString(), $mEnd->toDateString()])
                ->sum(DB::raw("
                    CASE
                        WHEN LTRIM(credit_account) LIKE '7%' THEN amount
                        WHEN LTRIM(debit_account) LIKE '7%' THEN -amount
                        ELSE 0
                    END
                "));
            $turnoverChartData[] = $caMonth;
        }

        $documentsCount = (clone $documentsQuery)->count();
        $pendingDocumentsCount = (clone $documentsQuery)
            ->whereIn('status', ['pending', 'pending_validation', 'ocr_failed'])
            ->count();

        $encaissementsTotal = (clone $treasuryQuery)
            ->where('type', 'encaissement')
            ->where('status', 'effectue')
            ->sum('amount');
        $decaissementsTotal = (clone $treasuryQuery)
            ->where('type', 'decaissement')
            ->where('status', 'effectue')
            ->sum('amount');
        $soldeActuel = $encaissementsTotal - $decaissementsTotal;

        $monthlyTreasury = TreasuryTransaction::whereIn('user_id', $userIds)
            ->where('status', 'effectue')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth]);
        $encaissementsMonth = (clone $monthlyTreasury)->where('type', 'encaissement')->sum('amount');
        $decaissementsMonth = (clone $monthlyTreasury)->where('type', 'decaissement')->sum('amount');

        $plannedTreasury = TreasuryTransaction::whereIn('user_id', $userIds)
            ->where('status', 'planifie');
        $encaissementsPlanifies = (clone $plannedTreasury)->where('type', 'encaissement')->sum('amount');
        $decaissementsPlanifies = (clone $plannedTreasury)->where('type', 'decaissement')->sum('amount');
        $soldeProjete = $soldeActuel + $encaissementsPlanifies - $decaissementsPlanifies;

        $latestEntries = AccountingEntry::whereIn('user_id', $userIds)
            ->latest('date')
            ->take(5)
            ->get();
        $latestTransactions = TreasuryTransaction::whereIn('user_id', $userIds)
            ->latest('transaction_date')
            ->take(5)
            ->get();
        $recentDocuments = AccountingDocument::whereIn('user_id', $userIds)
            ->latest()
            ->take(5)
            ->get();

        $chartLabels = [];
        $treasuryEncChart = [];
        $treasuryDecChart = [];
        $accountingBarChart = [];
        for ($i = 5; $i >= 0; $i--) {
            $mStart = $today->copy()->subMonths($i)->startOfMonth();
            $mEnd = $mStart->copy()->endOfMonth();
            $chartLabels[] = $mStart->locale('fr')->translatedFormat('M Y');
            $treasuryEncChart[] = (float) TreasuryTransaction::whereIn('user_id', $userIds)
                ->where('status', 'effectue')
                ->where('type', 'encaissement')
                ->whereBetween('transaction_date', [$mStart->toDateString(), $mEnd->toDateString()])
                ->sum('amount');
            $treasuryDecChart[] = (float) TreasuryTransaction::whereIn('user_id', $userIds)
                ->where('status', 'effectue')
                ->where('type', 'decaissement')
                ->whereBetween('transaction_date', [$mStart->toDateString(), $mEnd->toDateString()])
                ->sum('amount');
            $accountingBarChart[] = (float) AccountingEntry::whereIn('user_id', $userIds)
                ->whereBetween('date', [$mStart->toDateString(), $mEnd->toDateString()])
                ->sum('amount');
        }

        $ocrRows = AccountingEntry::whereIn('user_id', $userIds)
            ->select('ocr_status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('ocr_status')
            ->orderByDesc('cnt')
            ->get();
        $ocrLabelMap = [
            'verified' => 'Vérifié',
            'manual_verified' => 'Vérifié manuellement',
            'mismatch' => 'Incohérences',
            'mismatched' => 'Incohérences',
            'failed' => 'Erreur OCR',
            'pending' => 'En attente / sans fichier',
        ];
        $ocrColorMap = [
            'verified' => '#22c55e',
            'manual_verified' => '#16a34a',
            'mismatch' => '#f59e0b',
            'mismatched' => '#f59e0b',
            'failed' => '#ef4444',
            'pending' => '#6b7280',
        ];
        $ocrDonutLabels = [];
        $ocrDonutData = [];
        $ocrDonutColors = [];
        foreach ($ocrRows as $row) {
            $key = $row->ocr_status ?? 'pending';
            $ocrDonutLabels[] = $ocrLabelMap[$key] ?? (string) $key;
            $ocrDonutData[] = (int) $row->cnt;
            $ocrDonutColors[] = $ocrColorMap[$key] ?? '#94a3b8';
        }

        return [
            'accountingEntriesCount' => $accountingEntriesCount,
            'accountingMonthlyAmount' => $accountingMonthlyAmount,
            'turnoverMonth' => max(0, $turnoverMonth),
            'turnoverYear' => max(0, $turnoverYear),
            'turnoverChartLabels' => $turnoverChartLabels,
            'turnoverChartData' => $turnoverChartData,
            'documentsCount' => $documentsCount,
            'pendingDocumentsCount' => $pendingDocumentsCount,
            'encaissementsTotal' => $encaissementsTotal,
            'decaissementsTotal' => $decaissementsTotal,
            'soldeActuel' => $soldeActuel,
            'encaissementsMonth' => $encaissementsMonth,
            'decaissementsMonth' => $decaissementsMonth,
            'soldeProjete' => $soldeProjete,
            'encaissementsPlanifies' => $encaissementsPlanifies,
            'decaissementsPlanifies' => $decaissementsPlanifies,
            'latestEntries' => $latestEntries,
            'latestTransactions' => $latestTransactions,
            'recentDocuments' => $recentDocuments,
            'currentMonthLabel' => $startOfMonth->translatedFormat('F Y'),
            'chartLabels' => $chartLabels,
            'treasuryEncChart' => $treasuryEncChart,
            'treasuryDecChart' => $treasuryDecChart,
            'accountingBarChart' => $accountingBarChart,
            'ocrDonutLabels' => $ocrDonutLabels,
            'ocrDonutData' => $ocrDonutData,
            'ocrDonutColors' => $ocrDonutColors,
        ];
    }
}
