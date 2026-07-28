<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use PhpOffice\PhpSpreadsheet\IOFactory;

$filePath = base_path('Doc_comptabilite/modele_syscohada_PLAN COMPLET + LIASSE BCEAO_5.xlsx');

echo "=== FAST READER TEST ===\n";
$startTime = microtime(true);

$reader = IOFactory::createReaderForFile($filePath);
$reader->setReadDataOnly(true); // Don't load styles, formatting, images

// List sheet names without loading full file
$sheetNames = $reader->listWorksheetNames($filePath);
echo "Sheets found: " . implode(', ', $sheetNames) . "\n";

$targetSheet = null;
$candidates = ['Plan_Comptable', 'Plan Comptable', 'Plan Comptable SYSCOHADA', 'Feuil1', 'Sheet1'];
foreach ($candidates as $candidate) {
    if (in_array($candidate, $sheetNames, true)) {
        $targetSheet = $candidate;
        break;
    }
}
if (!$targetSheet && !empty($sheetNames)) {
    $targetSheet = $sheetNames[0];
}

echo "Targeting sheet: '{$targetSheet}'\n";
$reader->setLoadSheetsOnly([$targetSheet]);

$spreadsheet = $reader->load($filePath);
$sheet = $spreadsheet->getActiveSheet();

$highestRow = $sheet->getHighestRow();
$highestCol = $sheet->getHighestColumn();
echo "Sheet dimensions: {$highestCol} x {$highestRow}\n";

$endTime = microtime(true);
echo "Fast execution time: " . number_format($endTime - $startTime, 2) . " seconds!\n";
echo "Memory peak: " . number_format(memory_get_peak_usage() / 1024 / 1024, 2) . " MB!\n";
