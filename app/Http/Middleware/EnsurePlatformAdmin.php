<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restreint les routes au personnel éditeur (super-admin plateforme).
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->is_platform_admin) {
            abort(Response::HTTP_FORBIDDEN, 'Accès réservé aux administrateurs de la plateforme.');
        }

        return $next($request);
    }
}
