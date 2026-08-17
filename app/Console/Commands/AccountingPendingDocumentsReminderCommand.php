<?php

namespace App\Console\Commands;

use App\Models\AccountingDocument;
use App\Models\AppNotification;
use Illuminate\Console\Command;

/**
 * Relance des pièces en attente de revue manuelle (statut `pending_validation`) —
 * la branche du filtre de qualité (PRD 4.1) qui n'a pas franchi l'auto-validation
 * et attend un comptable, sans délai ni rappel jusqu'ici (constat de l'audit UML).
 */
class AccountingPendingDocumentsReminderCommand extends Command
{
    protected $signature = 'app:accounting-pending-reminder {--days=3 : Ancienneté minimale avant relance} {--cooldown=3 : Jours avant une nouvelle relance sur la même pièce}';

    protected $description = 'Notifie les entreprises ayant des pièces comptables en attente de revue manuelle depuis trop longtemps';

    public function handle(): int
    {
        $staleSince = now()->subDays((int) $this->option('days'));
        $cooldownSince = now()->subDays((int) $this->option('cooldown'));

        $staleDocuments = AccountingDocument::query()
            ->where('status', 'pending_validation')
            ->where('created_at', '<=', $staleSince)
            ->where(function ($q) use ($cooldownSince) {
                $q->whereNull('last_reminder_sent_at')
                    ->orWhere('last_reminder_sent_at', '<=', $cooldownSince);
            })
            ->get();

        $byUser = $staleDocuments->groupBy('user_id');
        $notifiedUsers = 0;

        foreach ($byUser as $userId => $documents) {
            $count = $documents->count();

            AppNotification::create([
                'user_id' => $userId,
                'title' => 'Pièces comptables en attente de revue',
                'body' => $count === 1
                    ? '1 pièce comptable attend une revue manuelle depuis plus de '.$this->option('days').' jours.'
                    : "{$count} pièces comptables attendent une revue manuelle depuis plus de ".$this->option('days').' jours.',
                'type' => 'accounting_docs_reminder',
                'action_url' => route('accounting'),
            ]);

            AccountingDocument::query()
                ->whereIn('id', $documents->pluck('id'))
                ->update(['last_reminder_sent_at' => now()]);

            $notifiedUsers++;
        }

        $this->info("Entreprises relancées : {$notifiedUsers} ({$staleDocuments->count()} pièce(s) au total).");

        return self::SUCCESS;
    }
}
