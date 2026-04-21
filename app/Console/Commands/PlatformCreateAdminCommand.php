<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Crée un utilisateur administrateur plateforme (ou promeut un compte existant).
 */
class PlatformCreateAdminCommand extends Command
{
    protected $signature = 'platform:create-admin
                            {email : Adresse e-mail du compte administrateur}
                            {--name=Administrateur plateforme : Nom affiché}
                            {--password= : Mot de passe (sinon généré et affiché)}';

    protected $description = 'Crée un compte administrateur plateforme ou accorde le rôle si l’e-mail existe déjà';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $name = (string) $this->option('name');

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            if ($existing->is_platform_admin) {
                $this->warn("Le compte {$email} existe déjà et est déjà administrateur plateforme.");

                return self::SUCCESS;
            }

            if (! $this->confirm("Le compte {$email} existe déjà. Lui accorder le rôle administrateur plateforme ?", true)) {
                return self::FAILURE;
            }

            $existing->forceFill([
                'is_platform_admin' => true,
                'is_accountant' => false,
            ])->save();
            $this->info("Rôle administrateur accordé pour le compte existant : {$email}");

            return self::SUCCESS;
        }

        $password = $this->option('password');
        if ($password === null || $password === '') {
            $password = Str::password(16);
            $this->line('Mot de passe généré (conservez-le précieusement) :');
            $this->line('<fg=green>'.$password.'</>');
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'is_platform_admin' => true,
            'is_accountant' => false,
        ]);

        $this->info("Compte administrateur créé : {$email}");
        $this->line('Vous pouvez vous connecter sur la page Connexion de l’application.');

        return self::SUCCESS;
    }
}
