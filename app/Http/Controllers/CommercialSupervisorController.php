<?php

namespace App\Http\Controllers;

use App\Models\CommercialProspection;
use App\Models\Prospect;
use App\Models\User;
use App\Services\CommercialTeamOverviewService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Portail dédié à la supervision commerciale (role_key = commercial_supervisor),
 * séparé du dashboard admin plateforme (AdminCommercialDashboardController) —
 * même source de données (CommercialTeamOverviewService) mais un espace à
 * part, réservé au superviseur commercial, sans le reste de l'administration
 * plateforme. Lecture seule : aucune action de validation/rejet n'est
 * proposée ici, conformément à la décision prise pour ce rôle.
 */
class CommercialSupervisorController extends Controller
{
    public function __construct(
        private readonly CommercialTeamOverviewService $overview
    ) {}

    public function dashboard(): View
    {
        return view('commercial-supervisor.dashboard', $this->overview->build());
    }

    public function showCommercial(User $commercial): View
    {
        abort_unless($commercial->role_key === 'commercial', 404);

        return view('commercial-supervisor.commercial-show', $this->overview->buildForCommercial($commercial));
    }

    public function prospects(Request $request): View
    {
        $commercialId = (int) $request->query('commercial_id', 0);
        $status = (string) $request->query('status', '');

        $query = Prospect::with(['commercial:id,name'])
            ->whereHas('commercial', fn ($q) => $q->where('role_key', 'commercial'))
            ->latest();

        if ($commercialId > 0) {
            $query->where('commercial_user_id', $commercialId);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        $prospects = $query->paginate(20)->withQueryString();
        $commercials = User::query()->where('role_key', 'commercial')->orderBy('name')->get(['id', 'name']);

        return view('commercial-supervisor.prospects', [
            'prospects' => $prospects,
            'commercials' => $commercials,
            'filters' => [
                'commercial_id' => $commercialId,
                'status' => $status,
            ],
        ]);
    }

    public function prospections(Request $request): View
    {
        $commercialId = (int) $request->query('commercial_id', 0);
        $status = (string) $request->query('status', '');

        $query = CommercialProspection::with(['commercial:id,name,email'])
            ->whereNot('status', CommercialProspection::STATUS_DRAFT)
            ->latest('submitted_at');

        if ($commercialId > 0) {
            $query->where('commercial_id', $commercialId);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        $prospections = $query->paginate(20)->withQueryString();
        $commercials = User::query()->where('role_key', 'commercial')->orderBy('name')->get(['id', 'name']);

        return view('commercial-supervisor.prospections', [
            'prospections' => $prospections,
            'commercials' => $commercials,
            'filters' => [
                'commercial_id' => $commercialId,
                'status' => $status,
            ],
        ]);
    }

    public function showProspection(CommercialProspection $prospection): View
    {
        $this->authorize('view', $prospection);

        return view('commercial-supervisor.prospection-show', compact('prospection'));
    }

    public function downloadProspectionFile(CommercialProspection $prospection)
    {
        $this->authorize('view', $prospection);

        return $prospection->downloadResponse();
    }

    public function exportCsv(): Response
    {
        $csv = $this->overview->buildPerformanceCsv();

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="performance-commerciale-'.now()->format('Y-m-d').'.csv"',
        ]);
    }
}
