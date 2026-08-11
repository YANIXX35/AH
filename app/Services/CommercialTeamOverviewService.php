<?php

namespace App\Services;

use App\Models\CommercialDocument;
use App\Models\CommercialFeedback;
use App\Models\CommercialProspection;
use App\Models\Prospect;
use App\Models\User;
use App\Models\UserLoginLog;

/**
 * Construit les données de pilotage de l'équipe commerciale (performance,
 * commissions en lecture seule, prospections, pipeline, feedback, documents).
 * Source unique utilisée à la fois par le dashboard admin plateforme
 * (AdminCommercialDashboardController) et le portail dédié du superviseur
 * commercial (CommercialSupervisorController), pour ne jamais faire diverger
 * les deux vues.
 */
class CommercialTeamOverviewService
{
    public function __construct(
        private readonly CommercialCommissionService $commission
    ) {}

    public function build(): array
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

        $clientsThisMonthByCommercial = User::query()
            ->whereIn('created_by_user_id', $commercialIds)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('created_by_user_id, count(*) as cnt')
            ->groupBy('created_by_user_id')
            ->pluck('cnt', 'created_by_user_id');

        $prospectionsThisMonthByCommercial = CommercialProspection::query()
            ->whereIn('commercial_id', $commercialIds)
            ->whereNot('status', CommercialProspection::STATUS_DRAFT)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('commercial_id, count(*) as cnt')
            ->groupBy('commercial_id')
            ->pluck('cnt', 'commercial_id');

        $monthlyLeaderboard = $commercials->map(fn (User $commercial) => [
            'commercial' => $commercial,
            'clientsThisMonth' => (int) ($clientsThisMonthByCommercial[$commercial->id] ?? 0),
            'prospectionsThisMonth' => (int) ($prospectionsThisMonthByCommercial[$commercial->id] ?? 0),
        ])
            ->filter(fn (array $row) => $row['clientsThisMonth'] > 0 || $row['prospectionsThisMonth'] > 0)
            ->sortByDesc(fn (array $row) => $row['clientsThisMonth'] * 10 + $row['prospectionsThisMonth'])
            ->take(5)
            ->values();

        $recentLogins = UserLoginLog::query()
            ->whereIn('user_id', $commercialIds)
            ->where('event', 'login')
            ->with('user:id,name')
            ->latest()
            ->limit(10)
            ->get();

        return [
            'rows' => $rows,
            'totalCommercials' => $commercials->count(),
            'totalClientsReferred' => $rows->sum('totalClients'),
            'referredClientsThisMonth' => $clientsThisMonthByCommercial->sum(),
            'grandTotalEarned' => $rows->sum('totalEarned'),
            'grandTotalPaid' => $rows->sum('totalPaid'),
            'grandTotalRemaining' => $rows->sum('remaining'),
            'prospectionStats' => $prospectionStats,
            'recentProspections' => $recentProspections,
            'prospectStatusStats' => $prospectStatusStats,
            'recentFeedback' => $recentFeedback,
            'recentDocuments' => $recentDocuments,
            'monthlyLeaderboard' => $monthlyLeaderboard,
            'recentLogins' => $recentLogins,
        ];
    }

    /**
     * Fiche détaillée d'un commercial : ses clients (avec le détail des
     * commissions par client, réutilisé tel quel depuis
     * CommercialCommissionService), ses prospects, ses prospections et son
     * historique de connexion.
     */
    public function buildForCommercial(User $commercial): array
    {
        $balance = $this->commission->calculateBalance($commercial);
        $totalPaid = $this->commission->totalPaid($commercial);

        $prospects = Prospect::query()
            ->where('commercial_user_id', $commercial->id)
            ->latest()
            ->get();

        $prospections = CommercialProspection::query()
            ->where('commercial_id', $commercial->id)
            ->whereNot('status', CommercialProspection::STATUS_DRAFT)
            ->latest()
            ->get();

        $loginHistory = UserLoginLog::query()
            ->where('user_id', $commercial->id)
            ->where('event', 'login')
            ->latest()
            ->limit(20)
            ->get();

        return [
            'commercial' => $commercial,
            'clientRows' => $balance['rows'],
            'totalClients' => $balance['totalClients'],
            'totalEarned' => $balance['totalBalance'],
            'totalPaid' => $totalPaid,
            'remaining' => max(0, $balance['totalBalance'] - $totalPaid),
            'prospects' => $prospects,
            'prospections' => $prospections,
            'loginHistory' => $loginHistory,
        ];
    }

    /**
     * Export CSV du tableau de performance (mêmes lignes que build()['rows']),
     * pour extraction/reporting hors de l'application.
     */
    public function buildPerformanceCsv(): string
    {
        $rows = $this->build()['rows'];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Commercial', 'Email', 'Clients parrainés', 'Prospects', 'Gagné (FCFA)', 'Versé (FCFA)', 'Restant dû (FCFA)', 'Dernière connexion', 'Dernière prospection']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['commercial']->name,
                $row['commercial']->email,
                $row['totalClients'],
                $row['prospectsCount'],
                $row['totalEarned'],
                $row['totalPaid'],
                $row['remaining'],
                $row['lastLoginAt'] ? (string) $row['lastLoginAt'] : '',
                $row['lastProspectionAt'] ? (string) $row['lastProspectionAt'] : '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
