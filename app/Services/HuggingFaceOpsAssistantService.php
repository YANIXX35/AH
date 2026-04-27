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
        $token = (string) config('services.huggingface.token', '');
        $model = (string) config('services.huggingface.model', 'meta-llama/Llama-3.1-8B-Instruct');
        $baseUrl = rtrim((string) config('services.huggingface.base_url', 'https://router.huggingface.co/v1'), '/');
        $timeout = (int) config('services.huggingface.timeout', 45);

        if ($token === '') {
            return [
                'ok' => false,
                'answer' => '',
                'error' => 'HUGGINGFACE_TOKEN non configuré.',
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
                return [
                    'ok' => false,
                    'answer' => '',
                    'error' => 'Erreur HF API: HTTP '.$response->status(),
                ];
            }

            $json = $response->json();
            $answer = (string) data_get($json, 'choices.0.message.content', '');
            if ($answer === '') {
                return [
                    'ok' => false,
                    'answer' => '',
                    'error' => 'Réponse vide du modèle Hugging Face.',
                ];
            }

            return [
                'ok' => true,
                'answer' => trim($answer),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'answer' => '',
                'error' => $e->getMessage(),
            ];
        }
    }
}

