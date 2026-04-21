<?php

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/bootstrap/app.php';

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Teste la création d'un utilisateur de test
$user = User::create([
    'name' => 'Test Entreprise',
    'email' => 'test@sitiam.ci',
    'password' => Hash::make('TestPassword123'),
    'company_name' => 'Sitiam Test SARL',
    'sector' => 'Technologies & IT',
    'rccm' => 'CI-DKN-2024-12345',
    'address' => '123 Rue de Treichville',
    'city' => 'Abidjan',
    'phone' => '+225 01 44 55 66 77',
]);

echo "✅ Utilisateur créé avec succès !\n";
echo "ID: " . $user->id . "\n";
echo "Email: " . $user->email . "\n";
echo "Entreprise: " . $user->company_name . "\n";
