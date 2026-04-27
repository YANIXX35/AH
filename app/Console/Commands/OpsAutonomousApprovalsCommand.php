<?php

namespace App\Console\Commands;

use App\Models\AdminApprovalRequest;
use App\Models\AppNotification;
use App\Models\MenuActionLog;
use App\Models\PaymentTransaction;
use App\Models\PlatformSetting;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Console\Command;

class OpsAutonomousApprovalsCommand extends Command
{
    protected $signature = 'app:ops-ai-autonomous-approvals';

    protected $description = "Ouvre automatiquement des demandes d'approbation IA pour actions sensibles";

    public function handle(): int
    {
        $config = PlatformSetting::query()->firstOrCreate(
            ['key' => 'ops_ai_autonomous_approvals'],
            ['value' => [
                'enabled' => false,
                'thresholds' => [
                    'blocked_tickets_48h' => 3,
                    'expiring_premium_7d' => 5,
                    'payment_success_rate_pct' => 90,
                    'revenue_growth_pct' => 0,
                ],
            ]]
        );

        $value = (array) ($config->value ?? []);
        if (! ((bool) ($value['enabled'] ?? false))) {
            $this->info("Mode autonome IA désactivé.");
            return self::SUCCESS;
        }

        $thresholds = (array) ($value['thresholds'] ?? []);
        $blockedTicketsThreshold = (int) ($thresholds['blocked_tickets_48h'] ?? 3);
        $expiringPremiumThreshold = (int) ($thresholds['expiring_premium_7d'] ?? 5);
        $paymentSuccessThreshold = (float) ($thresholds['payment_success_rate_pct'] ?? 90);
        $revenueGrowthThreshold = (float) ($thresholds['revenue_growth_pct'] ?? 0);

        $requesterAdmin = User::query()
            ->where('is_platform_admin', true)
            ->orderBy('id')
            ->first();

        if (! $requesterAdmin) {
            $this->warn('Aucun administrateur plateforme disponible pour porter la demande.');
            return self::SUCCESS;
        }

        $alerts = $this->detectSensitiveCandidates(
            $blockedTicketsThreshold,
            $expiringPremiumThreshold,
            $paymentSuccessThreshold,
            $revenueGrowthThreshold
        );

        $created = 0;
        foreach ($alerts as $candidate) {
            if ($this->alreadyPending($candidate['action_key'])) {
                continue;
            }

            AdminApprovalRequest::query()->create([
                'action_key' => $candidate['action_key'],
                'target_type' => 'ops.ai.autonomous',
                'target_id' => null,
                'payload' => $candidate['payload'],
                'status' => 'pending',
                'requested_by_user_id' => $requesterAdmin->id,
            ]);

            $created++;
        }

        if ($created > 0) {
            $this->notifyAdmins($created);
        }

        $this->info("Demandes d'approbation créées: {$created}.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{action_key: string, payload: array<string, mixed>}>
     */
    private function detectSensitiveCandidates(
        int $blockedTicketsThreshold,
        int $expiringPremiumThreshold,
        float $paymentSuccessThreshold,
        float $revenueGrowthThreshold
    ): array {
        $now = now();
        $candidates = [];

        $blockedTickets = SupportTicket::query()
            ->whereIn('status', [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_IN_PROGRESS])
            ->where('updated_at', '<=', $now->copy()->subHours(48))
            ->count();
        if ($blockedTickets >= $blockedTicketsThreshold) {
            $candidates[] = [
                'action_key' => 'ai.sensitive.support_escalation_plan',
                'payload' => [
                    'reason' => 'Tickets bloqués au-delà du seuil autorisé',
                    'metric' => ['blocked_tickets_48h' => $blockedTickets],
                    'recommended_action' => 'Valider un plan de remédiation support prioritaire',
                ],
            ];
        }

        $expiringPremium = User::query()
            ->clients()
            ->where('is_premium', true)
            ->whereNotNull('premium_ends_at')
            ->where('premium_ends_at', '>', $now)
            ->where('premium_ends_at', '<=', $now->copy()->addDays(7))
            ->count();
        if ($expiringPremium >= $expiringPremiumThreshold) {
            $candidates[] = [
                'action_key' => 'ai.sensitive.premium_retention_campaign',
                'payload' => [
                    'reason' => 'Risque de churn premium au-dessus du seuil autorisé',
                    'metric' => ['expiring_premium_7d' => $expiringPremium],
                    'recommended_action' => 'Autoriser une campagne de rétention premium ciblée',
                ],
            ];
        }

        $successfulStatuses = ['COMPLETED', 'ACCEPTED', 'SUBMITTED', 'effectue'];
        $payments30 = PaymentTransaction::query()->where('created_at', '>=', $now->copy()->subDays(30));
        $totalPayments30 = (int) (clone $payments30)->count();
        $successPayments30 = (int) (clone $payments30)->whereIn('status', $successfulStatuses)->count();
        $paymentSuccessRate = $totalPayments30 > 0 ? round(($successPayments30 / $totalPayments30) * 100, 2) : 100.0;
        if ($paymentSuccessRate < $paymentSuccessThreshold) {
            $candidates[] = [
                'action_key' => 'ai.sensitive.payment_fallback_activation',
                'payload' => [
                    'reason' => 'Taux de succès paiement inférieur au seuil autorisé',
                    'metric' => ['payment_success_rate_pct' => $paymentSuccessRate],
                    'recommended_action' => 'Valider une intervention de sécurisation du tunnel paiement',
                ],
            ];
        }

        $currentRevenue = (float) (clone $payments30)->whereIn('status', $successfulStatuses)->sum('amount');
        $previousRevenue = (float) PaymentTransaction::query()
            ->where('created_at', '>=', $now->copy()->subDays(60))
            ->where('created_at', '<', $now->copy()->subDays(30))
            ->whereIn('status', $successfulStatuses)
            ->sum('amount');
        $growth = $this->percentChange($currentRevenue, $previousRevenue);
        if ($growth < $revenueGrowthThreshold) {
            $candidates[] = [
                'action_key' => 'ai.sensitive.growth_recovery_plan',
                'payload' => [
                    'reason' => 'Croissance du chiffre d’affaires sous le seuil autorisé',
                    'metric' => ['revenue_growth_pct' => $growth],
                    'recommended_action' => 'Valider un plan de relance croissance 30/60/90 jours',
                ],
            ];
        }

        $errors5xx = MenuActionLog::query()
            ->where('status_code', '>=', 500)
            ->where('created_at', '>=', $now->copy()->subHour())
            ->count();
        if ($errors5xx >= 8) {
            $candidates[] = [
                'action_key' => 'ai.sensitive.stability_emergency_plan',
                'payload' => [
                    'reason' => 'Erreurs 5xx critiques détectées',
                    'metric' => ['http_5xx_last_hour' => $errors5xx],
                    'recommended_action' => "Autoriser un plan d'urgence de stabilisation technique",
                ],
            ];
        }

        return $candidates;
    }

    private function alreadyPending(string $actionKey): bool
    {
        return AdminApprovalRequest::query()
            ->where('status', 'pending')
            ->where('action_key', $actionKey)
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();
    }

    private function notifyAdmins(int $count): void
    {
        $admins = User::query()->where('is_platform_admin', true)->get(['id']);
        foreach ($admins as $admin) {
            AppNotification::query()->create([
                'user_id' => $admin->id,
                'title' => "Demandes d'approbation IA générées",
                'body' => "{$count} demande(s) d'approbation sensible en attente de validation admin.",
                'type' => 'warning',
                'action_url' => route('admin.ops.index'),
            ]);
        }
    }

    private function percentChange(float $current, float $previous): float
    {
        if (abs($previous) < 0.00001) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }
}

