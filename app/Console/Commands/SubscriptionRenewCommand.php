<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserPremiumService;
use Illuminate\Console\Command;

/**
 * Renouvelle (ou active) l'abonnement Premium d'un compte entreprise comme s'il
 * s'agissait d'un vrai paiement CinetPay — utile pour rétablir/tester un compte
 * sans passer par le flux de paiement réel. Source enregistrée : cinetpay_simulate,
 * donc comptée normalement dans les commissions de renouvellement des commerciaux.
 */
class SubscriptionRenewCommand extends Command
{
    protected $signature = 'subscription:renew
                            {email : Adresse e-mail du compte à renouveler}
                            {--days=30 : Nombre de jours ajoutés à l’abonnement}
                            {--amount=15000 : Montant du paiement enregistré dans l’historique (FCFA)}';

    protected $description = "Renouvelle l'abonnement Premium d'un compte entreprise comme un vrai paiement";

    public function handle(UserPremiumService $userPremium): int
    {
        $email = (string) $this->argument('email');
        $days = (int) $this->option('days');
        $amount = (int) $this->option('amount');

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("Aucun utilisateur avec l'e-mail : {$email}");

            return self::FAILURE;
        }

        if (! $userPremium->canManageEnterprisePremium($user)) {
            $this->error('Ce compte n’est pas un compte entreprise (administrateur ou comptable) — pas d’abonnement à renouveler.');

            return self::FAILURE;
        }

        $wasPremiumActive = $user->is_premium && $user->premium_ends_at?->isFuture();

        $userPremium->activateForDays(
            user: $user,
            days: $days,
            source: 'cinetpay_simulate',
            note: "Renouvellement d'abonnement ({$days} jours, script manuel).",
            meta: ['amount' => $amount, 'manual_renewal' => true],
        );

        $refreshed = $user->fresh();

        $this->info(($wasPremiumActive ? 'Abonnement prolongé' : 'Abonnement renouvelé').' pour : '.$email);
        $this->info('Nouvelle échéance : '.$refreshed->premium_ends_at->format('d/m/Y H:i'));

        return self::SUCCESS;
    }
}
