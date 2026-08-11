<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accès au portail dédié de supervision commerciale : superviseurs commerciaux
 * habilités (role_key = commercial_supervisor) ou administrateur plateforme.
 */
class EnsureCommercialSupervisor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $allowed = $user !== null && (
            ($user->role_key ?? null) === 'commercial_supervisor'
            || ($user->is_platform_admin ?? false)
        );

        if (! $allowed) {
            abort(Response::HTTP_FORBIDDEN, 'Accès réservé à la supervision commerciale.');
        }

        return $next($request);
    }
}
