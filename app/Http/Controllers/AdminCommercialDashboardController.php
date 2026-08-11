<?php

namespace App\Http\Controllers;

use App\Models\CommercialDocument;
use App\Models\CommercialFeedback;
use App\Models\CommercialProspection;
use App\Models\Prospect;
use App\Models\User;
use App\Models\UserLoginLog;
use App\Services\CommercialCommissionService;
use Illuminate\View\View;

/**
 * Tableau de bord de pilotage de l'équipe commerciale, pour l'admin plateforme.
 * Page séparée de admin.commerciale (qui reste inchangée) : consolide ici la
 * performance, les prospections, les commissions (lecture seule — la
 * validation des paiements reste du ressort du comptable), le pipeline de
 * prospects et les retours commerciaux, sans dupliquer la logique métier
 * existante (réutilise CommercialCommissionService comme la page comptable).
 */
class AdminCommercialDashboardController extends Controller
{
    public function __construct(
        private readonly CommercialCommissionService $commission
    ) {}

    public function index(): View
    {
        $commercials = User::query()
            ->where('role_key', 'commercial')
            ->orderBy('name')
            ->get();

        $commercialIds = $commercials->pluck('id');

        $lastLogins = UserLoginLog::query()
            ->whereIn('user_id', $commercialIds)
            ->where('event', 'login')
            ->selectRaw('user_id, MAX(created_at) as last_login_at')
            ->groupBy('user_id')
            ->pluck('last_login_at', 'user_id');

        $lastProspections = CommercialProspection::query()
            ->whereIn('commercial_id', $commercialIds)
            ->selectRaw('commercial_id, MAX(created_at) as last_at')
            ->groupBy('commercial_id')
            ->pluck('last_at', 'commercial_id');

        $prospectCounts = Prospect::query()
            ->whereIn('commercial_user_id', $commercialIds)
            ->selectRaw('commercial_user_id, count(*) as cnt')
            ->groupBy('commercial_user_id')
            ->pluck('cnt', 'commercial_user_id');

        $rows = $commercials->map(function (User $commercial) use ($lastLogins, $lastProspections, $prospectCounts) {
            $balance = $this->commission->calculateBalance($commercial);
            $totalPaid = $this->commission->totalPaid($commercial);
            $remaining = max(0, $balance['totalBalance'] - $totalPaid);

            return [
                'commercial' => $commercial,
                'totalClients' => $balance['totalClients'],
                'totalEarned' => $balance['totalBalance'],
                'totalPaid' => $totalPaid,
                'remaining' => $remaining,
                'prospectsCount' => (int) ($prospectCounts[$commercial->id] ?? 0),
                'lastLoginAt' => $lastLogins[$commercial->id] ?? null,
                'lastProspectionAt' => $lastProspections[$commercial->id] ?? null,
            ];
        })->sortByDesc('totalEarned')->values();

        $prospectionStats = CommercialProspection::query()
            ->whereIn('commercial_id', $commercialIds)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $recentProspections = CommercialProspection::query()
            ->whereIn('commercial_id', $commercialIds)
            ->whereNot('status', CommercialProspection::STATUS_DRAFT)
            ->with('commercial:id,name')
            ->latest()
            ->limit(6)
            ->get();

        $prospectStatusStats = Prospect::query()
            ->whereIn('commercial_user_id', $commercialIds)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $recentFeedback = CommercialFeedback::query()
            ->whereIn('user_id', $commercialIds)
            ->with('user:id,name')
            ->latest()
            ->limit(8)
            ->get();

        $recentDocuments = CommercialDocument::query()
            ->whereIn('user_id', $commercialIds)
            ->with('user:id,name')
            ->latest()
            ->limit(8)
            ->get();

        $referredClientsThisMonth = User::query()
            ->whereIn('created_by_user_id', $commercialIds)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        return view('admin.commercial-dashboard', [
            'rows' => $rows,
            'totalCommercials' => $commercials->count(),
            'totalClientsReferred' => $rows->sum('totalClients'),
            'referredClientsThisMonth' => $referredClientsThisMonth,
            'grandTotalEarned' => $rows->sum('totalEarned'),
            'grandTotalPaid' => $rows->sum('totalPaid'),
            'grandTotalRemaining' => $rows->sum('remaining'),
            'prospectionStats' => $prospectionStats,
            'recentProspections' => $recentProspections,
            'prospectStatusStats' => $prospectStatusStats,
            'recentFeedback' => $recentFeedback,
            'recentDocuments' => $recentDocuments,
        ]);
    }
}
