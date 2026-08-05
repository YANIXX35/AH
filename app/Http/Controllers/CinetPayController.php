<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Services\CinetPayService;
use App\Services\UserPremiumService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CinetPayController extends Controller
{
    public function __construct(
        private readonly CinetPayService $cinetPay,
        private readonly UserPremiumService $userPremium
    ) {}

    /**
     * Lance le checkout CinetPay (POST /v2/payment).
     */
    public function checkout(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->isPlatformAdmin() || ($user->is_accountant ?? false)) {
            return redirect()
                ->route('payments.sandbox')
                ->withErrors(['cinetpay' => 'Ce flux de paiement concerne les comptes entreprise.']);
        }

        $fixedAmount = $this->cinetPay->subscriptionAmount();
        $data = $request->validate([
            'amount' => ['nullable', 'integer', Rule::in([$fixedAmount])],
            'channels' => ['nullable', 'string', Rule::in(['ALL', 'MOBILE_MONEY', 'CREDIT_CARD', 'WALLET'])],
            'customer_phone' => ['nullable', 'string', 'min:8', 'max:20'],
        ]);

        $result = $this->cinetPay->initiatePayment($user, [
            'amount' => $fixedAmount,
            'currency' => config('services.cinetpay.currency', 'XOF'),
            'channels' => $data['channels'] ?? config('services.cinetpay.channels', 'ALL'),
            'customer_phone' => $data['customer_phone'] ?? $user->phone,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_city' => $user->city,
            'customer_address' => $user->address,
            'description' => 'Abonnement Enterprise Premium — '.config('app.name'),
        ]);

        $transaction = PaymentTransaction::query()->create([
            'user_id' => $user->id,
            'provider' => 'cinetpay',
            'provider_reference' => $result['transaction_id'],
            'status' => $result['status'],
            'amount' => $fixedAmount,
            'currency' => config('services.cinetpay.currency', 'XOF'),
            'country' => config('services.cinetpay.customer_country', 'CI'),
            'correspondent' => (string) ($data['channels'] ?? 'ALL'),
            'payer_msisdn' => (string) ($data['customer_phone'] ?? $user->phone ?? ''),
            'request_payload' => $result['request_payload'],
            'response_payload' => array_filter([
                'init' => $result['response_payload'],
                'payment_token' => $result['payment_token'] ?? null,
            ]),
            'failure_reason' => $result['success'] ? null : $result['message'],
        ]);

        if (! $result['success']) {
            return redirect()
                ->route('payments.sandbox.result', ['transaction' => $transaction->id, 'state' => 'failed'])
                ->with('result_error', 'CinetPay : '.$result['message']);
        }

        $paymentUrl = (string) ($result['payment_url'] ?? '');
        if ($paymentUrl !== '') {
            return redirect()->away($paymentUrl);
        }

        return redirect()
            ->route('payments.sandbox.result', ['transaction' => $transaction->id, 'state' => 'pending'])
            ->with('status', 'Transaction CinetPay créée (réf. '.$transaction->provider_reference.').');
    }

    /**
     * Notification serveur (notify_url) — GET ou POST, réponse HTTP 200 obligatoire.
     *
     * @see https://docs.cinetpay.com/api/1.0-fr/checkout/notification
     */
    public function notify(Request $request): Response
    {
        $payload = $request->all();
        $transactionId = $this->cinetPay->extractTransactionIdFromNotify($payload);

        if ($transactionId === '') {
            Log::warning('cinetpay_notify_missing_transaction_id', ['payload' => $payload]);

            return response('cpm_trans_id manquant', 400);
        }

        if (! $this->cinetPay->validateNotification($payload, $request->header('x-token'))) {
            Log::warning('cinetpay_notify_invalid_signature', [
                'transaction_id' => $transactionId,
                'cpm_site_id' => $payload['cpm_site_id'] ?? null,
            ]);

            return response('notification invalide', 403);
        }

        $transaction = PaymentTransaction::query()
            ->where('provider', 'cinetpay')
            ->where('provider_reference', $transactionId)
            ->latest('id')
            ->first();

        if ($transaction === null) {
            Log::warning('cinetpay_notify_unknown_transaction', [
                'transaction_id' => $transactionId,
                'cpm_result' => $payload['cpm_result'] ?? null,
            ]);

            return response('transaction inconnue', 404);
        }

        // Ne pas se fier à cpm_result : vérification API obligatoire (doc CinetPay).
        $this->syncTransactionFromProvider($transaction, [
            'notify' => $payload,
            'cpm_amount' => $payload['cpm_amount'] ?? null,
            'cpm_currency' => $payload['cpm_currency'] ?? null,
            'cpm_trans_date' => $payload['cpm_trans_date'] ?? null,
            'cpm_error_message' => $payload['cpm_error_message'] ?? null,
        ], 'cinetpay_notify');

        return response('OK', 200);
    }

    /**
     * Retour navigateur (return_url) après paiement sur CinetPay.
     */
    public function return(Request $request): RedirectResponse
    {
        $transactionId = trim((string) (
            $request->query('transaction_id')
            ?? $request->query('cpm_trans_id')
            ?? ''
        ));

        // Le paramètre "simulate=accepted" ne doit jamais pouvoir activer un
        // Premium réel : réservé au développement local, à un utilisateur
        // authentifié, et uniquement sur SA PROPRE transaction. Sans ces trois
        // garde-fous, n'importe qui pouvait appeler cette URL sans être connecté
        // et faire créditer la transaction CinetPay la plus récente du système,
        // quel qu'en soit le propriétaire (contournement de paiement complet).
        $simulateRequested = $request->query('simulate') === 'accepted';
        $simulateAllowed = $simulateRequested
            && app()->environment(['local', 'testing'])
            && auth()->check();

        $transaction = $this->resolveTransaction($transactionId, $simulateAllowed);

        if ($transaction === null) {
            return redirect()
                ->route('payments.sandbox')
                ->withErrors(['cinetpay' => 'Transaction CinetPay introuvable après retour paiement.']);
        }

        if ($simulateAllowed) {
            $transaction->update([
                'status' => 'ACCEPTED',
                'response_payload' => array_merge($transaction->response_payload ?? [], ['simulate_return' => true]),
            ]);
            $this->activatePremiumIfPaid($transaction, 'cinetpay_simulate');

            return redirect()
                ->route('payments.sandbox.result', ['transaction' => $transaction->id, 'state' => 'success'])
                ->with('status', 'Paiement simulé accepté. Premium activé.');
        }

        $this->syncTransactionFromProvider($transaction, $request->query(), 'cinetpay_return');

        $status = strtoupper((string) $transaction->fresh()->status);
        $isSuccess = in_array($status, ['ACCEPTED', 'COMPLETED', 'APPROVED', 'SUCCESS', 'SUCCES'], true);
        $isFailure = in_array($status, ['REFUSED', 'FAILED', 'CANCELED', 'CANCELLED', 'DECLINED'], true);

        return redirect()
            ->route('payments.sandbox.result', [
                'transaction' => $transaction->id,
                'state' => $isSuccess ? 'success' : ($isFailure ? 'failed' : 'pending'),
            ])
            ->with(
                $isSuccess ? 'status' : 'result_error',
                $isSuccess
                  ? 'Paiement CinetPay confirmé. Votre abonnement Premium est actif.'
                  : ($isFailure
                    ? 'Paiement CinetPay refusé ou annulé.'
                    : 'Paiement CinetPay en attente de confirmation.')
            );
    }

    protected function resolveTransaction(string $transactionId, bool $simulate): ?PaymentTransaction
    {
        if ($transactionId !== '') {
            $found = PaymentTransaction::query()
                ->where('provider', 'cinetpay')
                ->where('provider_reference', $transactionId)
                ->latest('id')
                ->first();

            if ($found !== null) {
                return $found;
            }
        }

        if ($simulate || auth()->check()) {
            return PaymentTransaction::query()
                ->where('provider', 'cinetpay')
                ->when(auth()->check(), fn ($q) => $q->where('user_id', auth()->id()))
                ->latest('id')
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function syncTransactionFromProvider(PaymentTransaction $transaction, array $context, string $source): void
    {
        if ($this->isAlreadyFulfilled($transaction)) {
            return;
        }

        $check = $this->cinetPay->checkTransactionStatus((string) $transaction->provider_reference);
        $status = strtoupper((string) ($check['status'] ?? 'UNKNOWN'));

        $transaction->update([
            'status' => $status,
            'response_payload' => array_merge($transaction->response_payload ?? [], [
                $source => $context,
                'check' => $check['response_payload'],
                'checked_at' => now()->toIso8601String(),
            ]),
            'failure_reason' => ($check['is_paid'] ?? false) ? null : (string) ($check['message'] ?? null),
        ]);

        if ($check['is_paid'] ?? false) {
            $this->activatePremiumIfPaid($transaction->fresh(), $source);
        }
    }

    protected function isAlreadyFulfilled(PaymentTransaction $transaction): bool
    {
        $payload = $transaction->response_payload ?? [];

        return ($payload['premium_activated'] ?? false) === true
          && in_array(strtoupper((string) $transaction->status), ['ACCEPTED', 'SUCCESS', 'SUCCES'], true);
    }

    protected function activatePremiumIfPaid(PaymentTransaction $transaction, string $source): void
    {
        $user = $transaction->user;
        if ($user === null || ! $this->userPremium->canManageEnterprisePremium($user)) {
            return;
        }

        if (($transaction->response_payload['premium_activated'] ?? false) === true) {
            return;
        }

        $this->userPremium->activateForDays(
            $user,
            (int) config('services.cinetpay.premium_days', 30),
            $source,
            'Activation Premium suite à paiement CinetPay approuvé (vérification API).',
            null,
            [
                'payment_transaction_id' => $transaction->id,
                'provider_reference' => $transaction->provider_reference,
                'provider' => 'cinetpay',
                'amount' => (int) $transaction->amount,
            ],
            request()
        );

        $transaction->update([
            'response_payload' => array_merge($transaction->response_payload ?? [], [
                'premium_activated' => true,
                'premium_activated_at' => now()->toIso8601String(),
            ]),
        ]);
    }
}
