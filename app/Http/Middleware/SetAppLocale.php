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
        $allowedLocales = ['fr', 'en', 'es', 'de', 'pt', 'ar', 'it', 'nl'];

        $sessionLocale = (string) $request->session()->get('locale', '');
        $userLocale = (string) optional($request->user())->locale;

        $locale = in_array($sessionLocale, $allowedLocales, true)
            ? $sessionLocale
            : (in_array($userLocale, $allowedLocales, true) ? $userLocale : config('app.locale', 'fr'));

        App::setLocale($locale);
        $request->session()->put('locale', $locale);

        return $next($request);
    }
}
