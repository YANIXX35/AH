<?php

namespace App\Http\Middleware;

use App\Support\ClientWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accès aux écrans « cabinet comptable » : comptables habilités ou administrateur plateforme.
 */
class EnsureAccountant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! ClientWorkspace::canUseWorkspace($user)) {
            abort(Response::HTTP_FORBIDDEN, 'Accès réservé au personnel comptable habilité.');
        }

        return $next($request);
    }
}
