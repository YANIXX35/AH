<?php

namespace App\Http\Controllers;

use App\Models\Prospect;
use App\Models\User;
use App\Services\CommercialTeamOverviewService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Tableau de bord de pilotage de l'équipe commerciale, pour l'admin plateforme.
 * Page séparée de admin.commerciale (qui reste inchangée). Les données sont
 * construites par CommercialTeamOverviewService, partagé avec le portail
 * dédié du superviseur commercial (CommercialSupervisorController) pour ne
 * jamais faire diverger les deux vues.
 */
class AdminCommercialDashboardController extends Controller
{
    public function __construct(
        private readonly CommercialTeamOverviewService $overview
    ) {}

    public function index(): View
    {
        return view('admin.commercial-dashboard', $this->overview->build());
    }

    public function showCommercial(User $commercial): View
    {
        abort_unless($commercial->role_key === 'commercial', 404);

        return view('admin.commercial-show', $this->overview->buildForCommercial($commercial));
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

        return view('admin.commercial-prospects', [
            'prospects' => $prospects,
            'commercials' => $commercials,
            'filters' => [
                'commercial_id' => $commercialId,
                'status' => $status,
            ],
        ]);
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
