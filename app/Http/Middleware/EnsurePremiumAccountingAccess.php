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

        if ($user->isPlatformAdmin() || $user->isAccountant() || $user->isFinancialAnalyst()) {
            return $next($request);
        }

        if ($user->hasActivePremiumPeriod()) {
            return $next($request);
        }

        return redirect()
            ->route('payments.sandbox')
            ->withErrors([
                'subscription' => 'Comptabilité indisponible : activez Enterprise Premium (15 000 FCFA) via le paiement pour débloquer ce module.',
            ]);
    }
}
