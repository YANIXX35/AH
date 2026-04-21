<?php

namespace App\Services;

use App\Models\AccountingDocument;
use App\Models\AccountingEntry;
use App\Models\TreasuryTransaction;
use Carbon\Carbon;

/**
 * Agrège compta / trésorerie / ratios PME pour produire scores risque-performance et profil investisseur.
 */
class InvestorReadinessService
{
    public function __construct(
        private readonly SmeFinancialRatioService $smeFinancialRatioService
    ) {}

    /**
     * @return array{
     *   risk_score: float,
     *   performance_score: float,
     *   investor_profile: string,
     *   investor_profile_detail: string,
     *   profile_code: string,
     *   breakdown: array<string, mixed>
     * }
     */
    public function build(int $userId): array
    {
        $today = Carbon::today();

        $soldeActuel = $this->computeSoldeActuel($userId);
        $planned = $this->computePlannedNet($userId);
        $soldeProjete = $soldeActuel + $planned['encaissements'] - $planned['decaissements'];

        $forecastBlock = $this->computeForecastReliability($userId, $today, 8);
        $reliability = (float) $forecastBlock['score'];
        $alertWeeks = (int) $forecastBlock['alertWeeks'];

        $entriesTotal = AccountingEntry::where('user_id', $userId)->count();
        $ocrVerified = AccountingEntry::where('user_id', $userId)
            ->whereIn('ocr_status', ['verified', 'manual_verified'])
            ->count();
        $ocrStress = AccountingEntry::where('user_id', $userId)
            ->whereIn('ocr_status', ['failed', 'mismatch', 'mismatched'])
            ->count();

        $docsTotal = AccountingDocument::where('user_id', $userId)->count();
        $docsPending = AccountingDocument::where('user_id', $userId)
            ->whereIn('status', ['pending', 'pending_validation', 'ocr_failed'])
            ->count();

        // Sous-scores risque opérationnel (0 = faible risque, 100 = risque élevé)
        $liquidityRisk = $this->liquidityRiskScore($soldeActuel, $soldeProjete);
        $ocrRisk = $entriesTotal > 0
            ? min(100.0, ($ocrStress / $entriesTotal) * 100)
            : 40.0;
        $backlogRisk = $docsTotal > 0
            ? ($docsPending / $docsTotal) * 100
            : ($docsPending > 0 ? 80.0 : 15.0);
        $forecastRisk = min(100.0, ($alertWeeks / 8) * 100);

        $riskOperational = round(
            0.38 * $liquidityRisk
            + 0.22 * $ocrRisk
            + 0.22 * $backlogRisk
            + 0.18 * $forecastRisk,
            1
        );
        $riskOperational = max(0.0, min(100.0, $riskOperational));

        // Performance opérationnelle (0 = faible, 100 = forte)
        $ocrPerf = $entriesTotal > 0
            ? ($ocrVerified / $entriesTotal) * 100
            : 45.0;
        $treasuryHealth = $this->treasuryHealthScore($soldeActuel, $soldeProjete);
        $activityScore = $this->activityScore($userId, $today);

        $performanceOperational = round(
            0.38 * $reliability
            + 0.24 * $ocrPerf
            + 0.22 * $treasuryHealth
            + 0.16 * $activityScore,
            1
        );
        $performanceOperational = max(0.0, min(100.0, $performanceOperational));

        // Analyse financière (écritures) — fusion étape 8
        $financialAnalysis = $this->smeFinancialRatioService->analyze($userId);
        $finEntries = (int) ($financialAnalysis['entries_count'] ?? 0);
        $finScores = $financialAnalysis['scores'] ?? [];
        $sSolv = isset($finScores['solvabilite']['valeur']) ? (float) $finScores['solvabilite']['valeur'] : null;
        $sRent = isset($finScores['rentabilite']['valeur']) ? (float) $finScores['rentabilite']['valeur'] : null;
        $sFiabGlob = isset($finScores['global']['valeur_fiabilisee']) ? (float) $finScores['global']['valeur_fiabilisee'] : null;
        $classement = $financialAnalysis['classement'] ?? [];

        [$riskScore, $performanceScore] = $this->blendWithFinancialScores(
            $riskOperational,
            $performanceOperational,
            $finEntries,
            $sSolv,
            $sRent,
            $sFiabGlob,
            $classement
        );

        $profile = $this->resolveInvestorProfile($riskScore, $performanceScore, $classement);

        $financialSnapshot = $this->buildFinancialSnapshot($financialAnalysis);

        return [
            'risk_score' => $riskScore,
            'performance_score' => $performanceScore,
            'investor_profile' => $profile['label'],
            'investor_profile_detail' => $profile['detail'],
            'profile_code' => $profile['code'],
            'breakdown' => [
                'solde_actuel' => $soldeActuel,
                'solde_projete' => $soldeProjete,
                'forecast_reliability' => $reliability,
                'alert_weeks' => $alertWeeks,
                'ocr_verified_ratio' => $entriesTotal > 0 ? round(($ocrVerified / $entriesTotal) * 100, 1) : null,
                'documents_pending_ratio' => $docsTotal > 0 ? round(($docsPending / $docsTotal) * 100, 1) : null,
                'liquidity_risk' => round($liquidityRisk, 1),
                'ocr_risk' => round($ocrRisk, 1),
                'backlog_risk' => round($backlogRisk, 1),
                'forecast_risk' => round($forecastRisk, 1),
                'treasury_health' => round($treasuryHealth, 1),
                'activity' => round($activityScore, 1),
                'risk_operational' => $riskOperational,
                'performance_operational' => $performanceOperational,
                'financial' => $financialSnapshot,
            ],
        ];
    }

    /**
     * Mélange scores opérationnels et scores issus du grand livre (SmeFinancialRatioService).
     *
     * @param  array<string, mixed>  $classement
     * @return array{0: float, 1: float}
     */
    private function blendWithFinancialScores(
        float $riskOperational,
        float $performanceOperational,
        int $finEntries,
        ?float $sSolv,
        ?float $sRent,
        ?float $sFiabGlob,
        array $classement
    ): array {
        $risk = $riskOperational;
        $perf = $performanceOperational;

        // Risque financier dérivé de la solvabilité (score élevé = structure saine = risque faible)
        $financialRisk = 50.0;
        if ($finEntries > 0 && $sSolv !== null) {
            $financialRisk = max(0.0, min(100.0, 100.0 - $sSolv));
        }

        // Performance financière : synthèse fiabilisée ; sinon moyenne solvabilité / rentabilité
        $financialPerf = null;
        if ($sFiabGlob !== null) {
            $financialPerf = $sFiabGlob;
        } elseif ($sSolv !== null && $sRent !== null) {
            $financialPerf = ($sSolv + $sRent) / 2.0;
        } elseif ($sSolv !== null) {
            $financialPerf = $sSolv;
        } elseif ($sRent !== null) {
            $financialPerf = $sRent;
        }

        $code = (string) ($classement['code'] ?? '');
        if ($code === 'financable') {
            $financialRisk = max(0.0, $financialRisk - 12.0);
        } elseif (in_array($code, ['non_retenu', 'insuffisant'], true)) {
            $financialRisk = min(100.0, $financialRisk + 10.0);
        }

        if ($finEntries >= 8 && $financialPerf !== null) {
            $wOp = 0.48;
            $wFin = 0.52;
            $risk = round($wOp * $riskOperational + $wFin * $financialRisk, 1);
            $perf = round($wOp * $performanceOperational + $wFin * $financialPerf, 1);
        } elseif ($finEntries >= 3 && $financialPerf !== null) {
            $risk = round(0.62 * $riskOperational + 0.38 * $financialRisk, 1);
            $perf = round(0.58 * $performanceOperational + 0.42 * $financialPerf, 1);
        } elseif ($finEntries >= 1 && $sSolv !== null) {
            $risk = round(0.72 * $riskOperational + 0.28 * $financialRisk, 1);
            if ($financialPerf !== null) {
                $perf = round(0.68 * $performanceOperational + 0.32 * $financialPerf, 1);
            }
        }

        $risk = max(0.0, min(100.0, $risk));
        $perf = max(0.0, min(100.0, $perf));

        return [$risk, $perf];
    }

    /**
     * Extrait un instantané lisible pour la fiche profil et le stockage JSON.
     *
     * @param  array<string, mixed>  $financialAnalysis
     * @return array<string, mixed>
     */
    private function buildFinancialSnapshot(array $financialAnalysis): array
    {
        $scores = $financialAnalysis['scores'] ?? [];
        $classement = $financialAnalysis['classement'] ?? [];
        $ratios = $financialAnalysis['ratios'] ?? [];
        $base = $financialAnalysis['base'] ?? [];

        return [
            'entries_count' => (int) ($financialAnalysis['entries_count'] ?? 0),
            'classement' => [
                'code' => $classement['code'] ?? null,
                'libelle' => $classement['libelle'] ?? null,
                'solvable' => $classement['solvable'] ?? null,
                'financable' => $classement['financable'] ?? null,
            ],
            'scores' => [
                'solvabilite' => $scores['solvabilite']['valeur'] ?? null,
                'rentabilite' => $scores['rentabilite']['valeur'] ?? null,
                'global_fiabilise' => $scores['global']['valeur_fiabilisee'] ?? null,
                'fiabilite_donnees_pct' => $scores['fiabilite_donnees_pct'] ?? null,
            ],
            'ratios' => [
                'roa_pct' => $ratios['roa_pct'] ?? null,
                'roe_pct' => $ratios['roe_pct'] ?? null,
                'marge_nette_pct' => $ratios['marge_nette_pct'] ?? null,
                'endettement_sur_actif_pct' => $ratios['endettement_sur_actif_pct'] ?? null,
                'liquidite_generale' => $ratios['liquidite_generale'] ?? null,
            ],
            'base' => [
                'chiffre_affaires_ht' => $base['chiffre_affaires_ht'] ?? null,
                'resultat_net' => $base['resultat_net'] ?? null,
                'capitaux_propres_estimes' => $base['capitaux_propres_estimes'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $classement
     * @return array{label: string, detail: string, code: string}
     */
    private function resolveInvestorProfile(float $risk, float $performance, array $classement): array
    {
        $codeFin = (string) ($classement['code'] ?? '');
        $financable = ($classement['financable'] ?? false) === true;

        if ($risk < 38 && $performance >= 72 && ($financable || $codeFin === 'solvable_seulement')) {
            return [
                'label' => 'Profil premium',
                'detail' => 'Liquidité, prévisions et lecture financière alignées : base solide pour des échanges de due diligence avec investisseurs.',
                'code' => 'premium',
            ];
        }

        if ($risk < 38 && $performance >= 72) {
            return [
                'label' => 'Profil premium (opérationnel)',
                'detail' => 'Trésorerie et discipline de pilotage très favorables ; consolidez les états financiers pour sécuriser la lecture « investisseur ».',
                'code' => 'premium_operational',
            ];
        }

        if ($risk < 52 && $performance >= 58) {
            return [
                'label' => 'Profil équilibré',
                'detail' => 'Risques modérés et performance crédible : dossier cohérent pour une levée structurée, sous réserve de pièces justificatives.',
                'code' => 'balanced',
            ];
        }

        if ($risk > 68 || $codeFin === 'non_retenu') {
            return [
                'label' => 'Profil à renforcer',
                'detail' => 'Les signaux de risque ou la structure financière retenue par l’analyse automatique invitent à sécuriser trésorerie, bilan et documentation avant pitch.',
                'code' => 'strengthening',
            ];
        }

        return [
            'label' => 'Profil en structuration',
            'detail' => 'Potentiel intéressant mais indicateurs encore hétérogènes : compléter le suivi comptable, les ratios et la documentation investisseur.',
            'code' => 'structuring',
        ];
    }

    private function computeSoldeActuel(int $userId): float
    {
        $enc = (float) TreasuryTransaction::where('user_id', $userId)
            ->where('status', 'effectue')
            ->where('type', 'encaissement')
            ->sum('amount');
        $dec = (float) TreasuryTransaction::where('user_id', $userId)
            ->where('status', 'effectue')
            ->where('type', 'decaissement')
            ->sum('amount');

        return $enc - $dec;
    }

    /**
     * @return array{encaissements: float, decaissements: float}
     */
    private function computePlannedNet(int $userId): array
    {
        $enc = (float) TreasuryTransaction::where('user_id', $userId)
            ->where('status', 'planifie')
            ->where('type', 'encaissement')
            ->sum('amount');
        $dec = (float) TreasuryTransaction::where('user_id', $userId)
            ->where('status', 'planifie')
            ->where('type', 'decaissement')
            ->sum('amount');

        return ['encaissements' => $enc, 'decaissements' => $dec];
    }

    /**
     * Fiabilité des prévisions (0–100), même logique que le contrôleur trésorerie.
     *
     * @return array{score: float, alertWeeks: int}
     */
    private function computeForecastReliability(int $userId, Carbon $endDate, int $weeks): array
    {
        $totalAbsoluteError = 0.0;
        $totalPlanned = 0.0;
        $alertWeeks = 0;

        $cursor = $endDate->copy()->startOfWeek()->subWeeks($weeks - 1);
        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $cursor->copy()->startOfWeek();
            $weekEnd = $cursor->copy()->endOfWeek();

            $plannedRows = TreasuryTransaction::where('user_id', $userId)
                ->planifiees()
                ->byDateRange($weekStart, $weekEnd)
                ->get(['type', 'amount']);

            $actualRows = TreasuryTransaction::where('user_id', $userId)
                ->effectuees()
                ->byDateRange($weekStart, $weekEnd)
                ->get(['type', 'amount']);

            $plannedNet = (float) $plannedRows
                ->sum(fn ($tx) => $tx->type === 'encaissement' ? (float) $tx->amount : -(float) $tx->amount);
            $actualNet = (float) $actualRows
                ->sum(fn ($tx) => $tx->type === 'encaissement' ? (float) $tx->amount : -(float) $tx->amount);

            $gap = $actualNet - $plannedNet;
            $absError = abs($gap);
            $denominator = max(1.0, abs($plannedNet));
            $errorPct = $plannedNet == 0.0 ? null : round(($absError / $denominator) * 100, 1);

            if ($errorPct !== null && $errorPct > 30) {
                $alertWeeks++;
            }

            $totalAbsoluteError += $absError;
            $totalPlanned += abs($plannedNet);

            $cursor->addWeek();
        }

        $mape = $totalPlanned > 0 ? ($totalAbsoluteError / $totalPlanned) * 100 : 0.0;
        $score = max(0.0, min(100.0, round(100 - $mape, 1)));

        return [
            'score' => $score,
            'alertWeeks' => $alertWeeks,
        ];
    }

    private function liquidityRiskScore(float $soldeActuel, float $soldeProjete): float
    {
        if ($soldeProjete < 0) {
            return 100.0;
        }
        if ($soldeActuel < 0) {
            return 92.0;
        }
        if ($soldeActuel > 0 && $soldeProjete < $soldeActuel * 0.25) {
            return 68.0;
        }
        if ($soldeProjete < $soldeActuel * 0.6) {
            return 48.0;
        }

        return 22.0;
    }

    private function treasuryHealthScore(float $soldeActuel, float $soldeProjete): float
    {
        if ($soldeActuel < 0) {
            return 25.0;
        }
        if ($soldeProjete < 0) {
            return 30.0;
        }
        if ($soldeProjete >= $soldeActuel) {
            return 88.0;
        }
        if ($soldeProjete >= $soldeActuel * 0.85) {
            return 72.0;
        }

        return 55.0;
    }

    private function activityScore(int $userId, Carbon $today): float
    {
        $from = $today->copy()->subDays(90);
        $count = AccountingEntry::where('user_id', $userId)
            ->whereDate('date', '>=', $from->toDateString())
            ->count();

        return max(0.0, min(100.0, $count * 4.0));
    }
}
