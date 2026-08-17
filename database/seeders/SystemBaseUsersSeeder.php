<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SystemBaseUsersSeeder extends Seeder
{
    public function run(): void
    {
        $records = [
            [
                'name' => 'Admin Plateforme',
                'email' => 'admin@sitiame.local',
                'password' => 'AdminSitiame2026!',
                'is_platform_admin' => true,
                'is_accountant' => false,
                'company_name' => 'Sitiame Capital',
                'premium_status' => 'free',
                'is_premium' => false,
            ],
            [
                'name' => 'Entreprise Premium',
                'email' => 'entreprise@sitiame.local',
                'password' => 'Entreprise2026!',
                'is_platform_admin' => false,
                'is_accountant' => false,
                'company_name' => 'Entreprise Demo SARL',
                'premium_status' => 'active',
                'is_premium' => true,
            ],
            [
                'name' => 'Entreprise Standard',
                'email' => 'client@sitiame.local',
                'password' => 'ClientSitiame2026!',
                'is_platform_admin' => false,
                'is_accountant' => false,
                'company_name' => 'Client Demo SARL',
                'premium_status' => 'free',
                'is_premium' => false,
            ],
            [
                'name' => 'Comptable Cabinet',
                'email' => 'comptable@sitiame.local',
                'password' => 'Comptable2026!',
                'is_platform_admin' => false,
                'is_accountant' => true,
                'company_name' => 'Cabinet Demo',
                'premium_status' => 'free',
                'is_premium' => false,
            ],
        ];

        foreach ($records as $record) {
            $plainPassword = $record['password'];
            unset($record['password']);

            User::query()->updateOrCreate(
                ['email' => $record['email']],
                [
                    ...$record,
                    'email_verified_at' => now(),
                    'password' => Hash::make($plainPassword),
                    'timezone' => 'Africa/Abidjan',
                    'locale' => 'fr',
                    'currency' => 'XOF',
                    'email_notifications' => true,
                    'weekly_digest' => false,
                    'marketing_emails' => false,
                    'two_factor_enabled' => false,
                    'premium_ends_at' => $record['is_premium'] ? now()->addDays(30) : null,
                ]
            );
        }
    }
}
