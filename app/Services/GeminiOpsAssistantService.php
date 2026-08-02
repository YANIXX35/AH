<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service Gemini avec :
 *  - Rotation automatique des clés API (quota 429 → clé suivante)
 *  - Fallback multi-modèles (circuit breaker par modèle)
 *  - Retry exponentiel
 *  - Cache des réponses identiques
 */
class GeminiOpsAssistantService
{
    /**
     * Retourne la liste des clés API disponibles (primaire + extra_keys).
     * Filtre les clés marquées comme épuisées en cache.
     *
     * @return list<string>
     */
    private function availableKeys(): array
    {
        $primary = (string) config('gemini.key', env('GEMINI_API_KEY', ''));
        $extras = (array) config('gemini.extra_keys', []);

        // Toutes les clés dans l'ordre de priorité
        $allKeys = array_values(array_filter(array_merge([$primary], $extras)));

        // Retirer les clés dont le quota est épuisé (lockées en cache)
        return array_values(array_filter($allKeys, fn ($k) => ! Cache::get($this->keyLockoutCacheKey($k), false)));
    }

    /**
     * Clé cache pour le lockout d'une clé API quota-épuisée.
     */
    private function keyLockoutCacheKey(string $apiKey): string
    {
        // On hash la clé pour ne pas l'exposer dans le cache
        return 'gemini:key:exhausted:'.substr(md5($apiKey), 0, 12);
    }

    /**
     * Marque une clé API comme épuisée (quota 429) pendant $minutes.
     */
    private function lockoutKey(string $apiKey, int $minutes): void
    {
        Cache::put($this->keyLockoutCacheKey($apiKey), true, now()->addMinutes($minutes));

        $logChannel = (string) config('gemini.logging.channel', 'gemini');
        // On ne loggue que les 8 derniers caractères pour la sécurité
        $maskedKey = '***'.substr($apiKey, -8);
        Log::channel($logChannel)->warning(
            "Gemini KEY QUOTA EXHAUSTED | Clé [{$maskedKey}] verrouillée pour {$minutes} min. Basculement sur la clé suivante."
        );
    }

    /**
     * Envoyez une conversation avec Google Gemini API (v1beta).
     *
     * Rotation automatique des clés API si quota 429 épuisé.
     * Circuit Breaker + Retry exponentiel par modèle.
     * Cache des réponses identiques.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{ok: bool, answer: string, error?: string, enabled: bool}
     */
    public function chat(array $messages): array
    {
        $startTime = microtime(true);
        $logChannel = (string) config('gemini.logging.channel', 'gemini');

        $defaultModel = (string) config('gemini.default_model', 'gemini-2.0-flash');
        $fallbacks = (array) config('gemini.fallback_models', []);
        $timeout = (int) config('gemini.timeout', 30);
        $connectTimeout = (int) config('gemini.connect_timeout', 10);
        $maxAttempts = (int) config('gemini.retry.max_attempts', 3);
        $baseDelay = (int) config('gemini.retry.base_delay_seconds', 1);
        $cacheEnabled = (bool) config('gemini.cache.enabled', true);
        $cacheTtl = (int) config('gemini.cache.ttl_minutes', 10);
        $cbEnabled = (bool) config('gemini.circuit_breaker.enabled', true);
        $keyLockout = (int) config('gemini.key_lockout_minutes', 60);

        // ─── Vérification qu'il existe au moins une clé disponible ──────────
        $availableKeys = $this->availableKeys();

        if (empty($availableKeys)) {
            $allKeys = array_values(array_filter(array_merge(
                [(string) config('gemini.key', '')],
                (array) config('gemini.extra_keys', [])
            )));

            if (empty($allKeys)) {
                Log::channel($logChannel)->error('Gemini : Aucune clé API configurée.');

                return [
                    'ok' => false,
                    'answer' => '',
                    'error' => 'Aucune clé GEMINI_API_KEY configurée.',
                    'enabled' => false,
                ];
            }

            // Toutes les clés sont épuisées → on libère les verrous et on reprend avec la clé primaire
            Log::channel($logChannel)->warning('Gemini : Toutes les clés API sont épuisées. Réinitialisation des verrous pour retenter.');
            foreach ($allKeys as $k) {
                Cache::forget($this->keyLockoutCacheKey($k));
            }
            $availableKeys = $allKeys;
        }

        // ─── Cache de réponses identiques ───────────────────────────────────
        $cacheKey = null;
        if ($cacheEnabled) {
            $cacheKey = 'gemini:chat:'.hash('sha256', json_encode($messages));
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                $ms = round((microtime(true) - $startTime) * 1000, 2);
                Log::channel($logChannel)->info("Cache HIT | {$ms}ms");

                return ['ok' => true, 'answer' => $cached, 'enabled' => true];
            }
        }

        // ─── Construction du payload Gemini ─────────────────────────────────
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
                    'parts' => [['text' => $content]],
                ];
            }
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 1200,
            ],
        ];
        if (! empty($systemInstructionParts)) {
            $payload['systemInstruction'] = ['parts' => $systemInstructionParts];
        }

        // ─── Boucle : pour chaque MODÈLE, essayer toutes les CLÉS disponibles ─
        $modelsToTry = array_unique(array_merge([$defaultModel], $fallbacks));
        $lastError = '';

        foreach ($modelsToTry as $model) {
            // Circuit Breaker : modèle verrouillé ?
            if ($cbEnabled && $this->isModelLocked($model)) {
                Log::channel($logChannel)->warning("Circuit Breaker actif pour le modèle [{$model}]. Modèle suivant.");

                continue;
            }

            // ── Boucle sur les clés API disponibles ───────────────────────
            $keysToTry = $this->availableKeys();

            foreach ($keysToTry as $apiKey) {
                $attempts = 0;

                while ($attempts < $maxAttempts) {
                    $attempts++;
                    $reqStart = microtime(true);

                    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

                    try {
                        $response = Http::timeout($timeout)
                            ->connectTimeout($connectTimeout)
                            ->acceptJson()
                            ->post($url, $payload);

                        $reqMs = round((microtime(true) - $reqStart) * 1000, 2);
                        $totalMs = round((microtime(true) - $startTime) * 1000, 2);
                        $masked = '***'.substr($apiKey, -8);

                        if ($response->successful()) {
                            $json = $response->json();
                            $answer = (string) data_get($json, 'candidates.0.content.parts.0.text', '');

                            if ($answer !== '') {
                                Log::channel($logChannel)->info(
                                    sprintf(
                                        'SUCCESS | Model: %s | Key: %s | Resp: %sms | Total: %sms | Retries: %d',
                                        $model, $masked, $reqMs, $totalMs, $attempts - 1
                                    )
                                );

                                if ($cacheEnabled && $cacheKey) {
                                    Cache::put($cacheKey, trim($answer), now()->addMinutes($cacheTtl));
                                }

                                return ['ok' => true, 'answer' => trim($answer), 'enabled' => true];
                            }
                        }

                        $errorDetails = $response->json();
                        $lastError = data_get($errorDetails, 'error.message', 'HTTP '.$response->status());
                        $httpStatus = $response->status();

                        Log::channel($logChannel)->warning(
                            sprintf(
                                'FAILURE | Model: %s | Key: %s | HTTP: %d | Error: %s | Attempt: %d/%d',
                                $model, $masked, $httpStatus, $lastError, $attempts, $maxAttempts
                            )
                        );

                        // ── 429 = quota épuisé → verrouiller la clé et passer à la suivante ──
                        if ($httpStatus === 429) {
                            $this->lockoutKey($apiKey, $keyLockout);
                            break; // Sortir du while → passer à la clé suivante
                        }

                        // Erreur transitoire (503 / overloaded) → retry exponentiel
                        $isTransient = ($httpStatus === 503
                            || stripos($lastError, 'overloaded') !== false
                            || stripos($lastError, 'demand') !== false);

                        if ($isTransient && $attempts < $maxAttempts) {
                            sleep($baseDelay * (int) pow(2, $attempts - 1));
                        } else {
                            if ($cbEnabled) {
                                $this->registerFailure($model);
                            }
                            break; // Clé suivante
                        }

                    } catch (\Throwable $e) {
                        $lastError = $e->getMessage();
                        $masked = '***'.substr($apiKey, -8);

                        Log::channel($logChannel)->error(
                            sprintf(
                                'EXCEPTION | Model: %s | Key: %s | Error: %s | Attempt: %d/%d',
                                $model, $masked, $lastError, $attempts, $maxAttempts
                            )
                        );

                        if ($attempts < $maxAttempts) {
                            sleep($baseDelay * (int) pow(2, $attempts - 1));
                        } else {
                            if ($cbEnabled) {
                                $this->registerFailure($model);
                            }
                            break;
                        }
                    }
                }
            }
        }

        $totalMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::channel($logChannel)->error("ALL MODELS/KEYS FAILED | Total: {$totalMs}ms | Last: {$lastError}");

        return [
            'ok' => false,
            'answer' => '',
            'error' => "Le service de l'assistant IA est momentanément très sollicité. Veuillez patienter quelques instants puis réessayer.",
            'enabled' => true,
        ];
    }

    // ─── Circuit Breaker helpers ─────────────────────────────────────────────

    private function isModelLocked(string $model): bool
    {
        return (bool) Cache::get("gemini:cb:locked:{$model}", false);
    }

    private function registerFailure(string $model): void
    {
        $maxFailures = (int) config('gemini.circuit_breaker.max_failures', 5);
        $timeWindow = (int) config('gemini.circuit_breaker.time_window_seconds', 120);
        $lockoutDuration = (int) config('gemini.circuit_breaker.lockout_duration_seconds', 300);
        $logChannel = (string) config('gemini.logging.channel', 'gemini');

        $failuresKey = "gemini:cb:failures:{$model}";
        $now = time();

        $failures = (array) Cache::get($failuresKey, []);
        $failures = array_values(array_filter($failures, fn ($ts) => ($now - $ts) < $timeWindow));
        $failures[] = $now;

        Cache::put($failuresKey, $failures, now()->addSeconds($timeWindow));

        if (count($failures) >= $maxFailures) {
            Cache::put("gemini:cb:locked:{$model}", true, now()->addSeconds($lockoutDuration));
            Log::channel($logChannel)->critical(
                "CIRCUIT BREAKER TRIGGERED | Modèle [{$model}] verrouillé {$lockoutDuration}s après ".count($failures).' échecs.'
            );
        }
    }
}
