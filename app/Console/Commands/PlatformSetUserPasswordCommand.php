<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Définit le mot de passe d’un compte utilisateur existant (ligne de commande).
 */
class PlatformSetUserPasswordCommand extends Command
{
    protected $signature = 'platform:set-password
                            {email : Adresse e-mail du compte}
                            {--password= : Nouveau mot de passe (sinon saisie masquée)}';

    protected $description = 'Modifie le mot de passe d’un utilisateur existant';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("Aucun utilisateur avec l’e-mail : {$email}");

            return self::FAILURE;
        }

        $password = $this->option('password');
        if ($password === null || $password === '') {
            $password = $this->secret('Nouveau mot de passe');
            $confirm = $this->secret('Confirmer le mot de passe');
            if ($password !== $confirm) {
                $this->error('Les mots de passe ne correspondent pas.');

                return self::FAILURE;
            }
        }

        if (strlen($password) < 8) {
            $this->error('Le mot de passe doit contenir au moins 8 caractères.');

            return self::FAILURE;
        }

        $user->forceFill([
            'password' => Hash::make($password),
        ])->save();

        $this->info("Mot de passe mis à jour pour : {$email}");

        return self::SUCCESS;
    }
}
