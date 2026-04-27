<?php

use App\Http\Middleware\AttachRequestContext;
use App\Http\Middleware\EnforceAccountantPortalPolicy;
use App\Http\Middleware\EnsureAccountant;
use App\Http\Middleware\EnsureAccountNotSuspended;
use App\Http\Middleware\EnsureModulePermission;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsurePremiumAccountingAccess;
use App\Http\Middleware\LogMenuNavigation;
use App\Http\Middleware\SanitizeClientWorkspaceSession;
use App\Http\Middleware\SetAppLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'platform.admin' => EnsurePlatformAdmin::class,
            'accountant' => EnsureAccountant::class,
            'premium.accounting' => EnsurePremiumAccountingAccess::class,
            'module.permission' => EnsureModulePermission::class,
        ]);
        $middleware->appendToGroup('web', SetAppLocale::class);
        $middleware->appendToGroup('web', EnsureAccountNotSuspended::class);
        $middleware->appendToGroup('web', SanitizeClientWorkspaceSession::class);
        $middleware->appendToGroup('web', EnforceAccountantPortalPolicy::class);
        $middleware->appendToGroup('web', LogMenuNavigation::class);
        $middleware->appendToGroup('web', AttachRequestContext::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
