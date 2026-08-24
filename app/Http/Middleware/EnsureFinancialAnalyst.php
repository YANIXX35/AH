<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accès au portail dédié Analyste Financier (role_key = financial_analyst),
 * ou administrateur plateforme.
 */
class EnsureFinancialAnalyst
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $allowed = $user !== null && (
            ($user->role_key ?? null) === 'financial_analyst'
            || ($user->is_platform_admin ?? false)
        );

        if (! $allowed) {
            abort(Response::HTTP_FORBIDDEN, 'Accès réservé aux analystes financiers.');
        }

        return $next($request);
    }
}
