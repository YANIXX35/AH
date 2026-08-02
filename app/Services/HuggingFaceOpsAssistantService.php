<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class HuggingFaceOpsAssistantService
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{ok: bool, answer: string, error?: string}
     */
    public function chat(array $messages): array
    {
        $geminiKey = (string) config('services.gemini.key', env('GEMINI_API_KEY', ''));
        if (! empty($geminiKey)) {
            $geminiService = new GeminiOpsAssistantService;

            return $geminiService->chat($messages);
        }

        $token = (string) config('services.huggingface.token', '');
        $model = (string) config('services.huggingface.model', 'meta-llama/Llama-3.1-8B-Instruct');
        $baseUrl = rtrim((string) config('services.huggingface.base_url', 'https://router.huggingface.co/v1'), '/');
        $timeout = (int) config('services.huggingface.timeout', 45);

        if ($token === '') {
            \Log::warning('Ni Gemini API Key ni HuggingFace token ne sont configurés.');

            return [
                'ok' => false,
                'answer' => '',
                'error' => 'GEMINI_API_KEY non configuré. Veuillez définir GEMINI_API_KEY dans le fichier .env.',
                'enabled' => false,
            ];
        }

        try {
            $response = Http::timeout($timeout)
                ->withToken($token)
                ->acceptJson()
                ->post($baseUrl.'/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => 0.2,
                    'max_tokens' => 700,
                ]);

            if (! $response->successful()) {
                \Log::error('HuggingFace API error', [
                    'status_code' => $response->status(),
                    'model' => $model,
                ]);

                return [
                    'ok' => false,
                    'answer' => '',
                    'error' => 'Erreur HF API: HTTP '.$response->status(),
                    'enabled' => true,
                ];
            }

            $json = $response->json();
            $answer = (string) data_get($json, 'choices.0.message.content', '');
            if ($answer === '') {
                \Log::warning('HuggingFace réponse vide', [
                    'model' => $model,
                ]);

                return [
                    'ok' => false,
                    'answer' => '',
                    'error' => 'Réponse vide du modèle Hugging Face.',
                    'enabled' => true,
                ];
            }

            \Log::info('HuggingFace chat réussi', [
                'model' => $model,
                'answer_length' => strlen($answer),
            ]);

            return [
                'ok' => true,
                'answer' => trim($answer),
                'enabled' => true,
            ];
        } catch (\Throwable $e) {
            \Log::error('HuggingFace chat exception', [
                'error' => $e->getMessage(),
                'model' => $model,
            ]);

            return [
                'ok' => false,
                'answer' => '',
                'error' => $e->getMessage(),
                'enabled' => true,
            ];
        }
    }
}
