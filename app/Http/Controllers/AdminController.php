<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminUpdateUserRequest;
use App\Models\AccountingDocument;
use App\Models\AccountingEntry;
use App\Models\EnterpriseLicense;
use App\Models\InvestmentRequest;
use App\Models\MenuActionLog;
use App\Models\SupportTicket;
use App\Models\TreasuryAuditLog;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Services\AdminAuditTrailService;
use App\Services\HuggingFaceOpsAssistantService;
use App\Services\UserPremiumService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __construct(
        private readonly AdminAuditTrailService $auditTrail,
        private readonly HuggingFaceOpsAssistantService $hfAssistant,
        private readonly UserPremiumService $userPremium
    ) {}

    /**
     * Tableau de bord administrateur (vue synthétique multi-tenant).
     */
    public function index(Request $request): View
    {
        $userCount = User::query()->count();
        $premiumCount = User::query()->where('is_premium', true)->count();
        $entriesCount = AccountingEntry::query()->count();
        $withTradeRegister = User::query()
            ->whereNotNull('trade_register_file')
            ->where('trade_register_file', '!=', '')
            ->count();

        $usersNewLast7Days = User::query()->where('created_at', '>=', now()->subDays(7))->count();
        $usersNewLast30Days = User::query()->where('created_at', '>=', now()->subDays(30))->count();

        // Série jour par jour sur 7 jours pour le graphique d’inscriptions.
        $registrationSeries = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->startOfDay();
            $registrationSeries[] = [
                'label' => $day->format('d/m'),
                'count' => User::query()->whereDate('created_at', $day->toDateString())->count(),
            ];
        }

        $documentsCount = AccountingDocument::query()->count();
        $treasuryCount = TreasuryTransaction::query()->count();
        $ticketsOpenCount = SupportTicket::query()->whereIn('status', [
            SupportTicket::STATUS_OPEN,
            SupportTicket::STATUS_IN_PROGRESS,
        ])->count();
        $ticketsTotal = SupportTicket::query()->count();
        $investmentRequestsCount = InvestmentRequest::query()->count();
        $investmentRequestsPendingCount = InvestmentRequest::query()->where('status', 'pending')->count();

        $platformAdminCount = User::query()->where('is_platform_admin', true)->count();
        $pctTradeRegister = $userCount > 0 ? (int) round(100 * $withTradeRegister / $userCount) : 0;
        $pctPremium = $userCount > 0 ? (int) round(100 * $premiumCount / $userCount) : 0;

        $recentUsers = User::query()
            ->latest()
            ->limit(8)
            ->get(['id', 'name', 'email', 'company_name', 'created_at', 'is_premium', 'is_platform_admin']);

        $now = now();
        $licensesBase = EnterpriseLicense::query();
        $licensesActiveCount = (clone $licensesBase)
            ->whereNull('revoked_at')
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->count();
        $licensesExpiring7Count = (clone $licensesBase)
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->where('expires_at', '<=', (clone $now)->addDays(7))
            ->count();
        $licensesExpiredCount = (clone $licensesBase)
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->count();
        $nextLicenseExpiry = (clone $licensesBase)
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->orderBy('expires_at')
            ->value('expires_at');

        $subsBase = User::query()
            ->where('is_platform_admin', false)
            ->where('is_accountant', false)
            ->where('is_premium', true);
        $subscriptionsActiveCount = (clone $subsBase)
            ->where(function ($q) use ($now) {
                $q->whereNull('premium_ends_at')->orWhere('premium_ends_at', '>', $now);
            })
            ->count();
        $subscriptionsExpiring7Count = (clone $subsBase)
            ->whereNotNull('premium_ends_at')
            ->where('premium_ends_at', '>', $now)
            ->where('premium_ends_at', '<=', (clone $now)->addDays(7))
            ->count();
        $subscriptionsExpiredCount = (clone $subsBase)
            ->whereNotNull('premium_ends_at')
            ->where('premium_ends_at', '<=', $now)
            ->count();
        $nextSubscriptionExpiry = (clone $subsBase)
            ->whereNotNull('premium_ends_at')
            ->where('premium_ends_at', '>', $now)
            ->orderBy('premium_ends_at')
            ->value('premium_ends_at');

        $openTicketRate = $ticketsTotal > 0 ? (float) (100 * $ticketsOpenCount / $ticketsTotal) : 0.0;
        $pendingInvestmentRate = $investmentRequestsCount > 0 ? (float) (100 * $investmentRequestsPendingCount / $investmentRequestsCount) : 0.0;
        $menuErrors24h = MenuActionLog::query()
            ->where('status_code', '>=', 500)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $riskHeatmap = $this->buildRiskHeatmap(
            $openTicketRate,
            $pendingInvestmentRate,
            (float) $pctTradeRegister,
            (float) $pctPremium,
            (int) $menuErrors24h
        );

        $incidentTimeline = $this->buildIncidentTimeline();
        $actionsOfDay = $this->buildActionsOfDay(
            $openTicketRate,
            $pendingInvestmentRate,
            (float) $pctTradeRegister,
            (float) $pctPremium,
            (int) $menuErrors24h,
            $entriesCount,
            $userCount
        );
        $healthScore = 100.0;
        $healthScore -= min(30.0, $openTicketRate * 0.35);
        $healthScore -= min(20.0, max(0, 55 - $pctTradeRegister) * 0.30);
        $healthScore -= min(20.0, max(0, 30 - $pctPremium) * 0.40);
        $healthScore -= min(15.0, $pendingInvestmentRate * 0.18);
        $healthScore = max(0.0, round($healthScore, 1));
        $aiInconsistencies = $this->buildAccountingTreasuryInconsistencies();
        $aiLiveInsight = $this->buildDashboardLiveInsight(
            [
                'open_ticket_rate' => $openTicketRate,
                'pending_investment_rate' => $pendingInvestmentRate,
                'pct_trade_register' => (float) $pctTradeRegister,
                'pct_premium' => (float) $pctPremium,
                'menu_errors_24h' => (int) $menuErrors24h,
                'entries_count' => $entriesCount,
                'treasury_count' => $treasuryCount,
                'health_score' => $healthScore,
            ],
            $aiInconsistencies
        );

        return view('admin.dashboard', [
            'userCount' => $userCount,
            'premiumCount' => $premiumCount,
            'entriesCount' => $entriesCount,
            'withTradeRegister' => $withTradeRegister,
            'usersNewLast7Days' => $usersNewLast7Days,
            'usersNewLast30Days' => $usersNewLast30Days,
            'registrationSeries' => $registrationSeries,
            'documentsCount' => $documentsCount,
            'treasuryCount' => $treasuryCount,
            'ticketsOpenCount' => $ticketsOpenCount,
            'ticketsTotal' => $ticketsTotal,
            'investmentRequestsCount' => $investmentRequestsCount,
            'investmentRequestsPendingCount' => $investmentRequestsPendingCount,
            'platformAdminCount' => $platformAdminCount,
            'pctTradeRegister' => $pctTradeRegister,
            'pctPremium' => $pctPremium,
            'recentUsers' => $recentUsers,
            'riskHeatmap' => $riskHeatmap,
            'incidentTimeline' => $incidentTimeline,
            'actionsOfDay' => $actionsOfDay,
            'menuErrors24h' => $menuErrors24h,
            'aiInconsistencies' => $aiInconsistencies,
            'aiLiveInsight' => $aiLiveInsight,
            'licenseAlerts' => [
                'active' => $licensesActiveCount,
                'expiring_7' => $licensesExpiring7Count,
                'expired' => $licensesExpiredCount,
                'next_expiry' => $nextLicenseExpiry,
            ],
            'subscriptionAlerts' => [
                'active' => $subscriptionsActiveCount,
                'expiring_7' => $subscriptionsExpiring7Count,
                'expired' => $subscriptionsExpiredCount,
                'next_expiry' => $nextSubscriptionExpiry,
            ],
            'generatedAt' => now(),
        ]);
    }

    public function dashboardLiveInsights(): JsonResponse
    {
        $userCount = User::query()->count();
        $entriesCount = AccountingEntry::query()->count();
        $treasuryCount = TreasuryTransaction::query()->count();
        $ticketsOpenCount = SupportTicket::query()->whereIn('status', [
            SupportTicket::STATUS_OPEN,
            SupportTicket::STATUS_IN_PROGRESS,
        ])->count();
        $ticketsTotal = SupportTicket::query()->count();
        $investmentRequestsCount = InvestmentRequest::query()->count();
        $investmentRequestsPendingCount = InvestmentRequest::query()->where('status', 'pending')->count();
        $withTradeRegister = User::query()
            ->whereNotNull('trade_register_file')
            ->where('trade_register_file', '!=', '')
            ->count();
        $premiumCount = User::query()->where('is_premium', true)->count();
        $menuErrors24h = MenuActionLog::query()
            ->where('status_code', '>=', 500)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $openTicketRate = $ticketsTotal > 0 ? (float) (100 * $ticketsOpenCount / $ticketsTotal) : 0.0;
        $pendingInvestmentRate = $investmentRequestsCount > 0 ? (float) (100 * $investmentRequestsPendingCount / $investmentRequestsCount) : 0.0;
        $pctTradeRegister = $userCount > 0 ? (float) (100 * $withTradeRegister / $userCount) : 0.0;
        $pctPremium = $userCount > 0 ? (float) (100 * $premiumCount / $userCount) : 0.0;

        $healthScore = 100.0;
        $healthScore -= min(30.0, $openTicketRate * 0.35);
        $healthScore -= min(20.0, max(0, 55 - $pctTradeRegister) * 0.30);
        $healthScore -= min(20.0, max(0, 30 - $pctPremium) * 0.40);
        $healthScore -= min(15.0, $pendingInvestmentRate * 0.18);
        $healthScore = max(0.0, round($healthScore, 1));

        $inconsistencies = $this->buildAccountingTreasuryInconsistencies();
        $insight = $this->buildDashboardLiveInsight(
            [
                'open_ticket_rate' => round($openTicketRate, 1),
                'pending_investment_rate' => round($pendingInvestmentRate, 1),
                'pct_trade_register' => round($pctTradeRegister, 1),
                'pct_premium' => round($pctPremium, 1),
                'menu_errors_24h' => $menuErrors24h,
                'entries_count' => $entriesCount,
                'treasury_count' => $treasuryCount,
                'health_score' => $healthScore,
            ],
            $inconsistencies
        );

        return response()->json([
            'ok' => true,
            'generated_at' => now()->toIso8601String(),
            'inconsistencies' => $inconsistencies,
            'live_insight' => $insight,
        ]);
    }

    /**
     * Construit une heatmap de risques orientée exploitation plateforme.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildRiskHeatmap(
        float $openTicketRate,
        float $pendingInvestmentRate,
        float $pctTradeRegister,
        float $pctPremium,
        int $menuErrors24h
    ): array {
        $rows = [
            [
                'domain' => 'Support',
                'risk' => 'Backlog tickets',
                'probability' => $this->toScale($openTicketRate, 12, 24, 40, 55),
                'impact' => 4,
                'detail' => sprintf('%.1f%% de tickets ouverts.', $openTicketRate),
            ],
            [
                'domain' => 'Conformité',
                'risk' => 'Dossiers incomplets',
                'probability' => $this->toScale(100 - $pctTradeRegister, 12, 24, 35, 50),
                'impact' => 5,
                'detail' => sprintf('%.0f%% des comptes avec registre joint.', $pctTradeRegister),
            ],
            [
                'domain' => 'Financement',
                'risk' => 'Délai traitement demandes',
                'probability' => $this->toScale($pendingInvestmentRate, 10, 22, 38, 55),
                'impact' => 4,
                'detail' => sprintf('%.1f%% de demandes en attente.', $pendingInvestmentRate),
            ],
            [
                'domain' => 'Observabilité',
                'risk' => 'Erreurs HTTP critiques',
                'probability' => $this->toScale((float) $menuErrors24h, 1, 3, 6, 10),
                'impact' => 5,
                'detail' => $menuErrors24h.' erreur(s) HTTP 5xx sur 24h.',
            ],
            [
                'domain' => 'Revenus',
                'risk' => 'Faible taux Premium',
                'probability' => $this->toScale(max(0.0, 45.0 - $pctPremium), 4, 10, 16, 24),
                'impact' => 3,
                'detail' => sprintf('%.0f%% de comptes Premium.', $pctPremium),
            ],
        ];

        return collect($rows)
            ->map(function (array $row) {
                $score = (int) (($row['probability'] ?? 1) * ($row['impact'] ?? 1));
                $row['score'] = $score;
                $row['severity'] = $score >= 16 ? 'danger' : ($score >= 9 ? 'warning' : 'success');

                return $row;
            })
            ->sortByDesc('score')
            ->values()
            ->all();
    }

    /**
     * Timeline fusionnée d'incidents/signaux opérationnels.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildIncidentTimeline(): array
    {
        $ticketIncidents = SupportTicket::query()
            ->with('user:id,name,email')
            ->whereIn('status', [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_IN_PROGRESS])
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(function (SupportTicket $ticket) {
                return [
                    'at' => $ticket->updated_at ?? $ticket->created_at,
                    'module' => 'support',
                    'severity' => $ticket->status === SupportTicket::STATUS_OPEN ? 'warning' : 'info',
                    'title' => 'Ticket support '.$ticket->status,
                    'detail' => '#'.$ticket->id.' · '.($ticket->subject ?: 'Sans objet').' · '.($ticket->user?->email ?? 'n/a'),
                    'url' => route('support.tickets.show', $ticket),
                ];
            });

        $investmentIncidents = InvestmentRequest::query()
            ->with('user:id,name,email')
            ->where('status', 'pending')
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(function (InvestmentRequest $request) {
                return [
                    'at' => $request->updated_at ?? $request->created_at,
                    'module' => 'investment',
                    'severity' => 'warning',
                    'title' => 'Demande investissement en attente',
                    'detail' => '#'.$request->id.' · '.number_format((float) $request->amount_requested, 0, ',', ' ').' '.$request->currency,
                    'url' => route('admin.investment-requests.show', $request),
                ];
            });

        $errorIncidents = MenuActionLog::query()
            ->where('status_code', '>=', 500)
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(function (MenuActionLog $log) {
                return [
                    'at' => $log->created_at,
                    'module' => 'http',
                    'severity' => 'danger',
                    'title' => 'Erreur HTTP '.$log->status_code,
                    'detail' => strtoupper((string) $log->http_method).' '.$log->path.' · route '.($log->route_name ?: 'n/a'),
                    'url' => route('admin.logs.index', ['module' => 'menu']),
                ];
            });

        $treasurySignals = TreasuryAuditLog::query()
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(function (TreasuryAuditLog $log) {
                $severity = str_contains((string) $log->action, 'unlock') ? 'warning' : 'info';
                return [
                    'at' => $log->created_at,
                    'module' => 'treasury',
                    'severity' => $severity,
                    'title' => 'Événement trésorerie',
                    'detail' => (string) $log->action,
                    'url' => route('admin.logs.index', ['module' => 'treasury']),
                ];
            });

        return $ticketIncidents
            ->concat($investmentIncidents)
            ->concat($errorIncidents)
            ->concat($treasurySignals)
            ->sortByDesc(fn (array $row) => $row['at'] instanceof Carbon ? $row['at']->getTimestamp() : 0)
            ->take(14)
            ->values()
            ->all();
    }

    /**
     * Génère des actions du jour triées automatiquement par priorité.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildActionsOfDay(
        float $openTicketRate,
        float $pendingInvestmentRate,
        float $pctTradeRegister,
        float $pctPremium,
        int $menuErrors24h,
        int $entriesCount,
        int $userCount
    ): array {
        $entriesPerUser = $userCount > 0 ? ($entriesCount / $userCount) : 0.0;

        $actions = [
            [
                'title' => 'Traiter le backlog support',
                'detail' => sprintf('Tickets ouverts: %.1f%%. Objectif < 20%%.', $openTicketRate),
                'priority' => min(100, (int) round($openTicketRate * 1.6)),
                'route' => route('support.tickets'),
            ],
            [
                'title' => 'Débloquer les demandes d’investissement',
                'detail' => sprintf('En attente: %.1f%%. Réduire le stock prioritaire.', $pendingInvestmentRate),
                'priority' => min(100, (int) round($pendingInvestmentRate * 1.7)),
                'route' => route('admin.investment-requests.index'),
            ],
            [
                'title' => 'Réduire les erreurs HTTP critiques',
                'detail' => $menuErrors24h.' erreur(s) 5xx détectée(s) sur 24h.',
                'priority' => min(100, 30 + ($menuErrors24h * 8)),
                'route' => route('admin.logs.index', ['module' => 'menu']),
            ],
            [
                'title' => 'Améliorer la conformité documentaire',
                'detail' => sprintf('Registre joint: %.0f%%. Cible >= 75%%.', $pctTradeRegister),
                'priority' => min(100, (int) round(max(0, 75 - $pctTradeRegister) * 1.4)),
                'route' => route('admin.users'),
            ],
            [
                'title' => 'Accélérer l’adoption Premium',
                'detail' => sprintf('Premium: %.0f%%. Cible >= 35%%.', $pctPremium),
                'priority' => min(100, (int) round(max(0, 35 - $pctPremium) * 2.0)),
                'route' => route('admin.payments.index'),
            ],
            [
                'title' => 'Animer les comptes peu actifs',
                'detail' => sprintf('Densité comptable: %.1f écriture/compte.', $entriesPerUser),
                'priority' => $entriesPerUser < 20 ? min(100, (int) round((20 - $entriesPerUser) * 3.5)) : 0,
                'route' => route('admin.users'),
            ],
        ];

        return collect($actions)
            ->filter(fn (array $row) => ($row['priority'] ?? 0) > 0)
            ->sortByDesc('priority')
            ->take(6)
            ->values()
            ->all();
    }

    private function toScale(float $value, float $l1, float $l2, float $l3, float $l4): int
    {
        if ($value >= $l4) {
            return 5;
        }
        if ($value >= $l3) {
            return 4;
        }
        if ($value >= $l2) {
            return 3;
        }
        if ($value >= $l1) {
            return 2;
        }

        return 1;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildAccountingTreasuryInconsistencies(): array
    {
        $items = [];

        $entriesMissingAttachment = AccountingEntry::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNull('document_id')
            ->where(function ($q) {
                $q->whereNull('attachment_path')->orWhere('attachment_path', '');
            })
            ->count();
        if ($entriesMissingAttachment > 0) {
            $items[] = [
                'severity' => $entriesMissingAttachment >= 10 ? 'danger' : 'warning',
                'title' => "Écritures sans pièce justificative",
                'detail' => "{$entriesMissingAttachment} écriture(s) sur 30 jours sans document associé.",
                'proposal' => "Imposer un contrôle de complétude documentaire avant validation.",
            ];
        }

        $ocrMismatch = AccountingEntry::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->where('ocr_status', 'mismatch')
            ->count();
        if ($ocrMismatch > 0) {
            $items[] = [
                'severity' => $ocrMismatch >= 8 ? 'danger' : 'warning',
                'title' => "Incohérences OCR comptables",
                'detail' => "{$ocrMismatch} écriture(s) avec mismatch OCR sur 30 jours.",
                'proposal' => "Lancer une revue comptable ciblée des dossiers OCR incohérents.",
            ];
        }

        $treasuryNoReference = TreasuryTransaction::query()
            ->where('created_at', '>=', now()->subDays(90))
            ->where('status', 'effectue')
            ->where(function ($q) {
                $q->whereNull('reference')->orWhere('reference', '');
            })
            ->count();
        if ($treasuryNoReference > 0) {
            $items[] = [
                'severity' => $treasuryNoReference >= 12 ? 'danger' : 'warning',
                'title' => "Mouvements trésorerie sans référence",
                'detail' => "{$treasuryNoReference} mouvement(s) effectués sans référence.",
                'proposal' => "Rendre le champ référence obligatoire pour les flux de trésorerie.",
            ];
        }

        $negativeOrZeroAmount = TreasuryTransaction::query()
            ->where('created_at', '>=', now()->subDays(90))
            ->where('status', 'effectue')
            ->where('amount', '<=', 0)
            ->count();
        if ($negativeOrZeroAmount > 0) {
            $items[] = [
                'severity' => 'danger',
                'title' => "Montants de trésorerie invalides",
                'detail' => "{$negativeOrZeroAmount} mouvement(s) avec montant inférieur ou égal à zéro.",
                'proposal' => "Bloquer les montants non positifs au niveau formulaire et API.",
            ];
        }

        if (empty($items)) {
            $items[] = [
                'severity' => 'success',
                'title' => "Aucune incohérence majeure détectée",
                'detail' => "La cohérence comptable et trésorerie est stable sur les périodes analysées.",
                'proposal' => "Maintenir le rythme de contrôle hebdomadaire.",
            ];
        }

        return collect($items)
            ->sortByDesc(fn (array $item) => match ($item['severity']) {
                'danger' => 3,
                'warning' => 2,
                default => 1,
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @param  array<int, array<string, mixed>>  $inconsistencies
     */
    private function buildDashboardLiveInsight(array $metrics, array $inconsistencies): string
    {
        $fallback = "**Priorité immédiate :** Réduire les incohérences comptables et trésorerie.\n"
            ."**Comment faire :**\n"
            ."1) Traiter les 3 anomalies les plus critiques (pièces manquantes, mismatch OCR, références absentes).\n"
            ."2) Assigner un responsable comptable et un responsable trésorerie avec délai de 48h.\n"
            ."3) Contrôler l'impact business sur encaissements, taux premium et backlog support.\n"
            ."**KPI de suivi :** anomalies ouvertes, taux de succès paiement, cashflow net.\n"
            ."**Impact attendu :** meilleure fiabilité financière et hausse de conversion.";

        $tokenConfigured = (string) config('services.huggingface.token', '') !== '';
        if (! $tokenConfigured) {
            return $fallback;
        }

        $messages = [
            [
                'role' => 'system',
                'content' => "Tu es un copilote IA live pour dashboard admin. Réponds en français, format compact et actionnable: Priorité immédiate, Comment faire (4 étapes), KPI de suivi, Impact business. Tu dois analyser comptabilité et trésorerie.",
            ],
            [
                'role' => 'system',
                'content' => 'Contexte JSON: '.json_encode([
                    'metrics' => $metrics,
                    'inconsistencies' => $inconsistencies,
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'role' => 'user',
                'content' => "Fais une proposition live pour améliorer le chiffre d'affaires en corrigeant les incohérences comptables et trésorerie.",
            ],
        ];

        $result = $this->hfAssistant->chat($messages);
        if (! ($result['ok'] ?? false)) {
            return $fallback;
        }

        $answer = trim((string) ($result['answer'] ?? ''));
        return $answer !== '' ? $answer : $fallback;
    }

    /**
     * Liste des comptes entreprises (utilisateurs inscrits).
     */
    public function users(): View
    {
        $users = User::query()->latest()->get();
        $enterpriseGroups = $this->groupUsersByEnterprise($users);
        $enterpriseOptions = $enterpriseGroups
            ->map(function ($group) {
                /** @var User|null $template */
                $template = $group['users']->first();
                if ($template === null) {
                    return null;
                }

                $parts = [$group['company_name']];
                if (! empty($group['company_tax_id'])) {
                    $parts[] = 'NIF '.$group['company_tax_id'];
                }
                if (! empty($group['enterprise_license_id'])) {
                    $parts[] = 'Licence #'.$group['enterprise_license_id'];
                }
                $parts[] = $group['users_count'].' utilisateur(s)';

                return [
                    'template_user_id' => $template->id,
                    'label' => implode(' · ', $parts),
                ];
            })
            ->filter()
            ->values();

        $suspendedCount = User::query()->where('account_suspended', true)->count();

        // Tous les comptes avec Premium encore valide (échéance absente ou future).
        $premiumActiveCount = User::query()
            ->where('is_premium', true)
            ->where(function ($q) {
                $q->whereNull('premium_ends_at')
                    ->orWhere('premium_ends_at', '>', now());
            })
            ->count();

        // Sous-ensemble : échéance dans les 7 prochains jours (alerte renouvellement).
        $premiumExpiringSoon = User::query()
            ->where('is_platform_admin', false)
            ->where('is_premium', true)
            ->whereNotNull('premium_ends_at')
            ->where('premium_ends_at', '>', now())
            ->where('premium_ends_at', '<=', now()->addDays(7))
            ->count();

        return view('admin.users', [
            'users' => $users,
            'enterpriseGroups' => $enterpriseGroups,
            'enterpriseOptions' => $enterpriseOptions,
            'totalUsers' => $users->count(),
            'withFiles' => $users->filter(fn (User $user) => ! empty($user->trade_register_file))->count(),
            'withoutFiles' => $users->filter(fn (User $user) => empty($user->trade_register_file))->count(),
            'suspendedCount' => $suspendedCount,
            'premiumActiveCount' => $premiumActiveCount,
            'premiumExpiringSoon' => $premiumExpiringSoon,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'template_user_id' => ['required', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $template = User::query()->findOrFail((int) $data['template_user_id']);
        if ($template->isPlatformAdmin() || $template->isAccountant()) {
            return back()
                ->withErrors(['template_user_id' => 'Le compte modèle doit être un compte entreprise.'])
                ->withInput($request->except('password', 'password_confirmation'));
        }

        DB::transaction(function () use ($template, $data) {
            $enterpriseLicenseId = $template->enterprise_license_id;
            $license = null;

            if ($enterpriseLicenseId !== null) {
                /** @var EnterpriseLicense|null $license */
                $license = EnterpriseLicense::query()->whereKey($enterpriseLicenseId)->lockForUpdate()->first();
                if ($license === null || ! $license->isUsable()) {
                    throw ValidationException::withMessages([
                        'template_user_id' => 'La licence associée à l’entreprise n’est plus valide.',
                    ]);
                }
                if (! $license->hasAvailableSeat()) {
                    throw ValidationException::withMessages([
                        'template_user_id' => 'Nombre maximum d’utilisateurs atteint pour cette entreprise.',
                    ]);
                }
            }

            User::query()->create(array_merge(
                $template->sharedProfileForNewTeammate(),
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'enterprise_license_id' => $enterpriseLicenseId,
                    'is_platform_admin' => false,
                    'is_accountant' => false,
                    'account_suspended' => false,
                    'suspended_at' => null,
                    'suspended_reason' => null,
                ]
            ));

            if ($license !== null) {
                $license->syncPrimaryWorkspaceUser();
            }
        });

        return redirect()
            ->route('admin.users')
            ->with('status', 'Utilisateur créé et rattaché à l’entreprise sélectionnée.');
    }

    /**
     * Formulaire de gestion d’un compte (admin plateforme).
     */
    public function edit(User $user): View
    {
        $user->load('enterpriseLicense');

        return view('admin.user-edit', [
            'user' => $user,
        ]);
    }

    /**
     * Enregistre les modifications (rôles, abonnement, suspension).
     */
    public function update(AdminUpdateUserRequest $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $data = $request->validated();
        $before = $user->only([
            'name',
            'email',
            'is_platform_admin',
            'is_accountant',
            'is_premium',
            'premium_status',
            'premium_ends_at',
            'account_suspended',
            'suspended_at',
            'suspended_reason',
        ]);

        if ($actor->id === $user->id && ($data['account_suspended'] ?? false)) {
            return back()->withErrors([
                'account_suspended' => 'Vous ne pouvez pas suspendre votre propre compte.',
            ])->withInput();
        }

        if ($actor->id === $user->id && ! ($data['is_platform_admin'] ?? false)) {
            $otherAdminsExist = User::query()
                ->where('is_platform_admin', true)
                ->where('id', '!=', $user->id)
                ->exists();
            if (! $otherAdminsExist) {
                return back()->withErrors([
                    'is_platform_admin' => 'Conservez au moins un administrateur plateforme (invitez un collègue avant de retirer votre accès).',
                ])->withInput();
            }
        }

        $asPlatformAdmin = (bool) ($data['is_platform_admin'] ?? false);
        $asAccountant = (bool) ($data['is_accountant'] ?? false);

        // Rôles exclusifs : administrateur, comptable ou entreprise (ni admin ni comptable).
        if ($asPlatformAdmin) {
            $asAccountant = false;
        }
        if ($asAccountant) {
            $asPlatformAdmin = false;
        }

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'company_name' => $data['company_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'sector' => $data['sector'] ?? null,
            'rccm' => $data['rccm'] ?? null,
            'is_platform_admin' => $asPlatformAdmin,
            'is_accountant' => $asAccountant,
        ];

        // Comptable cabinet : pas de logique Gratuit / Premium entreprise.
        if ($asAccountant) {
            $payload['is_premium'] = false;
            $payload['premium_ends_at'] = null;
            $payload['premium_trial_ends_at'] = null;
            $payload['premium_status'] = 'free';
        }

        if ($asPlatformAdmin) {
            $payload['is_premium'] = true;
            $payload['premium_status'] = 'active';
            $payload['premium_ends_at'] = null;
            $payload['premium_trial_ends_at'] = null;
        }

        // Abonnement entreprise (hors admin / comptable).
        if (! $asPlatformAdmin && ! $asAccountant) {
            $payload['is_premium'] = (bool) ($data['is_premium'] ?? false);

            if (($data['is_premium'] ?? false)) {
                if (! empty($data['premium_ends_at'])) {
                    $payload['premium_ends_at'] = $data['premium_ends_at'];
                }
                $payload['premium_status'] = $data['premium_status'] ?? $user->premium_status ?? 'active';
                if (($payload['premium_status'] ?? 'free') === 'free') {
                    $payload['premium_status'] = 'active';
                }
            } else {
                $payload['premium_ends_at'] = null;
                $payload['premium_trial_ends_at'] = null;
                $payload['premium_status'] = 'free';
            }
        }

        if (($data['account_suspended'] ?? false)) {
            $payload['account_suspended'] = true;
            $payload['suspended_reason'] = $data['suspended_reason'] ?? $user->suspended_reason;
            if ($user->suspended_at === null) {
                $payload['suspended_at'] = now();
            }
        } else {
            $payload['account_suspended'] = false;
            $payload['suspended_at'] = null;
            $payload['suspended_reason'] = null;
            $payload['auto_suspended_for_payment'] = false;
        }

        $user->update($payload);

        if ($asPlatformAdmin) {
            $this->userPremium->ensurePlatformAdminPremium($user->fresh(), $actor, $request);
        }

        $this->auditTrail->log(
            'user.admin_update',
            User::class,
            $user->id,
            $actor?->id,
            $before,
            $user->fresh()?->only([
                'name',
                'email',
                'is_platform_admin',
                'is_accountant',
                'is_premium',
                'premium_status',
                'premium_ends_at',
                'account_suspended',
                'suspended_at',
                'suspended_reason',
            ]),
            ['route' => 'admin.users.update'],
            $request
        );

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('status', 'Compte mis à jour.');
    }

    public function activatePremiumTrial(Request $request, User $user): RedirectResponse
    {
        if (! $this->userPremium->canManageEnterprisePremium($user)) {
            return back()->withErrors([
                'premium' => 'Le Premium d’essai concerne uniquement les comptes entreprise (clients).',
            ]);
        }

        $validated = $request->validate([
            'days' => ['required', 'integer', 'in:7,14,30,90'],
        ]);

        $days = (int) $validated['days'];
        $this->userPremium->activateForDays(
            $user,
            $days,
            'admin_users_trial',
            "Essai Premium {$days} jours activé par l’administrateur (test application).",
            $request->user(),
            ['admin_user_id' => $request->user()?->id],
            $request
        );

        return back()->with('status', "Premium activé pour {$days} jour(s) sur le compte {$user->email}.");
    }

    public function deactivatePremium(Request $request, User $user): RedirectResponse
    {
        if (! $this->userPremium->canManageEnterprisePremium($user)) {
            return back()->withErrors([
                'premium' => 'Cette action concerne uniquement les comptes entreprise.',
            ]);
        }

        $this->userPremium->deactivate(
            $user,
            'admin_users_trial',
            'Premium désactivé manuellement par l’administrateur.',
            $request->user(),
            ['admin_user_id' => $request->user()?->id],
            $request
        );

        return back()->with('status', "Le compte {$user->email} est repassé en mode Gratuit.");
    }

    /**
     * Regroupe les comptes par entreprise (licence, NIF puis nom) pour la gestion admin.
     *
     * @param  Collection<int, User>  $users
     * @return Collection<int, array<string, mixed>>
     */
    protected function groupUsersByEnterprise(Collection $users): Collection
    {
        return $users
            ->groupBy(function (User $u) {
                if ($u->enterprise_license_id) {
                    return 'lic:'.$u->enterprise_license_id;
                }

                $nif = strtoupper(preg_replace('/\s+/', '', (string) ($u->company_tax_id ?? '')));
                if ($nif !== '') {
                    return 'nif:'.$nif;
                }

                $company = mb_strtolower(trim((string) ($u->company_name ?? '')));
                if ($company !== '') {
                    return 'name:'.$company;
                }

                return 'user:'.$u->id;
            })
            ->map(function (Collection $group) {
                /** @var User $first */
                $first = $group->first();

                return [
                    'company_name' => $first->company_name ?: ('Entreprise #'.$first->id),
                    'company_tax_id' => $first->company_tax_id,
                    'enterprise_license_id' => $first->enterprise_license_id,
                    'users' => $group->sortBy('name')->values(),
                    'users_count' => $group->count(),
                ];
            })
            ->sortBy(fn ($row) => mb_strtolower((string) $row['company_name']))
            ->values();
    }
}
