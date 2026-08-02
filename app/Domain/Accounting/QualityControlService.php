<?php

namespace App\Domain\Accounting;

use App\Models\AccountingQualityReview;
use App\Services\SmeFinancialRatioService;
use Illuminate\Support\Carbon;

/**
 * Gate de contrôle qualité périodique (PRD 4.2) : empêche qu'une période non
 * revue soit présentée comme fiable pour le scoring/financement, sans imposer
 * de méthode de contrôle définitive — celle-ci reste "à définir avec la
 * Comptabilité". Le statut par période, une fois enregistré, est figé (voir
 * `method_version` sur AccountingQualityReview) : un changement de méthode ne
 * réinterprète jamais rétroactivement une revue déjà faite.
 */
class QualityControlService
{
    public function __construct(private readonly SmeFinancialRatioService $ratioService) {}

    /**
     * Période "courante" selon la cadence configurée (trimestrielle par défaut).
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function currentPeriod(): array
    {
        $now = now();

        return match (config('quality_review.period', 'quarter')) {
            'quarter' => ['start' => $now->copy()->startOfQuarter(), 'end' => $now->copy()->endOfQuarter()],
            default => ['start' => $now->copy()->startOfQuarter(), 'end' => $now->copy()->endOfQuarter()],
        };
    }

    public function isQualityCheckedForPeriod(int $smeUserId, Carbon $periodStart, Carbon $periodEnd): bool
    {
        return AccountingQualityReview::query()
            ->where('user_id', $smeUserId)
            ->whereDate('period_start', $periodStart->toDateString())
            ->whereDate('period_end', $periodEnd->toDateString())
            ->where('status', 'validated')
            ->exists();
    }

    public function findForPeriod(int $smeUserId, Carbon $periodStart, Carbon $periodEnd): ?AccountingQualityReview
    {
        return AccountingQualityReview::query()
            ->where('user_id', $smeUserId)
            ->whereDate('period_start', $periodStart->toDateString())
            ->whereDate('period_end', $periodEnd->toDateString())
            ->first();
    }

    /**
     * Suggestion non engageante basée sur l'indice de fiabilité des données déjà
     * calculé par SmeFinancialRatioService::analyze() — ne duplique pas sa
     * logique, se contente de lire `classement.fiabilite_donnees_pct`. Un humain
     * (comptable/admin) reste seul décisionnaire via markPeriodReviewed().
     *
     * @return array{reliability_pct: float, suggested_status: string}
     */
    public function computeSuggestedStatus(int $smeUserId, Carbon $periodStart, Carbon $periodEnd): array
    {
        $analysis = $this->ratioService->analyze($smeUserId, $periodStart, $periodEnd);
        $reliabilityPct = (float) data_get($analysis, 'classement.fiabilite_donnees_pct', 0.0);
        $threshold = (float) config('quality_review.auto_suggest_threshold', 70.0);

        return [
            'reliability_pct' => $reliabilityPct,
            'suggested_status' => $reliabilityPct >= $threshold ? 'validated' : 'flagged',
        ];
    }

    public function markPeriodReviewed(
        int $smeUserId,
        Carbon $periodStart,
        Carbon $periodEnd,
        string $status,
        ?int $actorUserId,
        ?string $notes = null
    ): AccountingQualityReview {
        if (! in_array($status, ['validated', 'flagged', 'pending'], true)) {
            throw new \InvalidArgumentException("Statut de contrôle qualité invalide : {$status}");
        }

        $suggestion = $this->computeSuggestedStatus($smeUserId, $periodStart, $periodEnd);
        $attributes = [
            'status' => $status,
            'method_version' => config('quality_review.method_version', 'provisional-reliability-v1'),
            'reliability_score_snapshot' => $suggestion['reliability_pct'],
            'reviewed_at' => now(),
            'reviewed_by' => $actorUserId,
            'notes' => $notes,
        ];

        // Recherche via findForPeriod (whereDate, tolérant à la composante horaire) plutôt
        // que le matching implicite d'updateOrCreate() : une comparaison de date en chaîne
        // brute ('2026-07-01' vs '2026-07-01 00:00:00' une fois castée par le modèle) fait
        // rater la ligne existante et provoque un doublon en violation de la contrainte
        // unique — constaté en test avant cette correction.
        $existing = $this->findForPeriod($smeUserId, $periodStart, $periodEnd);
        if ($existing) {
            $existing->update($attributes);

            return $existing->fresh();
        }

        return AccountingQualityReview::create(array_merge($attributes, [
            'user_id' => $smeUserId,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
        ]));
    }
}
