<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\AccountingController;

$controller = new AccountingController();
$reflector = new ReflectionClass(AccountingController::class);
$method = $reflector->getMethod('parsePlanComptableUniversal');
$method->setAccessible(true);

$filePath = base_path('Doc_comptabilite/modele_syscohada_PLAN COMPLET + LIASSE BCEAO_5.xlsx');

echo "=== INTEGRATION TEST: CONTROLLER PARSING ===\n";
$start = microtime(true);

$result = $method->invoke($controller, $filePath);

$end = microtime(true);

echo "Parsed " . count($result['accounts']) . " accounts successfully!\n";
echo "Execution time: " . number_format($end - $start, 2) . " s\n";
echo "Memory peak: " . number_format(memory_get_peak_usage() / 1024 / 1024, 2) . " MB\n";

if (!empty($result['accounts'])) {
    $first5 = array_slice($result['accounts'], 0, 5);
    echo "\nSample Accounts:\n";
    foreach ($first5 as $code => $acc) {
        echo "  - {$code} : {$acc['libelle_compte']} (Classe {$acc['prefix']})\n";
    }
}
