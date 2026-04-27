<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Models\SubscriptionHistory;
use App\Models\User;
use App\Services\AdminAuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPaymentController extends Controller
{
    public function __construct(
        private readonly AdminAuditTrailService $auditTrail
    ) {}

    public function index(Request $request): View
    {
        $query = PaymentTransaction::query()
            ->with('user:id,name,email,is_platform_admin,is_accountant,is_premium,premium_status,premium_ends_at')
            ->latest();

        $status = (string) $request->query('status', '');
        $country = (string) $request->query('country', '');
        $correspondent = (string) $request->query('correspondent', '');
        $userId = (int) $request->query('user_id', 0);
        $search = trim((string) $request->query('q', ''));
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');

        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($country !== '') {
            $query->where('country', $country);
        }
        if ($correspondent !== '') {
            $query->where('correspondent', $correspondent);
        }
        if ($userId > 0) {
            $query->where('user_id', $userId);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('provider_reference', 'like', '%'.$search.'%')
                    ->orWhere('payer_msisdn', 'like', '%'.$search.'%')
                    ->orWhere('failure_reason', 'like', '%'.$search.'%');
            });
        }
        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $payments = $query->paginate(25)->withQueryString();

        $baseStats = PaymentTransaction::query();
        $totalCount = (clone $baseStats)->count();
        $acceptedCount = (clone $baseStats)->whereIn('status', ['COMPLETED', 'ACCEPTED', 'SUBMITTED'])->count();
        $rejectedCount = (clone $baseStats)->whereIn('status', ['REJECTED', 'FAILED'])->count();
        $totalAmount = (float) (clone $baseStats)->sum('amount');

        $statuses = PaymentTransaction::query()
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');
        $countries = PaymentTransaction::query()
            ->select('country')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');
        $correspondents = PaymentTransaction::query()
            ->select('correspondent')
            ->distinct()
            ->orderBy('correspondent')
            ->pluck('correspondent');
        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.payments', [
            'payments' => $payments,
            'totalCount' => $totalCount,
            'acceptedCount' => $acceptedCount,
            'rejectedCount' => $rejectedCount,
            'totalAmount' => $totalAmount,
            'statuses' => $statuses,
            'countries' => $countries,
            'correspondents' => $correspondents,
            'users' => $users,
            'filters' => [
                'status' => $status,
                'country' => $country,
                'correspondent' => $correspondent,
                'user_id' => $userId,
                'q' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function activatePremium(PaymentTransaction $paymentTransaction): RedirectResponse
    {
        $actor = request()->user();
        $user = $paymentTransaction->user;
        if ($user === null) {
            return back()->withErrors(['payment' => 'Aucun utilisateur rattaché à ce paiement.']);
        }
        if ($user->isPlatformAdmin() || $user->isAccountant()) {
            return back()->withErrors(['payment' => 'Cette action concerne uniquement les comptes entreprise.']);
        }

        $previousStatus = (string) ($user->premium_status ?? 'free');
        $referenceStart = $user->premium_ends_at && $user->premium_ends_at->isFuture()
            ? $user->premium_ends_at->copy()
            : now();
        $newEndsAt = $referenceStart->copy()->addDays(30);
        $before = $user->only(['is_premium', 'premium_status', 'premium_trial_ends_at', 'premium_ends_at', 'account_suspended', 'suspended_at', 'suspended_reason']);

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
            'source' => 'admin_payments',
            'note' => 'Activation Premium 30 jours depuis la gestion admin des paiements.',
            'meta' => [
                'payment_transaction_id' => $paymentTransaction->id,
                'provider_reference' => $paymentTransaction->provider_reference,
                'provider' => $paymentTransaction->provider,
                'status' => $paymentTransaction->status,
            ],
        ]);

        $this->auditTrail->log(
            'premium.activate',
            User::class,
            $user->id,
            $actor?->id,
            $before,
            $user->fresh()?->only(['is_premium', 'premium_status', 'premium_trial_ends_at', 'premium_ends_at', 'account_suspended', 'suspended_at', 'suspended_reason']),
            ['payment_transaction_id' => $paymentTransaction->id],
            request()
        );

        return back()->with('status', 'Premium activé pour 30 jours sur le compte '.$user->email.'.');
    }

    public function setFree(PaymentTransaction $paymentTransaction): RedirectResponse
    {
        $actor = request()->user();
        $user = $paymentTransaction->user;
        if ($user === null) {
            return back()->withErrors(['payment' => 'Aucun utilisateur rattaché à ce paiement.']);
        }
        if ($user->isPlatformAdmin() || $user->isAccountant()) {
            return back()->withErrors(['payment' => 'Cette action concerne uniquement les comptes entreprise.']);
        }

        $previousStatus = (string) ($user->premium_status ?? 'free');
        $before = $user->only(['is_premium', 'premium_status', 'premium_trial_ends_at', 'premium_ends_at']);

        $user->update([
            'is_premium' => false,
            'premium_status' => 'free',
            'premium_trial_ends_at' => null,
            'premium_ends_at' => null,
        ]);

        SubscriptionHistory::create([
            'user_id' => $user->id,
            'from_status' => $previousStatus,
            'to_status' => 'free',
            'is_premium' => false,
            'starts_at' => now(),
            'ends_at' => null,
            'source' => 'admin_payments',
            'note' => 'Passage manuel en Gratuit depuis la gestion admin des paiements.',
            'meta' => [
                'payment_transaction_id' => $paymentTransaction->id,
                'provider_reference' => $paymentTransaction->provider_reference,
                'provider' => $paymentTransaction->provider,
                'status' => $paymentTransaction->status,
            ],
        ]);

        $this->auditTrail->log(
            'premium.set_free',
            User::class,
            $user->id,
            $actor?->id,
            $before,
            $user->fresh()?->only(['is_premium', 'premium_status', 'premium_trial_ends_at', 'premium_ends_at']),
            ['payment_transaction_id' => $paymentTransaction->id],
            request()
        );

        return back()->with('status', 'Le compte '.$user->email.' est repassé en mode Gratuit.');
    }
}
