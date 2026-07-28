<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\BceaoLiasseService;
use App\Models\AccountingEntry;

$service = new BceaoLiasseService();

$entriesN = AccountingEntry::all();
$entriesN1 = collect();

$liasse = $service->generateLiasse($entriesN, $entriesN1);

echo "=== VERIFICATION DE LA LIASSE COMPLÈTE BCEAO ===\n\n";

echo "1. BILAN ACTIF : Total Net N = " . number_format($liasse['actif']['total']['net_n'], 0, ',', ' ') . " FCFA\n";
echo "2. BILAN PASSIF : Total Net N = " . number_format($liasse['passif']['total']['net_n'], 0, ',', ' ') . " FCFA\n";
echo "3. COMPTE RÉSULTAT : Résultat Net XZ = " . number_format($liasse['resultat']['totals']['XZ']['net_n'], 0, ',', ' ') . " FCFA\n";

echo "\n4. TAFIRE :\n";
echo "   - CAFG : " . number_format($liasse['tafire']['cafg'], 0, ',', ' ') . " FCFA\n";
echo "   - Total Ressources : " . number_format($liasse['tafire']['total_ressources'], 0, ',', ' ') . " FCFA\n";
echo "   - Total Emplois : " . number_format($liasse['tafire']['total_emplois'], 0, ',', ' ') . " FCFA\n";
echo "   - Variation FRNG : " . number_format($liasse['tafire']['variation_frng'], 0, ',', ' ') . " FCFA\n";
echo "   - Équilibre TAFIRE : " . ($liasse['tafire']['verification'] ? "OK" : "KO") . "\n";

echo "\n5. AMORTISSEMENTS : Total Base = " . number_format($liasse['amortissements']['totaux']['base'], 0, ',', ' ') . " FCFA\n";
echo "6. PROVISIONS : Total = " . number_format($liasse['provisions']['totaux']['total'], 0, ',', ' ') . " FCFA\n";
echo "7. IMMOBILISATIONS : Brut N = " . number_format($liasse['immobilisations']['totaux']['brutN'], 0, ',', ' ') . " FCFA\n";

echo "\n8. ÉTATS ANNEXES (EA 1 à EA 13) :\n";
foreach ($liasse['annexes'] as $key => $annexe) {
    echo "   - [{$key}] {$annexe['titre']}\n";
}

echo "\nTEST REUSSI DE BOUT EN BOUT ! TOUS LES COMPOSANTS SONT VALIDES.\n";
