<?php

namespace App\Services;

use App\Models\AccountingEntry;
use App\Models\PlatformSetting;
use App\Models\TreasuryTransaction;
use App\Support\Scoring360Defaults;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class Scoring360Service
{
    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        try {
            $setting = PlatformSetting::query()->where('key', 'scoring_360')->first();

            return (array) ($setting?->value ?? Scoring360Defaults::defaults());
        } catch (\Throwable) {
            // Tant que les migrations ne sont pas appliquées, on retombe sur les valeurs par défaut.
            return Scoring360Defaults::defaults();
        }
    }

    /**
     * Calcule les scores (Banque/Investisseur/Interne + Composite) pour un utilisateur.
     *
     * Les ratios sont déduits du grand livre (écritures) et de la trésorerie « effectuée ».
     *
     * @return array<string, mixed>
     */
    public function scoreUser(int $userId, ?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        $cfg = $this->config();
        $coeffs = (array) ($cfg['coefficients'] ?? ['strong' => 1.0, 'medium' => 0.6, 'weak' => 0.2]);

        $entries = $this->loadEntries($userId, $dateFrom, $dateTo);
        $ledger = $this->buildLedger($entries);
        $sumByPrefixes = $this->prefixSummator($ledger);

        $revenue = $sumByPrefixes(['7'], 'credit_net');
        $expenses = $sumByPrefixes(['6'], 'debit_net');
        $netResult = $revenue - $expenses;

        $interestExpense = $sumByPrefixes(['66'], 'debit_net');
        $caf = $netResult;
        $ebit = $netResult + $interestExpense;
        $ebitda = $ebit;

        $stocks = max($sumByPrefixes(['311', '321', '322', '331', '335', '341', '351', '355'], 'debit_net') - $sumByPrefixes(['391', '392'], 'credit_net'), 0.0);
        $receivables = max($sumByPrefixes(['411', '413', '418'], 'debit_net'), 0.0);
        $cash = max($sumByPrefixes(['512', '514', '515', '521', '531', '541', '542', '566', '581'], 'debit_net'), 0.0);

        $suppliers = $sumByPrefixes(['401', '403', '408'], 'credit_net');
        $taxDebts = $sumByPrefixes(['441', '442', '443', '444', '445', '447'], 'credit_net');
        $socialDebts = $sumByPrefixes(['428', '431'], 'credit_net');
        $otherDebts = $sumByPrefixes(['421', '451', '455', '467'], 'credit_net');
        $cashLiabilities = $sumByPrefixes(['512', '514', '515', '521', '531', '541', '542', '566', '581'], 'credit_net');

        $currentAssets = $stocks + $receivables + $cash;
        $currentLiabilities = $suppliers + $taxDebts + $socialDebts + $otherDebts + $cashLiabilities;

        $dettesFinancieres = $sumByPrefixes(['161', '162', '163'], 'credit_net');
        $dettesCreditBail = $sumByPrefixes(['164'], 'credit_net');
        $dettesFinDiverses = $sumByPrefixes(['109', '165', '166'], 'credit_net');
        $financialDebt = $dettesFinancieres + $dettesCreditBail + $dettesFinDiverses;

        // Total actif (approx. bilan) : immobilisations + circulant + trésorerie.
        $immobilisations = max($sumByPrefixes(['201', '203', '205', '206', '207', '208', '211', '212', '213', '215', '221', '231', '241', '244', '261', '271', '275'], 'debit_net'), 0.0);
        $assets = $immobilisations + $stocks + $receivables + $cash;

        $equity = $assets - ($currentLiabilities + $financialDebt);
        $roe = $equity > 0.01 ? ($netResult / $equity) : null;

        $dscr = $interestExpense > 0.0001 ? ($caf / $interestExpense) : null;
        $interestCoverage = $interestExpense > 0.0001 ? ($ebit / $interestExpense) : null;
        $currentRatio = $currentLiabilities > 0.0001 ? ($currentAssets / $currentLiabilities) : null;
        $quickRatio = $currentLiabilities > 0.0001 ? (($receivables + $cash) / $currentLiabilities) : null;
        $debtAsset = $assets > 0.0001 ? ($financialDebt / $assets) : null;

        $bfr = $currentAssets - $currentLiabilities;
        $bfrDays = $revenue > 0.0001 ? ($bfr * 365.0 / $revenue) : null;

        $ebitdaMargin = $revenue > 0.0001 ? ($ebitda / $revenue) : null;
        $netMargin = $revenue > 0.0001 ? ($netResult / $revenue) : null;
        $assetTurnover = $assets > 0.0001 ? ($revenue / $assets) : null;

        $treasuryNet = $this->treasuryNetBalance($userId, $dateFrom, $dateTo);
        $fcfMargin = $revenue > 0.0001 ? ($treasuryNet / $revenue) : null;

        $receivableDays = $revenue > 0.0001 ? ($receivables * 365.0 / $revenue) : null;
        $purchases = $sumByPrefixes(['60', '61'], 'debit_net');
        $inventoryDays = $purchases > 0.0001 ? ($stocks * 365.0 / $purchases) : null;

        $revenueGrowth = $this->growthRatio($userId, $dateFrom, $dateTo, 'revenue');
        $ebitdaGrowth = $this->growthRatio($userId, $dateFrom, $dateTo, 'ebitda');

        $ratios = [
            'dscr' => $dscr,
            'interest_coverage' => $interestCoverage,
            'current_ratio' => $currentRatio,
            'debt_asset' => $debtAsset,
            'bfr_days' => $bfrDays,
            'revenue_growth' => $revenueGrowth,
            'ebitda_margin' => $ebitdaMargin,
            'roe' => $roe,
            'fcf_margin' => $fcfMargin,
            'asset_turnover' => $assetTurnover,
            'net_margin' => $netMargin,
            'quick_ratio' => $quickRatio,
            'receivable_days' => $receivableDays,
            'inventory_days' => $inventoryDays,
            'ebitda_growth' => $ebitdaGrowth,
        ];

        $bank = $this->scoreBlock('bank', $ratios, $cfg, $coeffs);
        $investor = $this->scoreBlock('investor', $ratios, $cfg, $coeffs);
        $internal = $this->scoreBlock('internal', $ratios, $cfg, $coeffs);

        $composite = $this->scoreComposite($bank, $investor, $internal, $cfg);

        return [
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'ratios' => $ratios,
            'blocks' => [
                'bank' => $bank,
                'investor' => $investor,
                'internal' => $internal,
            ],
            'composite' => $composite,
            'inputs' => [
                'revenue' => $revenue,
                'net_result' => $netResult,
                'interest_expense' => $interestExpense,
                'financial_debt' => $financialDebt,
                'current_assets' => $currentAssets,
                'current_liabilities' => $currentLiabilities,
                'assets' => $assets,
                'treasury_net' => $treasuryNet,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @param  array<string, float>  $coeffs
     * @param  array<string, mixed>  $ratios
     * @return array<string, mixed>
     */
    private function scoreBlock(string $blockKey, array $ratios, array $cfg, array $coeffs): array
    {
        $block = (array) ($cfg[$blockKey] ?? []);
        $thresholds = (array) ($block['thresholds'] ?? []);
        $weights = (array) ($block['weights'] ?? []);
        $decision = (array) ($block['decision'] ?? []);

        $rows = [];
        $total = 0.0;
        foreach ($weights as $crit => $weight) {
            $t = (array) ($thresholds[$crit] ?? []);
            $direction = (string) ($t['direction'] ?? 'gte');
            $strong = (float) ($t['strong'] ?? 0);
            $medium = (float) ($t['medium'] ?? 0);
            $value = $ratios[$crit] ?? null;

            $eval = $this->evaluateCriterion($value, (float) $weight, $strong, $medium, $direction, $coeffs);
            $rows[$crit] = $eval;
            $total += (float) ($eval['score'] ?? 0.0);
        }

        $d = $this->decisionFromScore($total, $decision);

        return [
            'total' => round($total, 1),
            'decision' => $d,
            'criteria' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $bank
     * @param  array<string, mixed>  $investor
     * @param  array<string, mixed>  $internal
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>
     */
    private function scoreComposite(array $bank, array $investor, array $internal, array $cfg): array
    {
        $weights = (array) ($cfg['composite']['weights'] ?? []);
        $decisionCfg = (array) ($cfg['composite']['decision'] ?? []);

        $sb = (float) ($bank['total'] ?? 0);
        $si = (float) ($investor['total'] ?? 0);
        $sn = (float) ($internal['total'] ?? 0);

        $wb = (float) ($weights['bank'] ?? 0);
        $wi = (float) ($weights['investor'] ?? 0);
        $wn = (float) ($weights['internal'] ?? 0);

        $cb = $sb * $wb / 100.0;
        $ci = $si * $wi / 100.0;
        $cn = $sn * $wn / 100.0;

        $total = $cb + $ci + $cn;

        return [
            'total' => round($total, 1),
            'decision' => $this->decisionFromScore($total, $decisionCfg),
            'contributions' => [
                'bank' => round($cb, 2),
                'investor' => round($ci, 2),
                'internal' => round($cn, 2),
            ],
        ];
    }

    /**
     * @param  array<string, float>  $coeffs
     * @return array{value: ?float, score: float, level: string}
     */
    private function evaluateCriterion(?float $value, float $weight, float $strong, float $medium, string $direction, array $coeffs): array
    {
        if ($value === null) {
            return ['value' => null, 'score' => 0.0, 'level' => 'missing'];
        }

        $isStrong = $direction === 'lte' ? ($value <= $strong) : ($value >= $strong);
        $isMedium = $direction === 'lte' ? ($value <= $medium) : ($value >= $medium);

        if ($isStrong) {
            $level = 'strong';
            $coef = (float) ($coeffs['strong'] ?? 1.0);
        } elseif ($isMedium) {
            $level = 'medium';
            $coef = (float) ($coeffs['medium'] ?? 0.6);
        } else {
            $level = 'weak';
            $coef = (float) ($coeffs['weak'] ?? 0.2);
        }

        return [
            'value' => $value,
            'score' => round($weight * $coef, 2),
            'level' => $level,
        ];
    }

    /**
     * @param  array<string, mixed>  $decision
     * @return array{level: string, label: string, lecture: string}
     */
    private function decisionFromScore(float $score, array $decision): array
    {
        $strongMin = (float) ($decision['strong_min'] ?? 80.0);
        $mediumMin = (float) ($decision['medium_min'] ?? 60.0);
        $labels = (array) ($decision['labels'] ?? []);
        $lectures = (array) ($decision['lectures'] ?? []);

        if ($score >= $strongMin) {
            $level = 'strong';
        } elseif ($score >= $mediumMin) {
            $level = 'medium';
        } else {
            $level = 'weak';
        }

        return [
            'level' => $level,
            'label' => (string) ($labels[$level] ?? strtoupper($level)),
            'lecture' => (string) ($lectures[$level] ?? ''),
        ];
    }

    private function loadEntries(int $userId, ?Carbon $dateFrom, ?Carbon $dateTo): Collection
    {
        $q = AccountingEntry::query()->where('user_id', $userId);
        if ($dateFrom !== null) {
            $q->whereDate('date', '>=', $dateFrom->toDateString());
        }
        if ($dateTo !== null) {
            $q->whereDate('date', '<=', $dateTo->toDateString());
        }

        return $q->orderBy('date')->get(['debit_account', 'credit_account', 'amount']);
    }

    /**
     * @param  Collection<int, AccountingEntry>  $entries
     * @return array<string, array{debit: float, credit: float, debit_net: float, credit_net: float}>
     */
    private function buildLedger(Collection $entries): array
    {
        $ledger = [];
        foreach ($entries as $entry) {
            foreach (['debit_account', 'credit_account'] as $side) {
                $code = $this->extractAccountCode((string) $entry->{$side});
                if ($code === null) {
                    continue;
                }
                if (! isset($ledger[$code])) {
                    $ledger[$code] = ['debit' => 0.0, 'credit' => 0.0];
                }
                if ($side === 'debit_account') {
                    $ledger[$code]['debit'] += (float) $entry->amount;
                } else {
                    $ledger[$code]['credit'] += (float) $entry->amount;
                }
            }
        }

        $rowsByCode = [];
        foreach ($ledger as $code => $amounts) {
            $debit = (float) ($amounts['debit'] ?? 0.0);
            $credit = (float) ($amounts['credit'] ?? 0.0);
            $rowsByCode[$code] = [
                'debit' => $debit,
                'credit' => $credit,
                'debit_net' => max($debit - $credit, 0.0),
                'credit_net' => max($credit - $debit, 0.0),
            ];
        }

        return $rowsByCode;
    }

    /**
     * @param  array<string, array{debit: float, credit: float, debit_net: float, credit_net: float}>  $rowsByCode
     * @return callable(array<int, string>, string=): float
     */
    private function prefixSummator(array $rowsByCode): callable
    {
        return function (array $prefixes, string $column = 'credit_net') use ($rowsByCode): float {
            $total = 0.0;
            foreach ($rowsByCode as $code => $row) {
                foreach ($prefixes as $prefix) {
                    $prefix = (string) $prefix;
                    if ($prefix !== '' && str_starts_with((string) $code, $prefix)) {
                        $total += (float) ($row[$column] ?? 0.0);
                        break;
                    }
                }
            }

            return $total;
        };
    }

    private function extractAccountCode(string $account): ?string
    {
        $account = trim($account);
        if ($account === '') {
            return null;
        }
        if (preg_match('/^(\\d{2,6})/', $account, $m)) {
            return $m[1];
        }

        return null;
    }

    private function treasuryNetBalance(int $userId, ?Carbon $dateFrom, ?Carbon $dateTo): float
    {
        $q = TreasuryTransaction::query()
            ->where('user_id', $userId)
            ->where('status', 'effectue');

        if ($dateFrom !== null) {
            $q->whereDate('transaction_date', '>=', $dateFrom->toDateString());
        }
        if ($dateTo !== null) {
            $q->whereDate('transaction_date', '<=', $dateTo->toDateString());
        }

        $enc = (float) (clone $q)->where('type', 'encaissement')->sum('amount');
        $dec = (float) (clone $q)->where('type', 'decaissement')->sum('amount');

        return $enc - $dec;
    }

    private function growthRatio(int $userId, ?Carbon $dateFrom, ?Carbon $dateTo, string $metric): ?float
    {
        if ($dateFrom === null || $dateTo === null) {
            return null;
        }

        $days = max(1, $dateFrom->diffInDays($dateTo) + 1);
        $prevFrom = (clone $dateFrom)->subDays($days);
        $prevTo = (clone $dateTo)->subDays($days);

        $n = $this->metricValue($userId, $dateFrom, $dateTo, $metric);
        $n1 = $this->metricValue($userId, $prevFrom, $prevTo, $metric);

        if ($n1 <= 0.0001) {
            return null;
        }

        return ($n / $n1) - 1.0;
    }

    private function metricValue(int $userId, Carbon $from, Carbon $to, string $metric): float
    {
        $entries = $this->loadEntries($userId, $from, $to);
        $ledger = $this->buildLedger($entries);
        $sumByPrefixes = $this->prefixSummator($ledger);

        $revenue = $sumByPrefixes(['7'], 'credit_net');
        $expenses = $sumByPrefixes(['6'], 'debit_net');
        $netResult = $revenue - $expenses;
        $interestExpense = $sumByPrefixes(['66'], 'debit_net');

        if ($metric === 'revenue') {
            return $revenue;
        }
        if ($metric === 'ebitda') {
            return $netResult + $interestExpense;
        }

        return 0.0;
    }
}
