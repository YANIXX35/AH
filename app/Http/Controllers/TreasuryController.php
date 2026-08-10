<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesClientWorkspace;
use App\Models\AppNotification;
use App\Models\TreasuryAuditLog;
use App\Models\TreasuryPeriodLock;
use App\Models\TreasuryTransaction;
use App\Services\FedaPaySandboxService;
use App\Services\StripeTreasuryService;
use App\Services\Treasury\TreasuryBalanceService;
use App\Services\TreasuryAudit;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TreasuryController extends Controller
{
    use UsesClientWorkspace;

    /**
     * Affiche le tableau de bord de trésorerie
     */
    public function index()
    {
        $userIds = $this->workspaceDataUserIds();
        $today = now()->startOfDay();
        $month_start = now()->startOfMonth();
        $month_end = now()->endOfMonth();

        // Transactions du mois
        $transactions = TreasuryTransaction::whereIn('user_id', $userIds)
            ->orderByDesc('transaction_date')
            ->paginate(15);

        // Calculs des soldes
        $totalEncaissements = TreasuryTransaction::whereIn('user_id', $userIds)
            ->encaissements()
            ->effectuees()
            ->sum('amount');

        $totalDecaissements = TreasuryTransaction::whereIn('user_id', $userIds)
            ->decaissements()
            ->effectuees()
            ->sum('amount');

        $solde = $totalEncaissements - $totalDecaissements;

        // Encaissements/décaissements planifiés
        $encaissementsPlanifies = TreasuryTransaction::whereIn('user_id', $userIds)
            ->encaissements()
            ->planifiees()
            ->sum('amount');

        $decaissementsPlanifies = TreasuryTransaction::whereIn('user_id', $userIds)
            ->decaissements()
            ->planifiees()
            ->sum('amount');

        $soldeProjecte = $solde + $encaissementsPlanifies - $decaissementsPlanifies;

        // Prévisions du mois
        $monthTransactions = TreasuryTransaction::whereIn('user_id', $userIds)
            ->byDateRange($month_start, $month_end)
            ->get();

        $monthEncaissements = $monthTransactions->where('type', 'encaissement')->sum('amount');
        $monthDecaissements = $monthTransactions->where('type', 'decaissement')->sum('amount');

        // Nouvelles données pour les graphiques
        $chartData = $this->getChartData($userIds);
        $categoryData = $this->getCategoryData($userIds);
        $trendsData = $this->getTrendsData($userIds);
        $recentActivity = $this->getRecentActivity($userIds);

        return view('treasury.index', array_merge([
            'transactions' => $transactions,
            'totalEncaissements' => $totalEncaissements,
            'totalDecaissements' => $totalDecaissements,
            'solde' => $solde,
            'encaissementsPlanifies' => $encaissementsPlanifies,
            'decaissementsPlanifies' => $decaissementsPlanifies,
            'soldeProjecte' => $soldeProjecte,
            'monthEncaissements' => $monthEncaissements,
            'monthDecaissements' => $monthDecaissements,
        ], $chartData, $categoryData, $trendsData, ['recentActivity' => $recentActivity]));
    }

    /**
     * Affiche le dashboard style crypto
     */
    public function dashboardCrypto()
    {
        $userIds = $this->workspaceDataUserIds();
        $month_start = now()->startOfMonth();
        $month_end = now()->endOfMonth();

        // Transactions récentes pour le dashboard
        $transactions = TreasuryTransaction::whereIn('user_id', $userIds)
            ->orderByDesc('transaction_date')
            ->take(10)
            ->get();

        // Calculs des soldes
        $totalEncaissements = TreasuryTransaction::whereIn('user_id', $userIds)
            ->encaissements()
            ->effectuees()
            ->sum('amount');

        $totalDecaissements = TreasuryTransaction::whereIn('user_id', $userIds)
            ->decaissements()
            ->effectuees()
            ->sum('amount');

        $solde = $totalEncaissements - $totalDecaissements;

        // Encaissements/décaissements planifiés
        $encaissementsPlanifies = TreasuryTransaction::whereIn('user_id', $userIds)
            ->encaissements()
            ->planifiees()
            ->sum('amount');

        $decaissementsPlanifies = TreasuryTransaction::whereIn('user_id', $userIds)
            ->decaissements()
            ->planifiees()
            ->sum('amount');

        $soldeProjecte = $solde + $encaissementsPlanifies - $decaissementsPlanifies;

        // Transactions en attente
        $pendingTransactions = TreasuryTransaction::whereIn('user_id', $userIds)
            ->planifiees()
            ->orderBy('transaction_date')
            ->get();

        // Vraies notifications de l'utilisateur (le menu affichait auparavant
        // 4 entrées inventées en dur avec des liens morts).
        $notifications = AppNotification::where('user_id', auth()->id())
            ->latest()
            ->take(5)
            ->get();

        return view('treasury.dashboard-crypto', [
            'transactions' => $transactions,
            'totalEncaissements' => $totalEncaissements,
            'totalDecaissements' => $totalDecaissements,
            'solde' => $solde,
            'soldeProjecte' => $soldeProjecte,
            'pendingTransactions' => $pendingTransactions,
            'notifications' => $notifications,
        ]);
    }

    /**
     * Affiche le suivi des encaissements/décaissements
     */
    public function tracking(Request $request)
    {
        $userIds = $this->workspaceDataUserIds();
        $type = $request->query('type', 'all');
        $status = $request->query('status', 'all');
        $paymentModule = $request->query('payment_module', 'all');
        $dateFrom = $request->query('date_from', '');
        $dateTo = $request->query('date_to', '');

        if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $dateFromCarbon = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : null;
        $dateToCarbon = $dateTo ? Carbon::parse($dateTo)->endOfDay() : null;

        $query = TreasuryTransaction::whereIn('user_id', $userIds);

        if ($type !== 'all') {
            $query->where('type', $type);
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($paymentModule !== 'all') {
            if ($paymentModule === 'none') {
                $query->whereNull('payment_module');
            } else {
                $query->where('payment_module', $paymentModule);
            }
        }

        if ($dateFrom) {
            $query->where('transaction_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('transaction_date', '<=', $dateTo);
        }

        $transactionsQuery = clone $query;
        $transactions = $transactionsQuery
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        $summaryQuery = clone $query;
        $summaryRows = $summaryQuery->get(['type', 'status', 'amount']);

        $encaissementsTotal = (float) $summaryRows
            ->where('type', 'encaissement')
            ->sum('amount');
        $decaissementsTotal = (float) $summaryRows
            ->where('type', 'decaissement')
            ->sum('amount');
        $soldeNetFiltre = $encaissementsTotal - $decaissementsTotal;

        $encaissementsEffectues = (float) $summaryRows
            ->where('type', 'encaissement')
            ->where('status', 'effectue')
            ->sum('amount');
        $decaissementsEffectues = (float) $summaryRows
            ->where('type', 'decaissement')
            ->where('status', 'effectue')
            ->sum('amount');
        $soldeNetEffectue = $encaissementsEffectues - $decaissementsEffectues;

        $engagementsPlanifies = (float) $summaryRows
            ->where('status', 'planifie')
            ->sum(fn ($tx) => $tx->type === 'encaissement' ? (float) $tx->amount : -(float) $tx->amount);

        // Solde d'ouverture/clôture comptable basé sur les opérations effectuées.
        $soldeOuverturePeriode = $dateFromCarbon
            ? (float) TreasuryTransaction::whereIn('user_id', $userIds)
                ->effectuees()
                ->where('transaction_date', '<', $dateFromCarbon)
                ->sum(DB::raw("CASE WHEN type = 'encaissement' THEN amount ELSE -amount END"))
            : 0.0;

        $impactEffectuePeriode = (float) $summaryRows
            ->where('status', 'effectue')
            ->sum(fn ($tx) => $tx->type === 'encaissement' ? (float) $tx->amount : -(float) $tx->amount);
        $soldeCloturePeriode = $soldeOuverturePeriode + $impactEffectuePeriode;

        return view('treasury.tracking', [
            'transactions' => $transactions,
            'type' => $type,
            'status' => $status,
            'paymentModule' => $paymentModule,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'encaissementsTotal' => $encaissementsTotal,
            'decaissementsTotal' => $decaissementsTotal,
            'soldeNetFiltre' => $soldeNetFiltre,
            'soldeNetEffectue' => $soldeNetEffectue,
            'engagementsPlanifies' => $engagementsPlanifies,
            'soldeOuverturePeriode' => $soldeOuverturePeriode,
            'soldeCloturePeriode' => $soldeCloturePeriode,
        ]);
    }

    /**
     * Affiche le solde de trésorerie
     */
    public function balance(Request $request, TreasuryBalanceService $treasuryBalanceService)
    {
        $userIds = $this->workspaceDataUserIds();
        $viewData = $treasuryBalanceService->build(
            $userIds,
            $request->query('date_from'),
            $request->query('date_to')
        );

        return view('treasury.balance', $viewData);
    }

    /**
     * Affiche la prévision de trésorerie
     */
    public function forecast(Request $request)
    {
        $userIds = $this->workspaceDataUserIds();
        $workspaceOwnerId = $this->workspaceUserId();

        $horizon = (int) $request->query('horizon', 90);
        if (! in_array($horizon, [30, 60, 90], true)) {
            $horizon = 90;
        }

        $today = now()->startOfDay();
        $periodEnd = $today->copy()->addDays($horizon);
        $scenario = (string) $request->query('scenario', 'realiste');
        if (! in_array($scenario, ['prudent', 'realiste', 'optimiste'], true)) {
            $scenario = 'realiste';
        }
        $safetyThreshold = max(0, (float) $request->query('safety_threshold', 0));

        // Flux planifiés uniquement sur la période (cohérent avec une prévision).
        $plannedTransactions = TreasuryTransaction::whereIn('user_id', $userIds)
            ->planifiees()
            ->byDateRange($today, $periodEnd)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        // Solde réalisé à date (base des projections).
        $currentBalance = (float) TreasuryTransaction::whereIn('user_id', $userIds)
            ->effectuees()
            ->sum(DB::raw("CASE WHEN type = 'encaissement' THEN amount ELSE -amount END"));

        $totalPlannedIn = (float) $plannedTransactions->where('type', 'encaissement')->sum('amount');
        $totalPlannedOut = (float) $plannedTransactions->where('type', 'decaissement')->sum('amount');
        $netPlanned = $totalPlannedIn - $totalPlannedOut;
        $projectedEndBalance = $currentBalance + $netPlanned;

        // Projections hebdomadaires : uniquement les opérations planifiées (pas de double comptage avec l'historique effectué).
        $weeklyProjections = $this->calculateWeeklyProjections($userIds, $today, $periodEnd, true);

        $scenarioMultipliers = [
            'prudent' => ['in' => 0.90, 'out' => 1.10, 'label' => 'Prudent'],
            'realiste' => ['in' => 1.00, 'out' => 1.00, 'label' => 'Réaliste'],
            'optimiste' => ['in' => 1.10, 'out' => 0.95, 'label' => 'Optimiste'],
        ];
        $scenarioConfig = $scenarioMultipliers[$scenario];

        $scenarioPlannedIn = $totalPlannedIn * $scenarioConfig['in'];
        $scenarioPlannedOut = $totalPlannedOut * $scenarioConfig['out'];
        $scenarioNet = $scenarioPlannedIn - $scenarioPlannedOut;
        $scenarioProjectedEndBalance = $currentBalance + $scenarioNet;

        $scenarioWeeklyProjections = [];
        $scenarioRunningBalance = $currentBalance;
        foreach ($weeklyProjections as $week) {
            $scenarioInflow = (float) $week['inflow'] * $scenarioConfig['in'];
            $scenarioOutflow = (float) $week['outflow'] * $scenarioConfig['out'];
            $scenarioRunningBalance += $scenarioInflow - $scenarioOutflow;

            $scenarioWeeklyProjections[] = [
                'week' => $week['week'],
                'inflow' => $scenarioInflow,
                'outflow' => $scenarioOutflow,
                'balance' => $scenarioRunningBalance,
            ];
        }

        $minScenarioBalance = empty($scenarioWeeklyProjections)
            ? $scenarioProjectedEndBalance
            : (float) min(array_column($scenarioWeeklyProjections, 'balance'));

        $riskAlerts = [];
        if ($minScenarioBalance < 0) {
            $riskAlerts[] = "Alerte critique : le solde projeté devient négatif pendant la période ({$scenarioConfig['label']}).";
        }
        if ($safetyThreshold > 0 && $minScenarioBalance < $safetyThreshold) {
            $riskAlerts[] = 'Alerte de sécurité : le solde projeté passe sous le seuil de sécurité défini.';
        }
        if ($scenarioProjectedEndBalance < $currentBalance) {
            $riskAlerts[] = 'Tendance baissière : le solde de fin de période est inférieur au solde actuel.';
        }

        // Etape 2 : suivi de fiabilité des prévisions (8 dernières semaines).
        $accuracyData = $this->calculateForecastAccuracy($userIds, $today, 8);

        // Etape 3 : gouvernance (verrouillages + journal d'audit).
        $periodLocks = TreasuryPeriodLock::where('user_id', $workspaceOwnerId)
            ->orderByDesc('period_month')
            ->get();
        $auditLogs = TreasuryAuditLog::with(['actor:id,name,email', 'user:id,name,email'])
            ->where('user_id', $workspaceOwnerId)
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return view('treasury.forecast', [
            'plannedTransactions' => $plannedTransactions,
            'currentBalance' => $currentBalance,
            'weeklyProjections' => $weeklyProjections,
            'scenarioWeeklyProjections' => $scenarioWeeklyProjections,
            'periodEnd' => $periodEnd,
            'horizon' => $horizon,
            'scenario' => $scenario,
            'scenarioLabel' => $scenarioConfig['label'],
            'safetyThreshold' => $safetyThreshold,
            'totalPlannedIn' => $totalPlannedIn,
            'totalPlannedOut' => $totalPlannedOut,
            'netPlanned' => $netPlanned,
            'projectedEndBalance' => $projectedEndBalance,
            'scenarioPlannedIn' => $scenarioPlannedIn,
            'scenarioPlannedOut' => $scenarioPlannedOut,
            'scenarioNet' => $scenarioNet,
            'scenarioProjectedEndBalance' => $scenarioProjectedEndBalance,
            'minScenarioBalance' => $minScenarioBalance,
            'riskAlerts' => $riskAlerts,
            'accuracyRows' => $accuracyData['rows'],
            'forecastReliabilityScore' => $accuracyData['score'],
            'forecastGapTotal' => $accuracyData['totalGap'],
            'alertWeeksCount' => $accuracyData['alertWeeks'],
            'periodLocks' => $periodLocks,
            'auditLogs' => $auditLogs,
            'governancePeriodMonth' => $request->query('governance_month', now()->format('Y-m')),
        ]);
    }

    /**
     * Verrouille un mois civil pour empêcher toute modification des mouvements de trésorerie.
     */
    public function lockTreasuryPeriod(Request $request)
    {
        $workspaceOwnerId = $this->workspaceUserId();
        $validated = $request->validate([
            'period_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $lock = TreasuryPeriodLock::updateOrCreate(
            [
                'user_id' => $workspaceOwnerId,
                'period_month' => $validated['period_month'],
            ],
            [
                'locked_at' => now(),
                'locked_by' => Auth::id(),
                'notes' => $validated['notes'] ?? null,
            ]
        );

        TreasuryAudit::log($workspaceOwnerId, 'treasury.period.lock', $lock, [
            'period_month' => $validated['period_month'],
        ]);

        return redirect()->route('treasury.forecast', [
            'horizon' => (int) $request->input('horizon', 90),
            'scenario' => $request->input('scenario', 'realiste'),
            'safety_threshold' => $request->input('safety_threshold', 0),
            'governance_month' => $validated['period_month'],
        ])->with('status', 'Période verrouillée.');
    }

    /**
     * Déverrouille un mois civil (si aucune validation formelle n'a été posée).
     */
    public function unlockTreasuryPeriod(Request $request)
    {
        $workspaceOwnerId = $this->workspaceUserId();
        $validated = $request->validate([
            'period_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $lock = TreasuryPeriodLock::where('user_id', $workspaceOwnerId)
            ->where('period_month', $validated['period_month'])
            ->first();

        if (! $lock) {
            return back()->withErrors(['period_month' => 'Aucun verrou trouvé pour cette période.']);
        }

        if ($lock->validated_at) {
            return back()->withErrors(['period_month' => 'Impossible de déverrouiller : la prévision est validée. Contactez un administrateur.']);
        }

        $lock->delete();

        TreasuryAudit::log($workspaceOwnerId, 'treasury.period.unlock', null, [
            'period_month' => $validated['period_month'],
        ]);

        return redirect()->route('treasury.forecast', [
            'horizon' => (int) $request->input('horizon', 90),
            'scenario' => $request->input('scenario', 'realiste'),
            'safety_threshold' => $request->input('safety_threshold', 0),
            'governance_month' => $validated['period_month'],
        ])->with('status', 'Période déverrouillée.');
    }

    /**
     * Valide formellement la prévision pour un mois déjà verrouillé.
     */
    public function validateTreasuryForecast(Request $request)
    {
        $workspaceOwnerId = $this->workspaceUserId();
        $validated = $request->validate([
            'period_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $lock = TreasuryPeriodLock::where('user_id', $workspaceOwnerId)
            ->where('period_month', $validated['period_month'])
            ->first();

        if (! $lock) {
            return back()->withErrors(['period_month' => 'Verrouillez d\'abord la période avant validation.']);
        }

        $lock->update([
            'validated_at' => now(),
            'validated_by' => Auth::id(),
        ]);

        TreasuryAudit::log($workspaceOwnerId, 'treasury.forecast.validate', $lock, [
            'period_month' => $validated['period_month'],
        ]);

        return redirect()->route('treasury.forecast', [
            'horizon' => (int) $request->input('horizon', 90),
            'scenario' => $request->input('scenario', 'realiste'),
            'safety_threshold' => $request->input('safety_threshold', 0),
            'governance_month' => $validated['period_month'],
        ])->with('status', 'Prévision validée pour la période.');
    }

    /**
     * Crée une nouvelle transaction
     */
    public function create()
    {
        $userIds = $this->workspaceDataUserIds();
        $recentPayments = TreasuryTransaction::whereIn('user_id', $userIds)
            ->whereIn('payment_module', ['stripe', 'fedapay_mobile'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get([
                'id',
                'type',
                'transaction_type',
                'description',
                'amount',
                'transaction_date',
                'status',
                'reference',
                'payment_module',
                'stripe_status',
                'stripe_payout_id',
            ]);

        return view('treasury.create', [
            'recentPayments' => $recentPayments,
        ]);
    }

    /**
     * Stocke une nouvelle transaction
     */
    public function store(Request $request, StripeTreasuryService $stripe)
    {
        $validated = $request->validate([
            'type' => 'required|in:encaissement,decaissement',
            'transaction_type' => 'required|string',
            'payment_module' => 'required|in:stripe,fedapay_mobile',
            'payment_provider' => 'nullable|string|max:60',
            'stripe_payment_channel' => 'nullable|required_if:payment_module,stripe|in:card,bank_debit',
            'stripe_bank_scheme' => 'nullable|in:ach,sepa',
            'mobile_method' => 'nullable|required_if:payment_module,fedapay_mobile|in:orange_money,mtn_money,moov_money,wave',
            'mobile_number' => 'nullable|required_if:payment_module,fedapay_mobile|string|max:30',
            'fedapay_country' => 'nullable|in:CIV,SEN,BEN,TGO,CMR',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'transaction_date' => 'required|date',
            'reference' => 'nullable|string|max:100',
            'bank_account' => 'nullable|string|max:100',
            'status' => 'required|in:planifie,effectue,annule',
            'notes' => 'nullable|string',
        ]);
        $validated = $this->normalizePaymentFields($validated);
        $validated = $this->ensureTrackingReference($validated);

        $workspaceOwnerId = $this->workspaceUserId();
        $this->assertTreasuryPeriodUnlocked($workspaceOwnerId, $validated['transaction_date']);

        $transaction = TreasuryTransaction::create(array_merge($validated, [
            'user_id' => $workspaceOwnerId,
            'actor_user_id' => Auth::id(),
        ]));

        TreasuryAudit::log($workspaceOwnerId, 'treasury.transaction.create', $transaction, [
            'amount' => (string) $transaction->amount,
            'transaction_date' => $transaction->transaction_date->toDateString(),
        ]);

        $providerRedirect = $this->maybeStartProviderCheckout($request, $transaction, $stripe);
        if ($providerRedirect !== null) {
            return $providerRedirect;
        }

        return redirect()->route('treasury.index')->with('success', 'Transaction ajoutée avec succès.');
    }

    /**
     * Édite une transaction
     */
    public function edit(TreasuryTransaction $transaction)
    {
        $this->authorize('own', $transaction);

        return view('treasury.edit', ['transaction' => $transaction]);
    }

    /**
     * Mise à jour d'une transaction
     */
    public function update(Request $request, TreasuryTransaction $transaction, StripeTreasuryService $stripe)
    {
        $this->authorize('own', $transaction);

        $validated = $request->validate([
            'type' => 'required|in:encaissement,decaissement',
            'transaction_type' => 'required|string',
            'payment_module' => 'required|in:stripe,fedapay_mobile',
            'payment_provider' => 'nullable|string|max:60',
            'stripe_payment_channel' => 'nullable|required_if:payment_module,stripe|in:card,bank_debit',
            'stripe_bank_scheme' => 'nullable|in:ach,sepa',
            'mobile_method' => 'nullable|required_if:payment_module,fedapay_mobile|in:orange_money,mtn_money,moov_money,wave',
            'mobile_number' => 'nullable|required_if:payment_module,fedapay_mobile|string|max:30',
            'fedapay_country' => 'nullable|in:CIV,SEN,BEN,TGO,CMR',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'transaction_date' => 'required|date',
            'reference' => 'nullable|string|max:100',
            'bank_account' => 'nullable|string|max:100',
            'status' => 'required|in:planifie,effectue,annule',
            'notes' => 'nullable|string',
        ]);
        $validated = $this->normalizePaymentFields($validated);
        $validated = $this->ensureTrackingReference($validated, $transaction);

        $workspaceOwnerId = $this->workspaceUserId();
        $this->assertTreasuryPeriodUnlocked($workspaceOwnerId, $transaction->transaction_date);
        $this->assertTreasuryPeriodUnlocked($workspaceOwnerId, $validated['transaction_date']);

        $validated['actor_user_id'] = Auth::id();
        $before = $transaction->only(['type', 'amount', 'transaction_date', 'status']);
        $transaction->update($validated);
        $transaction->refresh();

        TreasuryAudit::log($workspaceOwnerId, 'treasury.transaction.update', $transaction, [
            'before' => $before,
            'after' => $transaction->only(['type', 'amount', 'transaction_date', 'status']),
        ]);

        $providerRedirect = $this->maybeStartProviderCheckout($request, $transaction, $stripe);
        if ($providerRedirect !== null) {
            return $providerRedirect;
        }

        return redirect()->route('treasury.index')->with('success', 'Transaction mise à jour avec succès.');
    }

    public function stripeSuccess(Request $request, TreasuryTransaction $transaction, StripeTreasuryService $stripe): RedirectResponse
    {
        $this->authorize('own', $transaction);

        $sessionId = (string) $request->query('session_id', '');
        if ($sessionId === '') {
            return redirect()->route('treasury.tracking')
                ->withErrors(['stripe' => 'Retour Stripe invalide : session manquante.']);
        }

        if (! $stripe->enabled()) {
            return redirect()->route('treasury.tracking')
                ->withErrors(['stripe' => 'Stripe n’est pas activé dans la configuration.']);
        }

        try {
            $session = $stripe->retrieveCheckoutSession($sessionId);
        } catch (\Throwable $e) {
            return redirect()->route('treasury.tracking')
                ->withErrors(['stripe' => 'Impossible de vérifier le paiement Stripe : '.$e->getMessage()]);
        }

        $isPaid = ($session['payment_status'] ?? '') === 'paid';
        $transaction->update([
            'payment_module' => 'stripe',
            'payment_provider' => 'Stripe',
            'stripe_checkout_session_id' => $session['id'] ?? $sessionId,
            'stripe_payment_intent_id' => $session['payment_intent_id'] ?? null,
            'stripe_charge_id' => $session['charge_id'] ?? null,
            'stripe_status' => $isPaid ? 'paid' : (($session['payment_status'] ?? '') ?: 'pending'),
            'stripe_failure_reason' => $isPaid ? null : 'Paiement Stripe non finalisé.',
            'stripe_paid_at' => $isPaid ? now() : null,
            'status' => $isPaid ? 'effectue' : 'planifie',
            'actor_user_id' => Auth::id(),
        ]);

        TreasuryAudit::log($this->workspaceUserId(), 'treasury.transaction.stripe.success', $transaction, [
            'stripe_session_id' => $session['id'] ?? $sessionId,
            'stripe_payment_intent_id' => $session['payment_intent_id'] ?? null,
            'stripe_payment_status' => $session['payment_status'] ?? null,
        ]);

        return redirect()->route('treasury.tracking')
            ->with($isPaid ? 'success' : 'status', $isPaid
                ? 'Paiement Stripe confirmé. Transaction effectuée.'
                : 'Retour Stripe reçu. Paiement encore en attente.');
    }

    public function stripeCancel(TreasuryTransaction $transaction): RedirectResponse
    {
        $this->authorize('own', $transaction);

        if ($transaction->payment_module === 'stripe') {
            $transaction->update([
                'stripe_status' => 'canceled',
                'stripe_failure_reason' => 'Paiement Stripe annulé par l’utilisateur.',
                'status' => 'annule',
                'actor_user_id' => Auth::id(),
            ]);

            TreasuryAudit::log($this->workspaceUserId(), 'treasury.transaction.stripe.cancel', $transaction, [
                'stripe_session_id' => $transaction->stripe_checkout_session_id,
            ]);
        }

        return redirect()->route('treasury.tracking')
            ->withErrors(['stripe' => 'Paiement Stripe annulé.']);
    }

    // Initiation de paiement (FedaPaySandboxController) : voir la note de conformité
    // au sommet de FedaPaySandboxController.php avant toute évolution de ce flux.
    public function fedapayCheckoutForm(Request $request, TreasuryTransaction $transaction)
    {
        $this->authorize('own', $transaction);

        if ($transaction->payment_module !== 'fedapay_mobile') {
            return redirect()->route('treasury.tracking')
                ->withErrors(['fedapay' => 'Cette transaction n\'utilise pas FedaPay Mobile Money.']);
        }

        $country = strtoupper((string) $request->query('country', 'CIV'));
        if (! in_array($country, ['CIV', 'SEN', 'BEN', 'TGO', 'CMR'], true)) {
            $country = 'CIV';
        }

        $user = $request->user();
        [$lastName, $firstName] = $this->splitUserDisplayName((string) ($user->name ?? ''));

        return view('treasury.fedapay-checkout', [
            'transaction' => $transaction,
            'country' => $country,
            'payer' => [
                'last_name' => $lastName,
                'first_name' => $firstName,
                'email' => (string) ($user->email ?? ''),
                'country' => $country,
                'phone' => (string) ($transaction->mobile_number ?? ''),
            ],
        ]);
    }

    public function fedapayCheckoutStart(Request $request, TreasuryTransaction $transaction, FedaPaySandboxService $fedapay): RedirectResponse
    {
        $this->authorize('own', $transaction);

        if ($transaction->payment_module !== 'fedapay_mobile') {
            return redirect()->route('treasury.tracking')
                ->withErrors(['fedapay' => 'Cette transaction n\'utilise pas FedaPay Mobile Money.']);
        }

        if ($transaction->type === 'decaissement') {
            return redirect()->route('treasury.tracking')
                ->withErrors(['fedapay' => 'Les décaissements Mobile Money doivent être suivis manuellement (pas de payout automatique FedaPay ici).']);
        }

        $validated = $request->validate([
            'fedapay_country' => 'required|in:CIV,SEN,BEN,TGO,CMR',
        ]);

        $country = strtoupper((string) $validated['fedapay_country']);
        $correspondentCandidates = $this->resolveFedapayCorrespondentCandidates($country, (string) $transaction->mobile_method);
        if (empty($correspondentCandidates)) {
            return back()->withErrors(['fedapay' => 'Canal Mobile Money non disponible pour ce pays.']);
        }

        $result = null;
        $attemptErrors = [];
        foreach ($correspondentCandidates as $correspondent) {
            $attempt = $fedapay->createTransaction($request->user(), [
                'amount' => (float) $transaction->amount,
                'currency' => $this->resolveFedapayCurrency($country),
                'country' => $country,
                'correspondent' => $correspondent,
                'payer_msisdn' => (string) ($transaction->mobile_number ?? ''),
            ]);

            if (($attempt['success'] ?? false) === true) {
                $result = $attempt;
                break;
            }

            $attemptErrors[] = $correspondent.': '.((string) ($attempt['message'] ?? 'Erreur inconnue'));
            $result = $attempt;
        }

        if ($result === null) {
            return back()->withErrors(['fedapay' => 'Impossible d\'initialiser la transaction FedaPay.']);
        }

        if (! $result['success']) {
            $apiDetail = $this->extractFedapayErrorDetail($result['response_payload'] ?? null);
            $attemptDetail = empty($attemptErrors) ? '' : (' | Tentatives: '.implode(' ; ', array_slice($attemptErrors, 0, 4)));

            $fallbackToHostedPage = (bool) config('services.fedapay.sandbox.fallback_to_payment_page', true);
            $hostedPaymentUrl = trim((string) config('services.fedapay.sandbox.payment_page_url', ''));
            if ($fallbackToHostedPage && $hostedPaymentUrl !== '' && filter_var($hostedPaymentUrl, FILTER_VALIDATE_URL)) {
                $fallbackReference = 'FDP-PAGE-'.Carbon::now()->format('YmdHis').'-'.Str::upper(Str::random(4));
                $transaction->update([
                    'status' => 'planifie',
                    'bank_reference' => $fallbackReference,
                    'payment_provider' => 'FedaPay',
                    'notes' => trim(((string) ($transaction->notes ?? '')).' | Fallback page FedaPay: '.$result['message'].' '.$apiDetail.$attemptDetail),
                ]);

                TreasuryAudit::log($this->workspaceUserId(), 'treasury.transaction.fedapay.checkout.fallback_page', $transaction, [
                    'provider_reference' => $fallbackReference,
                    'country' => $country,
                    'mobile_method' => $transaction->mobile_method,
                    'api_error' => $result['message'] ?? null,
                    'api_detail' => $apiDetail,
                    'attempts' => $attemptErrors,
                ]);

                $hostedUrlWithPrefill = $this->buildFedapayHostedPaymentUrl(
                    $hostedPaymentUrl,
                    $request->user(),
                    $country,
                    (string) ($transaction->mobile_number ?? '')
                );

                return redirect()->away($hostedUrlWithPrefill)
                    ->with('status', 'API FedaPay indisponible (sandbox). Redirection vers la page de paiement hébergée.');
            }

            $transaction->update([
                'status' => 'planifie',
                'bank_reference' => (string) ($result['provider_reference'] ?? ''),
                'notes' => trim(((string) ($transaction->notes ?? '')).' | FedaPay error: '.$result['message'].' '.$apiDetail.$attemptDetail),
            ]);

            return back()->withErrors([
                'fedapay' => 'Création API FedaPay impossible : '.$result['message'].($apiDetail !== '' ? ' | Détail: '.$apiDetail : '').$attemptDetail.' Veuillez réessayer avec un autre opérateur ou vérifier la configuration sandbox.',
            ]);
        }

        $transaction->update([
            'status' => 'planifie',
            'bank_reference' => (string) ($result['provider_reference'] ?? ''),
            'payment_provider' => 'FedaPay',
        ]);

        TreasuryAudit::log($this->workspaceUserId(), 'treasury.transaction.fedapay.checkout', $transaction, [
            'provider_reference' => $result['provider_reference'] ?? null,
            'mobile_method' => $transaction->mobile_method,
            'mobile_number' => $transaction->mobile_number,
            'country' => $country,
        ]);

        $checkoutUrl = trim((string) ($result['checkout_url'] ?? ''));
        if ($checkoutUrl !== '') {
            return redirect()->away($checkoutUrl);
        }

        return back()->withErrors([
            'fedapay' => 'Transaction API créée mais aucun checkout_url n\'a été retourné par FedaPay.',
        ]);
    }

    public function stripeWebhook(Request $request, StripeTreasuryService $stripe): Response
    {
        try {
            $event = $stripe->constructWebhookEvent(
                (string) $request->getContent(),
                $request->header('Stripe-Signature')
            );
        } catch (\Throwable $e) {
            return response('Invalid Stripe webhook: '.$e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $object = $event->data->object;
        $eventType = (string) $event->type;
        $tx = null;

        if (isset($object->metadata->treasury_transaction_id)) {
            $tx = TreasuryTransaction::query()->find((int) $object->metadata->treasury_transaction_id);
        }

        if (! $tx && isset($object->id) && str_starts_with((string) $object->id, 'cs_')) {
            $tx = TreasuryTransaction::query()
                ->where('stripe_checkout_session_id', (string) $object->id)
                ->latest('id')
                ->first();
        }

        if (! $tx && isset($object->id) && str_starts_with((string) $object->id, 'pi_')) {
            $tx = TreasuryTransaction::query()
                ->where('stripe_payment_intent_id', (string) $object->id)
                ->latest('id')
                ->first();
        }

        if (! $tx && isset($object->id) && str_starts_with((string) $object->id, 'po_')) {
            $tx = TreasuryTransaction::query()
                ->where('stripe_payout_id', (string) $object->id)
                ->latest('id')
                ->first();
        }

        if ($tx) {
            $updates = [
                'payment_module' => 'stripe',
                'payment_provider' => 'Stripe',
                'stripe_last_event_id' => (string) $event->id,
            ];

            if ($eventType === 'checkout.session.completed') {
                $updates['stripe_checkout_session_id'] = (string) ($object->id ?? $tx->stripe_checkout_session_id);
                $updates['stripe_payment_intent_id'] = (string) ($object->payment_intent ?? $tx->stripe_payment_intent_id);
                $updates['stripe_status'] = (string) ($object->payment_status ?? 'paid');
                $updates['status'] = (($object->payment_status ?? '') === 'paid') ? 'effectue' : $tx->status;
                $updates['stripe_paid_at'] = (($object->payment_status ?? '') === 'paid') ? now() : $tx->stripe_paid_at;
                $updates['stripe_failure_reason'] = null;
            } elseif (in_array($eventType, ['checkout.session.expired', 'payment_intent.payment_failed', 'payment_intent.canceled'], true)) {
                $updates['stripe_status'] = $eventType;
                $updates['status'] = 'annule';
                $updates['stripe_failure_reason'] = 'Échec/annulation confirmé(e) par Stripe: '.$eventType;
            } elseif ($eventType === 'payment_intent.succeeded') {
                $updates['stripe_payment_intent_id'] = (string) ($object->id ?? $tx->stripe_payment_intent_id);
                $updates['stripe_status'] = 'paid';
                $updates['status'] = 'effectue';
                $updates['stripe_paid_at'] = now();
                $updates['stripe_failure_reason'] = null;
            } elseif (in_array($eventType, ['payout.created', 'payout.updated'], true)) {
                $updates['stripe_payout_id'] = (string) ($object->id ?? $tx->stripe_payout_id);
                $updates['stripe_status'] = (string) ($object->status ?? 'pending');
                $updates['status'] = 'planifie';
            } elseif ($eventType === 'payout.paid') {
                $updates['stripe_payout_id'] = (string) ($object->id ?? $tx->stripe_payout_id);
                $updates['stripe_status'] = 'paid';
                $updates['status'] = 'effectue';
                $updates['stripe_failure_reason'] = null;
                $updates['stripe_paid_at'] = now();
            } elseif (in_array($eventType, ['payout.failed', 'payout.canceled'], true)) {
                $updates['stripe_payout_id'] = (string) ($object->id ?? $tx->stripe_payout_id);
                $updates['stripe_status'] = $eventType;
                $updates['status'] = 'annule';
                $updates['stripe_failure_reason'] = 'Décaissement Stripe non abouti: '.$eventType;
            }

            $tx->update($updates);
        }

        return response('ok', Response::HTTP_OK);
    }

    /**
     * Supprime une transaction
     */
    public function destroy(TreasuryTransaction $transaction)
    {
        $this->authorize('own', $transaction);

        $workspaceOwnerId = $this->workspaceUserId();
        $this->assertTreasuryPeriodUnlocked($workspaceOwnerId, $transaction->transaction_date);

        $snapshot = $transaction->only(['id', 'type', 'amount', 'transaction_date', 'description', 'status']);
        $transaction->delete();

        TreasuryAudit::log($workspaceOwnerId, 'treasury.transaction.delete', null, [
            'deleted' => $snapshot,
        ]);

        return redirect()->route('treasury.index')->with('success', 'Transaction supprimée avec succès.');
    }

    /**
     * Calcul des projections hebdomadaires.
     *
     * @param  bool  $forecastOnly  Si vrai, n'intègre que les transactions planifiées (prévision fiable).
     */
    private function calculateWeeklyProjections(array $userIds, Carbon $start, Carbon $end, bool $forecastOnly = false)
    {
        $balance = (float) TreasuryTransaction::whereIn('user_id', $userIds)
            ->effectuees()
            ->sum(DB::raw("CASE WHEN type = 'encaissement' THEN amount ELSE -amount END"));

        $projections = [];
        $current = $start->copy();

        while ($current <= $end) {
            $weekEnd = $current->copy()->endOfWeek();
            if ($weekEnd->greaterThan($end)) {
                $weekEnd = $end->copy();
            }

            $query = TreasuryTransaction::whereIn('user_id', $userIds)->byDateRange($current, $weekEnd);
            if ($forecastOnly) {
                $query->planifiees();
            }
            $weekTransactions = $query->get();

            $weekTotal = (float) $weekTransactions
                ->sum(fn ($t) => $t->type === 'encaissement' ? (float) $t->amount : -(float) $t->amount);

            $balance += $weekTotal;

            $projections[] = [
                'week' => $current->format('d/m').' - '.$weekEnd->format('d/m'),
                'balance' => $balance,
                'inflow' => (float) $weekTransactions->where('type', 'encaissement')->sum('amount'),
                'outflow' => (float) $weekTransactions->where('type', 'decaissement')->sum('amount'),
            ];

            $current = $weekEnd->copy()->addDay()->startOfDay();
        }

        return $projections;
    }

    /**
     * Mesure la fiabilité des prévisions sur les dernières semaines.
     */
    private function calculateForecastAccuracy(array $userIds, Carbon $endDate, int $weeks = 8): array
    {
        $rows = [];
        $totalAbsoluteError = 0.0;
        $totalPlanned = 0.0;
        $alertWeeks = 0;

        // On remonte de N semaines complètes pour comparer planifié vs effectué.
        $cursor = $endDate->copy()->startOfWeek()->subWeeks($weeks - 1);
        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $cursor->copy()->startOfWeek();
            $weekEnd = $cursor->copy()->endOfWeek();

            $plannedRows = TreasuryTransaction::whereIn('user_id', $userIds)
                ->planifiees()
                ->byDateRange($weekStart, $weekEnd)
                ->get(['type', 'amount']);

            $actualRows = TreasuryTransaction::whereIn('user_id', $userIds)
                ->effectuees()
                ->byDateRange($weekStart, $weekEnd)
                ->get(['type', 'amount']);

            $plannedNet = (float) $plannedRows
                ->sum(fn ($tx) => $tx->type === 'encaissement' ? (float) $tx->amount : -(float) $tx->amount);
            $actualNet = (float) $actualRows
                ->sum(fn ($tx) => $tx->type === 'encaissement' ? (float) $tx->amount : -(float) $tx->amount);

            $gap = $actualNet - $plannedNet;
            $absError = abs($gap);
            $denominator = max(1.0, abs($plannedNet));
            $errorPct = $plannedNet == 0.0 ? null : round(($absError / $denominator) * 100, 1);

            // Alerte si écart > 30%.
            if ($errorPct !== null && $errorPct > 30) {
                $alertWeeks++;
            }

            $totalAbsoluteError += $absError;
            $totalPlanned += abs($plannedNet);

            $rows[] = [
                'week' => $weekStart->format('d/m').' - '.$weekEnd->format('d/m'),
                'planned' => $plannedNet,
                'actual' => $actualNet,
                'gap' => $gap,
                'errorPct' => $errorPct,
            ];

            $cursor->addWeek();
        }

        $mape = $totalPlanned > 0 ? ($totalAbsoluteError / $totalPlanned) * 100 : 0.0;
        $score = max(0.0, min(100.0, round(100 - $mape, 1)));
        $totalGap = array_sum(array_column($rows, 'gap'));

        return [
            'rows' => $rows,
            'score' => $score,
            'totalGap' => (float) $totalGap,
            'alertWeeks' => $alertWeeks,
        ];
    }

    /**
     * Récupère les données pour les graphiques principaux
     */
    private function getChartData(array $userIds)
    {
        $startDate = now()->subDays(30);
        $endDate = now();

        $transactions = TreasuryTransaction::whereIn('user_id', $userIds)
            ->effectuees()
            ->byDateRange($startDate, $endDate)
            ->orderBy('transaction_date')
            ->get()
            ->groupBy(function ($item) {
                return $item->transaction_date->format('Y-m-d');
            });

        $labels = [];
        $encaissementsData = [];
        $decaissementsData = [];

        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dateKey = $date->format('Y-m-d');
            $dayTransactions = $transactions->get($dateKey, collect());

            $labels[] = $date->format('d M');
            $encaissementsData[] = $dayTransactions->where('type', 'encaissement')->sum('amount');
            $decaissementsData[] = $dayTransactions->where('type', 'decaissement')->sum('amount');
        }

        return [
            'chartLabels' => $labels,
            'encaissementsData' => $encaissementsData,
            'decaissementsData' => $decaissementsData,
        ];
    }

    /**
     * Récupère les données de répartition par catégorie
     */
    private function getCategoryData(array $userIds)
    {
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $categories = TreasuryTransaction::whereIn('user_id', $userIds)
            ->effectuees()
            ->byDateRange($monthStart, $monthEnd)
            ->selectRaw('transaction_type, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('transaction_type')
            ->orderByDesc('total')
            ->get();

        return [
            'categoryLabels' => $categories->pluck('transaction_type'),
            'categoryData' => $categories->pluck('total'),
            'topCategories' => $categories->take(5),
        ];
    }

    /**
     * Récupère les données de tendances
     */
    private function getTrendsData(array $userIds)
    {
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();
        $lastMonthEnd = now()->subMonth()->endOfMonth();

        $thisMonthEncaissements = TreasuryTransaction::whereIn('user_id', $userIds)
            ->encaissements()
            ->effectuees()
            ->where('transaction_date', '>=', $thisMonth)
            ->sum('amount');

        $lastMonthEncaissements = TreasuryTransaction::whereIn('user_id', $userIds)
            ->encaissements()
            ->effectuees()
            ->byDateRange($lastMonth, $lastMonthEnd)
            ->sum('amount');

        $thisMonthDecaissements = TreasuryTransaction::whereIn('user_id', $userIds)
            ->decaissements()
            ->effectuees()
            ->where('transaction_date', '>=', $thisMonth)
            ->sum('amount');

        $lastMonthDecaissements = TreasuryTransaction::whereIn('user_id', $userIds)
            ->decaissements()
            ->effectuees()
            ->byDateRange($lastMonth, $lastMonthEnd)
            ->sum('amount');

        $encaissementTrend = $lastMonthEncaissements > 0
            ? (($thisMonthEncaissements - $lastMonthEncaissements) / $lastMonthEncaissements) * 100
            : 0;

        $decaissementTrend = $lastMonthDecaissements > 0
            ? (($thisMonthDecaissements - $lastMonthDecaissements) / $lastMonthDecaissements) * 100
            : 0;

        return [
            'encaissementTrend' => round($encaissementTrend, 1),
            'decaissementTrend' => round($decaissementTrend, 1),
        ];
    }

    /**
     * Récupère l'activité récente
     */
    private function getRecentActivity(array $userIds)
    {
        return TreasuryTransaction::whereIn('user_id', $userIds)
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->take(5)
            ->get(['id', 'type', 'amount', 'description', 'transaction_date', 'status', 'created_at']);
    }

    /**
     * Exporte les transactions en CSV
     */
    public function exportCsv(Request $request)
    {
        $userIds = $this->workspaceDataUserIds();

        $transactions = TreasuryTransaction::whereIn('user_id', $userIds)
            ->orderByDesc('transaction_date')
            ->get();

        $filename = 'tresorerie_'.date('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($transactions) {
            $file = fopen('php://output', 'w');

            // En-tête CSV
            fputcsv($file, [
                'Date', 'Type', 'Catégorie', 'Description', 'Montant', 'Référence',
                'Compte bancaire', 'Statut', 'Notes',
            ], ';');

            // Données
            foreach ($transactions as $tx) {
                fputcsv($file, [
                    $tx->transaction_date->format('d/m/Y'),
                    $tx->type === 'encaissement' ? 'Encaissement' : 'Décaissement',
                    $tx->transaction_type,
                    $tx->description,
                    number_format($tx->amount, 2, ',', ' '),
                    $tx->reference ?? '',
                    $tx->bank_account ?? '',
                    ucfirst($tx->status),
                    $tx->notes ?? '',
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Génère un rapport PDF
     */
    public function generatePdfReport(Request $request)
    {
        $userIds = $this->workspaceDataUserIds();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        // Données pour le rapport
        $transactions = TreasuryTransaction::whereIn('user_id', $userIds)
            ->byDateRange($monthStart, $monthEnd)
            ->orderBy('transaction_date')
            ->get();

        $totalEncaissements = $transactions->where('type', 'encaissement')->sum('amount');
        $totalDecaissements = $transactions->where('type', 'decaissement')->sum('amount');
        $solde = $totalEncaissements - $totalDecaissements;

        // Utiliser DomPDF pour générer le PDF
        $pdf = Pdf::loadView('treasury.reports.monthly', [
            'transactions' => $transactions,
            'totalEncaissements' => $totalEncaissements,
            'totalDecaissements' => $totalDecaissements,
            'solde' => $solde,
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
        ]);

        return $pdf->download('rapport_tresorerie_'.date('Y-m').'.pdf');
    }

    /**
     * Récupère les données du dashboard pour l'API
     */
    public function getDashboardData(Request $request)
    {
        $userIds = $this->workspaceDataUserIds();
        $period = $request->get('period', 'month');

        $data = [
            'chartData' => $this->getChartData($userIds),
            'categoryData' => $this->getCategoryData($userIds),
            'trendsData' => $this->getTrendsData($userIds),
            'summary' => [
                'totalEncaissements' => TreasuryTransaction::whereIn('user_id', $userIds)
                    ->encaissements()->effectuees()->sum('amount'),
                'totalDecaissements' => TreasuryTransaction::whereIn('user_id', $userIds)
                    ->decaissements()->effectuees()->sum('amount'),
                'solde' => TreasuryTransaction::whereIn('user_id', $userIds)
                    ->effectuees()->sum(DB::raw("CASE WHEN type = 'encaissement' THEN amount ELSE -amount END")),
            ],
        ];

        return response()->json($data);
    }

    /**
     * Empêche toute écriture si le mois civil est verrouillé pour la trésorerie.
     */
    private function assertTreasuryPeriodUnlocked(int $userId, $transactionDate): void
    {
        $month = Carbon::parse($transactionDate)->format('Y-m');
        $locked = TreasuryPeriodLock::where('user_id', $userId)
            ->where('period_month', $month)
            ->exists();

        if ($locked) {
            abort(403, 'Période verrouillée : aucune modification de trésorerie n\'est autorisée pour ce mois.');
        }
    }

    private function normalizePaymentFields(array $validated): array
    {
        if (($validated['payment_module'] ?? 'stripe') === 'fedapay_mobile') {
            $validated['payment_provider'] = 'FedaPay';
            $validated['mobile_method'] = $validated['mobile_method'] ?? 'wave';
            unset($validated['fedapay_country']);
            $validated['stripe_payment_channel'] = null;
            $validated['stripe_bank_scheme'] = null;
            $validated['card_network'] = null;
            $validated['card_last4'] = null;
            $validated['bank_name'] = null;
            $validated['stripe_checkout_session_id'] = null;
            $validated['stripe_payment_intent_id'] = null;
            $validated['stripe_charge_id'] = null;
            $validated['stripe_payout_id'] = null;
            $validated['stripe_status'] = null;
            $validated['stripe_failure_reason'] = null;
            $validated['stripe_paid_at'] = null;
            $validated['stripe_last_event_id'] = null;

            return $validated;
        }

        $validated['payment_module'] = 'stripe';
        $validated['payment_provider'] = 'Stripe';
        unset($validated['fedapay_country']);

        if (($validated['stripe_payment_channel'] ?? '') !== 'bank_debit') {
            $validated['stripe_bank_scheme'] = null;
        } elseif (empty($validated['stripe_bank_scheme'])) {
            $validated['stripe_bank_scheme'] = 'sepa';
        }

        $validated['mobile_method'] = null;
        $validated['mobile_number'] = null;
        $validated['card_network'] = null;
        $validated['card_last4'] = null;
        $validated['bank_name'] = null;
        $validated['bank_reference'] = null;

        return $validated;
    }

    private function ensureTrackingReference(array $validated, ?TreasuryTransaction $existing = null): array
    {
        if (! empty($validated['reference'])) {
            return $validated;
        }

        if ($existing && ! empty($existing->reference)) {
            $validated['reference'] = $existing->reference;

            return $validated;
        }

        $datePart = Carbon::parse($validated['transaction_date'] ?? now())->format('Ymd');
        $validated['reference'] = 'TRS-'.$datePart.'-'.Str::upper(Str::random(6));

        return $validated;
    }

    private function maybeStartProviderCheckout(
        Request $request,
        TreasuryTransaction $transaction,
        StripeTreasuryService $stripe
    ): ?RedirectResponse {
        if ($transaction->payment_module === 'fedapay_mobile') {
            return $this->maybeStartFedaPayCheckout($request, $transaction);
        }

        return $this->maybeStartStripeCheckout($request, $transaction, $stripe);
    }

    private function maybeStartFedaPayCheckout(
        Request $request,
        TreasuryTransaction $transaction
    ): ?RedirectResponse {
        if ($transaction->payment_module !== 'fedapay_mobile' || $transaction->status !== 'effectue') {
            return null;
        }

        $country = strtoupper((string) $request->input('fedapay_country', 'CIV'));

        return redirect()->route('treasury.fedapay.checkout.form', [
            'transaction' => $transaction->id,
            'country' => $country,
        ]);
    }

    private function resolveFedapayCurrency(string $country): string
    {
        return in_array($country, ['CMR'], true) ? 'XAF' : 'XOF';
    }

    private function resolveFedapayCorrespondentCandidates(string $country, string $method): array
    {
        $countryGeneric = match ($country) {
            'SEN' => 'mobile_money_sen',
            'BEN', 'TGO' => 'mobile_money_ben',
            'CMR' => 'mobile_money_cmr',
            default => 'mobile_money_ci',
        };

        $method = strtolower($method);
        $candidates = match ($method) {
            'wave' => in_array($country, ['CIV', 'SEN'], true)
                ? ['wave', 'WAVE', $countryGeneric]
                : [$countryGeneric],
            'mtn_money' => ['MTN_MOMO', $countryGeneric],
            'orange_money' => ['ORANGE_MONEY', $countryGeneric],
            'moov_money' => ['MOOV_MONEY', $countryGeneric],
            default => [$countryGeneric],
        };

        return array_values(array_unique(array_filter($candidates, fn ($value) => is_string($value) && trim($value) !== '')));
    }

    private function extractFedapayErrorDetail($responsePayload): string
    {
        if (! is_array($responsePayload)) {
            return '';
        }

        $candidates = [
            (string) ($responsePayload['message'] ?? ''),
            (string) ($responsePayload['error'] ?? ''),
            (string) ($responsePayload['errors'][0]['message'] ?? ''),
            (string) ($responsePayload['errors'][0]['description'] ?? ''),
        ];

        foreach ($candidates as $detail) {
            $detail = trim($detail);
            if ($detail !== '') {
                return $detail;
            }
        }

        return '';
    }

    private function splitUserDisplayName(string $displayName): array
    {
        $displayName = trim($displayName);
        if ($displayName === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $displayName) ?: [];
        if (count($parts) === 1) {
            return [$parts[0], $parts[0]];
        }

        $lastName = array_shift($parts) ?: '';
        $firstName = trim(implode(' ', $parts));

        return [$lastName, $firstName];
    }

    private function buildFedapayHostedPaymentUrl(string $baseUrl, $user, string $country, string $mobileNumber): string
    {
        [$lastName, $firstName] = $this->splitUserDisplayName((string) ($user->name ?? ''));
        $params = [
            'lastname' => $lastName,
            'firstname' => $firstName,
            'email' => (string) ($user->email ?? ''),
            'country' => strtoupper($country),
            'phone_number' => $mobileNumber,
            'phone' => $mobileNumber,
            'msisdn' => $mobileNumber,
        ];

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl.$separator.http_build_query(array_filter($params, fn ($value) => trim((string) $value) !== ''));
    }

    private function maybeStartStripeCheckout(Request $request, TreasuryTransaction $transaction, StripeTreasuryService $stripe): ?RedirectResponse
    {
        if ($transaction->payment_module !== 'stripe' || $transaction->status !== 'effectue') {
            return null;
        }

        if (! $stripe->enabled()) {
            return redirect()->route('treasury.tracking')
                ->withErrors(['stripe' => 'Stripe n’est pas activé. Renseignez STRIPE_ENABLED/STRIPE_SECRET.']);
        }

        if ($transaction->type === 'decaissement') {
            if ((string) $transaction->stripe_payment_channel !== 'bank_debit') {
                return redirect()->route('treasury.tracking')
                    ->withErrors(['stripe' => 'Pour un décaissement Stripe, sélectionnez "Virements bancaires (ACH / SEPA)".']);
            }

            try {
                $transaction->update([
                    'status' => 'planifie',
                    'stripe_status' => 'payout_pending',
                    'stripe_failure_reason' => null,
                ]);

                $payout = $stripe->createPayout($transaction);

                $transaction->update([
                    'stripe_payout_id' => $payout['id'],
                    'stripe_status' => $payout['status'] ?: 'pending',
                    'payment_provider' => 'Stripe',
                    'status' => in_array($payout['status'], ['paid', 'in_transit'], true) ? 'effectue' : 'planifie',
                ]);

                TreasuryAudit::log($this->workspaceUserId(), 'treasury.transaction.stripe.payout', $transaction, [
                    'stripe_payout_id' => $payout['id'],
                    'stripe_payout_status' => $payout['status'],
                    'stripe_payout_currency' => $payout['currency'] ?? null,
                    'amount' => (string) $transaction->amount,
                ]);

                return redirect()->route('treasury.tracking')
                    ->with('status', 'Décaissement Stripe lancé. ID payout: '.$payout['id'].' | Devise: '.strtoupper((string) ($payout['currency'] ?? 'n/a')));
            } catch (\Throwable $e) {
                if ($this->isStripeExternalAccountMissingError($e)) {
                    $transaction->update([
                        'stripe_status' => 'payout_manual_required',
                        'stripe_failure_reason' => $e->getMessage(),
                        'status' => 'planifie',
                    ]);

                    TreasuryAudit::log($this->workspaceUserId(), 'treasury.transaction.stripe.payout.manual_required', $transaction, [
                        'reason' => $e->getMessage(),
                    ]);

                    return redirect()->route('treasury.tracking')
                        ->with('status', 'Décaissement Stripe planifié en manuel : ajoutez un compte externe payout dans Stripe, puis relancez la transaction.');
                }

                if ($this->isStripeInsufficientFundsError($e)) {
                    $transaction->update([
                        'stripe_status' => 'payout_insufficient_funds',
                        'stripe_failure_reason' => $e->getMessage(),
                        'status' => 'planifie',
                    ]);

                    TreasuryAudit::log($this->workspaceUserId(), 'treasury.transaction.stripe.payout.insufficient_funds', $transaction, [
                        'reason' => $e->getMessage(),
                    ]);

                    return redirect()->route('treasury.tracking')
                        ->with('status', 'Décaissement Stripe planifié : solde insuffisant. Approvisionnez Stripe puis relancez la transaction.');
                }

                $transaction->update([
                    'stripe_status' => 'payout_error',
                    'stripe_failure_reason' => $e->getMessage(),
                    'status' => 'planifie',
                ]);

                return redirect()->route('treasury.tracking')
                    ->withErrors(['stripe' => 'Création du décaissement Stripe impossible : '.$e->getMessage()]);
            }
        }

        try {
            $transaction->update([
                'status' => 'planifie',
                'stripe_status' => 'checkout_pending',
                'stripe_failure_reason' => null,
            ]);

            $session = $stripe->createCheckoutSession($transaction, $request->user(), [
                'success_url' => route('treasury.stripe.success', $transaction),
                'cancel_url' => route('treasury.stripe.cancel', $transaction),
            ]);

            $transaction->update([
                'stripe_checkout_session_id' => $session['id'],
                'stripe_status' => $session['payment_status'] ?: 'checkout_created',
                'payment_provider' => 'Stripe',
            ]);

            TreasuryAudit::log($this->workspaceUserId(), 'treasury.transaction.stripe.checkout', $transaction, [
                'stripe_session_id' => $session['id'],
                'amount' => (string) $transaction->amount,
            ]);

            return redirect()->away($session['url']);
        } catch (\Throwable $e) {
            $transaction->update([
                'stripe_status' => 'checkout_error',
                'stripe_failure_reason' => $e->getMessage(),
                'status' => 'planifie',
            ]);

            return redirect()->route('treasury.tracking')
                ->withErrors(['stripe' => 'Création du paiement Stripe impossible : '.$e->getMessage()]);
        }
    }

    private function isStripeExternalAccountMissingError(\Throwable $e): bool
    {
        $message = strtolower((string) $e->getMessage());

        return str_contains($message, 'no external accounts')
            || str_contains($message, 'external accounts in that currency');
    }

    private function isStripeInsufficientFundsError(\Throwable $e): bool
    {
        $message = strtolower((string) $e->getMessage());

        return str_contains($message, 'insufficient funds')
            || str_contains($message, 'balance is too low');
    }
}
