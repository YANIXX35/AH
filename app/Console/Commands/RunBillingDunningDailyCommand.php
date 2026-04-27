<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Illuminate\Console\Command;

class RunBillingDunningDailyCommand extends Command
{
    protected $signature = 'app:billing-dunning-daily';

    protected $description = 'Genere les factures dues et execute les relances automatiques de paiement.';

    public function handle(BillingService $billingService): int
    {
        $result = $billingService->runDailyDunning();
        $this->info('Traitement billing termine.');
        $this->line('Factures creees: '.($result['created_invoices'] ?? 0));
        $this->line('Factures en retard: '.($result['marked_overdue'] ?? 0));
        $this->line('Tentatives de retry: '.($result['payment_retries'] ?? 0));
        $this->line('Notifications envoyees: '.($result['notifications_sent'] ?? 0));
        $this->line('Comptes suspendus: '.($result['suspended_users'] ?? 0));

        return self::SUCCESS;
    }
}
