<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\CommercialProspection;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminProspectionController extends Controller
{
    public function index(Request $request): View
    {
        $commercialId = (int) $request->query('commercial_id', 0);
        $status = (string) $request->query('status', '');
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');

        $query = CommercialProspection::with(['commercial:id,name,email'])
            ->whereNot('status', CommercialProspection::STATUS_DRAFT)
            ->latest('submitted_at');

        if ($commercialId > 0) {
            $query->where('commercial_id', $commercialId);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($dateFrom !== '') {
            $query->whereDate('submitted_at', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $query->whereDate('submitted_at', '<=', $dateTo);
        }

        $prospections = $query->paginate(20)->withQueryString();

        $commercials = User::query()->where('role_key', 'commercial')->orderBy('name')->get(['id', 'name']);

        $stats = [
            'total' => CommercialProspection::whereNot('status', CommercialProspection::STATUS_DRAFT)->count(),
            'this_week' => CommercialProspection::whereNot('status', CommercialProspection::STATUS_DRAFT)
                ->where('submitted_at', '>=', now()->startOfWeek())->count(),
            'pending' => CommercialProspection::whereIn('status', [
                CommercialProspection::STATUS_SUBMITTED, CommercialProspection::STATUS_UNDER_REVIEW,
            ])->count(),
            'approved' => CommercialProspection::where('status', CommercialProspection::STATUS_APPROVED)->count(),
            'needs_revision' => CommercialProspection::where('status', CommercialProspection::STATUS_NEEDS_REVISION)->count(),
            'rejected' => CommercialProspection::where('status', CommercialProspection::STATUS_REJECTED)->count(),
        ];

        $byCommercial = CommercialProspection::whereNot('status', CommercialProspection::STATUS_DRAFT)
            ->with('commercial:id,name')
            ->get()
            ->groupBy('commercial.name')
            ->map->count();

        return view('admin.prospections.index', [
            'prospections' => $prospections,
            'commercials' => $commercials,
            'stats' => $stats,
            'byCommercial' => $byCommercial,
            'filters' => [
                'commercial_id' => $commercialId,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function show(CommercialProspection $prospection): View
    {
        if ($prospection->status === CommercialProspection::STATUS_SUBMITTED) {
            $prospection->update(['status' => CommercialProspection::STATUS_UNDER_REVIEW]);
        }

        return view('admin.prospections.show', compact('prospection'));
    }

    public function download(CommercialProspection $prospection)
    {
        return $prospection->downloadResponse();
    }

    public function approve(Request $request, CommercialProspection $prospection): RedirectResponse
    {
        return $this->review($request, $prospection, CommercialProspection::STATUS_APPROVED, 'validée');
    }

    public function requestRevision(Request $request, CommercialProspection $prospection): RedirectResponse
    {
        return $this->review($request, $prospection, CommercialProspection::STATUS_NEEDS_REVISION, 'retournée pour correction');
    }

    public function reject(Request $request, CommercialProspection $prospection): RedirectResponse
    {
        return $this->review($request, $prospection, CommercialProspection::STATUS_REJECTED, 'rejetée');
    }

    private function review(Request $request, CommercialProspection $prospection, string $status, string $verbFr): RedirectResponse
    {
        $validated = $request->validate([
            'admin_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $prospection->update([
            'status' => $status,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
            'admin_comment' => $validated['admin_comment'] ?? $prospection->admin_comment,
        ]);

        $label = $prospection->title ?: 'votre prospection du '.$prospection->submitted_at->format('d/m/Y');

        AppNotification::create([
            'user_id' => $prospection->commercial_id,
            'title' => 'Prospection '.$verbFr,
            'body' => 'Votre prospection « '.$label.' » a été '.$verbFr.'.'.(! empty($validated['admin_comment']) ? ' Commentaire : '.$validated['admin_comment'] : ''),
            'type' => $status === CommercialProspection::STATUS_APPROVED ? 'success' : ($status === CommercialProspection::STATUS_REJECTED ? 'danger' : 'warning'),
            'action_url' => route('commercial.prospections.show', $prospection),
        ]);

        return redirect()->route('admin.prospections.index')
            ->with('status', 'Prospection '.$verbFr.'.');
    }
}
