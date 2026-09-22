<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers HTTP de durcissement de base.
 *
 * La Content-Security-Policy est envoyée en mode Report-Only (audit F-37) :
 * l'app charge des scripts/styles inline et plusieurs CDN externes (Font
 * Awesome, Google Fonts, PostHog, Unsplash) qui n'ont jamais été vérifiés
 * un par un dans un vrai navigateur. Passer directement en mode bloquant
 * casserait potentiellement le rendu ; Report-Only permet de collecter les
 * violations réelles avant d'activer le blocage.
 */
class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy-Report-Only', $this->contentSecurityPolicy());

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $posthogHost = (string) config('services.posthog.host', 'https://eu.i.posthog.com');
        $posthogAssetHost = str_replace('.i.posthog.com', '-assets.i.posthog.com', $posthogHost);

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' cdnjs.cloudflare.com {$posthogHost} {$posthogAssetHost}",
            "style-src 'self' 'unsafe-inline' cdnjs.cloudflare.com fonts.googleapis.com",
            "font-src 'self' data: cdnjs.cloudflare.com fonts.gstatic.com",
            "img-src 'self' data: blob: images.unsplash.com {$posthogHost}",
            "connect-src 'self' {$posthogHost} {$posthogAssetHost}",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        return implode('; ', $directives);
    }
}
