
<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\AccountingController;

$filePath = __DIR__ . '/Doc_comptabilite/modele_syscohada_PLAN COMPLET + LIASSE BCEAO_5.xlsx';

echo "Testing file exists? " . (file_exists($filePath) ? "YES" : "NO") . PHP_EOL;
echo "File size: " . (file_exists($filePath) ? filesize($filePath) : 0) . " bytes" . PHP_EOL;
echo "Testing parsePlanComptable: " . PHP_EOL;

$controller = new AccountingController();

try {
    $result = $controller->parsePlanComptable($filePath);
    echo "SUCCESS! Parsed " . count($result['accounts']) . " accounts!";
    echo PHP_EOL;
    echo "Invalid rows: " . count($result['invalidRows']) . PHP_EOL;
    if (count($result['accounts']) > 0) {
        $firstAccount = array_values($result['accounts'])[0];
        echo "First account: " . var_export($firstAccount, true) . PHP_EOL;
    }
    echo PHP_EOL;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
