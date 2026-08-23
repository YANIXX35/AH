<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restreint les routes au personnel commercial (ou à l'administrateur
 * plateforme, comme pour les portails comptable et supervision commerciale).
 */
class EnsureCommercial
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $allowed = $user !== null && (
            in_array($user->role_key, ['commercial', 'commerciale'], true)
            || ($user->is_platform_admin ?? false)
        );

        if (! $allowed) {
            abort(Response::HTTP_FORBIDDEN, 'Accès réservé au personnel commercial.');
        }

        return $next($request);
    }
}
