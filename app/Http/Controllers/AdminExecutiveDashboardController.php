<?php

namespace App\Http\Controllers;

use App\Models\BillingInvoice;
use App\Models\BillingSubscription;
use App\Models\PaymentTransaction;
use App\Models\TreasuryTransaction;
use App\Models\User;
use Illuminate\View\View;

class AdminExecutiveDashboardController extends Controller
{
    public function index(): View
    {
        $now = now();
        $startCurrent = $now->copy()->startOfMonth();
        $startPrevious = $startCurrent->copy()->subMonth();
        $endPrevious = $startCurrent->copy()->subSecond();

        $mrr = (float) BillingSubscription::query()
            ->whereIn('status', ['active', 'past_due'])
            ->join('billing_plans', 'billing_subscriptions.billing_plan_id', '=', 'billing_plans.id')
            ->sum('billing_plans.price');

        $currentClients = User::query()->clients()->count();
        $currentActivePremium = User::query()->clients()->where('premium_status', 'active')->count();
        $churn = $currentClients > 0 ? round((($currentClients - $currentActivePremium) / $currentClients) * 100, 2) : 0.0;

        $unpaidInvoices = BillingInvoice::query()
            ->whereIn('status', ['issued', 'overdue'])
            ->count();

        $paidInvoiceDates = BillingInvoice::query()
            ->whereNotNull('paid_at')
            ->get(['paid_at', 'issued_at']);
        $dso = $paidInvoiceDates->isEmpty()
            ? 0.0
            : round($paidInvoiceDates->avg(fn (BillingInvoice $invoice) => $invoice->issued_at->diffInDays($invoice->paid_at)), 2);

        $cashIn30 = (float) TreasuryTransaction::query()
            ->where('type', 'encaissement')
            ->where('transaction_date', '>=', $now->copy()->subDays(30))
            ->sum('amount');
        $cashOut30 = (float) TreasuryTransaction::query()
            ->where('type', 'decaissement')
            ->where('transaction_date', '>=', $now->copy()->subDays(30))
            ->sum('amount');
        $cashForecast = round($cashIn30 - $cashOut30, 2);

        $failedPayments = PaymentTransaction::query()
            ->whereIn('status', ['FAILED', 'REJECTED', 'DECLINED', 'CANCELED'])
            ->selectRaw('user_id, COUNT(*) as cnt')
            ->groupBy('user_id')
            ->orderByDesc('cnt')
            ->with('user:id,name,email,company_name')
            ->limit(10)
            ->get();

        $revenueCurrent = (float) PaymentTransaction::query()
            ->where('created_at', '>=', $startCurrent)
            ->whereIn('status', ['COMPLETED', 'APPROVED', 'ACCEPTED', 'SUBMITTED'])
            ->sum('amount');
        $revenuePrevious = (float) PaymentTransaction::query()
            ->whereBetween('created_at', [$startPrevious, $endPrevious])
            ->whereIn('status', ['COMPLETED', 'APPROVED', 'ACCEPTED', 'SUBMITTED'])
            ->sum('amount');
        $revenueGrowth = $revenuePrevious > 0 ? round((($revenueCurrent - $revenuePrevious) / $revenuePrevious) * 100, 2) : 100.0;

        $alerts = collect([
            ['label' => 'Impayés élevés', 'value' => $unpaidInvoices, 'threshold' => 10],
            ['label' => 'DSO critique', 'value' => $dso, 'threshold' => 30],
            ['label' => 'Churn élevé', 'value' => $churn, 'threshold' => 15],
        ])->map(function (array $alert) {
            $status = $alert['value'] >= $alert['threshold'] ? 'danger' : 'success';
            return [...$alert, 'status' => $status];
        })->all();

        return view('admin.executive-dashboard', [
            'kpis' => [
                'mrr' => round($mrr, 2),
                'churn' => $churn,
                'unpaid_invoices' => $unpaidInvoices,
                'dso' => $dso,
                'cash_forecast' => $cashForecast,
                'revenue_growth' => $revenueGrowth,
            ],
            'topRiskClients' => $failedPayments,
            'alerts' => $alerts,
        ]);
    }
}
