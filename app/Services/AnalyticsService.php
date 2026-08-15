<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use PostHog\PostHog;

/**
 * Fine couche autour du SDK PostHog PHP pour le suivi d'événements business
 * côté serveur (fiable, indépendant du JS/bloqueurs de pub côté navigateur,
 * contrairement au tracking client déjà en place dans layouts/app.blade.php).
 *
 * Ne doit jamais faire échouer une requête applicative : toute erreur
 * PostHog (config manquante, API injoignable...) est avalée et journalisée.
 */
class AnalyticsService
{
    private static bool $initialized = false;

    private static function ensureInitialized(): bool
    {
        $apiKey = trim((string) config('services.posthog.key', ''));
        if ($apiKey === '') {
            return false;
        }

        if (! self::$initialized) {
            PostHog::init($apiKey, [
                'host' => config('services.posthog.host', 'https://eu.i.posthog.com'),
            ]);
            self::$initialized = true;
        }

        return true;
    }

    /**
     * Enregistre un événement business rattaché à un utilisateur identifié.
     */
    public static function track(string $event, ?int $userId, array $properties = []): void
    {
        try {
            if (! self::ensureInitialized()) {
                return;
            }

            PostHog::capture([
                'distinctId' => $userId !== null ? (string) $userId : 'anonymous',
                'event' => $event,
                'properties' => $properties,
            ]);

            // Le consumer par défaut (lib_curl) met les événements en file
            // d'attente et ne les envoie réellement qu'au dépassement d'un
            // seuil ou à un flush explicite. Sans ça, un événement isolé émis
            // pendant une requête PHP-FPM courte est silencieusement perdu à
            // la fin de la requête. Le flush() est un appel bloquant, mais
            // ces événements sont peu fréquents (inscription, facture,
            // paiement Premium, import OCR) donc le coût est négligeable.
            PostHog::flush();
        } catch (\Throwable $e) {
            Log::warning('AnalyticsService: échec de l’envoi d’un événement PostHog: '.$e->getMessage(), [
                'event' => $event,
            ]);
        }
    }
}
