<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = new \App\Models\User();
$user->id = 1;
$user->name = 'Commercial Test';
$user->email = 'commercial@example.com';
$user->is_commercial = true;
$user->is_platform_admin = false;
$user->is_accountant = false;

auth()->setUser($user);

// Injecter $errors pour simuler la session Blade
$viewFactory = app('view');
$viewFactory->share('errors', new \Illuminate\Support\ViewErrorBag());

echo "Testing views with full error bag simulation...\n";

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
    'accountant.dashboard' => [
        'clientCount' => 0,
        'entriesTotal' => 0,
        'documentsPending' => 0,
        'ocrStressEntries' => 0,
        'treasuryVolume' => 0.0,
        'recentClients' => collect(),
        'accountingWorkspaceOpen' => false,
        'accountingWorkspaceLabel' => '',
        'aiInconsistencies' => []
    ],
    'dashboard' => [
        'user' => $user,
        'entriesCount' => 0,
        'monthlyVolume' => 0,
        'ocrDocumentsCount' => 0,
        'netTreasury' => 0,
        'accountingEntriesCount' => 0,
        'accountingMonthlyAmount' => 0,
        'currentMonthLabel' => 'Juillet 2026',
        'documentsCount' => 0,
        'pendingDocumentsCount' => 0,
        'soldeActuel' => 0
    ],
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
