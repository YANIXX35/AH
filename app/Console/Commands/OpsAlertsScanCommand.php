<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\MenuActionLog;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Console\Command;

class OpsAlertsScanCommand extends Command
{
    protected $signature = 'app:ops-alerts-scan';

    protected $description = 'Détecte alertes ops et notifie les administrateurs plateforme';

    public function handle(): int
    {
        $admins = User::query()->where('is_platform_admin', true)->get(['id']);
        if ($admins->isEmpty()) {
            $this->info('Aucun admin plateforme.');

            return self::SUCCESS;
        }

        $alerts = [];

        $errors5xx = MenuActionLog::query()
            ->where('status_code', '>=', 500)
            ->where('created_at', '>=', now()->subHour())
            ->count();
        if ($errors5xx >= 5) {
            $alerts[] = [
                'title' => 'Alerte HTTP 5xx',
                'body' => $errors5xx.' erreurs 5xx sur la dernière heure (escalade automatique).',
                'type' => 'error',
                'action_url' => route('admin.logs.index', ['module' => 'menu']),
            ];
        }

        $blockedTickets = SupportTicket::query()
            ->whereIn('status', [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_IN_PROGRESS])
            ->where('updated_at', '<=', now()->subHours(48))
            ->count();
        if ($blockedTickets >= 2) {
            $alerts[] = [
                'title' => 'Tickets bloqués',
                'body' => $blockedTickets.' ticket(s) non mis à jour depuis plus de 48h.',
                'type' => 'warning',
                'action_url' => route('support.tickets'),
            ];
        }

        foreach ($alerts as $alert) {
            foreach ($admins as $admin) {
                $already = AppNotification::query()
                    ->where('user_id', $admin->id)
                    ->where('title', $alert['title'])
                    ->where('created_at', '>=', now()->subHours(6))
                    ->exists();
                if ($already) {
                    continue;
                }
                AppNotification::query()->create(array_merge($alert, ['user_id' => $admin->id]));
            }
        }

        $this->info('Scan terminé: '.count($alerts).' alerte(s).');

        return self::SUCCESS;
    }
}
