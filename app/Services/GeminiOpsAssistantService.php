<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiOpsAssistantService
{
    /**
     * Envoyez une conversation avec Google Gemini API (v1beta)
     * 
     * @param array<int, array{role: string, content: string}> $messages
     * @return array{ok: bool, answer: string, error?: string, enabled: bool}
     */
    public function chat(array $messages): array
    {
        $apiKey = (string) config('services.gemini.key', env('GEMINI_API_KEY', ''));
        $preferredModel = (string) config('services.gemini.model', env('GEMINI_MODEL', 'gemini-2.0-flash'));
        $timeout = (int) config('services.gemini.timeout', 45);

        if (empty($apiKey)) {
            Log::warning('Gemini API key non configurée.');
            return [
                'ok' => false,
                'answer' => '',
                'error' => 'Clé GEMINI_API_KEY non configurée dans le fichier .env.',
                'enabled' => false,
            ];
        }

        // Modèles de secours en cas de forte affluence (High demand) sur un modèle
        $modelsToTry = array_unique([
            $preferredModel,
            'gemini-2.0-flash',
            'gemini-2.5-flash-lite',
            'gemini-2.5-flash',
            'gemini-flash-latest',
        ]);

        // Transformation du format OpenAI au format Gemini
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

        $lastError = '';

        foreach ($modelsToTry as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            try {
                $response = Http::timeout($timeout)
                    ->acceptJson()
                    ->post($url, $payload);

                if ($response->successful()) {
                    $json = $response->json();
                    $answer = (string) data_get($json, 'candidates.0.content.parts.0.text', '');

                    if ($answer !== '') {
                        Log::info('Gemini chat réponse générée avec succès', [
                            'model' => $model,
                            'length' => strlen($answer)
                        ]);

                        return [
                            'ok' => true,
                            'answer' => trim($answer),
                            'enabled' => true,
                        ];
                    }
                }

                $errorDetails = $response->json();
                $lastError = data_get($errorDetails, 'error.message', 'Erreur HTTP ' . $response->status());

                Log::warning("Gemini modèle {$model} indisponible ou en surcharge, tentative modèle suivant.", [
                    'status_code' => $response->status(),
                    'error' => $lastError,
                ]);

            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning("Gemini exception modèle {$model}: {$lastError}");
            }
        }

        return [
            'ok' => false,
            'answer' => '',
            'error' => 'Erreur Gemini API: ' . $lastError,
            'enabled' => true,
        ];
    }
}
