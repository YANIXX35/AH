<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque la navigation d'un compte créé avec un mot de passe temporaire
 * (assistant "Ajouter un client" côté Commercial/Comptable) tant qu'il n'a
 * pas défini son propre mot de passe. Le mot de passe temporaire par défaut
 * (Sitiame2026) est partagé entre tous les nouveaux clients tant qu'ils ne
 * le changent pas : le laisser en place indéfiniment est un vrai risque.
 */
class EnsurePasswordHasBeenChanged
{
    /**
     * Routes accessibles sans avoir changé le mot de passe (sinon l'utilisateur
     * serait bloqué avant même de pouvoir atteindre le formulaire qui le débloque).
     */
    private const ALLOWED_ROUTES = [
        'profile',
        'profile.update',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        if (! $user->must_change_password) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true)) {
            return $next($request);
        }

        return redirect()
            ->route('profile')
            ->with('warning', 'Pour la sécurité de votre compte, veuillez définir un nouveau mot de passe avant de continuer.');
    }
}
