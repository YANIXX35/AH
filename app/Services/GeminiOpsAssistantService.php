<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GeminiOpsAssistantService
{
    /**
     * Envoyez une conversation avec Google Gemini API (v1beta) avec Retry, Circuit Breaker et Cache
     * 
     * @param array<int, array{role: string, content: string}> $messages
     * @return array{ok: bool, answer: string, error?: string, enabled: bool}
     */
    public function chat(array $messages): array
    {
        $startTime = microtime(true);

        $apiKey = (string) config('gemini.key', env('GEMINI_API_KEY', ''));
        $defaultModel = (string) config('gemini.default_model', env('GEMINI_MODEL', 'gemini-2.0-flash'));
        $fallbacks = (array) config('gemini.fallback_models', []);
        $timeout = (int) config('gemini.timeout', 30);
        $connectTimeout = (int) config('gemini.connect_timeout', 10);
        $maxAttempts = (int) config('gemini.retry.max_attempts', 4);
        $baseDelay = (int) config('gemini.retry.base_delay_seconds', 1);

        $cacheEnabled = (bool) config('gemini.cache.enabled', true);
        $cacheTtl = (int) config('gemini.cache.ttl_minutes', 10);

        $cbEnabled = (bool) config('gemini.circuit_breaker.enabled', true);
        $logChannel = (string) config('gemini.logging.channel', 'gemini');

        if (empty($apiKey)) {
            Log::channel($logChannel)->warning('Gemini API key non configurée.');
            return [
                'ok' => false,
                'answer' => '',
                'error' => 'Clé GEMINI_API_KEY non configurée.',
                'enabled' => false,
            ];
        }

        // 1. Gestion du Cache
        if ($cacheEnabled) {
            $cacheKey = 'gemini:chat:' . hash('sha256', json_encode($messages));
            $cachedAnswer = Cache::get($cacheKey);
            if ($cachedAnswer !== null) {
                $totalTimeMs = round((microtime(true) - $startTime) * 1000, 2);
                Log::channel($logChannel)->info("Cache HIT | Total time: {$totalTimeMs}ms", [
                    'cache_key' => $cacheKey,
                ]);
                return [
                    'ok' => true,
                    'answer' => $cachedAnswer,
                    'enabled' => true,
                ];
            }
        }

        // Préparation du payload Gemini
        $contents = [];
        $systemInstructionParts = [];

        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            $content = trim($msg['content'] ?? '');

            if ($content === '') {
                continue;
            }

            if ($role === 'system') {
                $systemInstructionParts[] = ['text' => $content];
            } else {
                $geminiRole = ($role === 'assistant' || $role === 'model') ? 'model' : 'user';
                $contents[] = [
                    'role' => $geminiRole,
                    'parts' => [
                        ['text' => $content]
                    ]
                ];
            }
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 1200,
            ]
        ];

        if (!empty($systemInstructionParts)) {
            $payload['systemInstruction'] = [
                'parts' => $systemInstructionParts
            ];
        }

        // Liste ordonnée de modèles à tenter
        $modelsToTry = array_unique(array_merge([$defaultModel], $fallbacks));
        $lastError = '';

        foreach ($modelsToTry as $model) {
            // 2. Circuit Breaker : Check lockout
            if ($cbEnabled && $this->isModelLocked($model)) {
                Log::channel($logChannel)->warning("Circuit Breaker actif pour le modèle {$model}. Passage au modèle suivant.");
                continue;
            }

            $modelStartTime = microtime(true);
            $attempts = 0;

            while ($attempts < $maxAttempts) {
                $attempts++;
                $reqStartTime = microtime(true);

                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

                try {
                    $response = Http::timeout($timeout)
                        ->connectTimeout($connectTimeout)
                        ->acceptJson()
                        ->post($url, $payload);

                    $reqTimeMs = round((microtime(true) - $reqStartTime) * 1000, 2);
                    $totalTimeMs = round((microtime(true) - $startTime) * 1000, 2);

                    if ($response->successful()) {
                        $json = $response->json();
                        $answer = (string) data_get($json, 'candidates.0.content.parts.0.text', '');

                        if ($answer !== '') {
                            // Journaliser le succès
                            Log::channel($logChannel)->info(
                                sprintf(
                                    "SUCCESS | Model: %s | Resp: %sms | Total: %sms | Retries: %d | HTTP: %d",
                                    $model,
                                    $reqTimeMs,
                                    $totalTimeMs,
                                    $attempts - 1,
                                    $response->status()
                                )
                            );

                            // Mettre en cache la réponse réussie
                            if ($cacheEnabled && isset($cacheKey)) {
                                Cache::put($cacheKey, trim($answer), now()->addMinutes($cacheTtl));
                            }

                            return [
                                'ok' => true,
                                'answer' => trim($answer),
                                'enabled' => true,
                            ];
                        }
                    }

                    // En cas d'erreur de réponse API
                    $errorDetails = $response->json();
                    $lastError = data_get($errorDetails, 'error.message', 'Erreur HTTP ' . $response->status());
                    $httpStatus = $response->status();

                    Log::channel($logChannel)->warning(
                        sprintf(
                            "FAILURE | Model: %s | HTTP: %d | Error: %s | Attempt: %d/%d",
                            $model,
                            $httpStatus,
                            $lastError,
                            $attempts,
                            $maxAttempts
                        )
                    );

                    // Si erreur transitoire (429, 503, ou overloaded / high demand)
                    $isTransient = ($httpStatus === 429 || $httpStatus === 503 || stripos($lastError, 'demand') !== false || stripos($lastError, 'overloaded') !== false);
                    if ($isTransient && $attempts < $maxAttempts) {
                        $delay = $baseDelay * pow(2, $attempts - 1);
                        sleep($delay);
                    } else {
                        // Erreur non transitoire ou tentatives épuisées -> déclencher Circuit Breaker pour ce modèle
                        if ($cbEnabled) {
                            $this->registerFailure($model);
                        }
                        break; // Sortir du while retry et passer au modèle suivant
                    }

                } catch (\Throwable $e) {
                    $reqTimeMs = round((microtime(true) - $reqStartTime) * 1000, 2);
                    $lastError = $e->getMessage();

                    Log::channel($logChannel)->error(
                        sprintf(
                            "EXCEPTION | Model: %s | Error: %s | Attempt: %d/%d | Time: %sms",
                            $model,
                            $lastError,
                            $attempts,
                            $maxAttempts,
                            $reqTimeMs
                        )
                    );

                    if ($attempts < $maxAttempts) {
                        $delay = $baseDelay * pow(2, $attempts - 1);
                        sleep($delay);
                    } else {
                        if ($cbEnabled) {
                            $this->registerFailure($model);
                        }
                        break;
                    }
                }
            }
        }

        $totalTimeMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::channel($logChannel)->error("ALL MODELS FAILED | Total time: {$totalTimeMs}ms | Last error: {$lastError}");

        // Traduire le message pour l'utilisateur de manière polie et transparente
        $userFriendlyMessage = "Le service de l'assistant IA est momentanément très sollicité ou occupé. Veuillez patienter quelques instants puis réessayer.";

        return [
            'ok' => false,
            'answer' => '',
            'error' => $userFriendlyMessage,
            'enabled' => true,
        ];
    }

    /**
     * Vérifier si un modèle est verrouillé par le Circuit Breaker
     */
    private function isModelLocked(string $model): bool
    {
        return (bool) Cache::get("gemini:cb:locked:{$model}", false);
    }

    /**
     * Enregistrer un échec pour le modèle et verrouiller si nécessaire
     */
    private function registerFailure(string $model): void
    {
        $maxFailures = (int) config('gemini.circuit_breaker.max_failures', 5);
        $timeWindow = (int) config('gemini.circuit_breaker.time_window_seconds', 120);
        $lockoutDuration = (int) config('gemini.circuit_breaker.lockout_duration_seconds', 300);

        $failuresKey = "gemini:cb:failures:{$model}";
        $now = time();

        $failures = (array) Cache::get($failuresKey, []);
        // Conserver uniquement les échecs dans la fenêtre de temps
        $failures = array_filter($failures, fn ($ts) => ($now - $ts) < $timeWindow);
        $failures[] = $now;

        Cache::put($failuresKey, $failures, now()->addSeconds($timeWindow));

        if (count($failures) >= $maxFailures) {
            Cache::put("gemini:cb:locked:{$model}", true, now()->addSeconds($lockoutDuration));
            Log::channel(config('gemini.logging.channel', 'gemini'))->critical(
                "CIRCUIT BREAKER TRIGGERED | Le modèle {$model} est verrouillé pendant {$lockoutDuration} secondes suite à " . count($failures) . " échecs."
            );
        }
    }
}
