<?php

namespace App\Console\Commands;

use App\Models\SubscriptionHistory;
use App\Models\User;
use Illuminate\Console\Command;

class ExpirePremiumSubscriptionsCommand extends Command
{
    protected $signature = 'app:premium-expire';

    protected $description = 'Repasse en gratuit les abonnements Premium expirés';

    public function handle(): int
    {
        // Les administrateurs plateforme ne sont pas concernés par l’expiration Gratuit / Premium.
        $expiredUsers = User::query()
            ->where('is_platform_admin', false)
            ->where('is_premium', true)
            ->whereNotNull('premium_ends_at')
            ->where('premium_ends_at', '<=', now())
            ->get();

        foreach ($expiredUsers as $user) {
            $fromStatus = $user->premium_status ?? 'active';
            $previousEndsAt = $user->premium_ends_at;

            $suspendPayload = [
                'account_suspended' => true,
                'suspended_at' => now(),
                'suspended_reason' => 'Échéance de l\'abonnement Premium — renouvellement ou régularisation requis.',
                'auto_suspended_for_payment' => true,
            ];

            $user->update(array_merge([
                'is_premium' => false,
                'premium_status' => 'free',
                'premium_trial_ends_at' => null,
                'premium_ends_at' => null,
            ], $suspendPayload));

            SubscriptionHistory::create([
                'user_id' => $user->id,
                'from_status' => $fromStatus,
                'to_status' => 'free',
                'is_premium' => false,
                'starts_at' => now(),
                'ends_at' => null,
                'source' => 'scheduler_expire',
                'note' => 'Expiration automatique de l\'abonnement Premium.'.(isset($suspendPayload['account_suspended']) ? ' Compte suspendu (non-paiement / échéance).' : ''),
                'meta' => [
                    'previous_premium_ends_at' => optional($previousEndsAt)?->toDateTimeString(),
                    'account_suspended' => (bool) ($suspendPayload['account_suspended'] ?? false),
                ],
            ]);
        }

        $expiredCount = $expiredUsers->count();
        $this->info("Comptes repassés en gratuit: {$expiredCount}");

        return self::SUCCESS;
    }
}
