<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Accorde ou retire le droit administrateur plateforme (super-admin Sitiame).
 */
class PlatformGrantAdminCommand extends Command
{
    protected $signature = 'platform:grant-admin
                            {email : Adresse e-mail du compte utilisateur}
                            {--revoke : Retirer le droit administrateur}';

    protected $description = 'Accorde ou révoque le statut administrateur plateforme pour un utilisateur';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("Aucun utilisateur avec l’e-mail : {$email}");

            return self::FAILURE;
        }

        if ($this->option('revoke')) {
            $user->forceFill(['is_platform_admin' => false])->save();
            $this->info("Droit administrateur retiré pour : {$email}");

            return self::SUCCESS;
        }

        $user->forceFill([
            'is_platform_admin' => true,
            'is_accountant' => false,
        ])->save();
        $this->info("Droit administrateur accordé pour : {$email}");

        return self::SUCCESS;
    }
}
