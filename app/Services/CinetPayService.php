<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Intégration CinetPay Checkout API v2.
 *
 * @see https://docs.cinetpay.com/api/1.0-fr/checkout/initialisation
 * @see https://docs.cinetpay.com/api/1.0-fr/checkout/notification
 * @see https://docs.cinetpay.com/api/1.0-fr/checkout/verification
 * @see https://panel.cinetpay.net/sitiame-capital/developer/documentation?doc=overview
 */
class CinetPayService
{
    private const CHECKOUT_URL = 'https://api-checkout.cinetpay.com/v2/payment';

    private const CHECK_URL = 'https://api-checkout.cinetpay.com/v2/payment/check';

    /** @var list<string> */
    private const SUCCESS_CODES = ['201', '200'];

    /** @var list<string> */
    private const CHECK_SUCCESS_CODES = ['00', '0'];

    /** @var list<string> */
    private const PAID_STATUSES = ['ACCEPTED', 'SUCCES', 'SUCCESS'];

    /** @var list<string> */
    private const FAILED_STATUSES = ['REFUSED', 'FAILED', 'CANCELLED', 'CANCELED'];

    /** @var list<string> */
    private const ALLOWED_CHANNELS = ['ALL', 'MOBILE_MONEY', 'CREDIT_CARD', 'WALLET'];

    /** @var list<string> */
    private const ALLOWED_CURRENCIES = ['XOF', 'XAF', 'CDF', 'GNF', 'USD'];

    /**
     * Initialise un paiement (POST /v2/payment).
     *
     * @param  array{
     *   amount:int,
     *   currency?:string,
     *   description?:string,
     *   channels?:string,
     *   metadata?:array<string,mixed>,
     *   customer_phone?:string,
     *   customer_name?:string,
     *   customer_email?:string,
     *   customer_country?:string,
     *   customer_city?:string,
     *   customer_address?:string
     * }  $data
     * @return array{
     *   success:bool,
     *   status:string,
     *   transaction_id:string,
     *   message:string,
     *   payment_url:?string,
     *   payment_token:?string,
     *   request_payload:array<string,mixed>,
     *   response_payload:array<string,mixed>|null
     * }
     */
    public function initiatePayment(User $user, array $data): array
    {
        $transactionId = $this->generateTransactionId($user);
        $amount = $this->normalizeAmount((int) ($data['amount'] ?? 0));
        $currency = strtoupper((string) ($data['currency'] ?? config('services.cinetpay.currency', 'XOF')));

        if (! in_array($currency, self::ALLOWED_CURRENCIES, true)) {
            return $this->errorResult($transactionId, 'CONFIG_ERROR', 'Devise non supportée par CinetPay : '.$currency, []);
        }

        $channels = strtoupper((string) ($data['channels'] ?? config('services.cinetpay.channels', 'ALL')));
        if (! in_array($channels, self::ALLOWED_CHANNELS, true)) {
            $channels = 'ALL';
        }

        $description = (string) ($data['description'] ?? 'Abonnement Enterprise Premium — '.config('app.name'));
        $nameParts = $this->splitCustomerName((string) ($data['customer_name'] ?? $user->name));

        $requestPayload = [
            'apikey' => $this->apiKey(),
            'site_id' => $this->siteId(),
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => $currency,
            'description' => Str::limit($description, 255, ''),
            'notify_url' => $this->absoluteRoute('payments.cinetpay.notify'),
            'return_url' => $this->absoluteRoute('payments.cinetpay.return', [
                'transaction_id' => $transactionId,
            ]),
            'channels' => $channels,
            'lang' => (string) config('services.cinetpay.lang', 'fr'),
            'metadata' => $this->encodeMetadata(array_merge([
                'user_id' => (string) $user->id,
                'user_email' => (string) $user->email,
                'product' => 'enterprise_premium',
            ], $data['metadata'] ?? [])),
            'customer_name' => $nameParts['first'],
            'customer_surname' => $nameParts['last'],
            'customer_email' => (string) ($data['customer_email'] ?? $user->email),
            'customer_phone_number' => $this->normalizePhone((string) ($data['customer_phone'] ?? $user->phone ?? '')),
            'customer_country' => strtoupper((string) ($data['customer_country'] ?? config('services.cinetpay.customer_country', 'CI'))),
            'customer_city' => (string) ($data['customer_city'] ?? $user->city ?? ''),
            'customer_address' => (string) ($data['customer_address'] ?? $user->address ?? ''),
        ];

        $requestPayload = array_filter($requestPayload, static fn ($value) => $value !== null && $value !== '');

        if (! $this->isConfigured()) {
            if (! (bool) config('services.cinetpay.simulate_when_unconfigured', true)) {
                return $this->errorResult(
                    $transactionId,
                    'CONFIG_ERROR',
                    'CinetPay n’est pas configuré (CINETPAY_API_KEY / CINETPAY_SITE_ID).',
                    $requestPayload
                );
            }

            return [
                'success' => true,
                'status' => 'PENDING',
                'transaction_id' => $transactionId,
                'message' => 'Mode simulation CinetPay (credentials absents).',
                'payment_url' => $this->absoluteRoute('payments.cinetpay.return', [
                    'transaction_id' => $transactionId,
                    'simulate' => 'accepted',
                ]),
                'payment_token' => null,
                'request_payload' => $requestPayload,
                'response_payload' => ['simulated' => true],
            ];
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout((int) config('services.cinetpay.timeout', 30))
                ->post($this->checkoutUrl(), $requestPayload);

            $normalized = $this->normalizePayload($response->json(), $response->body());
            $code = (string) ($normalized['code'] ?? '');
            $paymentUrl = (string) ($normalized['data']['payment_url'] ?? '');
            $paymentToken = (string) ($normalized['data']['payment_token'] ?? '');

            if ($response->successful() && $paymentUrl !== '' && in_array($code, self::SUCCESS_CODES, true)) {
                return [
                    'success' => true,
                    'status' => 'PENDING',
                    'transaction_id' => $transactionId,
                    'message' => (string) ($normalized['message'] ?? 'Paiement initialisé.'),
                    'payment_url' => $paymentUrl,
                    'payment_token' => $paymentToken !== '' ? $paymentToken : null,
                    'request_payload' => $requestPayload,
                    'response_payload' => $normalized,
                ];
            }

            return $this->errorResult(
                $transactionId,
                'API_ERROR',
                (string) ($normalized['description'] ?? $normalized['message'] ?? 'Erreur lors de l’initialisation CinetPay.'),
                $requestPayload,
                $normalized
            );
        } catch (\Throwable $e) {
            return $this->errorResult($transactionId, 'EXCEPTION', $e->getMessage(), $requestPayload);
        }
    }

    /**
     * Vérifie le statut réel (POST /v2/payment/check) — obligatoire après notify/return.
     *
     * @return array{
     *   success:bool,
     *   status:string,
     *   message:string,
     *   response_payload:array<string,mixed>|null,
     *   is_paid:bool,
     *   is_failed:bool
     * }
     */
    public function checkTransactionStatus(string $transactionId): array
    {
        if ($transactionId === '') {
            return [
                'success' => false,
                'status' => 'UNKNOWN',
                'message' => 'Identifiant de transaction manquant.',
                'response_payload' => null,
                'is_paid' => false,
                'is_failed' => false,
            ];
        }

        if (! $this->isConfigured()) {
            return [
                'success' => true,
                'status' => 'ACCEPTED',
                'message' => 'Simulation locale : paiement accepté.',
                'response_payload' => ['simulated' => true],
                'is_paid' => true,
                'is_failed' => false,
            ];
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout((int) config('services.cinetpay.timeout', 30))
                ->post($this->checkUrl(), [
                    'apikey' => $this->apiKey(),
                    'site_id' => $this->siteId(),
                    'transaction_id' => $transactionId,
                ]);

            $normalized = $this->normalizePayload($response->json(), $response->body());
            $code = (string) ($normalized['code'] ?? '');
            $status = strtoupper((string) ($normalized['data']['status'] ?? 'UNKNOWN'));
            $isPaid = in_array($status, self::PAID_STATUSES, true) || in_array($code, self::CHECK_SUCCESS_CODES, true);
            $isFailed = in_array($status, self::FAILED_STATUSES, true);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'status' => 'API_ERROR',
                    'message' => (string) ($normalized['description'] ?? $normalized['message'] ?? 'Vérification CinetPay impossible.'),
                    'response_payload' => $normalized,
                    'is_paid' => false,
                    'is_failed' => false,
                ];
            }

            return [
                'success' => true,
                'status' => $status,
                'message' => (string) ($normalized['message'] ?? 'Statut récupéré.'),
                'response_payload' => $normalized,
                'is_paid' => $isPaid,
                'is_failed' => $isFailed,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 'EXCEPTION',
                'message' => $e->getMessage(),
                'response_payload' => null,
                'is_paid' => false,
                'is_failed' => false,
            ];
        }
    }

    /**
     * Extrait l’identifiant transaction depuis la notification CinetPay (cpm_trans_id).
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractTransactionIdFromNotify(array $payload): string
    {
        return trim((string) (
            $payload['cpm_trans_id']
            ?? $payload['transaction_id']
            ?? ''
        ));
    }

    /**
     * Valide la notification : site_id + optionnel HMAC (en-tête x-token).
     *
     * @param  array<string, mixed>  $payload
     */
    public function validateNotification(array $payload, ?string $xToken = null): bool
    {
        $expectedSiteId = $this->siteId();
        $receivedSiteId = trim((string) ($payload['cpm_site_id'] ?? ''));

        if ($expectedSiteId !== '' && $receivedSiteId !== '' && $receivedSiteId !== $expectedSiteId) {
            return false;
        }

        $secret = $this->secretKey();
        if ($secret === '' || $xToken === null || $xToken === '') {
            return true;
        }

        $transactionId = $this->extractTransactionIdFromNotify($payload);
        if ($transactionId === '') {
            return false;
        }

        $expectedToken = hash_hmac('sha256', $transactionId, $secret);

        return hash_equals($expectedToken, $xToken);
    }

    public function isConfigured(): bool
    {
        return $this->isEnabled()
          && $this->apiKey() !== ''
          && $this->siteId() !== '';
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.cinetpay.enabled', false);
    }

    public function subscriptionAmount(): int
    {
        return $this->normalizeAmount((int) config('services.cinetpay.subscription_amount', 15000));
    }

    protected function checkoutUrl(): string
    {
        return rtrim((string) config('services.cinetpay.checkout_url', self::CHECKOUT_URL), '/');
    }

    protected function checkUrl(): string
    {
        return rtrim((string) config('services.cinetpay.check_url', self::CHECK_URL), '/');
    }

    protected function apiKey(): string
    {
        return trim((string) config('services.cinetpay.api_key', ''));
    }

    protected function siteId(): string
    {
        return trim((string) config('services.cinetpay.site_id', ''));
    }

    protected function secretKey(): string
    {
        return trim((string) config('services.cinetpay.secret_key', ''));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function absoluteRoute(string $name, array $params = []): string
    {
        return URL::to(route($name, $params, false));
    }

    protected function generateTransactionId(User $user): string
    {
        // Identifiant unique alphanumérique (recommandation CinetPay).
        return 'SC'.$user->id.now()->format('ymdHis').Str::upper(Str::random(4));
    }

    protected function normalizeAmount(int $amount): int
    {
        $amount = max(5, $amount);
        $remainder = $amount % 5;
        if ($remainder !== 0) {
            $amount += 5 - $remainder;
        }

        return $amount;
    }

    protected function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    /**
     * @return array{first:string,last:string}
     */
    protected function splitCustomerName(string $fullName): array
    {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return ['first' => 'Client', 'last' => 'Sitiame'];
        }

        $parts = preg_split('/\s+/', $fullName, 2) ?: [];

        return [
            'first' => $parts[0] ?? $fullName,
            'last' => $parts[1] ?? $parts[0],
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function encodeMetadata(array $metadata): string
    {
        return (string) json_encode($metadata, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizePayload(mixed $json, string $rawBody): array
    {
        if (is_array($json)) {
            return $json;
        }

        return ['raw' => $rawBody];
    }

    /**
     * @param  array<string, mixed>  $requestPayload
     * @return array{
     *   success:bool,
     *   status:string,
     *   transaction_id:string,
     *   message:string,
     *   payment_url:null,
     *   payment_token:null,
     *   request_payload:array<string,mixed>,
     *   response_payload:array<string,mixed>|null
     * }
     */
    protected function errorResult(
        string $transactionId,
        string $status,
        string $message,
        array $requestPayload,
        ?array $responsePayload = null
    ): array {
        return [
            'success' => false,
            'status' => $status,
            'transaction_id' => $transactionId,
            'message' => $message,
            'payment_url' => null,
            'payment_token' => null,
            'request_payload' => $requestPayload,
            'response_payload' => $responsePayload,
        ];
    }
}
