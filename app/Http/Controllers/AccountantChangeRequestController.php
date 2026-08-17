<?php

namespace App\Http\Controllers;

use App\Models\AccountingChangeRequest;
use App\Services\AccountingChangeApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * File de validation comptable — accessible à tout utilisateur is_accountant,
 * toutes entreprises confondues (pas de rattachement comptable ↔ entreprise
 * dans cette version).
 */
class AccountantChangeRequestController extends Controller
{
    public function __construct(private readonly AccountingChangeApprovalService $changeApproval) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'pending');

        $query = AccountingChangeRequest::query()
            ->with(['requester:id,name,email,company_name', 'workspace:id,name,company_name', 'reviewer:id,name,email']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $requests = $query
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('accountant.change-requests-index', [
            'requests' => $requests,
            'status' => $status,
            'pendingCount' => AccountingChangeRequest::query()->pending()->count(),
        ]);
    }

    public function approve(Request $request, AccountingChangeRequest $changeRequest): RedirectResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        try {
            $this->changeApproval->approve($changeRequest, Auth::user(), $validated['note'] ?? null);
        } catch (\Throwable $e) {
            return back()->with('status', 'Erreur lors de l’application de la demande : '.$e->getMessage());
        }

        return back()->with('status', 'Demande approuvée et appliquée.');
    }

    public function reject(Request $request, AccountingChangeRequest $changeRequest): RedirectResponse
    {
        $validated = $request->validate(['note' => ['required', 'string', 'min:5', 'max:1000']]);

        $this->changeApproval->reject($changeRequest, Auth::user(), $validated['note']);

        return back()->with('status', 'Demande refusée.');
    }
}
