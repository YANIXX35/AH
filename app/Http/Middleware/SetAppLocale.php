<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetAppLocale
{
    /**
     * Applique la langue depuis la session ou le profil utilisateur.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = 'fr';
        App::setLocale($locale);
        $request->session()->put('locale', $locale);

        return $next($request);
    }
}
