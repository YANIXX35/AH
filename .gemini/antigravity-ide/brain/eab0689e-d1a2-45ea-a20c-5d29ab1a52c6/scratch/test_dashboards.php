<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::first();
if ($user) {
    auth()->login($user);
}

echo "Testing views...\n";

$viewsToTest = [
    'commercial.dashboard' => [
        'clients' => collect(),
        'prospects' => collect(),
        'totalClients' => 0,
        'activeTrials' => 0,
        'expiredTrials' => 0,
        'totalProspects' => 0,
        'newProspects' => 0,
        'qualifiedProspects' => 0,
        'convertedProspects' => 0
    ],
    'accountant.dashboard' => [],
    'dashboard' => [],
    'treasury.balance' => [
        'soldeActuel' => 0,
        'encaissementsEffectues' => 0,
        'decaissementsEffectues' => 0,
        'soldeOuverture' => 0,
        'monthTransactions' => collect(),
        'dailyBalances' => [],
        'monthStart' => now(),
        'monthEnd' => now(),
        'dateFrom' => now()->toDateString(),
        'dateTo' => now()->toDateString(),
        'perfIndicators' => []
    ],
    'commercial.import' => []
];

foreach ($viewsToTest as $viewName => $data) {
    try {
        echo "Testing view [{$viewName}]... ";
        $rendered = view($viewName, $data)->render();
        echo "SUCCESS (" . strlen($rendered) . " bytes)\n";
    } catch (\Throwable $e) {
        echo "FAILED!\n";
        echo "ERROR: " . $e->getMessage() . "\n";
        echo "FILE: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo $e->getTraceAsString() . "\n\n";
    }
}
