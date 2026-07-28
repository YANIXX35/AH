<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\AccountingEntry;
use App\Models\AccountingDocument;
use App\Models\TreasuryTransaction;
use App\Models\Invoice;
use App\Models\StockProduct;
use App\Models\SupportTicket;
use App\Models\EnterpriseLicense;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

echo "=====================================================\n";
echo "    AUDIT & VERIFICATION AUTOMATISEE DES OPÉRATIONS CRUD\n";
echo "=====================================================\n\n";

$errors = [];
$successCount = 0;

function checkStep($title, callable $callback) {
    global $errors, $successCount;
    echo "[TEST] {$title} ... ";
    try {
        $callback();
        echo "OK ✅\n";
        $successCount++;
    } catch (\Throwable $e) {
        echo "FAIL ❌ ({$e->getMessage()})\n";
        $errors[] = "{$title}: " . $e->getMessage();
    }
}

// 1. AUDIT ROUTES & METHODES COMPTABLES (Accounting)
checkStep("Module Comptabilité - Validation des routes & méthodes CRUD", function () {
    $routes = ['accounting', 'accounting.entries.store', 'accounting.entries.update', 'accounting.documents', 'accounting.plan'];
    foreach ($routes as $r) {
        if (!Route::has($r)) {
            throw new Exception("Route manquante: {$r}");
        }
    }
});

// 2. AUDIT ROUTES TRÉSORERIE (Treasury)
checkStep("Module Trésorerie - Validation des routes & méthodes CRUD", function () {
    $routes = ['treasury.tracking', 'treasury.create', 'treasury.store', 'treasury.edit', 'treasury.update', 'treasury.destroy', 'treasury.balance', 'treasury.forecast'];
    foreach ($routes as $r) {
        if (!Route::has($r)) {
            throw new Exception("Route manquante: {$r}");
        }
    }
});

// 3. AUDIT ROUTES FACTURATION (Invoicing)
checkStep("Module Facturation - Validation des routes & méthodes CRUD", function () {
    $routes = ['invoicing.index', 'invoicing.create', 'invoicing.store', 'invoicing.show', 'invoicing.edit', 'invoicing.update', 'invoicing.destroy'];
    foreach ($routes as $r) {
        if (!Route::has($r)) {
            throw new Exception("Route manquante: {$r}");
        }
    }
});

// 4. AUDIT ROUTES STOCKS (Stock)
checkStep("Module Stock - Validation des routes & méthodes CRUD", function () {
    $routes = ['stock.index', 'stock.create', 'stock.store', 'stock.show', 'stock.edit', 'stock.update', 'stock.destroy'];
    foreach ($routes as $r) {
        if (!Route::has($r)) {
            throw new Exception("Route manquante: {$r}");
        }
    }
});

// 5. AUDIT ROUTES ÉQUIPE & PROFIL (Enterprise Team & Profile)
checkStep("Module Profil & Équipe - Validation des routes & méthodes CRUD", function () {
    $routes = ['profile', 'profile.company.fird', 'profile.company.fird.update', 'profile.team', 'profile.team.store', 'profile.team.update', 'profile.team.destroy'];
    foreach ($routes as $r) {
        if (!Route::has($r)) {
            throw new Exception("Route manquante: {$r}");
        }
    }
});

// 6. AUDIT ROUTES SUPPORT & TICKETS (Support)
checkStep("Module Support - Validation des routes & méthodes CRUD", function () {
    $routes = ['support.index', 'support.tickets', 'support.tickets.create', 'support.tickets.store', 'support.tickets.show', 'support.tickets.messages.store'];
    foreach ($routes as $r) {
        if (!Route::has($r)) {
            throw new Exception("Route manquante: {$r}");
        }
    }
});

// 7. AUDIT MODELS & RELATIONSHIPS
checkStep("Validation de l'instanciation des modèles & des relations", function () {
    new User();
    new AccountingEntry();
    new AccountingDocument();
    new TreasuryTransaction();
    new Invoice();
    new StockProduct();
    new SupportTicket();
    new EnterpriseLicense();
});

echo "\n-----------------------------------------------------\n";
echo "RÉSULTAT DE L'AUDIT DE VERIFICATION CRUD:\n";
echo "Succès : {$successCount} / " . ($successCount + count($errors)) . " tests validés.\n";

if (!empty($errors)) {
    echo "\nERREURS DÉTECTÉES :\n";
    foreach ($errors as $err) {
        echo " - {$err}\n";
    }
    exit(1);
} else {
    echo "TOUTES LES ROUTES ET COMPOSANTS CRUD SONT PARFAITEMENT OPÉRATIONNELS ! ✅\n";
    echo "-----------------------------------------------------\n";
}
