<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Models\SubscriptionHistory;
use App\Models\User;
use App\Services\CinetPayService;
use App\Services\FedaPaySandboxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * NOTE COMPLIANCE (2026-07-13) : cette initiation de paiement Mobile Money (écriture,
 * mouvement de fonds) déroge à la note de cadrage produit (Cadrage_PME360.docx), qui
 * classe ce niveau d'intégration WON'T pour l'instant — il exige un agrément
 * d'établissement de paiement et une conformité KYC/AML, contrairement à la
 * réconciliation en lecture seule (voir App\Domain\Treasury\MobileMoney). Décision :
 * documenter et ne pas désactiver tant qu'aucun utilisateur n'est impacté ; toute
 * extension de ce module doit d'abord repasser par une revue réglementaire.
 */
class FedaPaySandboxController extends Controller
{
    protected function isApprovedStatus(?string $status): bool
    {
        return in_array(strtoupper((string) $status), ['APPROVED', 'COMPLETED'], true);
    }

    protected function isFailureStatus(?string $status): bool
    {
        return in_array(strtoupper((string) $status), ['FAILED', 'REJECTED', 'CANCELED', 'DECLINED'], true);
    }

    protected function extractProviderReference(Request $request): string
    {
        $candidates = [
            (string) $request->query('id', ''),
            (string) $request->query('transaction_id', ''),
            (string) $request->query('transactionId', ''),
            (string) $request->query('reference', ''),
        ];

        foreach ($candidates as $value) {
            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  array<string,mixed>|null  $payload
     * @return array{amount:float,currency:string,country:string,correspondent:string,payer_msisdn:string}
     */
    protected function mapPayloadForLocalTransaction(?array $payload): array
    {
        $transaction = $payload['transaction'] ?? $payload['data'] ?? $payload ?? [];
        $customer = is_array($transaction['customer'] ?? null) ? $transaction['customer'] : [];
        $metadata = is_array($transaction['metadata'] ?? null) ? $transaction['metadata'] : [];
        $currencyRaw = $transaction['currency']['iso'] ?? $transaction['currency'] ?? '';

        return [
            'amount' => (float) ($transaction['amount'] ?? 15000),
            'currency' => strtoupper((string) $currencyRaw) ?: 'XOF',
            'country' => strtoupper((string) ($customer['country'] ?? $metadata['country'] ?? 'CIV')),
            'correspondent' => (string) ($transaction['payment_method'] ?? $metadata['operator'] ?? 'wave'),
            'payer_msisdn' => (string) ($customer['phone_number'] ?? ''),
        ];
    }

    /**
     * @return array<string, array{currency:string, correspondents:array<string, string>}>
     */
    protected function gatewayConfig(): array
    {
        return [
            'CIV' => [
                'currency' => 'XOF',
                'correspondents' => [
                    'wave' => 'WAVE',
                    'mobile_money_ci' => 'Mobile Money CI',
                    'card' => 'Carte bancaire',
                ],
            ],
            'SEN' => [
                'currency' => 'XOF',
                'correspondents' => [
                    'wave' => 'WAVE',
                    'mobile_money_sen' => 'Mobile Money SEN',
                    'card' => 'Carte bancaire',
                ],
            ],
            'BEN' => [
                'currency' => 'XOF',
                'correspondents' => [
                    'mobile_money_ben' => 'Mobile Money BEN',
                    'card' => 'Carte bancaire',
                ],
            ],
            'CMR' => [
                'currency' => 'XAF',
                'correspondents' => [
                    'mobile_money_cmr' => 'Mobile Money CMR',
                    'card' => 'Carte bancaire',
                ],
            ],
        ];
    }

    public function index(Request $request, FedaPaySandboxService $service, CinetPayService $cinetPay): View
    {
        $user = $request->user();
        $eventResult = ['success' => true, 'events' => [], 'message' => null];

        $fixedAmount = $cinetPay->isEnabled()
            ? $cinetPay->subscriptionAmount()
            : 15000;

        $localTransactions = PaymentTransaction::query()
            ->whereIn('provider', ['fedapay_sandbox', 'cinetpay'])
            ->where('user_id', (int) $user->id)
            ->latest('id')
            ->limit(20)
            ->get();

        $localEvents = $localTransactions->map(function (PaymentTransaction $tx): array {
            return [
                'id' => (string) $tx->id,
                'name' => 'local.payment_transaction',
                'transaction_reference' => (string) ($tx->provider_reference ?? ''),
                'transaction_status' => (string) ($tx->status ?? ''),
                'amount' => number_format((float) $tx->amount, 0, ',', ' '),
                'failure_reason' => (string) ($tx->failure_reason ?? ''),
                'date' => optional($tx->created_at)?->format('Y-m-d H:i:s') ?? '',
            ];
        })->values()->all();

        // Sécurité: un utilisateur standard ne voit jamais les événements d'une autre entreprise.
        // Les admins peuvent consulter le flux fournisseur brut pour supervision.
        if ($user && $user->isPlatformAdmin()) {
            $remoteResult = $service->fetchRecentEvents(20);
            $eventResult = [
                'success' => (bool) $remoteResult['success'],
                'events' => $remoteResult['success'] ? ($remoteResult['events'] ?? []) : $localEvents,
                'message' => $remoteResult['success'] ? null : ($remoteResult['message'] ?? null),
            ];
        } else {
            $eventResult = [
                'success' => true,
                'events' => $localEvents,
                'message' => null,
            ];
        }

        $paymentRedirectUrl = trim((string) config('services.fedapay.sandbox.payment_page_url', ''));
        $isHostedPaymentMode = filter_var($paymentRedirectUrl, FILTER_VALIDATE_URL) !== false;
        $canPaySubscription = $user !== null
            && ! $user->isPlatformAdmin()
            && ! ($user->is_accountant ?? false);

        return view('payments.sandbox', [
            'useCinetPay' => $cinetPay->isEnabled(),
            'isCinetPayConfigured' => $cinetPay->isConfigured(),
            'cinetPayMobileMethods' => config('services.cinetpay.mobile_methods', []),
            'isFedaPaySandboxEnabled' => (bool) config('services.fedapay.sandbox.enabled', false),
            'isHostedPaymentMode' => $isHostedPaymentMode && ! $cinetPay->isEnabled(),
            'paymentAmount' => $fixedAmount,
            'paymentRedirectUrl' => $paymentRedirectUrl,
            'mobileMethods' => config('services.fedapay.sandbox.mobile_methods', []),
            'gatewayConfig' => $this->gatewayConfig(),
            'defaultCountry' => 'CIV',
            'defaultPhone' => (string) ($user->phone ?? ''),
            'canPaySubscription' => $canPaySubscription,
            'isPremiumActive' => $user?->hasActivePremiumPeriod() ?? false,
            'premiumEndsAt' => $user?->premium_ends_at,
            'fedapayEvents' => $eventResult['events'],
            'fedapayEventsError' => $eventResult['success'] ? null : $eventResult['message'],
        ]);
    }

    public function store(Request $request, FedaPaySandboxService $service): RedirectResponse
    {
        $user = $request->user();
        $config = $this->gatewayConfig();
        $countries = array_keys($config);
        $fixedAmount = 15000;

        $data = $request->validate([
            'amount' => ['required', 'integer', Rule::in([$fixedAmount])],
            'country' => ['required', Rule::in($countries)],
            'correspondent' => ['required', 'string', 'max:80'],
            'payer_msisdn' => ['required', 'string', 'min:8', 'max:20'],
        ]);

        $allowedCorrespondents = array_keys($config[$data['country']]['correspondents'] ?? []);
        if (! in_array($data['correspondent'], $allowedCorrespondents, true)) {
            return back()->withErrors([
                'fedapay' => 'Moyen de paiement invalide pour le pays sélectionné.',
            ])->withInput();
        }

        $result = $service->createTransaction($user, [
            ...$data,
            'currency' => $config[$data['country']]['currency'],
        ]);

        $transaction = PaymentTransaction::create([
            'user_id' => (int) $user->id,
            'provider' => 'fedapay_sandbox',
            'provider_reference' => $result['provider_reference'],
            'status' => $result['status'],
            'amount' => (float) $data['amount'],
            'currency' => $config[$data['country']]['currency'],
            'country' => $data['country'],
            'correspondent' => $data['correspondent'],
            'payer_msisdn' => (string) ($result['request_payload']['customer']['phone_number'] ?? $data['payer_msisdn']),
            'request_payload' => $result['request_payload'],
            'response_payload' => $result['response_payload'],
            'failure_reason' => $result['success'] ? null : $result['message'],
        ]);

        if (! $result['success']) {
            return redirect()
                ->route('payments.sandbox.result', ['transaction' => $transaction->id, 'state' => 'failed'])
                ->with('result_error', 'Test FedaPay échoué : '.$result['message']);
        }

        $checkoutUrl = (string) ($result['checkout_url'] ?? '');
        if ($checkoutUrl !== '') {
            return redirect()->away($checkoutUrl);
        }

        return redirect()
            ->route('payments.sandbox.result', ['transaction' => $transaction->id, 'state' => 'pending'])
            ->with('status', 'Transaction créée (réf: '.$transaction->provider_reference.').');
    }

    public function result(Request $request): View
    {
        $transactionId = (int) $request->query('transaction', 0);
        $transaction = $transactionId > 0
            ? PaymentTransaction::query()
                ->where('id', $transactionId)
                ->where('user_id', $request->user()->id)
                ->first()
            : null;

        $state = (string) $request->query('state', 'pending');
        $status = strtoupper((string) ($transaction?->status ?? 'PENDING'));
        $isSuccess = in_array($status, ['APPROVED', 'COMPLETED', 'ACCEPTED', 'SUCCESS'], true) || $state === 'success';
        $isFailure = in_array($status, ['FAILED', 'REJECTED', 'CANCELED', 'DECLINED', 'REFUSED'], true) || $state === 'failed';

        return view('payments.result', [
            'transaction' => $transaction,
            'isSuccess' => $isSuccess,
            'isFailure' => $isFailure,
            'isPremium' => (bool) ($request->user()->is_premium ?? false),
        ]);
    }

    public function callback(Request $request, FedaPaySandboxService $service): RedirectResponse
    {
        $providerReference = $this->extractProviderReference($request);
        if ($providerReference === '') {
            return redirect()
                ->route('payments.sandbox.result', ['state' => 'failed'])
                ->with('result_error', 'Retour FedaPay reçu sans identifiant transaction.');
        }

        $transaction = PaymentTransaction::query()
            ->where('provider', 'fedapay_sandbox')
            ->where('provider_reference', $providerReference)
            ->latest('id')
            ->first();
        $fetched = $service->fetchTransaction($providerReference);
        $status = strtoupper((string) ($fetched['status'] ?? 'UNKNOWN'));
        if ($transaction === null && ! $fetched['success']) {
            return redirect()
                ->route('payments.sandbox.result', ['state' => 'failed'])
                ->with('result_error', 'Transaction FedaPay introuvable pour la référence '.$providerReference.'.');
        }

        if ($transaction === null) {
            $mapped = $this->mapPayloadForLocalTransaction($fetched['response_payload'] ?? null);
            $customer = $fetched['response_payload']['transaction']['customer']
                ?? $fetched['response_payload']['data']['customer']
                ?? [];
            $email = is_array($customer) ? (string) ($customer['email'] ?? '') : '';
            $resolvedUser = $request->user();
            if ($resolvedUser === null && $email !== '') {
                $resolvedUser = User::query()->where('email', $email)->first();
            }
            if ($resolvedUser === null) {
                return redirect()
                    ->route('login')
                    ->withErrors([
                        'fedapay' => 'Impossible d’associer la transaction FedaPay à un utilisateur.',
                    ]);
            }

            $transaction = PaymentTransaction::create([
                'user_id' => (int) $resolvedUser->id,
                'provider' => 'fedapay_sandbox',
                'provider_reference' => $providerReference,
                'status' => $status,
                'amount' => $mapped['amount'],
                'currency' => $mapped['currency'],
                'country' => $mapped['country'],
                'correspondent' => $mapped['correspondent'],
                'payer_msisdn' => $mapped['payer_msisdn'],
                'request_payload' => [
                    'origin' => 'callback_sync',
                    'query' => $request->query(),
                ],
                'response_payload' => $fetched['response_payload'],
                'failure_reason' => null,
            ]);
        }

        $alreadyActivated = ($transaction->response_payload['premium_activated'] ?? false) === true;

        $transaction->update([
            'status' => $status,
            'response_payload' => array_merge(
                $fetched['response_payload'] ?? [],
                $alreadyActivated ? ['premium_activated' => true, 'premium_activated_at' => $transaction->response_payload['premium_activated_at'] ?? now()->toIso8601String()] : []
            ),
            'failure_reason' => ($fetched['success'] && $this->isApprovedStatus($status))
                ? null
                : ($this->isFailureStatus($status) ? ((string) ($fetched['message'] ?? 'Paiement non approuvé.')) : null),
        ]);

        $user = $transaction->user;
        $approved = $this->isApprovedStatus($status);
        if ($approved && $user !== null && ! $alreadyActivated && ! $user->isPlatformAdmin() && ! $user->isAccountant()) {
            $previousStatus = (string) ($user->premium_status ?? 'free');
            $referenceStart = $user->premium_ends_at && $user->premium_ends_at->isFuture()
                ? $user->premium_ends_at->copy()
                : now();
            $newEndsAt = $referenceStart->copy()->addDays(30);

            $user->update([
                'is_premium' => true,
                'premium_status' => 'active',
                'premium_trial_ends_at' => null,
                'premium_ends_at' => $newEndsAt,
                'auto_suspended_for_payment' => false,
                'account_suspended' => false,
                'suspended_at' => null,
                'suspended_reason' => null,
            ]);

            SubscriptionHistory::create([
                'user_id' => $user->id,
                'from_status' => $previousStatus,
                'to_status' => 'active',
                'is_premium' => true,
                'starts_at' => now(),
                'ends_at' => $newEndsAt,
                'source' => 'fedapay_sandbox',
                'note' => 'Activation automatique Premium suite à une transaction FedaPay sandbox approuvée.',
                'meta' => [
                    'provider' => 'fedapay_sandbox',
                    'provider_reference' => $providerReference,
                    'payment_status' => $status,
                    'amount' => (int) $transaction->amount,
                    'currency' => $transaction->currency,
                    'country' => $transaction->country,
                    'correspondent' => $transaction->correspondent,
                ],
            ]);

            $transaction->update([
                'response_payload' => array_merge($transaction->response_payload ?? [], [
                    'premium_activated' => true,
                    'premium_activated_at' => now()->toIso8601String(),
                ]),
            ]);
        }

        $failed = $this->isFailureStatus($status);
        $message = $approved
            ? 'Paiement FedaPay approuvé. Premium actif.'
            : ($failed
                ? 'Paiement FedaPay échoué (statut: '.$status.').'
                : 'Paiement FedaPay en attente de confirmation (statut: '.$status.').');

        return redirect()
            ->route('payments.sandbox.result', [
                'transaction' => $transaction->id,
                'state' => $approved ? 'success' : ($failed ? 'failed' : 'pending'),
            ])
            ->with($approved || ! $failed ? 'status' : 'result_error', $message);
    }
}
