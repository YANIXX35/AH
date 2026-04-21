<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'platform.admin' => \App\Http\Middleware\EnsurePlatformAdmin::class,
            'accountant' => \App\Http\Middleware\EnsureAccountant::class,
            'premium.accounting' => \App\Http\Middleware\EnsurePremiumAccountingAccess::class,
        ]);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureAccountNotSuspended::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\SanitizeClientWorkspaceSession::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnforceAccountantPortalPolicy::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\LogMenuNavigation::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
