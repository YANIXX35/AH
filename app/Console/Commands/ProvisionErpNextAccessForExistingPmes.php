<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ErpNextClient;
use Illuminate\Console\Command;

/**
 * One-off backfill: every PME registered before the automatic ERPNext
 * account creation existed already has a Company/Warehouse/Tax template
 * (erpnext_company_name set) but no ERPNext login. This replays the same
 * provisioning call, which now also creates the User/User Permission --
 * it never touches an account that already exists (staff or PME), and
 * never resets a password, since createPmeErpNextUser() only inserts a
 * new User when none exists yet for that email.
 */
class ProvisionErpNextAccessForExistingPmes extends Command
{
    protected $signature = 'pme:provision-erpnext-access';

    protected $description = 'Crée un compte ERPNext pour chaque PME déjà provisionnée qui n\'en a pas encore un';

    public function handle(ErpNextClient $erpNext): int
    {
        if (! $erpNext->enabled()) {
            $this->error('ERPNext non configuré.');

            return self::FAILURE;
        }

        $pmes = User::whereNotNull('erpnext_company_name')->get();
        $this->info("{$pmes->count()} PME(s) déjà provisionnée(s) à traiter.");

        $ok = 0;
        $failed = 0;

        foreach ($pmes as $pme) {
            try {
                $erpNext->provisionCompanyForPme($pme);
                $ok++;
                $this->line("OK: {$pme->email} ({$pme->erpnext_company_name})");
            } catch (\Throwable $exception) {
                $failed++;
                $this->warn("Échec: {$pme->email}: {$exception->getMessage()}");
            }
        }

        $this->info("Terminé : {$ok} succès, {$failed} échec(s).");

        return self::SUCCESS;
    }
}
