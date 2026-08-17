<?php

namespace App\Http\Controllers;

use App\Models\AccountingChangeRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Vue d'ensemble, lecture seule, des demandes de modification/suppression
 * comptables et de qui (quel comptable) les a traitées — toutes entreprises
 * confondues.
 */
class AdminAccountingChangeRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'all');

        $query = AccountingChangeRequest::query()
            ->with(['requester:id,name,email,company_name', 'workspace:id,name,company_name', 'reviewer:id,name,email']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $requests = $query
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.compliance.change-requests-index', [
            'requests' => $requests,
            'status' => $status,
            'stats' => [
                'pending' => AccountingChangeRequest::query()->where('status', 'pending')->count(),
                'approved' => AccountingChangeRequest::query()->where('status', 'approved')->count(),
                'rejected' => AccountingChangeRequest::query()->where('status', 'rejected')->count(),
            ],
        ]);
    }
}
