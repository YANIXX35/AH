<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Déconnecte les comptes suspendus (session active après suspension côté admin).
 */
class EnsureAccountNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        if (! $user->account_suspended) {
            return $next($request);
        }

        // Révoque les sessions persistantes ("remember me") des comptes suspendus.
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->withErrors([
                'email' => 'Ce compte est suspendu. Contactez le support ou l’administrateur de la plateforme.',
            ], 'login');
    }
}
