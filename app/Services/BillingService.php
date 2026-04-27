<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\BillingInvoice;
use App\Models\BillingInvoiceItem;
use App\Models\BillingPaymentAttempt;
use App\Models\BillingSubscription;
use App\Models\PaymentTransaction;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BillingService
{
    public function __construct(
        private readonly FedaPaySandboxService $fedaPaySandboxService
    ) {}

    public function generateInvoiceForSubscription(BillingSubscription $subscription): BillingInvoice
    {
        return DB::transaction(function () use ($subscription): BillingInvoice {
            $subscription->loadMissing(['plan', 'addons.addon', 'user']);

            $invoice = BillingInvoice::create([
                'user_id' => $subscription->user_id,
                'billing_subscription_id' => $subscription->id,
                'invoice_number' => $this->nextInvoiceNumber(),
                'status' => 'issued',
                'issued_at' => now(),
                'due_at' => now()->addDays(7),
                'currency' => $subscription->plan->currency ?? 'XOF',
                'subtotal' => 0,
                'tax_amount' => 0,
                'total_amount' => 0,
                'meta' => [
                    'dunning_level' => (int) $subscription->dunning_level,
                ],
            ]);

            $subtotal = 0.0;

            $planPrice = (float) ($subscription->plan->price ?? 0);
            BillingInvoiceItem::create([
                'billing_invoice_id' => $invoice->id,
                'label' => 'Plan: '.($subscription->plan->name ?? 'Plan'),
                'description' => 'Abonnement periodique',
                'quantity' => 1,
                'unit_price' => $planPrice,
                'total_price' => $planPrice,
            ]);
            $subtotal += $planPrice;

            foreach ($subscription->addons->where('is_active', true) as $subscriptionAddon) {
                $lineTotal = (float) $subscriptionAddon->total_price;
                BillingInvoiceItem::create([
                    'billing_invoice_id' => $invoice->id,
                    'label' => 'Add-on: '.($subscriptionAddon->addon->name ?? 'Addon'),
                    'description' => 'Option additionnelle',
                    'quantity' => (int) $subscriptionAddon->quantity,
                    'unit_price' => (float) $subscriptionAddon->unit_price,
                    'total_price' => $lineTotal,
                    'meta' => ['billing_addon_id' => $subscriptionAddon->billing_addon_id],
                ]);
                $subtotal += $lineTotal;
            }

            $taxAmount = 0.0;
            $invoice->update([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $subtotal + $taxAmount,
            ]);

            $this->renderInvoicePdf($invoice->fresh(['user', 'items', 'subscription.plan']));

            return $invoice->fresh(['items']);
        });
    }

    public function renderInvoicePdf(BillingInvoice $invoice): string
    {
        $invoice->loadMissing(['user', 'items', 'subscription.plan']);
        $pdf = Pdf::loadView('billing.invoice-pdf', ['invoice' => $invoice]);
        $path = 'billing/invoices/'.$invoice->invoice_number.'.pdf';
        Storage::disk('public')->put($path, $pdf->output());
        $invoice->update(['pdf_path' => $path]);

        return $path;
    }

    public function runDailyDunning(): array
    {
        $createdInvoices = 0;
        $markedOverdue = 0;
        $suspendedUsers = 0;
        $paymentRetries = 0;
        $notificationsSent = 0;

        $subscriptions = BillingSubscription::query()
            ->with(['plan', 'addons', 'user'])
            ->where('status', 'active')
            ->whereNotNull('next_billing_at')
            ->where('next_billing_at', '<=', now())
            ->get();

        foreach ($subscriptions as $subscription) {
            $invoice = $this->generateInvoiceForSubscription($subscription);
            $createdInvoices++;

            $subscription->update([
                'next_billing_at' => now()->addDays((int) ($subscription->plan->interval_days ?? 30)),
                'dunning_level' => 0,
            ]);

            AppNotification::create([
                'user_id' => $subscription->user_id,
                'title' => 'Nouvelle facture '.($invoice->invoice_number),
                'body' => 'Une facture de '.$invoice->total_amount.' '.$invoice->currency.' a ete emise.',
                'type' => 'info',
                'action_url' => route('billing.invoices'),
            ]);
            $notificationsSent++;
        }

        $overdueInvoices = BillingInvoice::query()
            ->with(['subscription', 'user'])
            ->whereIn('status', ['issued', 'overdue'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->get();

        foreach ($overdueInvoices as $invoice) {
            if ($invoice->status !== 'overdue') {
                $invoice->update(['status' => 'overdue']);
                $markedOverdue++;
            }

            $subscription = $invoice->subscription;
            $user = $invoice->user;
            if (! $subscription || ! $user) {
                continue;
            }

            $overdueDays = $this->overdueDays($invoice->due_at);
            $dunningLevel = match (true) {
                $overdueDays >= 7 => 4,
                $overdueDays >= 3 => 3,
                $overdueDays >= 1 => 2,
                default => 1,
            };
            $isReminderDay = in_array($overdueDays, [1, 3, 7], true);
            if (! $isReminderDay) {
                continue;
            }

            $retryResult = $this->retryInvoicePayment($invoice, $user);
            $paymentRetries++;
            if ($retryResult['paid'] === true) {
                $this->activateSubscriptionAfterPayment($subscription, $invoice, $user, $retryResult['provider_reference']);
                continue;
            }

            $updates = ['dunning_level' => $dunningLevel, 'status' => 'past_due'];
            if ($dunningLevel >= 3) {
                $updates['grace_ends_at'] = now()->addDays(3);
            }
            if ($dunningLevel >= 4) {
                $updates['status'] = 'suspended';
                $this->suspendUserForBilling($user);
                $suspendedUsers++;
            }
            $subscription->update($updates);

            $body = sprintf(
                'Relance D+%d: facture en retard. Merci de regler via la page paiement.',
                $overdueDays
            );
            AppNotification::create([
                'user_id' => $invoice->user_id,
                'title' => 'Relance D+'.$overdueDays.' - '.$invoice->invoice_number,
                'body' => $body,
                'type' => $dunningLevel >= 4 ? 'error' : 'warning',
                'action_url' => route('payments.sandbox'),
            ]);
            $notificationsSent++;
        }

        return [
            'created_invoices' => $createdInvoices,
            'marked_overdue' => $markedOverdue,
            'suspended_users' => $suspendedUsers,
            'payment_retries' => $paymentRetries,
            'notifications_sent' => $notificationsSent,
        ];
    }

    /**
     * @return array{paid:bool,provider_reference:string|null}
     */
    private function retryInvoicePayment(BillingInvoice $invoice, User $user): array
    {
        $lastTransaction = PaymentTransaction::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $country = strtoupper((string) ($lastTransaction?->country ?? 'CIV'));
        $correspondent = (string) ($lastTransaction?->correspondent ?? 'wave');
        $payerMsisdn = (string) ($lastTransaction?->payer_msisdn ?? '');

        $result = $this->fedaPaySandboxService->createTransaction($user, [
            'amount' => (int) round((float) $invoice->total_amount),
            'currency' => (string) $invoice->currency,
            'country' => $country,
            'correspondent' => $correspondent,
            'payer_msisdn' => $payerMsisdn !== '' ? $payerMsisdn : '00000000',
        ]);

        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'provider' => 'fedapay_sandbox',
            'provider_reference' => (string) ($result['provider_reference'] ?? ''),
            'status' => (string) ($result['status'] ?? 'FAILED'),
            'amount' => (float) $invoice->total_amount,
            'currency' => (string) $invoice->currency,
            'country' => $country,
            'correspondent' => $correspondent,
            'payer_msisdn' => $payerMsisdn,
            'request_payload' => $result['request_payload'] ?? ['source' => 'billing_dunning'],
            'response_payload' => $result['response_payload'] ?? null,
            'failure_reason' => $result['success'] ? null : (string) ($result['message'] ?? 'Retry failed'),
        ]);

        $status = strtoupper((string) ($transaction->status ?? ''));
        $paid = in_array($status, ['APPROVED', 'COMPLETED'], true);
        BillingPaymentAttempt::create([
            'billing_invoice_id' => $invoice->id,
            'payment_transaction_id' => $transaction->id,
            'attempted_at' => now(),
            'status' => $paid ? 'success' : 'failed',
            'provider' => 'fedapay_sandbox',
            'error_message' => $paid ? null : (string) ($result['message'] ?? 'Paiement non confirme'),
            'payload' => [
                'checkout_url' => $result['checkout_url'] ?? null,
                'status' => $status,
            ],
        ]);

        return [
            'paid' => $paid,
            'provider_reference' => $transaction->provider_reference ?: null,
        ];
    }

    private function activateSubscriptionAfterPayment(BillingSubscription $subscription, BillingInvoice $invoice, User $user, ?string $providerReference): void
    {
        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_provider' => 'fedapay_sandbox',
            'payment_reference' => $providerReference,
        ]);

        $subscription->update([
            'status' => 'active',
            'dunning_level' => 0,
            'grace_ends_at' => null,
        ]);

        $user->update([
            'is_premium' => true,
            'premium_status' => 'active',
            'premium_ends_at' => now()->addDays(30),
            'auto_suspended_for_payment' => false,
            'account_suspended' => false,
            'suspended_at' => null,
            'suspended_reason' => null,
        ]);

        AppNotification::create([
            'user_id' => $user->id,
            'title' => 'Paiement recu - '.$invoice->invoice_number,
            'body' => 'Votre paiement a ete confirme. Merci.',
            'type' => 'success',
            'action_url' => route('billing.invoices'),
        ]);
    }

    private function overdueDays(mixed $dueAt): int
    {
        if (! $dueAt instanceof Carbon) {
            return 0;
        }
        if ($dueAt->isFuture()) {
            return 0;
        }

        return $dueAt->startOfDay()->diffInDays(now()->startOfDay());
    }

    private function suspendUserForBilling(User $user): void
    {
        $user->update([
            'auto_suspended_for_payment' => true,
            'account_suspended' => true,
            'suspended_at' => now(),
            'suspended_reason' => 'Factures impayees apres relances automatiques',
        ]);
    }

    private function nextInvoiceNumber(): string
    {
        $prefix = now()->format('Ymd');
        $last = BillingInvoice::query()
            ->where('invoice_number', 'like', 'INV-'.$prefix.'-%')
            ->latest('id')
            ->value('invoice_number');
        $sequence = 1;
        if (is_string($last) && str_contains($last, '-')) {
            $tail = (int) substr($last, strrpos($last, '-') + 1);
            $sequence = $tail + 1;
        }

        return sprintf('INV-%s-%04d', $prefix, $sequence);
    }
}
