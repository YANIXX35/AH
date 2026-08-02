<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FedaPaySandboxService
{
    /**
     * Indicatifs internationaux par code pays ISO alpha-3.
     *
     * @var array<string, string>
     */
    private const COUNTRY_DIAL_CODES = [
        'CIV' => '225',
        'SEN' => '221',
        'BEN' => '229',
        'TGO' => '228',
        'CMR' => '237',
    ];

    private const API_PREFIX = '/v1';

    /**
     * @param  array{amount:numeric,currency:string,country:string,correspondent:string,payer_msisdn:string}  $data
     * @return array{
     *   success:bool,
     *   status:string,
     *   provider_reference:string,
     *   message:string,
     *   request_payload:array<string,mixed>,
     *   response_payload:array<string,mixed>|null
     * }
     */
    public function createTransaction(User $user, array $data): array
    {
        $providerReference = (string) Str::uuid();
        $country = strtoupper((string) $data['country']);
        $currency = strtoupper((string) $data['currency']);
        $amount = (int) round((float) $data['amount']);
        $normalizedMsisdn = $this->normalizeMsisdn((string) $data['payer_msisdn'], $country);

        $requestPayload = [
            'description' => 'Test sandbox '.config('app.name'),
            'amount' => $amount,
            'currency' => ['iso' => $currency],
            'callback_url' => route('payments.sandbox.callback'),
            'metadata' => [
                'provider_reference' => $providerReference,
                'country' => $country,
                'operator' => (string) $data['correspondent'],
                'env' => 'sandbox',
            ],
            'customer' => [
                'firstname' => (string) Str::of((string) $user->name)->before(' '),
                'lastname' => (string) Str::of((string) $user->name)->after(' '),
                'email' => (string) $user->email,
                'phone_number' => $normalizedMsisdn,
                'country' => $country,
            ],
            'payment_method' => (string) $data['correspondent'],
        ];

        $enabled = (bool) config('services.fedapay.sandbox.enabled', false);
        $apiKey = trim((string) config('services.fedapay.sandbox.api_key'));
        $baseUrl = rtrim((string) config('services.fedapay.sandbox.base_url', 'https://sandbox-api.fedapay.com'), '/');

        if (! $enabled) {
            return [
                'success' => true,
                'status' => 'PENDING',
                'provider_reference' => $providerReference,
                'message' => 'Mode sandbox fictif actif : transaction FedaPay simulée localement.',
                'checkout_url' => route('payments.sandbox'),
                'request_payload' => $requestPayload,
                'response_payload' => [
                    'simulated' => true,
                    'reason' => 'FEDAPAY_SANDBOX_ENABLED=false',
                    'actor' => $user->email,
                ],
            ];
        }

        if ($apiKey === '') {
            return [
                'success' => false,
                'status' => 'CONFIG_ERROR',
                'provider_reference' => $providerReference,
                'message' => 'Clé API FedaPay sandbox absente.',
                'checkout_url' => null,
                'request_payload' => $requestPayload,
                'response_payload' => null,
            ];
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($apiKey)
                ->acceptJson()
                ->timeout(15)
                ->post(self::API_PREFIX.'/transactions', $requestPayload);

            $payload = $response->json();
            $normalizedPayload = is_array($payload) ? $payload : ['raw' => $response->body()];

            if ($response->successful()) {
                \Log::info('FedaPay transaction créée avec succès', [
                    'user_id' => $user->id,
                    'provider_reference' => $providerReference,
                    'status' => $normalizedPayload['transaction']['status'] ?? 'unknown',
                ]);
                $transaction = $normalizedPayload['transaction'] ?? $normalizedPayload['data'] ?? $normalizedPayload;
                $transactionId = (int) ($transaction['id'] ?? 0);
                if ($transactionId <= 0) {
                    return [
                        'success' => false,
                        'status' => 'API_ERROR',
                        'provider_reference' => $providerReference,
                        'message' => 'Création transaction FedaPay sans identifiant exploitable.',
                        'checkout_url' => null,
                        'request_payload' => $requestPayload,
                        'response_payload' => $normalizedPayload,
                    ];
                }

                $tokenResponse = Http::baseUrl($baseUrl)
                    ->withToken($apiKey)
                    ->acceptJson()
                    ->timeout(15)
                    ->post(self::API_PREFIX.'/transactions/'.$transactionId.'/token');
                $tokenPayload = $tokenResponse->json();
                $normalizedTokenPayload = is_array($tokenPayload) ? $tokenPayload : ['raw' => $tokenResponse->body()];
                $checkoutUrl = (string) ($normalizedTokenPayload['url'] ?? $normalizedTokenPayload['token']['url'] ?? '');

                if (! $tokenResponse->successful() || $checkoutUrl === '') {
                    return [
                        'success' => false,
                        'status' => 'API_ERROR',
                        'provider_reference' => (string) $transactionId,
                        'message' => (string) ($normalizedTokenPayload['message'] ?? 'Lien de paiement FedaPay introuvable.'),
                        'checkout_url' => null,
                        'request_payload' => $requestPayload,
                        'response_payload' => [
                            'transaction' => $normalizedPayload,
                            'token' => $normalizedTokenPayload,
                        ],
                    ];
                }

                return [
                    'success' => true,
                    'status' => strtoupper((string) ($transaction['status'] ?? 'PENDING')),
                    'provider_reference' => (string) $transactionId,
                    'message' => 'Transaction FedaPay créée. Redirection vers la page de paiement.',
                    'checkout_url' => $checkoutUrl,
                    'request_payload' => $requestPayload,
                    'response_payload' => [
                        'transaction' => $normalizedPayload,
                        'token' => $normalizedTokenPayload,
                    ],
                ];
            }

            $errorMessage = (string) ($normalizedPayload['message'] ?? $normalizedPayload['error'] ?? 'Erreur API FedaPay sandbox.');

            \Log::error('FedaPay transaction error', [
                'user_id' => $user->id,
                'provider_reference' => $providerReference,
                'error' => $errorMessage,
                'status_code' => $response->status(),
            ]);

            return [
                'success' => false,
                'status' => 'API_ERROR',
                'provider_reference' => $providerReference,
                'message' => $errorMessage,
                'checkout_url' => null,
                'request_payload' => $requestPayload,
                'response_payload' => $normalizedPayload,
                'enabled' => true,
            ];
        } catch (\Throwable $e) {
            \Log::error('FedaPay transaction exception', [
                'user_id' => $user->id,
                'provider_reference' => $providerReference,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 'EXCEPTION',
                'provider_reference' => $providerReference,
                'message' => $e->getMessage(),
                'checkout_url' => null,
                'request_payload' => $requestPayload,
                'response_payload' => null,
                'enabled' => true,
            ];
        }
    }

    /**
     * @return array{
     *   success:bool,
     *   provider_reference:string,
     *   status:string,
     *   message:string,
     *   response_payload:array<string,mixed>|null
     * }
     */
    public function fetchTransaction(string $providerReference): array
    {
        $apiKey = trim((string) config('services.fedapay.sandbox.api_key'));
        $baseUrl = rtrim((string) config('services.fedapay.sandbox.base_url', 'https://sandbox-api.fedapay.com'), '/');

        if ($providerReference === '') {
            return [
                'success' => false,
                'provider_reference' => '',
                'status' => 'UNKNOWN',
                'message' => 'Référence transaction manquante.',
                'response_payload' => null,
            ];
        }

        if ($apiKey === '') {
            return [
                'success' => false,
                'provider_reference' => $providerReference,
                'status' => 'CONFIG_ERROR',
                'message' => 'Clé API FedaPay sandbox absente.',
                'response_payload' => null,
            ];
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($apiKey)
                ->acceptJson()
                ->timeout(15)
                ->get(self::API_PREFIX.'/transactions/'.$providerReference);

            $payload = $response->json();
            $normalizedPayload = is_array($payload) ? $payload : ['raw' => $response->body()];
            if (! $response->successful()) {
                return [
                    'success' => false,
                    'provider_reference' => $providerReference,
                    'status' => 'API_ERROR',
                    'message' => (string) ($normalizedPayload['message'] ?? 'Impossible de récupérer la transaction FedaPay.'),
                    'response_payload' => $normalizedPayload,
                ];
            }

            $transaction = $normalizedPayload['transaction'] ?? $normalizedPayload['data'] ?? $normalizedPayload;
            $status = strtoupper((string) ($transaction['status'] ?? $normalizedPayload['status'] ?? 'UNKNOWN'));

            return [
                'success' => true,
                'provider_reference' => $providerReference,
                'status' => $status,
                'message' => 'Statut transaction récupéré.',
                'response_payload' => $normalizedPayload,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'provider_reference' => $providerReference,
                'status' => 'EXCEPTION',
                'message' => $e->getMessage(),
                'response_payload' => null,
            ];
        }
    }

    /**
     * @return array{
     *   success:bool,
     *   message:string,
     *   events:array<int, array{
     *      id:string,
     *      name:string,
     *      date:string,
     *      transaction_reference:string,
     *      transaction_status:string,
     *      amount:string,
     *      failure_reason:string
     *   }>
     * }
     */
    public function fetchRecentEvents(int $limit = 20): array
    {
        $apiKey = trim((string) config('services.fedapay.sandbox.api_key'));
        $baseUrl = rtrim((string) config('services.fedapay.sandbox.base_url', 'https://sandbox-api.fedapay.com'), '/');

        if ($apiKey === '') {
            \Log::warning('FedaPay fetchRecentEvents: API key manquante');

            return [
                'success' => false,
                'message' => 'Clé API FedaPay sandbox absente.',
                'events' => [],
                'enabled' => false,
            ];
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($apiKey)
                ->acceptJson()
                ->timeout(15)
                ->get(self::API_PREFIX.'/events', [
                    'per_page' => max(1, min($limit, 100)),
                ]);

            $payload = $response->json();
            $normalizedPayload = is_array($payload) ? $payload : [];
            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => (string) ($normalizedPayload['message'] ?? 'Impossible de récupérer les événements FedaPay.'),
                    'events' => [],
                ];
            }

            $rawEvents = $normalizedPayload['v1/events']
                ?? $normalizedPayload['events']
                ?? $normalizedPayload['data']
                ?? $normalizedPayload['items']
                ?? [];
            if (! is_array($rawEvents)) {
                $rawEvents = [];
            }

            $events = [];
            foreach ($rawEvents as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $entityRaw = $item['entity'] ?? null;
                $entity = [];
                if (is_string($entityRaw) && $entityRaw !== '') {
                    $decoded = json_decode($entityRaw, true);
                    if (is_array($decoded)) {
                        $entity = $decoded;
                    }
                } elseif (is_array($entityRaw)) {
                    $entity = $entityRaw;
                }

                $events[] = [
                    'id' => (string) ($item['id'] ?? ''),
                    'name' => (string) ($item['type'] ?? $item['name'] ?? $item['event'] ?? 'event.unknown'),
                    'date' => (string) ($item['created_at'] ?? $item['date'] ?? ''),
                    'transaction_reference' => (string) ($entity['reference'] ?? ''),
                    'transaction_status' => strtoupper((string) ($entity['status'] ?? '')),
                    'amount' => (string) ($entity['amount'] ?? ''),
                    'failure_reason' => (string) ($entity['last_error_message'] ?? ''),
                ];
            }

            return [
                'success' => true,
                'message' => 'Événements FedaPay récupérés.',
                'events' => $events,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'events' => [],
            ];
        }
    }

    protected function normalizeMsisdn(string $rawMsisdn, string $country): string
    {
        $digits = preg_replace('/\D+/', '', $rawMsisdn) ?? '';
        $dialCode = self::COUNTRY_DIAL_CODES[$country] ?? '';

        if ($digits === '') {
            return '';
        }

        if ($dialCode !== '' && str_starts_with($digits, $dialCode)) {
            return $digits;
        }

        $trimmed = ltrim($digits, '0');
        if ($dialCode !== '') {
            return $dialCode.$trimmed;
        }

        return $trimmed;
    }
}
