<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModulePermission
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }
        if (! $user->canAccessModule($module)) {
            abort(403, "Accès refusé au module {$module}.");
        }

        return $next($request);
    }
}
