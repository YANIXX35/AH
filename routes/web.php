<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminEnterpriseLicenseController;
use App\Http\Controllers\AdminPlatformLogController;
use App\Http\Controllers\AdminPaymentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\AdminFinancialAnalysisController;
use App\Http\Controllers\AdminInvestmentRequestController;
use App\Http\Controllers\CompanyFirdController;
use App\Http\Controllers\EnterpriseTeamController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\InAppNotificationController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\AccountantClientController;
use App\Http\Controllers\AccountantDashboardController;
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AccountingDocumentController;
use App\Http\Controllers\AccountingDocumentViewerController;
use App\Http\Controllers\InvestorController;
use App\Http\Controllers\MenuActivityLogController;
use App\Http\Controllers\FedaPaySandboxController;
use App\Http\Controllers\TreasuryController;
use App\Models\AccountingDocument;
use App\Models\AccountingEntry;
use App\Models\PaymentTransaction;
use App\Models\SubscriptionHistory;
use App\Models\TreasuryTransaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Support\ClientWorkspace;

Route::get('/', function () {
    return Auth::check() ? redirect()->route('dashboard') : view('welcome');
})->name('home');
Route::view('/about-us', 'about-us')->name('about-us');
Route::view('/tarifs', 'pricing')->name('pricing');
Route::view('/documentation', 'documentation')->name('documentation');
Route::get('/payments/sandbox/callback', [FedaPaySandboxController::class, 'callback'])->name('payments.sandbox.callback');
Route::post('/payments/stripe/webhook', [TreasuryController::class, 'stripeWebhook'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->name('payments.stripe.webhook');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::view('/register', 'register')->name('register');

    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetOtp'])->name('password.otp.send');
    Route::get('/reset-password', [AuthController::class, 'showResetPassword'])->name('password.reset.form');
    Route::post('/reset-password', [AuthController::class, 'resetPasswordWithOtp'])->name('password.otp.reset');

    Route::post('/register', [RegisterController::class, 'store'])->name('register.post');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function (Request $request) {
        $userId = ClientWorkspace::effectiveUserId();
        $userIds = ClientWorkspace::dataScopeUserIds($request->user());
        $today = Carbon::today();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth();

        $entriesQuery = AccountingEntry::whereIn('user_id', $userIds);
        $documentsQuery = AccountingDocument::whereIn('user_id', $userIds);
        $treasuryQuery = TreasuryTransaction::whereIn('user_id', $userIds);

        $accountingEntriesCount = (clone $entriesQuery)->count();
        $accountingMonthlyAmount = (clone $entriesQuery)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');
        $yearStart = $today->copy()->startOfYear();
        $yearEnd = $today->copy()->endOfYear();

        // Chiffre d'affaires comptable (produits classe 7, net crédit - débit).
        $turnoverMonth = (float) AccountingEntry::whereIn('user_id', $userIds)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum(DB::raw("
                CASE
                    WHEN LTRIM(credit_account) LIKE '7%' THEN amount
                    WHEN LTRIM(debit_account) LIKE '7%' THEN -amount
                    ELSE 0
                END
            "));
        $turnoverYear = (float) AccountingEntry::whereIn('user_id', $userIds)
            ->whereBetween('date', [$yearStart, $yearEnd])
            ->sum(DB::raw("
                CASE
                    WHEN LTRIM(credit_account) LIKE '7%' THEN amount
                    WHEN LTRIM(debit_account) LIKE '7%' THEN -amount
                    ELSE 0
                END
            "));

        // Série CA : 12 derniers mois civils (pour graphique).
        $turnoverChartLabels = [];
        $turnoverChartData = [];
        for ($i = 11; $i >= 0; $i--) {
            $mStart = $today->copy()->subMonths($i)->startOfMonth();
            $mEnd = $mStart->copy()->endOfMonth();
            $turnoverChartLabels[] = $mStart->locale('fr')->translatedFormat('M Y');
            $caMonth = (float) AccountingEntry::whereIn('user_id', $userIds)
                ->whereBetween('date', [$mStart->toDateString(), $mEnd->toDateString()])
                ->sum(DB::raw("
                    CASE
                        WHEN LTRIM(credit_account) LIKE '7%' THEN amount
                        WHEN LTRIM(debit_account) LIKE '7%' THEN -amount
                        ELSE 0
                    END
                "));
            // Conserver le signe (avoirs / régularisations) pour cohérence avec le grand livre.
            $turnoverChartData[] = $caMonth;
        }

        $documentsCount = (clone $documentsQuery)->count();
        $pendingDocumentsCount = (clone $documentsQuery)
            ->whereIn('status', ['pending', 'pending_validation', 'ocr_failed'])
            ->count();

        $encaissementsTotal = (clone $treasuryQuery)
            ->where('type', 'encaissement')
            ->where('status', 'effectue')
            ->sum('amount');
        $decaissementsTotal = (clone $treasuryQuery)
            ->where('type', 'decaissement')
            ->where('status', 'effectue')
            ->sum('amount');
        $soldeActuel = $encaissementsTotal - $decaissementsTotal;

        $monthlyTreasury = TreasuryTransaction::whereIn('user_id', $userIds)
            ->where('status', 'effectue')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth]);
        $encaissementsMonth = (clone $monthlyTreasury)->where('type', 'encaissement')->sum('amount');
        $decaissementsMonth = (clone $monthlyTreasury)->where('type', 'decaissement')->sum('amount');

        $plannedTreasury = TreasuryTransaction::whereIn('user_id', $userIds)
            ->where('status', 'planifie');
        $encaissementsPlanifies = (clone $plannedTreasury)->where('type', 'encaissement')->sum('amount');
        $decaissementsPlanifies = (clone $plannedTreasury)->where('type', 'decaissement')->sum('amount');
        $soldeProjete = $soldeActuel + $encaissementsPlanifies - $decaissementsPlanifies;

        $latestEntries = AccountingEntry::whereIn('user_id', $userIds)
            ->latest('date')
            ->take(5)
            ->get();
        $latestTransactions = TreasuryTransaction::whereIn('user_id', $userIds)
            ->latest('transaction_date')
            ->take(5)
            ->get();
        $recentDocuments = AccountingDocument::whereIn('user_id', $userIds)
            ->latest()
            ->take(5)
            ->get();

        // Séries pour graphiques (6 derniers mois civils)
        $chartLabels = [];
        $treasuryEncChart = [];
        $treasuryDecChart = [];
        $accountingBarChart = [];
        for ($i = 5; $i >= 0; $i--) {
            $mStart = $today->copy()->subMonths($i)->startOfMonth();
            $mEnd = $mStart->copy()->endOfMonth();
            $chartLabels[] = $mStart->locale('fr')->translatedFormat('M Y');
            $treasuryEncChart[] = (float) TreasuryTransaction::whereIn('user_id', $userIds)
                ->where('status', 'effectue')
                ->where('type', 'encaissement')
                ->whereBetween('transaction_date', [$mStart->toDateString(), $mEnd->toDateString()])
                ->sum('amount');
            $treasuryDecChart[] = (float) TreasuryTransaction::whereIn('user_id', $userIds)
                ->where('status', 'effectue')
                ->where('type', 'decaissement')
                ->whereBetween('transaction_date', [$mStart->toDateString(), $mEnd->toDateString()])
                ->sum('amount');
            $accountingBarChart[] = (float) AccountingEntry::whereIn('user_id', $userIds)
                ->whereBetween('date', [$mStart->toDateString(), $mEnd->toDateString()])
                ->sum('amount');
        }

        // Répartition OCR (écritures) pour graphique en anneau
        $ocrRows = AccountingEntry::whereIn('user_id', $userIds)
            ->select('ocr_status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('ocr_status')
            ->orderByDesc('cnt')
            ->get();
        $ocrLabelMap = [
            'verified' => 'Vérifié',
            'manual_verified' => 'Vérifié manuellement',
            'mismatch' => 'Incohérences',
            'mismatched' => 'Montant incohérent',
            'failed' => 'Erreur OCR',
            'pending' => 'En attente / sans fichier',
        ];
        $ocrColorMap = [
            'verified' => '#22c55e',
            'manual_verified' => '#16a34a',
            'mismatch' => '#f59e0b',
            'mismatched' => '#f97316',
            'failed' => '#ef4444',
            'pending' => '#6b7280',
        ];
        $ocrDonutLabels = [];
        $ocrDonutData = [];
        $ocrDonutColors = [];
        foreach ($ocrRows as $row) {
            $key = $row->ocr_status ?? 'pending';
            $ocrDonutLabels[] = $ocrLabelMap[$key] ?? (string) $key;
            $ocrDonutData[] = (int) $row->cnt;
            $ocrDonutColors[] = $ocrColorMap[$key] ?? '#94a3b8';
        }

        return view('dashboard', [
            'accountingEntriesCount' => $accountingEntriesCount,
            'accountingMonthlyAmount' => $accountingMonthlyAmount,
            'turnoverMonth' => max(0, $turnoverMonth),
            'turnoverYear' => max(0, $turnoverYear),
            'turnoverChartLabels' => $turnoverChartLabels,
            'turnoverChartData' => $turnoverChartData,
            'documentsCount' => $documentsCount,
            'pendingDocumentsCount' => $pendingDocumentsCount,
            'encaissementsTotal' => $encaissementsTotal,
            'decaissementsTotal' => $decaissementsTotal,
            'soldeActuel' => $soldeActuel,
            'encaissementsMonth' => $encaissementsMonth,
            'decaissementsMonth' => $decaissementsMonth,
            'soldeProjete' => $soldeProjete,
            'encaissementsPlanifies' => $encaissementsPlanifies,
            'decaissementsPlanifies' => $decaissementsPlanifies,
            'latestEntries' => $latestEntries,
            'latestTransactions' => $latestTransactions,
            'recentDocuments' => $recentDocuments,
            'currentMonthLabel' => $startOfMonth->translatedFormat('F Y'),
            'chartLabels' => $chartLabels,
            'treasuryEncChart' => $treasuryEncChart,
            'treasuryDecChart' => $treasuryDecChart,
            'accountingBarChart' => $accountingBarChart,
            'ocrDonutLabels' => $ocrDonutLabels,
            'ocrDonutData' => $ocrDonutData,
            'ocrDonutColors' => $ocrDonutColors,
        ]);
    })->name('dashboard');

    Route::get('/profile', function (Request $request) {
        return view('profile', [
            'user' => $request->user(),
            'recentSandboxPayments' => PaymentTransaction::query()
                ->where('user_id', $request->user()->id)
                ->where('provider', 'fedapay_sandbox')
                ->latest()
                ->limit(5)
                ->get(),
            'isFedaPaySandboxEnabled' => (bool) config('services.fedapay.sandbox.enabled', false),
        ]);
    })->name('profile');

    Route::get('/profile/entreprise/fird', [CompanyFirdController::class, 'edit'])->name('profile.company.fird');
    Route::post('/profile/entreprise/fird', [CompanyFirdController::class, 'update'])->name('profile.company.fird.update');

    Route::get('/profile/equipe', [EnterpriseTeamController::class, 'index'])->name('profile.team');
    Route::post('/profile/equipe', [EnterpriseTeamController::class, 'store'])->name('profile.team.store');
    Route::get('/profile/equipe/historique', [EnterpriseTeamController::class, 'history'])->name('profile.team.history');

    Route::post('/profile', function (Request $request) {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:255'],
            'sector' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_sigle' => ['nullable', 'string', 'max:255'],
            'company_tax_id' => ['nullable', 'string', 'max:255'],
            'company_logo' => ['nullable', 'image', 'max:5120'],
            'avatar' => ['nullable', 'image', 'max:5120'],
            'timezone' => ['required', 'string', 'max:64'],
            'locale' => ['required', 'in:fr,en'],
            'currency' => ['required', 'in:XOF,EUR,USD'],
            'email_notifications' => ['nullable', 'boolean'],
            'weekly_digest' => ['nullable', 'boolean'],
            'marketing_emails' => ['nullable', 'boolean'],
            'two_factor_enabled' => ['nullable', 'boolean'],
            'password' => ['nullable', 'confirmed', 'min:8'],
        ]);

        if ($request->hasFile('company_logo')) {
            if ($user->company_logo) {
                Storage::disk('public')->delete($user->company_logo);
            }

            $validated['company_logo'] = $request->file('company_logo')->store('company-logos', 'public');
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = Hash::make($validated['password']);
        }

        $validated['email_notifications'] = $request->boolean('email_notifications');
        $validated['weekly_digest'] = $request->boolean('weekly_digest');
        $validated['marketing_emails'] = $request->boolean('marketing_emails');
        $validated['two_factor_enabled'] = $request->boolean('two_factor_enabled');

        $user->update($validated);

        return back()->with('status', 'Profil mis à jour.');
    })->name('profile.update');

    Route::post('/profile/subscription/simulate', function (Request $request) {
        $user = $request->user();
        if ($user->is_platform_admin) {
            return back()->withErrors([
                'subscription' => 'Les administrateurs plateforme ne sont pas concernés par les offres Gratuit / Premium.',
            ]);
        }
        $previousStatus = $user->premium_status ?? 'free';
        $previousEndsAt = $user->premium_ends_at;
        $validated = $request->validate([
            'plan_type' => ['required', 'in:free,trial,premium'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $durationDays = (int) ($validated['duration_days'] ?? 30);

        if ($validated['plan_type'] === 'free') {
            $user->update([
                'is_premium' => false,
                'premium_status' => 'free',
                'premium_trial_ends_at' => null,
                'premium_ends_at' => null,
            ]);

            SubscriptionHistory::create([
                'user_id' => $user->id,
                'from_status' => $previousStatus,
                'to_status' => 'free',
                'is_premium' => false,
                'starts_at' => now(),
                'ends_at' => null,
                'source' => 'simulation_profile',
                'note' => 'Passage manuel en version gratuite (simulation).',
                'meta' => [
                    'previous_premium_ends_at' => optional($previousEndsAt)?->toDateTimeString(),
                ],
            ]);
            return back()->with('status', 'Simulation appliquée : compte en version gratuite.');
        }

        if ($validated['plan_type'] === 'trial') {
            $trialEnds = now()->addDays($durationDays);
            $user->update([
                'is_premium' => true,
                'premium_status' => 'trialing',
                'premium_trial_ends_at' => $trialEnds,
                'premium_ends_at' => $trialEnds,
            ]);

            SubscriptionHistory::create([
                'user_id' => $user->id,
                'from_status' => $previousStatus,
                'to_status' => 'trialing',
                'is_premium' => true,
                'starts_at' => now(),
                'ends_at' => $trialEnds,
                'source' => 'simulation_profile',
                'note' => 'Activation d\'un essai gratuit via simulation.',
                'meta' => [
                    'duration_days' => $durationDays,
                    'previous_premium_ends_at' => optional($previousEndsAt)?->toDateTimeString(),
                ],
            ]);
            return back()->with('status', "Simulation appliquée : essai gratuit actif pour {$durationDays} jour(s).");
        }

        $premiumEnds = now()->addDays($durationDays);
        $user->update([
            'is_premium' => true,
            'premium_status' => 'active',
            'premium_trial_ends_at' => null,
            'premium_ends_at' => $premiumEnds,
        ]);

        SubscriptionHistory::create([
            'user_id' => $user->id,
            'from_status' => $previousStatus,
            'to_status' => 'active',
            'is_premium' => true,
            'starts_at' => now(),
            'ends_at' => $premiumEnds,
            'source' => 'simulation_profile',
            'note' => 'Activation Premium via simulation.',
            'meta' => [
                'duration_days' => $durationDays,
                'previous_premium_ends_at' => optional($previousEndsAt)?->toDateTimeString(),
            ],
        ]);

        return back()->with('status', "Simulation appliquée : Premium actif pour {$durationDays} jour(s).");
    })->name('profile.subscription.simulate');
    Route::get('/payments/sandbox', [FedaPaySandboxController::class, 'index'])->name('payments.sandbox');
    Route::post('/payments/sandbox', [FedaPaySandboxController::class, 'store'])->name('payments.sandbox.store');
    Route::get('/payments/sandbox/result', [FedaPaySandboxController::class, 'result'])->name('payments.sandbox.result');
    Route::get('/payments/redirect', function (Request $request) {

        $paymentUrl = trim((string) $request->query('url', ''));
        if ($paymentUrl === '') {
            $paymentUrl = trim((string) config('services.fedapay.sandbox.payment_page_url', ''));
        }

        if ($paymentUrl === '' || ! filter_var($paymentUrl, FILTER_VALIDATE_URL)) {
            return redirect()
                ->route('pricing')
                ->withErrors([
                    'payment_redirect' => 'Lien de paiement FedaPay invalide ou non configuré.',
                ]);
        }

        return view('payments.redirect', [
            'paymentUrl' => $paymentUrl,
            'mobileMethods' => config('services.fedapay.sandbox.mobile_methods', []),
        ]);
    })->name('payments.redirect');
    Route::post('/profile/subscription/fedapay-sandbox', [FedaPaySandboxController::class, 'store'])->name('profile.subscription.fedapay-sandbox');
    Route::get('/profile/subscription/fedapay-sandbox', function () {
        return redirect()
            ->route('payments.sandbox')
            ->with('status', 'Utilisez la page dédiée de test FedaPay (méthode POST).');
    });
    Route::post('/profile/subscription/pawapay-sandbox', [FedaPaySandboxController::class, 'store'])->name('profile.subscription.pawapay-sandbox');
    Route::get('/profile/subscription/pawapay-sandbox', function () {
        return redirect()
            ->route('payments.sandbox')
            ->with('status', 'Le test pawaPay a été remplacé par FedaPay sandbox.');
    });

    Route::get('/profile/subscription/simulate', function () {
        return redirect()
            ->route('profile')
            ->with('status', 'Utilisez le formulaire de simulation (méthode POST) depuis la page Profil.');
    });

    Route::get('/subscriptions/history', function (Request $request) {
        $history = SubscriptionHistory::query()
            ->where('user_id', $request->user()->id)
            ->latest('created_at')
            ->paginate(15);

        return view('subscriptions.history', [
            'history' => $history,
        ]);
    })->name('subscriptions.history');

    Route::get('/activity/menu-log', [MenuActivityLogController::class, 'index'])->name('activity-log.index');

    Route::get('/documentation/synthese', [DocumentationController::class, 'downloadSynthese'])->name('documentation.synthese');
    Route::get('/support', [SupportController::class, 'index'])->name('support.index');
    Route::get('/support/tickets', [SupportController::class, 'tickets'])->name('support.tickets');
    Route::get('/support/tickets/create', [SupportController::class, 'create'])->name('support.tickets.create');
    Route::post('/support/tickets', [SupportController::class, 'store'])->name('support.tickets.store');
    Route::get('/support/tickets/{ticket}', [SupportController::class, 'show'])->name('support.tickets.show');
    Route::post('/support/tickets/{ticket}/messages', [SupportController::class, 'storeMessage'])->name('support.tickets.messages.store');

    Route::get('/notifications', [InAppNotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{notification}/go', [InAppNotificationController::class, 'go'])->name('notifications.go');
    Route::post('/notifications/{notification}/read', [InAppNotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [InAppNotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::redirect('/company/settings', '/profile')->name('company.settings');
    Route::redirect('/treasury/settings', '/profile')->name('treasury.settings');

    Route::get('/investor/readiness', [InvestorController::class, 'index'])->name('investor.readiness');
    Route::post('/investor/investment-request', [InvestorController::class, 'storeRequest'])->name('investor.investment-request.store');
    Route::post('/investor/investment-request/{investmentRequest}/workflow', [InvestorController::class, 'updateRequestWorkflow'])->name('investor.investment-request.workflow');

    Route::middleware('accountant')->prefix('accountant')->name('accountant.')->group(function () {
        Route::get('/', [AccountantDashboardController::class, 'index'])->name('dashboard');
        Route::get('/clients', [AccountantClientController::class, 'index'])->name('clients.index');
        Route::get('/clients/{user}', [AccountantClientController::class, 'show'])->name('clients.show');
        // Déclarer /workspace/clear avant /workspace/{user}, sinon « clear » est capturé comme identifiant client (POST bloqué).
        Route::match(['get', 'post'], '/workspace/clear', [AccountantClientController::class, 'clearWorkspace'])->name('workspace.clear');
        Route::post('/workspace/{user}', [AccountantClientController::class, 'selectWorkspace'])->name('workspace.select');
    });




    Route::middleware('platform.admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('dashboard');
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::post('/users', [AdminController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [AdminController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [AdminController::class, 'update'])->name('users.update');
        Route::get('/financial-analysis', [AdminFinancialAnalysisController::class, 'index'])->name('financial-analysis');
        Route::get('/financial-ranking', [AdminFinancialAnalysisController::class, 'ranking'])->name('financial-ranking');
        Route::get('/investment-requests', [AdminInvestmentRequestController::class, 'index'])->name('investment-requests.index');
        Route::get('/investment-requests/{investmentRequest}', [AdminInvestmentRequestController::class, 'show'])->name('investment-requests.show');
        Route::post('/investment-requests/{investmentRequest}/workflow', [AdminInvestmentRequestController::class, 'updateWorkflow'])->name('investment-requests.workflow');
        Route::get('/licenses', [AdminEnterpriseLicenseController::class, 'index'])->name('licenses.index');
        Route::post('/licenses', [AdminEnterpriseLicenseController::class, 'store'])->name('licenses.store');
        Route::post('/licenses/assign', [AdminEnterpriseLicenseController::class, 'assign'])->name('licenses.assign');
        Route::post('/licenses/{enterprise_license}/revoke', [AdminEnterpriseLicenseController::class, 'revoke'])->name('licenses.revoke');
        Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::post('/payments/{paymentTransaction}/activate-premium', [AdminPaymentController::class, 'activatePremium'])->name('payments.activate-premium');
        Route::post('/payments/{paymentTransaction}/set-free', [AdminPaymentController::class, 'setFree'])->name('payments.set-free');
        Route::get('/platform-logs', [AdminPlatformLogController::class, 'index'])->name('logs.index');
    });





    Route::middleware('premium.accounting')->group(function () {
        Route::get('/accounting', [AccountingController::class, 'index'])->name('accounting');
        Route::get('/accounting/index', fn () => redirect()->route('accounting'))->name('accounting.index');
        Route::post('/accounting/demo', fn () => back()->with('status', 'Mode démo disponible prochainement.'))->name('accounting.demo');
        Route::post('/accounting/entries', [AccountingController::class, 'storeEntry'])->name('accounting.entries.store');
        Route::get('/accounting/entries/{entry}', [AccountingController::class, 'showEntry'])->name('accounting.entries.show');
        Route::get('/accounting/entries/{entry}/document', [AccountingDocumentViewerController::class, 'showEntryDocument'])->name('accounting.entries.document.viewer');
        Route::get('/accounting/entries/{entry}/document/source', [AccountingDocumentViewerController::class, 'streamEntryDocument'])->name('accounting.entries.document.stream');
        Route::get('/accounting/entries/{entry}/edit', [AccountingController::class, 'editEntry'])->name('accounting.entries.edit');
        Route::put('/accounting/entries/{entry}', [AccountingController::class, 'updateEntry'])->name('accounting.entries.update');
        Route::post('/accounting/entries/{entry}/ocr/retry', [AccountingController::class, 'retryEntryOcr'])->name('accounting.entries.ocr.retry');
        Route::post('/accounting/entries/{entry}/ocr/auto-correct', [AccountingController::class, 'autoCorrectEntryFromOcr'])->name('accounting.entries.ocr.auto-correct');
        Route::post('/accounting/entries/{entry}/ocr/manual', [AccountingController::class, 'storeManualOcrValidation'])->name('accounting.entries.ocr.manual');
        Route::post('/accounting/entries/bulk-delete', [AccountingController::class, 'bulkDeleteEntries'])->name('accounting.entries.bulk.delete');
        Route::post('/accounting/entries/bulk-ocr-retry', [AccountingController::class, 'bulkRetryEntryOcr'])->name('accounting.entries.bulk.ocr.retry');
        Route::delete('/accounting/entries/{entry}', [AccountingController::class, 'destroyEntry'])->name('accounting.entries.destroy');
        Route::get('/accounting/documents', [AccountingController::class, 'documents'])->name('accounting.documents');
        Route::get('/accounting/documents/comparison', [AccountingController::class, 'documentsComparison'])->name('accounting.documents.comparison');
        
        Route::post('/accounting/documents', [AccountingController::class, 'uploadDocuments'])->name('accounting.documents.upload');
        Route::get('/accounting/documents/{document}/viewer', [AccountingDocumentViewerController::class, 'showDocument'])->name('accounting.documents.viewer');
        Route::get('/accounting/documents/{document}/source', [AccountingDocumentViewerController::class, 'streamDocument'])->name('accounting.documents.stream');
        Route::post('/accounting/documents/{document}/ocr/retry', [AccountingController::class, 'retryDocumentOcr'])->name('accounting.documents.ocr.retry');
        Route::delete('/accounting/documents/{document}', [AccountingController::class, 'destroyDocument'])->name('accounting.documents.destroy');
        Route::get('/accounting/documents/{document}/validate', [AccountingDocumentController::class, 'showValidation'])->name('accounting.documents.validate');
        Route::post('/accounting/documents/{document}/validate', [AccountingDocumentController::class, 'storeValidation'])->name('accounting.documents.validate.store');
        Route::get('/accounting/plan-comptable', [AccountingController::class, 'planComptable'])->name('accounting.plan');
        Route::post('/accounting/plan-comptable/upload', [AccountingController::class, 'uploadPlanComptable'])->name('accounting.plan.upload');
        Route::post('/accounting/plan-comptable/update', [AccountingController::class, 'updatePlanComptable'])->name('accounting.plan.update');
        Route::post('/accounting/plan-comptable/reset', [AccountingController::class, 'resetPlanComptable'])->name('accounting.plan.reset');
        Route::get('/accounting/plan-comptable/template', [AccountingController::class, 'downloadPlanComptableTemplate'])->name('accounting.plan.download.template');
        Route::get('/accounting/moteur/rapprochement-bancaire', [AccountingController::class, 'bankReconciliation'])->name('accounting.bank-reconciliation');
        Route::get('/accounting/moteur/cloture-mensuelle', [AccountingController::class, 'monthlyClosing'])->name('accounting.monthly-closing');
        Route::post('/accounting/moteur/cloture-mensuelle', [AccountingController::class, 'storeMonthClosure'])->name('accounting.monthly-closing.store');
        Route::get('/accounting/report', [AccountingController::class, 'report'])->name('accounting.report');
        Route::get('/accounting/report/journal', [AccountingController::class, 'report'])->name('accounting.report.journal');
        Route::get('/accounting/report/grand-livre', [AccountingController::class, 'report'])->name('accounting.report.grand-livre');
        Route::get('/accounting/report/balance', [AccountingController::class, 'report'])->name('accounting.report.balance');
        Route::get('/accounting/report/bilan', [AccountingController::class, 'report'])->name('accounting.report.bilan');
        Route::get('/accounting/report/resultat', [AccountingController::class, 'report'])->name('accounting.report.resultat');
        Route::get('/accounting/report/tafire', [AccountingController::class, 'report'])->name('accounting.report.tafire');
        Route::get('/accounting/report/annexe', [AccountingController::class, 'report'])->name('accounting.report.annexe');
        Route::get('/accounting/report/bilan/viewer', [AccountingController::class, 'showBilanPdfViewer'])->name('accounting.report.bilan.viewer');
        Route::get('/accounting/report/bilan/view', [AccountingController::class, 'viewBilanPdf'])->name('accounting.report.bilan.view');
        Route::get('/accounting/report/bilan/download', [AccountingController::class, 'downloadBilan'])->name('accounting.report.bilan.download');
    });

    Route::redirect('/treasury', '/treasury/tracking')->name('treasury.index');
    Route::get('/treasury/tracking', [TreasuryController::class, 'tracking'])->name('treasury.tracking');
    Route::get('/treasury/balance', [TreasuryController::class, 'balance'])->name('treasury.balance');
    Route::get('/treasury/forecast', [TreasuryController::class, 'forecast'])->name('treasury.forecast');
    Route::post('/treasury/forecast/period/lock', [TreasuryController::class, 'lockTreasuryPeriod'])->name('treasury.forecast.period.lock');
    Route::post('/treasury/forecast/period/unlock', [TreasuryController::class, 'unlockTreasuryPeriod'])->name('treasury.forecast.period.unlock');
    Route::post('/treasury/forecast/period/validate', [TreasuryController::class, 'validateTreasuryForecast'])->name('treasury.forecast.period.validate');
    Route::get('/treasury/create', [TreasuryController::class, 'create'])->name('treasury.create');
    Route::post('/treasury', [TreasuryController::class, 'store'])->name('treasury.store');
    Route::get('/treasury/{transaction}/edit', [TreasuryController::class, 'edit'])->name('treasury.edit');
    Route::put('/treasury/{transaction}', [TreasuryController::class, 'update'])->name('treasury.update');
    Route::delete('/treasury/{transaction}', [TreasuryController::class, 'destroy'])->name('treasury.destroy');
    Route::get('/treasury/{transaction}/fedapay/checkout', [TreasuryController::class, 'fedapayCheckoutForm'])->name('treasury.fedapay.checkout.form');
    Route::post('/treasury/{transaction}/fedapay/checkout', [TreasuryController::class, 'fedapayCheckoutStart'])->name('treasury.fedapay.checkout.start');
    Route::get('/treasury/{transaction}/stripe/success', [TreasuryController::class, 'stripeSuccess'])->name('treasury.stripe.success');
    Route::get('/treasury/{transaction}/stripe/cancel', [TreasuryController::class, 'stripeCancel'])->name('treasury.stripe.cancel');

    Route::get('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    })->name('logout.get');

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    })->name('logout');
});
