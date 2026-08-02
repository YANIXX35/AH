<?php

namespace App\Http\Controllers;

use App\Models\AccountingEntry;
use App\Models\AdminApprovalAction;
use App\Models\AdminApprovalRequest;
use App\Models\AdminAuditTrail;
use App\Models\AiActionTask;
use App\Models\InvestmentRequest;
use App\Models\MenuActionLog;
use App\Models\PaymentTransaction;
use App\Models\PlatformSetting;
use App\Models\SubscriptionHistory;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AdminAuditTrailService;
use App\Services\HuggingFaceOpsAssistantService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminOpsCenterController extends Controller
{
    public function __construct(
        private readonly AdminAuditTrailService $auditTrail,
        private readonly HuggingFaceOpsAssistantService $hfAssistant
    ) {}

    public function index(): View
    {
        // Métriques agrégées (SLO, NOC, KPI business...) : coûteuses à recalculer
        // et pas besoin d'être seconde par seconde, contrairement aux checks de
        // santé et aux files d'approbation ci-dessous qui doivent rester en direct.
        $metrics = Cache::remember('admin.ops-center.metrics', now()->addMinutes(3), function () {
            $slo = $this->buildSloMetrics();
            $alertCenter = $this->buildAlertCenter();
            $noc = $this->buildNocMetrics();
            $queue = $this->buildQueueMetrics();
            $businessKpis = $this->buildBusinessKpis();
            $growthIdeas = $this->buildGrowthIdeas($businessKpis, $alertCenter, $slo);

            return compact('slo', 'alertCenter', 'noc', 'queue', 'businessKpis', 'growthIdeas');
        });
        ['slo' => $slo, 'alertCenter' => $alertCenter, 'noc' => $noc, 'queue' => $queue, 'businessKpis' => $businessKpis, 'growthIdeas' => $growthIdeas] = $metrics;

        $healthChecks = $this->runHealthChecks();
        $aiAutonomousApprovals = PlatformSetting::query()->firstOrCreate(
            ['key' => 'ops_ai_autonomous_approvals'],
            ['value' => [
                'enabled' => false,
                'thresholds' => [
                    'blocked_tickets_48h' => 3,
                    'expiring_premium_7d' => 5,
                    'payment_success_rate_pct' => 90,
                    'revenue_growth_pct' => 0,
                ],
            ]]
        );

        $featureFlags = PlatformSetting::query()->firstOrCreate(
            ['key' => 'feature_flags'],
            ['value' => [
                'modules' => [
                    'accounting' => true,
                    'treasury' => true,
                    'investor' => true,
                    'payments' => true,
                    'ops_center' => true,
                ],
            ]]
        );

        $approvalsPending = AdminApprovalRequest::query()
            ->with('requestedBy:id,name,email')
            ->where('status', 'pending')
            ->latest()
            ->limit(15)
            ->get();

        $auditRecent = AdminAuditTrail::query()
            ->with('actor:id,name,email')
            ->latest()
            ->limit(20)
            ->get();
        $aiTasks = collect();
        if (Schema::hasTable('ai_action_tasks')) {
            $aiTasks = AiActionTask::query()
                ->with(['assignedTo:id,name,email', 'createdBy:id,name,email'])
                ->latest()
                ->limit(25)
                ->get();
        }
        $platformAdmins = User::query()
            ->where('is_platform_admin', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $runbooks = [
            [
                'title' => 'Backlog support > 30%',
                'steps' => [
                    'Filtrer les tickets ouverts et prioriser les plus anciens.',
                    'Affecter un owner et fixer un ETA.',
                    'Notifier les clients impactés.',
                ],
                'action_url' => route('support.tickets'),
            ],
            [
                'title' => 'Erreurs HTTP 5xx en hausse',
                'steps' => [
                    'Ouvrir la journalisation plateforme (module menu).',
                    'Identifier route, méthode et volume des erreurs.',
                    'Appliquer correctif ou rollback et surveiller 15 min.',
                ],
                'action_url' => route('admin.logs.index', ['module' => 'menu']),
            ],
            [
                'title' => 'Échéances licences / abonnements proches',
                'steps' => [
                    'Lister licences/abonnements à < 7 jours.',
                    'Lancer relances automatiques et suivi account manager.',
                    'Escalader les comptes stratégiques.',
                ],
                'action_url' => route('admin.dashboard'),
            ],
        ];

        return view('admin.ops-center', [
            'slo' => $slo,
            'alertCenter' => $alertCenter,
            'noc' => $noc,
            'queue' => $queue,
            'healthChecks' => $healthChecks,
            'businessKpis' => $businessKpis,
            'growthIdeas' => $growthIdeas,
            'aiAutonomousApprovals' => (array) ($aiAutonomousApprovals->value ?? []),
            'featureFlags' => (array) ($featureFlags->value ?? []),
            'approvalsPending' => $approvalsPending,
            'auditRecent' => $auditRecent,
            'aiTasks' => $aiTasks,
            'platformAdmins' => $platformAdmins,
            'runbooks' => $runbooks,
            'hfAssistantEnabled' => (string) config('services.huggingface.token', '') !== '',
            'hfModel' => (string) config('services.huggingface.model', 'meta-llama/Llama-3.1-8B-Instruct'),
        ]);
    }

    public function aiChat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:2500'],
            'history' => ['nullable', 'array', 'max:12'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:2500'],
        ]);

        $slo = $this->buildSloMetrics();
        $alerts = $this->buildAlertCenter();
        $noc = $this->buildNocMetrics();
        $queue = $this->buildQueueMetrics();
        $businessKpis = $this->buildBusinessKpis();
        $growthIdeas = $this->buildGrowthIdeas($businessKpis, $alerts, $slo);

        $context = [
            'slo' => $slo,
            'alerts' => collect($alerts)->map(fn ($a) => [
                'label' => $a['label'] ?? '',
                'value' => $a['value'] ?? 0,
                'threshold' => $a['threshold'] ?? 0,
                'escalation' => $a['escalation'] ?? 0,
                'severity' => $a['severity'] ?? 'success',
            ])->values()->all(),
            'noc' => $noc,
            'queue' => $queue,
            'business_kpis' => $businessKpis,
            'growth_ideas' => $growthIdeas,
        ];

        // 1. Intercepter les salutations simples pour un accueil personnalisé immédiat
        $userMessage = trim((string) $data['message']);
        $cleanMessage = preg_replace('/[^\p{L}\s]/u', '', mb_strtolower($userMessage));
        $greetings = ['bonjour', 'salut', 'coucou', 'hello', 'hi', 'hey', 'bonsoir', 'yo'];
        if (in_array($cleanMessage, $greetings)) {
            return response()->json([
                'ok' => true,
                'answer' => "Bonjour ! Je suis l'assistant de Sitiame Capital et que puis-je faire pour vous ?",
            ]);
        }

        $history = collect((array) ($data['history'] ?? []))
            ->map(fn ($row) => [
                'role' => (string) ($row['role'] ?? 'user'),
                'content' => (string) ($row['content'] ?? ''),
            ])
            ->filter(fn ($row) => $row['content'] !== '')
            ->take(-8)
            ->values()
            ->all();

        $messages = [
            [
                'role' => 'system',
                'content' => "Tu es l'assistant IA officiel de l'application Sitiame Capital (spécialisé Ops, Performance et Administration). Réponds en français de manière claire, concise, orientée action et chiffre. Ne donne pas d'actions destructives.\n\n"
                    ."Voici la structure et les pages clés de l'application :\n"
                    ."- Tableau de bord : Synthèse de la santé financière, métriques de CA et cashflow, alertes et propositions en temps réel.\n"
                    ."- Comptabilité : Gestion des écritures, saisie et import de justificatifs avec extraction automatique par OCR, plan comptable OHADA et génération de la liasse fiscale BCEAO.\n"
                    ."- Trésorerie : Balance, suivi des soldes, encaissements/décaissements et net cashflow.\n"
                    ."- Diagnostics : readiness investor (éligibilité à l'investissement), heatmap des risques et scoring financier à 360°.\n"
                    ."- Équipe et Profil : Gestion des collaborateurs de l'entreprise et abonnements (Gratuit, Premium activable via CinetPay/FedaPay).\n"
                    ."- Factures & Support : Historique des factures de la plateforme et messagerie de tickets support technique.\n\n"
                    ."Tu possèdes une excellente culture générale pour répondre à toute question générale (histoire, célébrités, science, géographie, etc.). Cependant, si l'utilisateur pose une question offensante, injurieuse, dangereuse, illégale ou d'ordre sexuel, refuse poliment d'y répondre en disant exactement : 'Je suis conçu pour vous assister dans la gestion financière et comptable de votre entreprise sur Sitiame Capital. Je ne peux pas répondre à cette demande.'. Si la demande parle de croissance/chiffre d'affaires, propose un plan sur 30/60/90 jours avec KPI de suivi et impact attendu. Quand tu proposes une priorité, explique systématiquement COMMENT FAIRE sous forme de plan opérationnel: Étapes 1..N, responsable, délai, KPI de contrôle.",
            ],
            [
                'role' => 'system',
                'content' => 'Contexte plateforme JSON: '.json_encode($context, JSON_UNESCAPED_UNICODE),
            ],
        ];

        foreach ($history as $row) {
            $messages[] = $row;
        }
        $messages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        $result = $this->hfAssistant->chat($messages);
        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'error' => $result['error'] ?? 'Erreur IA inconnue.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'answer' => $result['answer'],
        ]);
    }

    public function aiLiveProposals(): JsonResponse
    {
        $slo = $this->buildSloMetrics();
        $alerts = $this->buildAlertCenter();
        $businessKpis = $this->buildBusinessKpis();
        $growthIdeas = $this->buildGrowthIdeas($businessKpis, $alerts, $slo);

        $fallback = $this->buildFallbackLiveProposal($businessKpis, $growthIdeas);
        $liveText = $fallback;

        $hfEnabled = (string) config('services.huggingface.token', '') !== '';
        if ($hfEnabled) {
            $messages = [
                [
                    'role' => 'system',
                    'content' => "Tu es un copilote business en direct. Réponds en français, ton exécutif, concret et chiffré. Maximum 1200 caractères. Format obligatoire: **Priorité immédiate** (1 ligne), **Comment faire** (3 à 5 étapes actionnables), **KPI cette semaine** (2 points), **Impact CA attendu** (1 ligne). Ne donne pas d'action destructive.",
                ],
                [
                    'role' => 'system',
                    'content' => 'Contexte JSON: '.json_encode([
                        'slo' => $slo,
                        'alerts' => $alerts,
                        'business_kpis' => $businessKpis,
                        'growth_ideas' => $growthIdeas,
                    ], JSON_UNESCAPED_UNICODE),
                ],
                [
                    'role' => 'user',
                    'content' => "Fais-moi une proposition en temps réel pour augmenter le chiffre d'affaires à partir des KPI actuels.",
                ],
            ];

            $result = $this->hfAssistant->chat($messages);
            if ($result['ok'] ?? false) {
                $candidate = trim((string) ($result['answer'] ?? ''));
                if ($candidate !== '') {
                    $liveText = $candidate;
                }
            }
        }

        return response()->json([
            'ok' => true,
            'generated_at' => now()->toIso8601String(),
            'live_text' => $liveText,
            'business_kpis' => $businessKpis,
            'growth_ideas' => $growthIdeas,
            'charts' => $this->makeKpiChartSeries($businessKpis),
        ]);
    }

    public function updateAiAutonomousApprovals(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'thresholds.blocked_tickets_48h' => ['nullable', 'integer', 'min:1', 'max:200'],
            'thresholds.expiring_premium_7d' => ['nullable', 'integer', 'min:1', 'max:500'],
            'thresholds.payment_success_rate_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'thresholds.revenue_growth_pct' => ['nullable', 'numeric', 'min:-100', 'max:300'],
        ]);

        $setting = PlatformSetting::query()->firstOrCreate(
            ['key' => 'ops_ai_autonomous_approvals'],
            ['value' => [
                'enabled' => false,
                'thresholds' => [
                    'blocked_tickets_48h' => 3,
                    'expiring_premium_7d' => 5,
                    'payment_success_rate_pct' => 90,
                    'revenue_growth_pct' => 0,
                ],
            ]]
        );

        $before = (array) ($setting->value ?? []);
        $current = (array) ($setting->value ?? []);
        $currentThresholds = (array) ($current['thresholds'] ?? []);

        $after = [
            'enabled' => $request->boolean('enabled'),
            'thresholds' => [
                'blocked_tickets_48h' => (int) ($data['thresholds']['blocked_tickets_48h'] ?? ($currentThresholds['blocked_tickets_48h'] ?? 3)),
                'expiring_premium_7d' => (int) ($data['thresholds']['expiring_premium_7d'] ?? ($currentThresholds['expiring_premium_7d'] ?? 5)),
                'payment_success_rate_pct' => (float) ($data['thresholds']['payment_success_rate_pct'] ?? ($currentThresholds['payment_success_rate_pct'] ?? 90)),
                'revenue_growth_pct' => (float) ($data['thresholds']['revenue_growth_pct'] ?? ($currentThresholds['revenue_growth_pct'] ?? 0)),
            ],
        ];

        $setting->update([
            'value' => $after,
            'updated_by' => $request->user()?->id,
        ]);

        $this->auditTrail->log(
            'ops.ai_autonomous_approvals.update',
            PlatformSetting::class,
            $setting->id,
            $request->user()?->id,
            $before,
            $after,
            ['source' => 'ops_center'],
            $request
        );

        return back()->with('status', "Configuration d'autonomie IA mise à jour.");
    }

    public function storeAiTask(Request $request): JsonResponse
    {
        if (! Schema::hasTable('ai_action_tasks')) {
            return response()->json([
                'ok' => false,
                'error' => "La table ai_action_tasks n'existe pas encore. Exécutez php artisan migrate.",
            ], 422);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:220'],
            'description' => ['nullable', 'string', 'max:4000'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'source' => ['nullable', 'string', 'max:64'],
        ]);

        $task = AiActionTask::query()->create([
            'title' => (string) $data['title'],
            'description' => (string) ($data['description'] ?? ''),
            'priority' => (string) ($data['priority'] ?? 'medium'),
            'source' => (string) ($data['source'] ?? 'ops_live'),
            'status' => 'todo',
            'created_by_user_id' => $request->user()?->id,
            'meta' => [
                'origin' => 'ai_live',
            ],
        ]);

        $this->auditTrail->log(
            'ops.ai_task.create',
            AiActionTask::class,
            $task->id,
            $request->user()?->id,
            null,
            $task->toArray(),
            ['source' => 'ops_center'],
            $request
        );

        return response()->json([
            'ok' => true,
            'task' => $task->only(['id', 'title', 'status', 'priority']),
            'message' => 'Tâche IA créée.',
        ]);
    }

    public function assignAiTask(Request $request, int $task): RedirectResponse
    {
        if (! Schema::hasTable('ai_action_tasks')) {
            return back()->withErrors(['ai_tasks' => "La table ai_action_tasks n'existe pas encore. Exécutez php artisan migrate."]);
        }

        $taskModel = AiActionTask::query()->find($task);
        if (! $taskModel) {
            return back()->withErrors(['ai_tasks' => 'Tâche IA introuvable.']);
        }

        $data = $request->validate([
            'assigned_to_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $before = $taskModel->toArray();
        $taskModel->update([
            'assigned_to_user_id' => (int) $data['assigned_to_user_id'],
            'status' => $taskModel->status === 'done' ? 'done' : 'in_progress',
        ]);

        $this->auditTrail->log(
            'ops.ai_task.assign',
            AiActionTask::class,
            $taskModel->id,
            $request->user()?->id,
            $before,
            $taskModel->fresh()?->toArray(),
            ['source' => 'ops_center'],
            $request
        );

        return back()->with('status', 'Tâche IA assignée.');
    }

    public function completeAiTask(Request $request, int $task): RedirectResponse
    {
        if (! Schema::hasTable('ai_action_tasks')) {
            return back()->withErrors(['ai_tasks' => "La table ai_action_tasks n'existe pas encore. Exécutez php artisan migrate."]);
        }

        $taskModel = AiActionTask::query()->find($task);
        if (! $taskModel) {
            return back()->withErrors(['ai_tasks' => 'Tâche IA introuvable.']);
        }

        $before = $taskModel->toArray();
        $taskModel->update([
            'status' => 'done',
            'completed_at' => now(),
        ]);

        $this->auditTrail->log(
            'ops.ai_task.complete',
            AiActionTask::class,
            $taskModel->id,
            $request->user()?->id,
            $before,
            $taskModel->fresh()?->toArray(),
            ['source' => 'ops_center'],
            $request
        );

        return back()->with('status', 'Tâche IA marquée comme terminée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBusinessKpis(): array
    {
        $now = now();
        $start30 = $now->copy()->subDays(30);
        $previousStart30 = $now->copy()->subDays(60);
        $previousEnd30 = $start30;

        $successfulStatuses = ['COMPLETED', 'ACCEPTED', 'SUBMITTED', 'effectue'];

        $payments30 = PaymentTransaction::query()->where('created_at', '>=', $start30);
        $paymentsPrevious30 = PaymentTransaction::query()
            ->where('created_at', '>=', $previousStart30)
            ->where('created_at', '<', $previousEnd30);

        $revenue30 = (float) (clone $payments30)->whereIn('status', $successfulStatuses)->sum('amount');
        $revenuePrevious30 = (float) (clone $paymentsPrevious30)->whereIn('status', $successfulStatuses)->sum('amount');
        $revenueGrowthPct = $this->percentChange($revenue30, $revenuePrevious30);

        $totalPayments30 = (int) (clone $payments30)->count();
        $successfulPayments30 = (int) (clone $payments30)->whereIn('status', $successfulStatuses)->count();
        $paymentSuccessRate = $totalPayments30 > 0 ? round(($successfulPayments30 / $totalPayments30) * 100, 2) : 100.0;

        $totalClients = User::query()->clients()->count();
        $activePremiumClients = User::query()
            ->clients()
            ->where('is_premium', true)
            ->where(function ($query): void {
                $query->whereNull('premium_ends_at')->orWhere('premium_ends_at', '>', now());
            })
            ->count();
        $premiumPenetrationRate = $totalClients > 0 ? round(($activePremiumClients / $totalClients) * 100, 2) : 0.0;

        $newPremiumActivations30 = SubscriptionHistory::query()
            ->where('to_status', 'active')
            ->where('created_at', '>=', $start30)
            ->count();
        $newPremiumActivationRate = $totalClients > 0 ? round(($newPremiumActivations30 / $totalClients) * 100, 2) : 0.0;

        $expiringIn7Days = User::query()
            ->clients()
            ->where('is_premium', true)
            ->whereNotNull('premium_ends_at')
            ->where('premium_ends_at', '>', $now)
            ->where('premium_ends_at', '<=', $now->copy()->addDays(7))
            ->count();

        $monthlyRevenuePerActivePremium = $activePremiumClients > 0 ? round($revenue30 / $activePremiumClients, 2) : 0.0;

        return [
            'revenue_30d' => [
                'label' => 'CA encaisse 30j',
                'value' => round($revenue30, 2),
                'previous' => round($revenuePrevious30, 2),
                'delta_pct' => $revenueGrowthPct,
                'target_delta_pct' => 10.0,
                'unit' => 'montant',
                'status' => $this->statusFromThreshold($revenueGrowthPct, 10.0, 0.0),
            ],
            'payment_success_rate' => [
                'label' => 'Taux de succes paiement',
                'value' => $paymentSuccessRate,
                'target' => 95.0,
                'unit' => 'pct',
                'status' => $this->statusFromThreshold($paymentSuccessRate, 95.0, 90.0),
            ],
            'premium_penetration_rate' => [
                'label' => 'Taux clients premium actifs',
                'value' => $premiumPenetrationRate,
                'target' => 35.0,
                'unit' => 'pct',
                'status' => $this->statusFromThreshold($premiumPenetrationRate, 35.0, 20.0),
            ],
            'new_premium_activation_rate' => [
                'label' => 'Activation premium (30j)',
                'value' => $newPremiumActivationRate,
                'target' => 8.0,
                'unit' => 'pct',
                'status' => $this->statusFromThreshold($newPremiumActivationRate, 8.0, 4.0),
            ],
            'expiring_premium_7d' => [
                'label' => 'Premium expirent <= 7j',
                'value' => $expiringIn7Days,
                'target' => 5,
                'unit' => 'count',
                'status' => $expiringIn7Days > 10 ? 'danger' : ($expiringIn7Days > 5 ? 'warning' : 'success'),
            ],
            'arpu_active_premium_30d' => [
                'label' => 'ARPU premium actif (30j)',
                'value' => $monthlyRevenuePerActivePremium,
                'target' => 15000.0,
                'unit' => 'montant',
                'status' => $this->statusFromThreshold($monthlyRevenuePerActivePremium, 15000.0, 9000.0),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $businessKpis
     * @param  array<int, array<string, mixed>>  $alerts
     * @param  array<string, mixed>  $slo
     * @return array<int, array<string, mixed>>
     */
    private function buildGrowthIdeas(array $businessKpis, array $alerts, array $slo): array
    {
        $ideas = [];

        $revenueGrowth = (float) data_get($businessKpis, 'revenue_30d.delta_pct', 0);
        $successRate = (float) data_get($businessKpis, 'payment_success_rate.value', 100);
        $premiumRate = (float) data_get($businessKpis, 'premium_penetration_rate.value', 0);
        $activationRate = (float) data_get($businessKpis, 'new_premium_activation_rate.value', 0);
        $expiringSoon = (int) data_get($businessKpis, 'expiring_premium_7d.value', 0);
        $supportStatus = (string) data_get($slo, 'support.status', 'success');
        $maxEscalation = (int) collect($alerts)->max('escalation');

        if ($successRate < 95.0) {
            $ideas[] = [
                'title' => 'Optimiser le tunnel de paiement',
                'why' => "Le taux de succes paiement est a {$successRate}%.",
                'actions' => [
                    'Analyser les rejets par correspondant, pays et tranche horaire.',
                    'Activer relance automatique a 15 min et 24 h pour paiements echoues.',
                    'Ajouter fallback de methode de paiement dans le checkout.',
                ],
                'kpi_target' => 'Taux de succes paiement >= 97% sous 30 jours',
                'impact' => 'Hausse directe du CA encaisse',
                'priority' => $successRate < 90.0 ? 'haute' : 'moyenne',
            ];
        }

        if ($premiumRate < 35.0 || $activationRate < 8.0) {
            $ideas[] = [
                'title' => 'Booster la conversion vers Premium',
                'why' => "Penetration premium {$premiumRate}% / activation 30j {$activationRate}%.",
                'actions' => [
                    'Lancer une sequence onboarding 7 jours avec preuve de valeur.',
                    'Afficher un paywall intelligent sur les fonctionnalites a forte valeur.',
                    'Proposer une offre d essai guidee puis une offre annuelle avec remise limitee.',
                ],
                'kpi_target' => 'Activation premium >= 10% et penetration >= 40% en 90 jours',
                'impact' => 'MRR en progression',
                'priority' => 'haute',
            ];
        }

        if ($expiringSoon > 5) {
            $ideas[] = [
                'title' => 'Programme anti-churn avant expiration',
                'why' => "{$expiringSoon} comptes premium expirent dans 7 jours.",
                'actions' => [
                    'Segmenter comptes a forte valeur et contacter en priorite.',
                    'Activer campagne de renouvellement avec incentive graduee.',
                    'Créer une relance multicanal (email + in-app) J-7, J-3, J-1.',
                ],
                'kpi_target' => 'Taux de renouvellement >= 85% sur le lot en risque',
                'impact' => 'Protection du CA recurrent',
                'priority' => $expiringSoon > 10 ? 'haute' : 'moyenne',
            ];
        }

        if ($supportStatus !== 'success') {
            $ideas[] = [
                'title' => 'Reduire le delai support pour proteger les upsells',
                'why' => 'Le SLO support est degrade, ce qui affecte la satisfaction et le renouvellement.',
                'actions' => [
                    'Mettre en place triage automatique des tickets a valeur business elevee.',
                    'Fixer un SLA prioritaire pour clients premium et prospects chauds.',
                    'Lancer une revue hebdomadaire des causes racines des tickets repetitifs.',
                ],
                'kpi_target' => 'Temps moyen de resolution support < 24 h',
                'impact' => 'Meilleur taux de retention et de conversion',
                'priority' => 'moyenne',
            ];
        }

        if ($revenueGrowth < 10.0) {
            $ideas[] = [
                'title' => 'Plan 30/60/90 de croissance CA',
                'why' => "Croissance CA 30j a {$revenueGrowth}%.",
                'actions' => [
                    '30 jours: corriger les frictions paiement et renforcer activation premium.',
                    '60 jours: lancer pack annuel et bundles de services a plus forte marge.',
                    '90 jours: segmenter clients a potentiel et automatiser les offres ciblees.',
                ],
                'kpi_target' => 'Croissance CA mensuelle >= +15%',
                'impact' => 'Acceleration previsible du chiffre d affaires',
                'priority' => 'haute',
            ];
        }

        if ($maxEscalation >= 2) {
            $ideas[] = [
                'title' => 'Stabiliser l exploitation avant scale commercial',
                'why' => "Escalade technique niveau {$maxEscalation} detectee.",
                'actions' => [
                    'Traiter les alertes critiques en moins de 4 heures.',
                    'Bloquer temporairement les campagnes agressives d acquisition.',
                    'Reprendre les campagnes apres retour a une exploitation stable.',
                ],
                'kpi_target' => 'Aucune alerte escalade >= 2 sur 7 jours glissants',
                'impact' => 'Croissance plus saine et durable',
                'priority' => 'haute',
            ];
        }

        return collect($ideas)
            ->sortByDesc(fn (array $idea) => match ($idea['priority']) {
                'haute' => 3,
                'moyenne' => 2,
                default => 1,
            })
            ->take(6)
            ->values()
            ->all();
    }

    private function percentChange(float $current, float $previous): float
    {
        if (abs($previous) < 0.00001) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    private function statusFromThreshold(float $value, float $successThreshold, float $warningThreshold): string
    {
        if ($value >= $successThreshold) {
            return 'success';
        }
        if ($value >= $warningThreshold) {
            return 'warning';
        }

        return 'danger';
    }

    /**
     * @param  array<string, mixed>  $businessKpis
     * @return array<string, mixed>
     */
    private function makeKpiChartSeries(array $businessKpis): array
    {
        $rows = collect($businessKpis)->values();

        $labels = $rows->map(fn ($kpi) => (string) ($kpi['label'] ?? 'KPI'))->all();
        $values = $rows->map(fn ($kpi) => (float) ($kpi['value'] ?? 0))->all();
        $statuses = $rows->map(fn ($kpi) => (string) ($kpi['status'] ?? 'secondary'))->all();

        return [
            'kpi_labels' => $labels,
            'kpi_values' => $values,
            'kpi_statuses' => $statuses,
            'revenue_compare' => [
                'labels' => ['30j precedent', '30j actuel'],
                'values' => [
                    (float) data_get($businessKpis, 'revenue_30d.previous', 0),
                    (float) data_get($businessKpis, 'revenue_30d.value', 0),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $businessKpis
     * @param  array<int, array<string, mixed>>  $growthIdeas
     */
    private function buildFallbackLiveProposal(array $businessKpis, array $growthIdeas): string
    {
        $revenueGrowth = (float) data_get($businessKpis, 'revenue_30d.delta_pct', 0);
        $paymentSuccess = (float) data_get($businessKpis, 'payment_success_rate.value', 0);
        $premiumRate = (float) data_get($businessKpis, 'premium_penetration_rate.value', 0);
        $priority = (string) data_get($growthIdeas, '0.title', "Stabiliser l'exploitation et optimiser la conversion");

        return "**Priorité immédiate :** {$priority}\n"
            ."**Comment faire :**\n"
            ."1) Identifier les causes majeures (paiements rejetés, comptes premium en risque, incidents critiques) via les blocs KPI/alertes.\n"
            ."2) Appliquer un plan d'action ciblé sur 24h: corriger les frictions de paiement, lancer relances anti-churn, prioriser les comptes à forte valeur.\n"
            ."3) Assigner un responsable par action (Ops, Finance, Support) avec un délai clair et suivi quotidien.\n"
            ."4) Vérifier les résultats en fin de journée et ajuster le plan selon les écarts KPI.\n"
            ."**KPI cette semaine :** succès paiement >= 97% ; activation premium >= 10%.\n"
            ."**Impact CA attendu :** hausse de la conversion et meilleure rétention premium.\n"
            .'Contexte actuel: croissance CA 30j '.number_format($revenueGrowth, 2, ',', ' ').'%, succès paiement '.number_format($paymentSuccess, 2, ',', ' ').'%, premium actif '.number_format($premiumRate, 2, ',', ' ').'%.';
    }

    public function updateFeatureFlags(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'modules.accounting' => ['nullable', 'boolean'],
            'modules.treasury' => ['nullable', 'boolean'],
            'modules.investor' => ['nullable', 'boolean'],
            'modules.payments' => ['nullable', 'boolean'],
            'modules.ops_center' => ['nullable', 'boolean'],
        ]);

        $modules = [
            'accounting' => $request->boolean('modules.accounting'),
            'treasury' => $request->boolean('modules.treasury'),
            'investor' => $request->boolean('modules.investor'),
            'payments' => $request->boolean('modules.payments'),
            'ops_center' => $request->boolean('modules.ops_center'),
        ];

        $setting = PlatformSetting::query()->firstOrCreate(['key' => 'feature_flags'], ['value' => ['modules' => $modules]]);
        $before = (array) ($setting->value ?? []);
        $after = ['modules' => $modules];

        $setting->update(['value' => $after, 'updated_by' => $request->user()?->id]);

        $this->auditTrail->log(
            'feature_flags.update',
            PlatformSetting::class,
            $setting->id,
            $request->user()?->id,
            $before,
            $after,
            ['source' => 'ops_center'],
            $request
        );

        return back()->with('status', 'Feature flags mises à jour.');
    }

    public function runHealthChecksNow(Request $request): RedirectResponse
    {
        $checks = $this->runHealthChecks();
        PlatformSetting::query()->updateOrCreate(
            ['key' => 'ops_health_checks'],
            ['value' => ['generated_at' => now()->toIso8601String(), 'checks' => $checks], 'updated_by' => $request->user()?->id]
        );

        return back()->with('status', 'Health checks exécutés.');
    }

    public function retryAllFailedJobs(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('failed_jobs')) {
            return back()->withErrors(['queue' => 'Table failed_jobs absente.']);
        }

        $ids = DB::table('failed_jobs')->pluck('id')->all();
        if (empty($ids)) {
            return back()->with('status', 'Aucun job en échec à relancer.');
        }

        Artisan::call('queue:retry', ['id' => $ids]);

        $this->auditTrail->log(
            'queue.retry_all_failed',
            'failed_jobs',
            null,
            $request->user()?->id,
            ['failed_ids' => $ids],
            ['retried' => count($ids)],
            null,
            $request
        );

        return back()->with('status', count($ids).' job(s) relancé(s).');
    }

    public function createApprovalRequest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action_key' => ['required', 'string', 'max:120'],
            'target_type' => ['nullable', 'string', 'max:160'],
            'target_id' => ['nullable', 'integer'],
            'payload_json' => ['nullable', 'string'],
            'required_approvals' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $payload = null;
        if (! empty($data['payload_json'])) {
            $decoded = json_decode((string) $data['payload_json'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $payload = $decoded;
            }
        }

        AdminApprovalRequest::query()->create([
            'action_key' => $data['action_key'],
            'target_type' => $data['target_type'] ?? null,
            'target_id' => $data['target_id'] ?? null,
            'payload' => $payload,
            'status' => 'pending',
            'required_approvals' => (int) ($data['required_approvals'] ?? 2),
            'current_approvals' => 0,
            'requested_by_user_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Demande d’approbation créée (étape 1/2).');
    }

    public function approveRequest(Request $request, AdminApprovalRequest $approval): RedirectResponse
    {
        if ($approval->status !== 'pending') {
            return back()->withErrors(['approval' => 'Cette demande n’est plus en attente.']);
        }
        if ($approval->requested_by_user_id === $request->user()->id) {
            return back()->withErrors(['approval' => 'Une demande doit être validée par un autre administrateur.']);
        }
        if (AdminApprovalAction::query()->where('admin_approval_request_id', $approval->id)->where('admin_user_id', $request->user()->id)->exists()) {
            return back()->withErrors(['approval' => 'Vous avez déjà statué sur cette demande.']);
        }

        $before = $approval->toArray();
        AdminApprovalAction::query()->create([
            'admin_approval_request_id' => $approval->id,
            'admin_user_id' => $request->user()->id,
            'action' => 'approved',
            'note' => (string) $request->input('approval_note', ''),
            'acted_at' => now(),
        ]);
        $newCount = AdminApprovalAction::query()
            ->where('admin_approval_request_id', $approval->id)
            ->where('action', 'approved')
            ->count();
        $isFinal = $newCount >= (int) $approval->required_approvals;
        $approval->update([
            'status' => $isFinal ? 'approved' : 'pending',
            'approved_by_user_id' => $isFinal ? $request->user()->id : null,
            'current_approvals' => (int) $newCount,
            'approval_note' => (string) $request->input('approval_note', ''),
            'approved_at' => $isFinal ? now() : null,
        ]);

        $this->auditTrail->log(
            'approval.approve',
            AdminApprovalRequest::class,
            $approval->id,
            $request->user()->id,
            $before,
            $approval->fresh()?->toArray(),
            null,
            $request
        );

        return back()->with('status', $isFinal
            ? 'Demande approuvée (seuil multi-niveaux atteint).'
            : 'Approbation enregistrée. En attente des autres validateurs.');
    }

    public function rejectRequest(Request $request, AdminApprovalRequest $approval): RedirectResponse
    {
        if ($approval->status !== 'pending') {
            return back()->withErrors(['approval' => 'Cette demande n’est plus en attente.']);
        }
        if (AdminApprovalAction::query()->where('admin_approval_request_id', $approval->id)->where('admin_user_id', $request->user()->id)->exists()) {
            return back()->withErrors(['approval' => 'Vous avez déjà statué sur cette demande.']);
        }

        $before = $approval->toArray();
        AdminApprovalAction::query()->create([
            'admin_approval_request_id' => $approval->id,
            'admin_user_id' => $request->user()->id,
            'action' => 'rejected',
            'note' => (string) $request->input('rejection_note', ''),
            'acted_at' => now(),
        ]);
        $approval->update([
            'status' => 'rejected',
            'rejected_by_user_id' => $request->user()->id,
            'rejection_note' => (string) $request->input('rejection_note', ''),
            'rejected_at' => now(),
        ]);

        $this->auditTrail->log(
            'approval.reject',
            AdminApprovalRequest::class,
            $approval->id,
            $request->user()->id,
            $before,
            $approval->fresh()?->toArray(),
            null,
            $request
        );

        return back()->with('status', 'Demande rejetée.');
    }

    public function exportAuditCsv()
    {
        $rows = AdminAuditTrail::query()
            ->with('actor:id,name,email')
            ->latest()
            ->limit(5000)
            ->get();

        $filename = 'admin-audit-trail-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'action', 'target_type', 'target_id', 'actor', 'actor_email', 'ip', 'before', 'after', 'meta']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    optional($row->created_at)->toDateTimeString(),
                    $row->action,
                    $row->target_type,
                    $row->target_id,
                    $row->actor?->name,
                    $row->actor?->email,
                    $row->ip_address,
                    json_encode($row->before_data, JSON_UNESCAPED_UNICODE),
                    json_encode($row->after_data, JSON_UNESCAPED_UNICODE),
                    json_encode($row->meta, JSON_UNESCAPED_UNICODE),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSloMetrics(): array
    {
        $supportHours = $this->averageHoursBetween(
            SupportTicket::query()->where('status', SupportTicket::STATUS_CLOSED),
            'created_at',
            'updated_at'
        );

        $ocrHours = $this->averageHoursBetween(
            AccountingEntry::query(),
            'created_at',
            'ocr_verified_at'
        );

        $investmentHours = $this->averageHoursBetween(
            InvestmentRequest::query(),
            'created_at',
            'reviewed_at'
        );

        return [
            'support' => $this->makeSloRow('Support', $supportHours, 24.0),
            'ocr' => $this->makeSloRow('Validation OCR', $ocrHours, 12.0),
            'investment' => $this->makeSloRow('Traitement demandes', $investmentHours, 48.0),
        ];
    }

    /**
     * Moyenne (en heures) entre deux colonnes date, calculée côté PHP plutôt qu'en SQL
     * (TIMESTAMPDIFF est MySQL-only et casse sous PostgreSQL/Neon en production).
     */
    private function averageHoursBetween(Builder $query, string $fromColumn, string $toColumn): float
    {
        $rows = $query->whereNotNull($toColumn)->get([$fromColumn, $toColumn]);
        if ($rows->isEmpty()) {
            return 0.0;
        }

        return (float) $rows->avg(fn ($row) => $row->{$fromColumn}->diffInHours($row->{$toColumn}));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildAlertCenter(): array
    {
        $alerts = [];

        $expiringSubs = User::query()
            ->clients()
            ->where('is_premium', true)
            ->whereNotNull('premium_ends_at')
            ->where('premium_ends_at', '>', now())
            ->where('premium_ends_at', '<=', now()->addDays(5))
            ->count();
        $alerts[] = $this->makeAlert('subscription.expire_lt_5', 'Abonnements expirent < 5 jours', $expiringSubs, 3, route('admin.payments.index'));

        $blockedTickets = SupportTicket::query()
            ->whereIn('status', [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_IN_PROGRESS])
            ->where('updated_at', '<=', now()->subHours(48))
            ->count();
        $alerts[] = $this->makeAlert('support.ticket_blocked_48h', 'Tickets bloqués > 48h', $blockedTickets, 2, route('support.tickets'));

        $errors5xx = MenuActionLog::query()
            ->where('status_code', '>=', 500)
            ->where('created_at', '>=', now()->subHour())
            ->count();
        $alerts[] = $this->makeAlert('http.5xx_spike', 'Erreurs HTTP 5xx sur 1h', $errors5xx, 5, route('admin.logs.index', ['module' => 'menu']));

        return collect($alerts)
            ->map(function (array $a) {
                $escalation = $a['value'] >= ($a['threshold'] * 3) ? 3 : ($a['value'] >= ($a['threshold'] * 2) ? 2 : ($a['value'] >= $a['threshold'] ? 1 : 0));
                $a['escalation'] = $escalation;
                $a['severity'] = $escalation >= 2 ? 'danger' : ($escalation === 1 ? 'warning' : 'success');

                return $a;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildNocMetrics(): array
    {
        $menu24h = MenuActionLog::query()->where('created_at', '>=', now()->subDay());
        $total = (int) (clone $menu24h)->count();
        $errors = (int) (clone $menu24h)->where('status_code', '>=', 500)->count();
        $availability = $total > 0 ? round(100 - (($errors / $total) * 100), 2) : 100.0;

        return [
            'incidents_24h' => $errors + SupportTicket::query()->where('status', SupportTicket::STATUS_OPEN)->count(),
            'availability_pct' => $availability,
            'http_5xx_24h' => $errors,
            'saturation_hint' => $total > 0 ? round(($errors / max($total, 1)) * 100, 2) : 0.0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildQueueMetrics(): array
    {
        $pending = Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : 0;
        $failed = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;

        return [
            'pending' => $pending,
            'failed' => $failed,
            'is_available' => Schema::hasTable('jobs') || Schema::hasTable('failed_jobs'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function runHealthChecks(): array
    {
        $checks = [];

        // DB
        try {
            DB::select('SELECT 1 as ok');
            $checks[] = ['name' => 'Database', 'status' => 'ok', 'detail' => 'Connexion OK'];
        } catch (\Throwable $e) {
            $checks[] = ['name' => 'Database', 'status' => 'ko', 'detail' => $e->getMessage()];
        }

        // Storage
        try {
            $path = 'healthchecks/.probe-'.uniqid().'.txt';
            Storage::disk('public')->put($path, 'ok');
            Storage::disk('public')->delete($path);
            $checks[] = ['name' => 'Storage public', 'status' => 'ok', 'detail' => 'Lecture/écriture OK'];
        } catch (\Throwable $e) {
            $checks[] = ['name' => 'Storage public', 'status' => 'ko', 'detail' => $e->getMessage()];
        }

        // Mail
        $mailHost = (string) config('mail.mailers.smtp.host', '');
        $checks[] = [
            'name' => 'Mail SMTP',
            'status' => $mailHost !== '' ? 'ok' : 'warn',
            'detail' => $mailHost !== '' ? 'Hôte SMTP configuré' : 'SMTP non configuré',
        ];

        // Webhook Stripe
        $stripeSecret = (string) config('services.stripe.webhook.secret', '');
        $checks[] = [
            'name' => 'Stripe webhook',
            'status' => $stripeSecret !== '' ? 'ok' : 'warn',
            'detail' => $stripeSecret !== '' ? 'Secret configuré' : 'Secret webhook absent',
        ];

        // OCR
        $pythonCmd = (string) config('services.ocr.python_binary', '');
        $checks[] = [
            'name' => 'OCR service',
            'status' => $pythonCmd !== '' ? 'ok' : 'warn',
            'detail' => $pythonCmd !== '' ? 'Binaire Python OCR configuré' : 'Binaire OCR non défini',
        ];

        return $checks;
    }

    /**
     * @return array<string, mixed>
     */
    private function makeSloRow(string $label, float $actualHours, float $targetHours): array
    {
        $status = 'success';
        if ($actualHours > ($targetHours * 1.5)) {
            $status = 'danger';
        } elseif ($actualHours > $targetHours) {
            $status = 'warning';
        }

        return [
            'label' => $label,
            'actual_hours' => round($actualHours, 2),
            'target_hours' => $targetHours,
            'status' => $status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function makeAlert(string $code, string $label, int $value, int $threshold, string $url): array
    {
        return [
            'code' => $code,
            'label' => $label,
            'value' => $value,
            'threshold' => $threshold,
            'url' => $url,
        ];
    }
}
