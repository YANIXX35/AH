<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\GeminiOpsAssistantService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class GeminiOpsAssistantServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        
        // Configurer des valeurs prévisibles pour les tests
        Config::set('gemini.key', 'fake-gemini-key');
        Config::set('gemini.extra_keys', []);
        Config::set('gemini.default_model', 'gemini-2.0-flash');
        Config::set('gemini.fallback_models', ['gemini-2.5-flash-lite', 'gemini-2.5-flash']);
        Config::set('gemini.timeout', 2);
        Config::set('gemini.connect_timeout', 1);
        Config::set('gemini.retry.max_attempts', 3);
        Config::set('gemini.retry.base_delay_seconds', 0); // Pas de délai pour que le test s'exécute instantanément
        Config::set('gemini.cache.enabled', true);
        Config::set('gemini.cache.ttl_minutes', 10);
        Config::set('gemini.circuit_breaker.enabled', true);
        Config::set('gemini.circuit_breaker.max_failures', 3);
        Config::set('gemini.circuit_breaker.time_window_seconds', 60);
        Config::set('gemini.circuit_breaker.lockout_duration_seconds', 30);
    }

    /**
     * Teste le fonctionnement normal de l'assistant IA avec succès.
     */
    public function test_chat_success(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Réponse factuelle de test']
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $service = new GeminiOpsAssistantService();
        $result = $service->chat([
            ['role' => 'user', 'content' => 'Bonjour']
        ]);

        $this->assertTrue($result['ok']);
        $this->assertEquals('Réponse factuelle de test', $result['answer']);
    }

    /**
     * Teste le système de cache de requêtes.
     */
    public function test_chat_caching(): void
    {
        $callCount = 0;
        Http::fake(function () use (&$callCount) {
            $callCount++;
            return Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Réponse numéro ' . $callCount]
                            ]
                        ]
                    ]
                ]
            ], 200);
        });

        $service = new GeminiOpsAssistantService();
        $messages = [['role' => 'user', 'content' => 'Bonjour unique']];

        // Premier appel (miss cache)
        $res1 = $service->chat($messages);
        // Deuxième appel (hit cache)
        $res2 = $service->chat($messages);

        $this->assertEquals(1, $callCount);
        $this->assertEquals($res1['answer'], $res2['answer']);
    }

    /**
     * Teste le retry et le failover vers un modèle de secours
     * si le modèle par défaut renvoie des erreurs transitoires.
     */
    public function test_chat_retry_and_failover(): void
    {
        $requests = [];
        Http::fake(function ($request) use (&$requests) {
            $url = $request->url();
            $requests[] = $url;

            // Simuler des erreurs temporaires pour gemini-2.0-flash
            if (str_contains($url, 'models/gemini-2.0-flash:generateContent')) {
                return Http::response([
                    'error' => [
                        'message' => 'This model is currently experiencing high demand.'
                    ]
                ], 503);
            }

            // Répondre avec succès pour le modèle de secours
            if (str_contains($url, 'models/gemini-2.5-flash-lite:generateContent')) {
                return Http::response([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => 'Réponse du modèle secours lite']
                                ]
                            ]
                        ]
                    ]
                ], 200);
            }

            return Http::response([], 500);
        });

        $service = new GeminiOpsAssistantService();
        $result = $service->chat([
            ['role' => 'user', 'content' => 'Bonjour avec erreur']
        ]);

        $this->assertTrue($result['ok']);
        $this->assertEquals('Réponse du modèle secours lite', $result['answer']);

        // Vérifier que gemini-2.0-flash a été tenté (3 fois configuré dans setUp), puis gemini-2.5-flash-lite
        $flashAttempts = array_filter($requests, fn($url) => str_contains($url, 'gemini-2.0-flash'));
        $liteAttempts = array_filter($requests, fn($url) => str_contains($url, 'gemini-2.5-flash-lite'));

        $this->assertEquals(3, count($flashAttempts));
        $this->assertEquals(1, count($liteAttempts));
    }

    /**
     * Teste le Circuit Breaker : quand un modèle échoue trop souvent,
     * il est verrouillé et directement ignoré lors des appels suivants.
     */
    public function test_circuit_breaker_lockout(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent*' => Http::response([
                'error' => [
                    'message' => 'Quota ou surcharge'
                ]
            ], 503),
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Succès secours pendant CB']
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $service = new GeminiOpsAssistantService();

        // Lancer 3 appels distincts (max_failures=3) pour bloquer le modèle
        $service->chat([['role' => 'user', 'content' => 'Bloquer CB 1']]);
        $service->chat([['role' => 'user', 'content' => 'Bloquer CB 2']]);
        $service->chat([['role' => 'user', 'content' => 'Bloquer CB 3']]);

        // Vérifier que le modèle par défaut a été verrouillé suite aux 3 échecs (max_failures=3)
        $this->assertTrue(Cache::get('gemini:cb:locked:gemini-2.0-flash', false));

        // Réinitialiser les mocks HTTP pour renvoyer un succès
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent*' => Http::response([
                'error' => [
                    'message' => 'Quota ou surcharge'
                ]
            ], 503),
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Succès après CB']
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        // Lancer un deuxième appel différent pour contourner le cache de requêtes
        $result = $service->chat([['role' => 'user', 'content' => 'Autre question post CB']]);

        // La réponse doit provenir du modèle gemini-2.5-flash-lite car gemini-2.0-flash est verrouillé et ignoré
        $this->assertTrue($result['ok']);
        $this->assertEquals('Succès après CB', $result['answer']);
    }
}
