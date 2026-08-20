<?php

namespace App\Http\Controllers;

use App\Models\CommercialPayout;
use App\Models\User;
use App\Services\CommercialCommissionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Suivi, côté cabinet comptable, du solde de commission de chaque commercial et
 * validation des versements effectués (avec génération du reçu PDF correspondant).
 */
class AccountantCommercialBalanceController extends Controller
{
    public function __construct(
        private readonly CommercialCommissionService $commission
    ) {}

    public function index(): View
    {
        $commercials = User::query()
            ->where('role_key', 'commercial')
            ->orderBy('name')
            ->get()
            ->map(function (User $commercial) {
                $balance = $this->commission->calculateBalance($commercial);
                $totalPaid = $this->commission->totalPaid($commercial);

                return [
                    'commercial' => $commercial,
                    'totalClients' => $balance['totalClients'],
                    'totalEarned' => $balance['totalBalance'],
                    'totalPaid' => $totalPaid,
                    'remaining' => max(0, $balance['totalBalance'] - $totalPaid),
                ];
            })
            ->sortByDesc('remaining')
            ->values();

        return view('accountant.commercials-balance-index', [
            'rows' => $commercials,
            'grandTotalEarned' => $commercials->sum('totalEarned'),
            'grandTotalPaid' => $commercials->sum('totalPaid'),
            'grandTotalRemaining' => $commercials->sum('remaining'),
        ]);
    }

    public function show(User $commercial): View
    {
        $this->authorizeCommercial($commercial);

        $balance = $this->commission->calculateBalance($commercial);
        $totalPaid = $this->commission->totalPaid($commercial);
        $remaining = max(0, $balance['totalBalance'] - $totalPaid);

        $payouts = CommercialPayout::query()
            ->where('commercial_user_id', $commercial->id)
            ->latest()
            ->get();

        return view('accountant.commercials-balance-show', [
            'commercial' => $commercial,
            'rows' => $balance['rows'],
            'totalBalance' => $balance['totalBalance'],
            'totalSignupEarnings' => $balance['totalSignupEarnings'],
            'totalRenewalEarnings' => $balance['totalRenewalEarnings'],
            'totalClients' => $balance['totalClients'],
            'totalPaid' => $totalPaid,
            'remaining' => $remaining,
            'payouts' => $payouts,
            'tier1Slots' => CommercialCommissionService::TIER1_SLOTS,
            'signupBonusTier1' => CommercialCommissionService::SIGNUP_BONUS_TIER1,
            'signupBonusTier2' => CommercialCommissionService::SIGNUP_BONUS_TIER2,
            'renewalBonus' => CommercialCommissionService::RENEWAL_BONUS,
        ]);
    }

    public function storePayout(Request $request, User $commercial): RedirectResponse
    {
        $this->authorizeCommercial($commercial);

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $accountant = $request->user();

        $payout = DB::transaction(function () use ($validated, $commercial, $accountant) {
            $balance = $this->commission->calculateBalance($commercial);
            $totalPaid = $this->commission->totalPaid($commercial);
            $remaining = max(0, $balance['totalBalance'] - $totalPaid);

            if ($validated['amount'] > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => "Le montant dépasse le solde restant dû ({$remaining} FCFA).",
                ]);
            }

            $receiptNumber = $this->commission->allocateReceiptNumber($commercial->id, now());

            return CommercialPayout::create([
                'commercial_user_id' => $commercial->id,
                'validated_by_user_id' => $accountant->id,
                'receipt_number' => $receiptNumber,
                'amount' => $validated['amount'],
                'balance_at_payment' => $balance['totalBalance'],
                'previously_paid_total' => $totalPaid,
                'note' => $validated['note'] ?? null,
            ]);
        });

        $pdf = Pdf::loadView('accountant.commercial-payout-receipt-pdf', [
            'payout' => $payout,
            'commercial' => $commercial,
            'accountant' => $accountant,
        ]);
        $path = 'commercial-payouts/'.$payout->receipt_number.'.pdf';
        Storage::disk('public')->put($path, $pdf->output());
        $payout->update(['pdf_path' => $path]);

        return redirect()
            ->route('accountant.commercials-balance.show', $commercial)
            ->with('status', "Paiement de {$payout->amount} FCFA validé. Reçu {$payout->receipt_number} généré.");
    }

    public function downloadReceipt(CommercialPayout $payout): StreamedResponse
    {
        abort_unless($payout->pdf_path && Storage::disk('public')->exists($payout->pdf_path), 404);

        return Storage::disk('public')->download($payout->pdf_path, $payout->receipt_number.'.pdf');
    }

    private function authorizeCommercial(User $commercial): void
    {
        abort_unless($commercial->role_key === 'commercial', 404);
    }
}
