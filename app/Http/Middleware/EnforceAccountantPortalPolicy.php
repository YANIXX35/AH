<?php

namespace App\Http\Middleware;

use App\Support\ClientWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comptes « comptable » : pas d’accès au portail entreprise (dashboard, abonnements…)
 * ni à la compta / trésorerie / FIRD sans dossier client ouvert en session.
 */
class EnforceAccountantPortalPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || $user->is_platform_admin || ! ($user->is_accountant ?? false)) {
            return $next($request);
        }

        $name = $request->route()?->getName();
        if ($name === null) {
            return $next($request);
        }

        if ($name === 'dashboard' || $name === 'subscriptions.history' || str_starts_with($name, 'activity-log')) {
            return redirect()->route('accountant.dashboard')
                ->with('status', 'Cet écran est réservé aux comptes entreprise. Utilisez le menu « Cabinet comptable ».');
        }

        if ($name === 'profile.subscription.simulate') {
            return redirect()->route('accountant.dashboard')
                ->with('status', 'La simulation d’abonnement est réservée aux comptes entreprise.');
        }

        $needsWorkspace = str_starts_with($name, 'accounting')
            || str_starts_with($name, 'treasury')
            || str_starts_with($name, 'investor')
            || $name === 'profile.company.fird'
            || $name === 'profile.company.fird.update';

        if ($needsWorkspace && ! ClientWorkspace::isViewingClient()) {
            return redirect()->route('accountant.clients.index')
                ->with('status', 'Ouvrez d’abord un dossier client (Cabinet comptable → Dossiers clients), puis utilisez « Ouvrir — … » sur la fiche.');
        }

        return $next($request);
    }
}
