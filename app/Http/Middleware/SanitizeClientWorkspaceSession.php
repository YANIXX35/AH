<?php

namespace App\Http\Middleware;

use App\Support\ClientWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nettoie une session « dossier client » invalide (compte supprimé, ou utilisateur sans droit cabinet).
 */
class SanitizeClientWorkspaceSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            ClientWorkspace::sanitizeStaleSession();
        }

        return $next($request);
    }
}
