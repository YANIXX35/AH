<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Http\Controllers\AdminController;
use Illuminate\Http\Request;

$admin = User::where('is_platform_admin', true)->first();
if (!$admin) {
    echo "No admin user found, creating fake admin request...\n";
    $admin = new User(['id' => 1, 'is_platform_admin' => true]);
}

$request = Request::create('/admin', 'GET');
$request->setUserResolver(fn() => $admin);

try {
    $controller = app(AdminController::class);
    $view = $controller->index($request);
    echo "SUCCESS! AdminController index returned view: " . $view->name() . "\n";
    $rendered = $view->render();
    echo "Render SUCCESS! Rendered length: " . strlen($rendered) . " bytes\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
