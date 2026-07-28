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
        $model = (string) config('services.gemini.model', env('GEMINI_MODEL', 'gemini-1.5-flash'));
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

        // Transformation du format OpenAI (system/user/assistant) au format Gemini (contents / systemInstruction)
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
                'maxOutputTokens' => 1000,
            ]
        ];

        if (!empty($systemInstructionParts)) {
            $payload['systemInstruction'] = [
                'parts' => $systemInstructionParts
            ];
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->post($url, $payload);

            if (!$response->successful()) {
                $errorDetails = $response->json();
                $errorMessage = data_get($errorDetails, 'error.message', 'Erreur HTTP ' . $response->status());

                Log::error('Gemini API error', [
                    'status_code' => $response->status(),
                    'model' => $model,
                    'details' => $errorDetails,
                ]);

                return [
                    'ok' => false,
                    'answer' => '',
                    'error' => 'Erreur Gemini API: ' . $errorMessage,
                    'enabled' => true,
                ];
            }

            $json = $response->json();
            $answer = (string) data_get($json, 'candidates.0.content.parts.0.text', '');

            if ($answer === '') {
                Log::warning('Gemini réponse vide', ['model' => $model, 'response' => $json]);
                return [
                    'ok' => false,
                    'answer' => '',
                    'error' => 'Réponse vide transmise par Google Gemini.',
                    'enabled' => true,
                ];
            }

            Log::info('Gemini chat réponse générée avec succès', ['model' => $model, 'length' => strlen($answer)]);

            return [
                'ok' => true,
                'answer' => trim($answer),
                'enabled' => true,
            ];

        } catch (\Throwable $e) {
            Log::error('Gemini chat exception', ['error' => $e->getMessage()]);

            return [
                'ok' => false,
                'answer' => '',
                'error' => 'Exception Gemini : ' . $e->getMessage(),
                'enabled' => true,
            ];
        }
    }
}
