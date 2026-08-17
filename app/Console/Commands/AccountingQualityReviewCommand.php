<?php

namespace App\Console\Commands;

use App\Models\AccountingEntry;
use App\Models\AppNotification;
use App\Models\User;
use App\Services\AccountingQualityReviewService;
use Illuminate\Console\Command;

/**
 * Contrôle qualité périodique des données comptables (PRD 4.2) : revérifie,
 * entreprise par entreprise, tous les mouvements déjà validés — à la
 * différence du filtre de qualité à l'import (PRD 4.1), qui ne s'exécute
 * qu'une fois, au moment où la pièce arrive.
 */
class AccountingQualityReviewCommand extends Command
{
    protected $signature = 'app:accounting-quality-review {--force : Revérifier même les entreprises pas encore dues}';

    protected $description = 'Revérifie périodiquement (cadence trimestrielle) la conformité des écritures comptables déjà validées';

    public function handle(AccountingQualityReviewService $reviewService): int
    {
        $force = (bool) $this->option('force');
        $dueBefore = now()->subMonths(3);

        $workspaceUserIds = AccountingEntry::query()
            ->select('user_id')
            ->distinct()
            ->pluck('user_id');

        $usersQuery = User::query()->clients()->whereIn('id', $workspaceUserIds);
        if (! $force) {
            $usersQuery->where(function ($q) use ($dueBefore) {
                $q->whereNull('accounting_quality_reviewed_at')
                    ->orWhere('accounting_quality_reviewed_at', '<=', $dueBefore);
            });
        }

        $users = $usersQuery->get();
        $reviewedCompanies = 0;
        $flaggedCompanies = 0;

        foreach ($users as $user) {
            $entries = AccountingEntry::query()->where('user_id', $user->id)->get();
            $nonCompliant = 0;

            foreach ($entries as $entry) {
                $result = $reviewService->reviewAndPersist($entry);
                if ($result['status'] === 'non_compliant') {
                    $nonCompliant++;
                }
            }

            $user->forceFill(['accounting_quality_reviewed_at' => now()])->save();
            $reviewedCompanies++;

            if ($nonCompliant > 0) {
                $flaggedCompanies++;
                AppNotification::create([
                    'user_id' => $user->id,
                    'title' => 'Contrôle qualité comptable trimestriel',
                    'body' => $nonCompliant === 1
                        ? '1 écriture comptable ne satisfait plus les critères de conformité (référence, montant ou identification du tiers manquants). Une correction est recommandée.'
                        : "{$nonCompliant} écritures comptables ne satisfont plus les critères de conformité (référence, montant ou identification du tiers manquants). Une correction est recommandée.",
                    'type' => 'accounting_quality_review',
                    'action_url' => route('accounting'),
                ]);
            }
        }

        $this->info("Entreprises revérifiées : {$reviewedCompanies} (dont {$flaggedCompanies} avec écritures non conformes).");

        return self::SUCCESS;
    }
}
