<?php

namespace App\Http\Controllers;

use App\Services\CommercialTeamOverviewService;
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
}
