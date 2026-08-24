<?php

namespace App\Http\Controllers;

use App\Contracts\FinancialRatioServiceContract;
use App\Models\AccountingEntry;
use App\Models\FinancialAnalystNote;
use App\Models\InvestmentRequest;
use App\Models\User;
use App\Services\Scoring360Service;
use App\Support\ClientWorkspace;
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
    public function __construct(
        private readonly FinancialRatioServiceContract $ratioService,
        private readonly Scoring360Service $scoring360
    ) {}

    /**
     * Portefeuille : toutes les PME de la plateforme (v1 — pas d'assignation par
     * analyste), avec un indicateur de santé basé sur le dernier score investisseur
     * déjà calculé (aucun recalcul coûteux sur cette liste).
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

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
            ->orderBy('company_name')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('financial-analyst.portfolio', [
            'companies' => $companies,
            'search' => $search,
        ]);
    }

    /**
     * Fiche PME : fiabilité des données, alertes, scoring, ratios, dossier de
     * financement, notes de l'analyste.
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
        ]);
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
