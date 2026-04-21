<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePremiumAccountingAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        if ($user->isPlatformAdmin() || $user->isAccountant()) {
            return $next($request);
        }

        if ($user->hasActivePremiumPeriod()) {
            return $next($request);
        }

        return redirect()
            ->route('profile')
            ->withErrors([
                'subscription' => 'Votre abonnement est en mode Gratuit. Activez Premium pour accéder à la Comptabilité.',
            ]);
    }
}
