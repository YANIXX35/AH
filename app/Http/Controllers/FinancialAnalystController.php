<?php

namespace App\Http\Controllers;

use App\Contracts\FinancialRatioServiceContract;
use App\Models\AccountingEntry;
use App\Models\FinancialAnalystNote;
use App\Models\InvestmentRequest;
use App\Models\User;
use App\Services\Scoring360Service;
use App\Support\ClientWorkspace;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Portail dédié Analyste Financier — portefeuille multi-PME, fiche de synthèse par
 * entreprise (fiabilité des données, alertes, scoring, ratios, dossier de
 * financement, notes) et accès aux pièces justificatives via ClientWorkspace.
 */
class FinancialAnalystController extends Controller
{
    /**
     * Même workflow que AdminInvestmentRequestController — dupliqué volontairement
     * (petite constante, pas de service partagé existant à ce jour) plutôt que de
     * coupler les deux contrôleurs.
     */
    private const ALLOWED_TRANSITIONS = [
        'pending' => ['in_review', 'declined'],
        'in_review' => ['accepted', 'declined'],
        'accepted' => [],
        'declined' => [],
    ];

    public function __construct(
        private readonly FinancialRatioServiceContract $ratioService,
        private readonly Scoring360Service $scoring360
    ) {}

    /**
     * Portefeuille : toutes les PME de la plateforme (v1 — pas d'assignation par
     * analyste), avec un indicateur de santé basé sur le dernier score investisseur
     * déjà calculé (aucun recalcul coûteux sur cette liste), une vue consolidée en
     * tête de page, et des filtres avancés (secteur, niveau de risque, dossier de
     * financement en attente).
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $sector = trim((string) $request->query('sector', ''));
        $risk = trim((string) $request->query('risk', ''));
        $pendingFinancingOnly = $request->boolean('pending_financing');

        $baseQuery = User::query()->clients();

        $sectors = (clone $baseQuery)
            ->whereNotNull('sector')
            ->where('sector', '!=', '')
            ->distinct()
            ->orderBy('sector')
            ->pluck('sector');

        $companies = User::query()
            ->clients()
            ->with('investorProfile')
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($sector, fn ($query, $sector) => $query->where('sector', $sector))
            ->when($risk, function ($query, $risk) {
                if ($risk === 'jamais_evalue') {
                    $query->doesntHave('investorProfile');

                    return;
                }

                $query->whereHas('investorProfile', function ($q) use ($risk) {
                    match ($risk) {
                        'bon' => $q->where('risk_score', '>=', 70),
                        'moyen' => $q->whereBetween('risk_score', [40, 69.99]),
                        'eleve' => $q->where('risk_score', '<', 40),
                        default => null,
                    };
                });
            })
            ->when($pendingFinancingOnly, function ($query) {
                $query->whereHas('investmentRequests', function ($q) {
                    $q->whereIn('status', ['pending', 'in_review']);
                });
            })
            ->orderBy('company_name')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $portfolioIds = (clone $baseQuery)->pluck('id');
        $pendingRequests = InvestmentRequest::whereIn('user_id', $portfolioIds)
            ->whereIn('status', ['pending', 'in_review'])
            ->get();

        return view('financial-analyst.portfolio', [
            'companies' => $companies,
            'search' => $search,
            'sector' => $sector,
            'risk' => $risk,
            'pendingFinancingOnly' => $pendingFinancingOnly,
            'sectors' => $sectors,
            'summary' => [
                'total' => $portfolioIds->count(),
                'never_evaluated' => User::whereIn('id', $portfolioIds)->doesntHave('investorProfile')->count(),
                'at_risk' => User::whereIn('id', $portfolioIds)->whereHas('investorProfile', fn ($q) => $q->where('risk_score', '<', 40))->count(),
                'pending_requests_count' => $pendingRequests->count(),
                'pending_requests_amount' => $pendingRequests->sum('amount_requested'),
            ],
        ]);
    }

    /**
     * Fiche PME : fiabilité des données, alertes, scoring, ratios, comparaison
     * sectorielle, dossier de financement (avec décision), notes de l'analyste.
     */
    public function show(User $company): View
    {
        if (! ClientWorkspace::isAssignableClient($company)) {
            abort(404);
        }

        $analysis = $this->ratioService->analyze($company->id);

        try {
            $scoring360 = $this->scoring360->scoreUser($company->id);
        } catch (\Throwable) {
            $scoring360 = null;
        }

        $missingAttachmentsCount = AccountingEntry::where('user_id', $company->id)
            ->where(function ($query) {
                $query->whereNull('attachment_path')->orWhere('attachment_path', '');
            })
            ->count();

        $investmentRequests = InvestmentRequest::where('user_id', $company->id)
            ->orderByDesc('created_at')
            ->get();

        $notes = FinancialAnalystNote::where('user_id', $company->id)
            ->with('analyst:id,name')
            ->orderByDesc('created_at')
            ->get();

        return view('financial-analyst.show', [
            'company' => $company,
            'analysis' => $analysis,
            'scoring360' => $scoring360,
            'missingAttachmentsCount' => $missingAttachmentsCount,
            'investmentRequests' => $investmentRequests,
            'notes' => $notes,
            'sectorComparison' => $this->buildSectorComparison($company, $analysis),
            'allowedFundingTransitions' => self::ALLOWED_TRANSITIONS,
        ]);
    }

    /**
     * Moyenne des ratios clés (ROA, marge nette, endettement) sur les autres PME du
     * même secteur — limité à 30 entreprises pour éviter un recalcul trop coûteux.
     * Retourne null si le secteur n'est pas renseigné ou qu'aucune autre PME du
     * secteur n'a de données comptables exploitables.
     */
    private function buildSectorComparison(User $company, array $analysis): ?array
    {
        $sector = trim((string) $company->sector);
        if ($sector === '') {
            return null;
        }

        $peerIds = User::query()
            ->clients()
            ->where('sector', $sector)
            ->where('id', '!=', $company->id)
            ->limit(30)
            ->pluck('id');

        if ($peerIds->isEmpty()) {
            return null;
        }

        $roaValues = [];
        $margeValues = [];
        $endettementValues = [];

        foreach ($peerIds as $peerId) {
            $peerAnalysis = $this->ratioService->analyze($peerId);
            if (($peerAnalysis['entries_count'] ?? 0) === 0) {
                continue;
            }
            if (($peerAnalysis['ratios']['roa_pct'] ?? null) !== null) {
                $roaValues[] = (float) $peerAnalysis['ratios']['roa_pct'];
            }
            if (($peerAnalysis['ratios']['marge_nette_pct'] ?? null) !== null) {
                $margeValues[] = (float) $peerAnalysis['ratios']['marge_nette_pct'];
            }
            if (($peerAnalysis['ratios']['endettement_sur_actif_pct'] ?? null) !== null) {
                $endettementValues[] = (float) $peerAnalysis['ratios']['endettement_sur_actif_pct'];
            }
        }

        if (empty($roaValues) && empty($margeValues) && empty($endettementValues)) {
            return null;
        }

        $avg = fn (array $values) => empty($values) ? null : round(array_sum($values) / count($values), 2);

        return [
            'sector' => $sector,
            'peers_count' => $peerIds->count(),
            'avg_roa_pct' => $avg($roaValues),
            'avg_marge_nette_pct' => $avg($margeValues),
            'avg_endettement_sur_actif_pct' => $avg($endettementValues),
        ];
    }

    public function storeNote(Request $request, User $company): RedirectResponse
    {
        if (! ClientWorkspace::isAssignableClient($company)) {
            abort(404);
        }

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:5000'],
        ]);

        FinancialAnalystNote::create([
            'user_id' => $company->id,
            'analyst_user_id' => $request->user()->id,
            'note' => $validated['note'],
        ]);

        return redirect()->route('analyst.pme.show', $company)->with('status', 'Note enregistrée.');
    }

    /**
     * Décision de l'analyste sur un dossier de financement — même workflow et
     * mêmes règles que côté admin (note obligatoire pour une décision finale).
     */
    public function updateFundingRequest(Request $request, InvestmentRequest $investmentRequest): RedirectResponse
    {
        $company = $investmentRequest->user;
        if (! $company || ! ClientWorkspace::isAssignableClient($company)) {
            abort(404);
        }

        $validated = $request->validate([
            'next_status' => ['required', 'in:in_review,accepted,declined'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $current = (string) $investmentRequest->status;
        $next = (string) $validated['next_status'];
        $allowed = self::ALLOWED_TRANSITIONS[$current] ?? [];

        if (! in_array($next, $allowed, true)) {
            return back()->withErrors(['workflow' => "Transition invalide : {$current} → {$next}."]);
        }

        $note = trim((string) ($validated['review_note'] ?? ''));
        if (in_array($next, ['accepted', 'declined'], true) && $note === '') {
            return back()->withErrors(['workflow' => 'Une note d’analyse est obligatoire pour une décision finale.']);
        }

        $investmentRequest->update([
            'status' => $next,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $note !== '' ? $note : null,
        ]);

        return redirect()->route('analyst.pme.show', $company)->with('status', 'Dossier de financement mis à jour.');
    }

    /**
     * Ouvre le dossier de la PME (même mécanisme que le comptable) puis redirige
     * vers ses rapports comptables — pièces justificatives en lecture.
     */
    public function openDossier(Request $request, User $company): RedirectResponse
    {
        if (! ClientWorkspace::isAssignableClient($company)) {
            abort(404);
        }

        ClientWorkspace::setWorkspaceUserId($company->id);

        return redirect()->route('accounting.report.bilan');
    }

    /**
     * Export PDF de la fiche d'analyse — pour partage avec un comité de crédit.
     */
    public function exportPdf(User $company)
    {
        if (! ClientWorkspace::isAssignableClient($company)) {
            abort(404);
        }

        $analysis = $this->ratioService->analyze($company->id);

        try {
            $scoring360 = $this->scoring360->scoreUser($company->id);
        } catch (\Throwable) {
            $scoring360 = null;
        }

        $investmentRequests = InvestmentRequest::where('user_id', $company->id)
            ->orderByDesc('created_at')
            ->get();

        $safeName = 'analyse-financiere-'.preg_replace('/[^a-z0-9\-]+/i', '-', $company->company_name ?: $company->name).'.pdf';

        return Pdf::loadView('financial-analyst.pdf.fiche', [
            'company' => $company,
            'analysis' => $analysis,
            'scoring360' => $scoring360,
            'investmentRequests' => $investmentRequests,
            'sectorComparison' => $this->buildSectorComparison($company, $analysis),
        ])->setPaper('a4', 'portrait')->download($safeName);
    }

    /**
     * Historique : toutes les notes déjà écrites par cet analyste, tous dossiers
     * confondus.
     */
    public function history(Request $request): View
    {
        $notes = FinancialAnalystNote::where('analyst_user_id', $request->user()->id)
            ->with('company:id,name,company_name')
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('financial-analyst.history', [
            'notes' => $notes,
        ]);
    }
}
