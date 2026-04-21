<?php

namespace App\Http\Middleware;

use App\Models\MenuActionLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Journalise les accès aux zones couvertes par le menu latéral (traçabilité utilisateur).
 */
class LogMenuNavigation
{
    /**
     * Noms de routes alignés sur resources/views/layouts/partials/sidebar.blade.php
     * (préfixes + routes profil / déconnexion liées à l’app authentifiée).
     */
    private const ROUTE_PATTERNS = [
        'dashboard',
        'admin.*',
        'accounting*',
        'treasury*',
        'investor.*',
        'profile',
        'profile.*',
        'company.settings',
        'treasury.settings',
        'subscriptions.history',
        'support.*',
        'notifications.*',
        'logout',
        'logout.get',
        'activity-log*',
        'accountant.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! filter_var(env('LOG_MENU_ACTIONS', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if (! Auth::check()) {
            return;
        }

        $route = $request->route();
        $routeName = $route?->getName();
        if ($routeName === null || $routeName === '') {
            return;
        }

        if (! $this->routeMatchesMenuScope($routeName)) {
            return;
        }

        $user = Auth::user();

        $payload = [
            'user_id' => $user->getAuthIdentifier(),
            'name' => $user->name ?? null,
            'email' => $user->email ?? null,
            'platform_admin' => (bool) ($user->is_platform_admin ?? false),
            'route' => $routeName,
            'method' => $request->method(),
            'path' => '/'.$request->path(),
            'status' => $response->getStatusCode(),
            'ip' => $request->ip(),
        ];

        Log::channel('menu_actions')->info('action_menu', $payload);

        try {
            MenuActionLog::query()->create([
                'user_id' => (int) $user->getAuthIdentifier(),
                'route_name' => $routeName,
                'http_method' => $request->method(),
                'path' => '/'.$request->path(),
                'status_code' => $response->getStatusCode(),
                'ip' => $request->ip(),
                'was_platform_admin' => (bool) ($user->is_platform_admin ?? false),
            ]);
        } catch (Throwable $e) {
            Log::warning('menu_action_log_db_failed', [
                'message' => $e->getMessage(),
                'route' => $routeName,
            ]);
        }
    }

    private function routeMatchesMenuScope(string $routeName): bool
    {
        foreach (self::ROUTE_PATTERNS as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }
}
