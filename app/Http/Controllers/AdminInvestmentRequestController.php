<?php

namespace App\Http\Controllers;

use App\Models\InvestmentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Réception et traitement des demandes d’investissement côté plateforme (équipe admin).
 */
class AdminInvestmentRequestController extends Controller
{
    private const ALLOWED_TRANSITIONS = [
        'pending' => ['in_review', 'declined'],
        'in_review' => ['accepted', 'declined'],
        'accepted' => [],
        'declined' => [],
    ];

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,in_review,accepted,declined,all'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $status = $validated['status'] ?? 'all';
        $q = trim((string) ($validated['q'] ?? ''));

        $query = InvestmentRequest::query()
            ->with(['user', 'reviewer'])
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->whereHas('user', function ($uq) use ($like) {
                $uq->where('company_name', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        $requests = $query->paginate(20)->withQueryString();

        $counts = [
            'pending' => InvestmentRequest::query()->where('status', 'pending')->count(),
            'in_review' => InvestmentRequest::query()->where('status', 'in_review')->count(),
            'accepted' => InvestmentRequest::query()->where('status', 'accepted')->count(),
            'declined' => InvestmentRequest::query()->where('status', 'declined')->count(),
            'all' => InvestmentRequest::query()->count(),
        ];

        return view('admin.investment-requests.index', [
            'requests' => $requests,
            'counts' => $counts,
            'status' => $status,
            'q' => $q,
        ]);
    }

    public function show(InvestmentRequest $investmentRequest): View
    {
        $investmentRequest->load(['user', 'reviewer']);

        return view('admin.investment-requests.show', [
            'req' => $investmentRequest,
        ]);
    }

    public function updateWorkflow(Request $request, InvestmentRequest $investmentRequest): RedirectResponse
    {
        $validated = $request->validate([
            'next_status' => ['required', 'in:in_review,accepted,declined'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $current = (string) $investmentRequest->status;
        $next = (string) $validated['next_status'];
        $allowed = self::ALLOWED_TRANSITIONS[$current] ?? [];

        if (! in_array($next, $allowed, true)) {
            return back()
                ->withErrors(['workflow' => "Transition invalide : {$current} → {$next}."])
                ->withInput();
        }

        $note = trim((string) ($validated['review_note'] ?? ''));
        if (in_array($next, ['accepted', 'declined'], true) && $note === '') {
            return back()
                ->withErrors(['workflow' => 'Une note d’analyse est obligatoire pour une décision finale.'])
                ->withInput();
        }

        $investmentRequest->update([
            'status' => $next,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $note !== '' ? $note : null,
        ]);

        return redirect()
            ->route('admin.investment-requests.show', $investmentRequest)
            ->with('status', 'Demande mise à jour.');
    }
}
