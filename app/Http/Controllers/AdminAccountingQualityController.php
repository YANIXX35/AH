<?php

namespace App\Http\Controllers;

use App\Models\AccountingEntry;
use App\Models\User;
use App\Services\AccountingQualityReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminAccountingQualityController extends Controller
{
    public function index(): View
    {
        $summaries = User::query()
            ->clients()
            ->whereIn('id', AccountingEntry::query()->select('user_id')->distinct()->pluck('user_id'))
            ->withCount([
                'accountingEntries as entries_total',
                'accountingEntries as entries_non_compliant' => fn ($q) => $q->where('quality_status', 'non_compliant'),
                'accountingEntries as entries_pending' => fn ($q) => $q->where('quality_status', 'pending'),
            ])
            ->orderByRaw('accounting_quality_reviewed_at IS NULL DESC')
            ->orderBy('accounting_quality_reviewed_at')
            ->paginate(20);

        return view('admin.compliance.accounting-quality-index', [
            'summaries' => $summaries,
        ]);
    }

    public function reviewNow(User $user, AccountingQualityReviewService $reviewService): RedirectResponse
    {
        abort_if($user->isPlatformAdmin() || $user->isAccountant(), 404);

        $nonCompliant = 0;
        AccountingEntry::query()->where('user_id', $user->id)->each(function (AccountingEntry $entry) use ($reviewService, &$nonCompliant) {
            $result = $reviewService->reviewAndPersist($entry);
            if ($result['status'] === 'non_compliant') {
                $nonCompliant++;
            }
        });

        $user->forceFill(['accounting_quality_reviewed_at' => now()])->save();

        return back()->with('status', "Revue qualité lancée pour {$user->company_name} : {$nonCompliant} écriture(s) non conforme(s).");
    }
}
