<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restreint les routes au personnel commercial.
 */
class EnsureCommercial
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->role_key !== 'commercial') {
            abort(Response::HTTP_FORBIDDEN, 'Accès réservé au personnel commercial.');
        }

        return $next($request);
    }
}
