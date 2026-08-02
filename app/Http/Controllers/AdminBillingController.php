<?php

namespace App\Http\Controllers;

use App\Models\BillingInvoice;
use App\Models\BillingPlan;
use App\Models\BillingSubscription;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminBillingController extends Controller
{
    public function index(Request $request): View
    {
        $userLookup = trim((string) $request->query('user_lookup', ''));
        $subscriptions = BillingSubscription::query()
            ->with(['user:id,name,email', 'plan:id,name,slug,price,currency'])
            ->latest()
            ->paginate(25);

        $invoices = BillingInvoice::query()->latest()->limit(15)->get();
        $plans = BillingPlan::query()->orderBy('price')->get();
        $matchedUsers = collect();
        if ($userLookup !== '') {
            $matchedUsers = User::query()
                ->where(function ($q) use ($userLookup): void {
                    $q->where('email', 'like', '%'.$userLookup.'%')
                        ->orWhere('name', 'like', '%'.$userLookup.'%');
                })
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name', 'email']);
        }

        return view('admin.billing.index', [
            'subscriptions' => $subscriptions,
            'invoices' => $invoices,
            'plans' => $plans,
            'matchedUsers' => $matchedUsers,
            'userLookup' => $userLookup,
            'stats' => [
                'active_subscriptions' => BillingSubscription::query()->where('status', 'active')->count(),
                'past_due_subscriptions' => BillingSubscription::query()->where('status', 'past_due')->count(),
                'suspended_subscriptions' => BillingSubscription::query()->where('status', 'suspended')->count(),
                'unpaid_invoices' => BillingInvoice::query()->whereIn('status', ['issued', 'overdue'])->count(),
            ],
        ]);
    }

    public function createOrSwitchSubscription(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'user_lookup' => ['nullable', 'string', 'max:255'],
            'billing_plan_id' => ['required', 'integer', 'exists:billing_plans,id'],
        ]);

        $resolvedUserId = (int) ($data['user_id'] ?? 0);
        if ($resolvedUserId <= 0) {
            $lookup = trim((string) ($data['user_lookup'] ?? ''));
            if ($lookup !== '') {
                $user = User::query()
                    ->where('email', $lookup)
                    ->orWhere('name', $lookup)
                    ->first();
                $resolvedUserId = (int) ($user?->id ?? 0);
            }
        }
        if ($resolvedUserId <= 0) {
            return back()->withErrors(['user_id' => 'Utilisateur introuvable (id/email/nom).']);
        }

        $user = User::query()->findOrFail($resolvedUserId);
        $plan = BillingPlan::query()->findOrFail((int) $data['billing_plan_id']);

        BillingSubscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'past_due', 'suspended'])
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        BillingSubscription::create([
            'user_id' => $user->id,
            'billing_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'next_billing_at' => now()->addDays((int) $plan->interval_days),
            'auto_renew' => true,
            'dunning_level' => 0,
        ]);

        $isPremium = $plan->slug === 'enterprise-premium';
        $user->update([
            'is_premium' => $isPremium,
            'premium_status' => $isPremium ? 'active' : 'free',
            'premium_ends_at' => $isPremium ? now()->addDays((int) $plan->interval_days) : null,
            'auto_suspended_for_payment' => false,
            'account_suspended' => false,
            'suspended_at' => null,
            'suspended_reason' => null,
        ]);

        return back()->with('status', 'Abonnement mis a jour pour '.$user->email.'.');
    }
}
