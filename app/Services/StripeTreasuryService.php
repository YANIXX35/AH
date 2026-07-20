<?php

namespace App\Services;

use App\Models\TreasuryTransaction;
use App\Models\User;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeTreasuryService
{
    public function enabled(): bool
    {
        return (bool) config('services.stripe.enabled', false)
            && (string) config('services.stripe.secret', '') !== '';
    }

    public function createCheckoutSession(TreasuryTransaction $transaction, User $user, array $urls): array
    {
        if (! $this->enabled()) {
            \Log::warning('Stripe non configuré - checkout session impossible', [
                'transaction_id' => $transaction->id,
                'user_id' => $user->id,
            ]);
            
            return [
                'error' => 'Stripe non configuré. Configurez STRIPE_ENABLED=true et STRIPE_SECRET.',
                'enabled' => false,
            ];
        }

        try {
            $client = $this->client();
            $channel = (string) ($transaction->stripe_payment_channel ?? 'card');
            $scheme = (string) ($transaction->stripe_bank_scheme ?? '');
            [$currency, $paymentMethodTypes] = $this->resolveCheckoutPaymentSetup($channel, $scheme);
            $amountInMinor = $this->toStripeAmount((float) $transaction->amount, $currency);

            $session = $client->checkout->sessions->create([
                'mode' => 'payment',
                'payment_method_types' => $paymentMethodTypes,
                'customer_email' => $user->email,
                'success_url' => $urls['success_url'].'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $urls['cancel_url'],
                'metadata' => [
                    'treasury_transaction_id' => (string) $transaction->id,
                    'workspace_user_id' => (string) $transaction->user_id,
                    'type' => (string) $transaction->type,
                    'stripe_payment_channel' => $channel,
                    'stripe_bank_scheme' => $scheme,
                ],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => $amountInMinor,
                        'product_data' => [
                            'name' => 'Transaction de trésorerie #'.$transaction->id,
                            'description' => (string) $transaction->description,
                        ],
                    ],
                ]],
            ]);

            return [
                'id' => (string) $session->id,
                'url' => (string) $session->url,
                'payment_status' => (string) ($session->payment_status ?? ''),
                'enabled' => true,
            ];
        } catch (\Throwable $e) {
            \Log::error('Stripe checkout session error', [
                'transaction_id' => $transaction->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'error' => 'Erreur Stripe: '.$e->getMessage(),
                'enabled' => true,
            ];
        }
    }

    public function retrieveCheckoutSession(string $sessionId): array
    {
        if (! $this->enabled()) {
            \Log::warning('Stripe non configuré - retrieve session impossible', [
                'session_id' => $sessionId,
            ]);
            
            return [
                'error' => 'Stripe non configuré',
                'enabled' => false,
            ];
        }

        try {
            $client = $this->client();
            $session = $client->checkout->sessions->retrieve($sessionId, [
                'expand' => ['payment_intent.latest_charge'],
            ]);

            $paymentIntent = $session->payment_intent;
            $latestCharge = is_object($paymentIntent) ? ($paymentIntent->latest_charge ?? null) : null;

            return [
                'id' => (string) $session->id,
                'payment_status' => (string) ($session->payment_status ?? ''),
                'status' => (string) ($session->status ?? ''),
                'payment_intent_id' => is_string($paymentIntent) ? $paymentIntent : (string) ($paymentIntent->id ?? ''),
                'charge_id' => is_string($latestCharge) ? $latestCharge : (string) ($latestCharge->id ?? ''),
                'metadata' => (array) ($session->metadata ?? []),
                'enabled' => true,
            ];
        } catch (\Throwable $e) {
            \Log::error('Stripe retrieve session error', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'error' => 'Erreur Stripe: '.$e->getMessage(),
                'enabled' => true,
            ];
        }
    }

    public function createPayout(TreasuryTransaction $transaction): array
    {
        if (! $this->enabled()) {
            \Log::warning('Stripe non configuré - payout impossible', [
                'transaction_id' => $transaction->id,
            ]);
            
            return [
                'error' => 'Stripe non configuré',
                'enabled' => false,
            ];
        }

        try {
            $client = $this->client();
            $scheme = (string) ($transaction->stripe_bank_scheme ?? 'sepa');
            $fallbackCurrencies = array_values(array_unique(array_filter([
                $this->resolvePayoutCurrency(),
                'usd',
                'eur',
            ])));
            $lastException = null;

            foreach ($fallbackCurrencies as $currency) {
                try {
                    $amountInMinor = $this->toStripeAmount((float) $transaction->amount, $currency);
                    $payout = $client->payouts->create([
                        'amount' => $amountInMinor,
                        'currency' => $currency,
                        'metadata' => [
                            'treasury_transaction_id' => (string) $transaction->id,
                            'workspace_user_id' => (string) $transaction->user_id,
                            'type' => (string) $transaction->type,
                            'stripe_payment_channel' => (string) ($transaction->stripe_payment_channel ?? 'bank_debit'),
                            'stripe_bank_scheme' => $scheme,
                        ],
                    ]);

                    return [
                        'id' => (string) $payout->id,
                        'status' => (string) ($payout->status ?? 'pending'),
                        'arrival_date' => isset($payout->arrival_date) ? (int) $payout->arrival_date : null,
                        'currency' => $currency,
                        'enabled' => true,
                    ];
                } catch (\Throwable $e) {
                    if (! $this->isCurrencyAvailabilityError($e)) {
                        throw $e;
                    }
                    $lastException = $e;
                }
            }

            if ($lastException !== null) {
                throw $lastException;
            }

            throw new \RuntimeException('Aucune devise Stripe disponible pour le décaissement.');
        } catch (\Throwable $e) {
            \Log::error('Stripe payout error', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'error' => 'Erreur Stripe: '.$e->getMessage(),
                'enabled' => true,
            ];
        }
    }

    /**
     * @throws UnexpectedValueException
     * @throws SignatureVerificationException
     */
    public function constructWebhookEvent(string $payload, ?string $signatureHeader): Event
    {
        if (! $this->enabled()) {
            \Log::warning('Stripe non configuré - webhook impossible');
            throw new UnexpectedValueException('Webhook Stripe non configuré.');
        }

        $secret = (string) config('services.stripe.webhook_secret', '');
        if ($secret === '' || $signatureHeader === null || $signatureHeader === '') {
            \Log::warning('Stripe webhook secret manquant');
            throw new UnexpectedValueException('Webhook Stripe non configuré.');
        }

        try {
            return Webhook::constructEvent($payload, $signatureHeader, $secret);
        } catch (\Throwable $e) {
            \Log::error('Stripe webhook construction error', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function client(): StripeClient
    {
        $secret = (string) config('services.stripe.secret', '');
        if ($secret === '') {
            throw new \RuntimeException('Stripe secret key non configurée');
        }
        
        return new StripeClient($secret);
    }

    /**
     * @return array{0:string,1:array<int,string>}
     */
    private function resolveCheckoutPaymentSetup(string $channel, string $scheme): array
    {
        if ($channel === 'bank_debit' && $scheme === 'sepa') {
            return ['eur', ['sepa_debit']];
        }

        if ($channel === 'bank_debit' && $scheme === 'ach') {
            return ['usd', ['us_bank_account']];
        }

        return [strtolower((string) config('services.stripe.currency', 'xof')), ['card']];
    }

    private function resolvePayoutCurrency(): string
    {
        return strtolower((string) config('services.stripe.payout_currency', config('services.stripe.currency', 'xof')));
    }

    private function toStripeAmount(float $amount, string $currency): int
    {
        $currency = strtolower($currency);
        $zeroDecimalCurrencies = [
            'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg',
            'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf',
        ];

        if (in_array($currency, $zeroDecimalCurrencies, true)) {
            return (int) round($amount);
        }

        return (int) round($amount * 100);
    }

    private function isCurrencyAvailabilityError(\Throwable $e): bool
    {
        $message = strtolower((string) $e->getMessage());

        return str_contains($message, 'external accounts in that currency')
            || (str_contains($message, 'currency') && str_contains($message, 'not supported'));
    }
}
