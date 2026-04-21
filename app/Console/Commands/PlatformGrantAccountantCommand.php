<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;


class PlatformGrantAccountantCommand extends Command
{
    protected $signature = 'platform:grant-accountant {email : Adresse e-mail du compte utilisateur}';

    protected $description = 'Accorde le rôle « comptable cabinet » (accès /accountant) à un utilisateur existant.';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("Aucun utilisateur avec l’e-mail : {$email}");

            return self::FAILURE;
        }

        if ($user->is_accountant) {
            $this->info('Ce compte a déjà le rôle comptable cabinet.');

            return self::SUCCESS;
        }

        $user->forceFill([
            'is_accountant' => true,
            'is_platform_admin' => false,
        ])->save();
        $this->info("Rôle comptable accordé à : {$user->email}");

        return self::SUCCESS;
    }
}
