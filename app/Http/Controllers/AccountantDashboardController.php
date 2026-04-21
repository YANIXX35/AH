<?php

namespace App\Http\Controllers;

use App\Models\AccountingDocument;
use App\Models\AccountingEntry;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Support\ClientWorkspace;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountantDashboardController extends Controller
{
    /**
     * Tableau de bord cabinet : vue transverse sur les dossiers clients.
     */
    public function index(Request $request): View
    {
        $clients = User::query()->clients()->orderBy('company_name')->orderBy('name');

        $clientCount = (clone $clients)->count();

        $clientIds = User::query()->clients()->pluck('id');

        $entriesTotal = AccountingEntry::query()
            ->whereIn('user_id', $clientIds)
            ->count();

        $documentsPending = AccountingDocument::query()
            ->whereIn('user_id', $clientIds)
            ->whereIn('status', ['pending', 'pending_validation', 'ocr_failed'])
            ->count();

        $ocrStressEntries = AccountingEntry::query()
            ->whereIn('user_id', $clientIds)
            ->whereIn('ocr_status', ['failed', 'mismatch', 'mismatched'])
            ->count();

        $treasuryVolume = (float) TreasuryTransaction::query()
            ->whereIn('user_id', $clientIds)
            ->where('status', 'effectue')
            ->sum('amount');

        $recentClients = User::query()
            ->clients()
            ->latest()
            ->limit(10)
            ->get(['id', 'name', 'email', 'company_name', 'created_at']);

        $workspaceTarget = ClientWorkspace::isViewingClient() ? ClientWorkspace::workspaceTarget() : null;
        $workspaceLabel = '';
        if ($workspaceTarget) {
            $workspaceLabel = (string) ($workspaceTarget->company_name ?: $workspaceTarget->name ?: $workspaceTarget->email);
        }

        return view('accountant.dashboard', [
            'clientCount' => $clientCount,
            'entriesTotal' => $entriesTotal,
            'documentsPending' => $documentsPending,
            'ocrStressEntries' => $ocrStressEntries,
            'treasuryVolume' => $treasuryVolume,
            'recentClients' => $recentClients,
            'accountingWorkspaceOpen' => ClientWorkspace::isViewingClient(),
            'accountingWorkspaceLabel' => $workspaceLabel,
        ]);
    }
}
