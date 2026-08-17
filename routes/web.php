<?php

use App\Http\Controllers\AccountantChangeRequestController;
use App\Http\Controllers\AccountantClientController;
use App\Http\Controllers\AccountantCommercialBalanceController;
use App\Http\Controllers\AccountantDashboardController;
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AccountingDocumentController;
use App\Http\Controllers\AccountingDocumentViewerController;
use App\Http\Controllers\AdminAccountingChangeRequestController;
use App\Http\Controllers\AdminAccountingQualityController;
use App\Http\Controllers\AdminBillingController;
use App\Http\Controllers\AdminBugReportController;
use App\Http\Controllers\AdminCommercialController;
use App\Http\Controllers\AdminCommercialDashboardController;
use App\Http\Controllers\AdminComplianceKycController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminDatabaseBackupController;
use App\Http\Controllers\AdminEnterpriseLicenseController;
use App\Http\Controllers\AdminExecutiveDashboardController;
use App\Http\Controllers\AdminFinancialAnalysisController;
use App\Http\Controllers\AdminInvestmentRequestController;
use App\Http\Controllers\AdminOpsCenterController;
use App\Http\Controllers\AdminPaymentController;
use App\Http\Controllers\AdminPlanComptableController;
use App\Http\Controllers\AdminPlatformLogController;
use App\Http\Controllers\AdminProspectionController;
use App\Http\Controllers\AdminRbacController;
use App\Http\Controllers\AdminScoringParametersController;
use App\Http\Controllers\AdminSupportTicketController;
use App\Http\Controllers\AiBusinessAdvisorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CinetPayController;
use App\Http\Controllers\CommercialController;
use App\Http\Controllers\CommercialProspectionController;
use App\Http\Controllers\CommercialSupervisorController;
use App\Http\Controllers\CompanyFirdController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\EnterpriseTeamController;
use App\Http\Controllers\FedaPaySandboxController;
use App\Http\Controllers\InAppNotificationController;
use App\Http\Controllers\InvestorController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MenuActivityLogController;
use App\Http\Controllers\MobileMoneyReconciliationController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\TreasuryController;
use App\Models\PaymentTransaction;
use App\Models\SubscriptionHistory;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : app(AuthController::class)->showLogin();
})->name('home');
Route::view('/about-us', 'about-us')->name('about-us');
Route::view('/tarifs', 'pricing')->name('pricing');
Route::view('/documentation', 'documentation')->name('documentation');
Route::post('/locale', function (Request $request) {
    $data = $request->validate([
        'locale' => ['required', 'in:fr,en,es,de,pt,ar,it,nl'],
    ]);

    $locale = (string) $data['locale'];
    $request->session()->put('locale', $locale);

    if ($request->user() && $request->user()->locale !== $locale) {
        $request->user()->update(['locale' => $locale]);
    }

    return back();
})->name('locale.switch');

Route::get('/payments/sandbox/callback', [FedaPaySandboxController::class, 'callback'])->name('payments.sandbox.callback');
Route::match(['get', 'post'], '/payments/cinetpay/notify', [CinetPayController::class, 'notify'])
    ->withoutMiddleware(VerifyCsrfToken::class)
    ->name('payments.cinetpay.notify');
Route::get('/payments/cinetpay/return', [CinetPayController::class, 'return'])->name('payments.cinetpay.return');
Route::post('/payments/stripe/webhook', [TreasuryController::class, 'stripeWebhook'])
    ->middleware('throttle:stripe-webhook')
    ->withoutMiddleware(VerifyCsrfToken::class)
    ->name('payments.stripe.webhook');

// Accessibles même si l'utilisateur est déjà connecté (ex: un admin teste le
// lien qu'il vient de générer pour quelqu'un d'autre dans le même navigateur ;
// ou l'utilisateur cible a encore une session active ailleurs).
Route::get('/reset-password/lien/{token}', [AuthController::class, 'showPasswordResetLink'])->middleware('throttle:auth-sensitive')->name('password.reset-link.show');
Route::post('/reset-password/lien/{token}', [AuthController::class, 'submitPasswordResetLink'])->middleware('throttle:auth-sensitive')->name('password.reset-link.submit');

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => redirect()->route('home'))->name('login');
    Route::view('/register', 'register')->name('register');

    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-sensitive')->name('login.post');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetOtp'])->middleware('throttle:auth-sensitive')->name('password.otp.send');
    Route::get('/reset-password', [AuthController::class, 'showResetPassword'])->name('password.reset.form');
    Route::post('/reset-password', [AuthController::class, 'resetPasswordWithOtp'])->middleware('throttle:auth-sensitive')->name('password.otp.reset');

    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:auth-sensitive')->name('register.post');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/ai/live', [DashboardController::class, 'liveInsights'])->name('dashboard.ai.live');

    Route::get('/profile', function (Request $request) {
        return view('profile', [
            'user' => $request->user(),
            'recentSandboxPayments' => PaymentTransaction::query()
                ->where('user_id', $request->user()->id)
                ->whereIn('provider', ['fedapay_sandbox', 'cinetpay'])
                ->latest()
                ->limit(5)
                ->get(),
            'isFedaPaySandboxEnabled' => (bool) config('services.fedapay.sandbox.enabled', false),
        ]);
    })->name('profile');

    Route::get('/profile/entreprise/fird', [CompanyFirdController::class, 'edit'])->name('profile.company.fird');
    Route::post('/profile/entreprise/fird', [CompanyFirdController::class, 'update'])->name('profile.company.fird.update');

    Route::get('/company-documents/{user}/{type}/view', [AccountingDocumentViewerController::class, 'showCompanyDocument'])
        ->whereIn('type', ['company_logo', 'trade_register'])
        ->name('company-documents.view');
    Route::get('/company-documents/{user}/{type}/stream', [AccountingDocumentViewerController::class, 'streamCompanyDocument'])
        ->whereIn('type', ['company_logo', 'trade_register'])
        ->name('company-documents.stream');

    Route::get('/profile/equipe', [EnterpriseTeamController::class, 'index'])->name('profile.team');
    Route::post('/profile/equipe', [EnterpriseTeamController::class, 'store'])->name('profile.team.store');
    Route::get('/profile/equipe/historique', [EnterpriseTeamController::class, 'history'])->name('profile.team.history');
    Route::put('/profile/equipe/{teammate}', [EnterpriseTeamController::class, 'update'])->name('profile.team.update');
    Route::delete('/profile/equipe/{teammate}', [EnterpriseTeamController::class, 'destroy'])->name('profile.team.destroy');

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
            'company_designation' => ['nullable', 'string', 'max:512'],
            'company_logo' => ['nullable', 'image', 'max:5120'],
            'rccm' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'max:5120'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'po_box' => ['nullable', 'string', 'max:64'],
            'full_geographic_address' => ['nullable', 'string', 'max:2000'],
            'main_activity_description' => ['nullable', 'string', 'max:5000'],
            'trade_register' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'timezone' => ['required', 'string', 'max:64'],
            'locale' => ['required', 'in:fr,en,es,de,pt,ar,it,nl'],
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

        if ($request->hasFile('trade_register')) {
            if ($user->trade_register_file) {
                Storage::disk('public')->delete($user->trade_register_file);
            }

            $validated['trade_register_file'] = $request->file('trade_register')->store('trade-registers', 'public');
        }
        unset($validated['trade_register']);

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = Hash::make($validated['password']);
            $validated['must_change_password'] = false;
        }

        $validated['email_notifications'] = $request->boolean('email_notifications');
        $validated['weekly_digest'] = $request->boolean('weekly_digest');
        $validated['marketing_emails'] = $request->boolean('marketing_emails');
        $validated['two_factor_enabled'] = $request->boolean('two_factor_enabled');

        $user->update($validated);
        $request->session()->put('locale', (string) $validated['locale']);

        return back()->with('status', 'Profil mis à jour.');
    })->name('profile.update');

    Route::post('/profile/subscription/simulate', function (Request $request) {
        // Réservé au développement local : sans ce garde-fou, n'importe quel
        // utilisateur authentifié pouvait s'auto-attribuer le Premium Enterprise
        // (jusqu'à 365 jours) en appelant directement cette route, contournant
        // entièrement le paiement CinetPay. Même garde-fou déjà utilisé pour la
        // simulation de retour de paiement CinetPay (voir CinetPayController).
        abort_unless(app()->environment(['local', 'testing']), 404);

        $user = $request->user();
        if ($user->is_platform_admin) {
            return back()->withErrors([
                'subscription' => 'Les administrateurs plateforme ne sont pas concernés par les offres Gratuit / Premium.',
            ]);
        }
        $previousStatus = $user->premium_status ?? 'free';
        $previousEndsAt = $user->premium_ends_at;
        $validated = $request->validate([
            'plan_type' => ['required', 'in:free,premium'],
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
                'note' => 'Passage manuel en offre Gratuit (période d\'essai) via simulation.',
                'meta' => [
                    'previous_premium_ends_at' => optional($previousEndsAt)?->toDateTimeString(),
                ],
            ]);

            return back()->with('status', 'Simulation appliquée : compte en offre Gratuit (période d\'essai).');
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
            'note' => 'Activation Enterprise Premium via simulation.',
            'meta' => [
                'duration_days' => $durationDays,
                'previous_premium_ends_at' => optional($previousEndsAt)?->toDateTimeString(),
            ],
        ]);

        return back()->with('status', "Simulation appliquée : Enterprise Premium actif pour {$durationDays} jour(s).");
    })->name('profile.subscription.simulate');
    Route::get('/payments/sandbox', [FedaPaySandboxController::class, 'index'])->name('payments.sandbox');
    Route::post('/payments/sandbox', [FedaPaySandboxController::class, 'store'])->name('payments.sandbox.store');
    Route::post('/payments/cinetpay/checkout', [CinetPayController::class, 'checkout'])->name('payments.cinetpay.checkout');
    Route::get('/payments/sandbox/result', [FedaPaySandboxController::class, 'result'])->name('payments.sandbox.result');
    Route::get('/payments/redirect', function (Request $request) {
        if ((bool) config('services.cinetpay.enabled', false)) {
            return redirect()
                ->route('payments.sandbox')
                ->with('status', 'Utilisez le bouton CinetPay sur cette page pour payer votre abonnement.');
        }

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
    Route::get('/billing/invoices', [BillingController::class, 'index'])->name('billing.invoices');
    Route::get('/billing/invoices/{invoice}/pdf/view', [BillingController::class, 'viewInvoicePdf'])->name('billing.invoices.pdf.view');
    Route::get('/billing/invoices/{invoice}/pdf/stream', [BillingController::class, 'streamInvoicePdf'])->name('billing.invoices.pdf.stream');
    Route::get('/billing/invoices/{invoice}/pdf', [BillingController::class, 'downloadInvoicePdf'])->name('billing.invoices.pdf');
    Route::post('/compliance/kyc/submit', [AdminComplianceKycController::class, 'submit'])->name('compliance.kyc.submit');

    Route::get('/activity/menu-log', [MenuActivityLogController::class, 'index'])->name('activity-log.index');

    Route::get('/documentation/synthese', [DocumentationController::class, 'downloadSynthese'])->name('documentation.synthese');
    Route::middleware('module.permission:support')->group(function () {
        Route::get('/support', [SupportController::class, 'index'])->name('support.index');
        Route::get('/support/tickets', [SupportController::class, 'tickets'])->name('support.tickets');
        Route::get('/support/tickets/create', [SupportController::class, 'create'])->name('support.tickets.create');
        Route::post('/support/tickets', [SupportController::class, 'store'])->name('support.tickets.store');
        Route::get('/support/tickets/{ticket}', [SupportController::class, 'show'])->name('support.tickets.show');
        Route::get('/support/tickets/{ticket}/feed', [SupportController::class, 'feed'])->name('support.tickets.feed');
        Route::get('/support/tickets/{ticket}/stream', [SupportController::class, 'stream'])->name('support.tickets.stream');
        Route::post('/support/tickets/{ticket}/read', [SupportController::class, 'markRead'])->name('support.tickets.read');
        Route::post('/support/tickets/{ticket}/messages', [SupportController::class, 'storeMessage'])->name('support.tickets.messages.store');
    });

    Route::get('/notifications', [InAppNotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{notification}/go', [InAppNotificationController::class, 'go'])->name('notifications.go');
    Route::post('/notifications/{notification}/read', [InAppNotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [InAppNotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::redirect('/company/settings', '/profile')->name('company.settings');
    Route::redirect('/treasury/settings', '/profile')->name('treasury.settings');

    Route::middleware('module.permission:investor')->group(function () {
        Route::get('/investor/readiness', [InvestorController::class, 'index'])->name('investor.readiness');
        Route::post('/investor/investment-request', [InvestorController::class, 'storeRequest'])->middleware('throttle:investor-actions')->name('investor.investment-request.store');
        Route::post('/investor/investment-request/{investmentRequest}/workflow', [InvestorController::class, 'updateRequestWorkflow'])->middleware('throttle:investor-actions')->name('investor.investment-request.workflow');
        Route::post('/investor/quality-review', [InvestorController::class, 'markQualityReview'])->middleware('throttle:investor-actions')->name('investor.quality-review.store');
    });
    Route::post('/ai/business/chat', [AiBusinessAdvisorController::class, 'chat'])->name('ai.business.chat');

    Route::middleware('accountant')->prefix('accountant')->name('accountant.')->group(function () {
        Route::get('/', [AccountantDashboardController::class, 'index'])->name('dashboard');
        Route::get('/ai/live', [AccountantDashboardController::class, 'liveInsights'])->name('dashboard.ai.live');
        Route::get('/commercials-tracking', [AccountantDashboardController::class, 'commercialsTracking'])->name('commercials.index');
        Route::get('/commercials-balance', [AccountantCommercialBalanceController::class, 'index'])->name('commercials-balance.index');
        Route::get('/commercials-balance/{commercial}', [AccountantCommercialBalanceController::class, 'show'])->name('commercials-balance.show');
        Route::post('/commercials-balance/{commercial}/payouts', [AccountantCommercialBalanceController::class, 'storePayout'])->name('commercials-balance.payouts.store');
        Route::get('/commercials-balance/payouts/{payout}/receipt', [AccountantCommercialBalanceController::class, 'downloadReceipt'])->name('commercials-balance.payouts.receipt');
        Route::post('/parse-company-document', [AccountantDashboardController::class, 'parseCompanyDocument'])->name('parseCompanyDocument');
        Route::get('/clients', [AccountantClientController::class, 'index'])->name('clients.index');
        Route::post('/clients', [AccountantClientController::class, 'store'])->name('clients.store');
        Route::get('/clients/{user}', [AccountantClientController::class, 'show'])->name('clients.show');
        Route::put('/clients/{user}', [AccountantClientController::class, 'update'])->name('clients.update');
        Route::delete('/clients/{user}', [AccountantClientController::class, 'destroy'])->name('clients.destroy');
        Route::get('/documents', [AccountantClientController::class, 'documents'])->name('documents.index');
        Route::post('/documents/{user}/{type}', [AccountantClientController::class, 'updateDocument'])
            ->whereIn('type', ['company_logo', 'trade_register'])
            ->name('documents.update');
        // Déclarer /workspace/clear avant /workspace/{user}, sinon « clear » est capturé comme identifiant client (POST bloqué).
        Route::match(['get', 'post'], '/workspace/clear', [AccountantClientController::class, 'clearWorkspace'])->name('workspace.clear');
        Route::post('/workspace/{user}', [AccountantClientController::class, 'selectWorkspace'])->name('workspace.select');
        Route::get('/change-requests', [AccountantChangeRequestController::class, 'index'])->name('change-requests.index');
        Route::post('/change-requests/{changeRequest}/approve', [AccountantChangeRequestController::class, 'approve'])->name('change-requests.approve');
        Route::post('/change-requests/{changeRequest}/reject', [AccountantChangeRequestController::class, 'reject'])->name('change-requests.reject');
    });

    Route::middleware('platform.admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('dashboard');
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::post('/users', [AdminController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [AdminController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [AdminController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/reset-password', [AdminController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('/users/{user}/password-reset-link', [AdminController::class, 'generatePasswordResetLink'])->name('users.password-reset-link');
        Route::get('/prospections', [AdminProspectionController::class, 'index'])->name('prospections.index');
        Route::get('/prospections/{prospection}', [AdminProspectionController::class, 'show'])->name('prospections.show');
        Route::get('/prospections/{prospection}/download', [AdminProspectionController::class, 'download'])->name('prospections.download');
        Route::post('/prospections/{prospection}/approve', [AdminProspectionController::class, 'approve'])->name('prospections.approve');
        Route::post('/prospections/{prospection}/request-revision', [AdminProspectionController::class, 'requestRevision'])->name('prospections.request-revision');
        Route::post('/prospections/{prospection}/reject', [AdminProspectionController::class, 'reject'])->name('prospections.reject');

        Route::get('/backups', [AdminDatabaseBackupController::class, 'index'])->name('backups.index');
        Route::post('/backups/run', [AdminDatabaseBackupController::class, 'run'])->name('backups.run');
        Route::get('/backups/{filename}/download', [AdminDatabaseBackupController::class, 'download'])->name('backups.download');
        Route::delete('/backups/{filename}', [AdminDatabaseBackupController::class, 'destroy'])->name('backups.destroy');
        Route::post('/users/{user}/premium/activate', [AdminController::class, 'activatePremiumTrial'])->name('users.premium.activate');
        Route::post('/users/{user}/premium/deactivate', [AdminController::class, 'deactivatePremium'])->name('users.premium.deactivate');
        Route::get('/dashboard/ai/live', [AdminController::class, 'dashboardLiveInsights'])->name('dashboard.ai.live');
        Route::get('/financial-analysis', [AdminFinancialAnalysisController::class, 'index'])->name('financial-analysis');
        Route::get('/financial-ranking', [AdminFinancialAnalysisController::class, 'ranking'])->name('financial-ranking');
        Route::get('/investment-requests', [AdminInvestmentRequestController::class, 'index'])->name('investment-requests.index');
        Route::get('/investment-requests/{investmentRequest}', [AdminInvestmentRequestController::class, 'show'])->name('investment-requests.show');
        Route::post('/investment-requests/{investmentRequest}/workflow', [AdminInvestmentRequestController::class, 'updateWorkflow'])->name('investment-requests.workflow');
        Route::get('/licenses', [AdminEnterpriseLicenseController::class, 'index'])->name('licenses.index');
        Route::post('/licenses', [AdminEnterpriseLicenseController::class, 'store'])->name('licenses.store');
        Route::post('/licenses/assign', [AdminEnterpriseLicenseController::class, 'assign'])->name('licenses.assign');
        Route::put('/licenses/{enterprise_license}', [AdminEnterpriseLicenseController::class, 'update'])->name('licenses.update');
        Route::post('/licenses/{enterprise_license}/revoke', [AdminEnterpriseLicenseController::class, 'revoke'])->name('licenses.revoke');
        Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::post('/payments/simulate', [AdminPaymentController::class, 'simulate'])->name('payments.simulate');
        Route::get('/payments/{paymentTransaction}/receipt', [AdminPaymentController::class, 'receipt'])->name('payments.receipt');
        Route::delete('/payments/{paymentTransaction}', [AdminPaymentController::class, 'destroy'])->name('payments.destroy');
        Route::post('/payments/{paymentTransaction}/activate-premium', [AdminPaymentController::class, 'activatePremium'])->name('payments.activate-premium');
        Route::post('/payments/{paymentTransaction}/set-free', [AdminPaymentController::class, 'setFree'])->name('payments.set-free');
        Route::get('/platform-logs', [AdminPlatformLogController::class, 'index'])->name('logs.index');
        Route::get('/signalements', [AdminBugReportController::class, 'index'])->name('signalements.index');
        Route::post('/signalements/{bugReport}/resolve', [AdminBugReportController::class, 'resolve'])->name('signalements.resolve');
        Route::delete('/signalements/{bugReport}', [AdminBugReportController::class, 'destroy'])->name('signalements.destroy');
        Route::post('/signalements/clear-logs', [AdminBugReportController::class, 'clearLogs'])->name('signalements.clear-logs');
        Route::get('/ops-center', [AdminOpsCenterController::class, 'index'])->name('ops.index');
        Route::post('/ops-center/feature-flags', [AdminOpsCenterController::class, 'updateFeatureFlags'])->name('ops.feature-flags.update');
        Route::post('/ops-center/health-checks/run', [AdminOpsCenterController::class, 'runHealthChecksNow'])->name('ops.health-checks.run');
        Route::post('/ops-center/queue/retry-all', [AdminOpsCenterController::class, 'retryAllFailedJobs'])->name('ops.queue.retry-all');
        Route::post('/ops-center/ai/autonomous-approvals', [AdminOpsCenterController::class, 'updateAiAutonomousApprovals'])->name('ops.ai.autonomous-approvals.update');
        Route::post('/ops-center/approvals', [AdminOpsCenterController::class, 'createApprovalRequest'])->name('ops.approvals.store');
        Route::post('/ops-center/approvals/{approval}/approve', [AdminOpsCenterController::class, 'approveRequest'])->name('ops.approvals.approve');
        Route::post('/ops-center/approvals/{approval}/reject', [AdminOpsCenterController::class, 'rejectRequest'])->name('ops.approvals.reject');
        Route::get('/ops-center/audit/export', [AdminOpsCenterController::class, 'exportAuditCsv'])->name('ops.audit.export');
        Route::post('/ops-center/ai/chat', [AdminOpsCenterController::class, 'aiChat'])->name('ops.ai.chat');
        Route::get('/ops-center/ai/live-proposals', [AdminOpsCenterController::class, 'aiLiveProposals'])->name('ops.ai.live');
        Route::post('/ops-center/ai/tasks', [AdminOpsCenterController::class, 'storeAiTask'])->name('ops.ai.tasks.store');
        Route::post('/ops-center/ai/tasks/{task}/assign', [AdminOpsCenterController::class, 'assignAiTask'])->name('ops.ai.tasks.assign');
        Route::post('/ops-center/ai/tasks/{task}/complete', [AdminOpsCenterController::class, 'completeAiTask'])->name('ops.ai.tasks.complete');
        Route::get('/scoring-parameters', [AdminScoringParametersController::class, 'index'])->name('scoring-parameters.index');
        Route::post('/scoring-parameters', [AdminScoringParametersController::class, 'update'])->name('scoring-parameters.update');
        Route::post('/scoring-parameters/import', [AdminScoringParametersController::class, 'import'])->name('scoring-parameters.import');
        Route::get('/plan-comptable', [AdminPlanComptableController::class, 'index'])->name('plan-comptable.index');
        Route::post('/plan-comptable/upload', [AdminPlanComptableController::class, 'upload'])->name('plan-comptable.upload');
        Route::post('/plan-comptable/apply-to-existing', [AdminPlanComptableController::class, 'applyToExisting'])->name('plan-comptable.apply-to-existing');
        Route::get('/support/tickets', [AdminSupportTicketController::class, 'index'])->name('support.tickets.index');
        Route::get('/support/tickets/{ticket}', [AdminSupportTicketController::class, 'show'])->name('support.tickets.show');
        Route::get('/support/tickets/{ticket}/feed', [AdminSupportTicketController::class, 'feed'])->name('support.tickets.feed');
        Route::get('/support/tickets/{ticket}/stream', [AdminSupportTicketController::class, 'stream'])->name('support.tickets.stream');
        Route::post('/support/tickets/{ticket}/read', [AdminSupportTicketController::class, 'markRead'])->name('support.tickets.read');
        Route::post('/support/tickets/{ticket}/assign', [AdminSupportTicketController::class, 'assign'])->name('support.tickets.assign');
        Route::post('/support/tickets/{ticket}/messages', [AdminSupportTicketController::class, 'storeMessage'])->name('support.tickets.messages.store');
        Route::get('/billing', [AdminBillingController::class, 'index'])->name('billing.index');
        Route::post('/billing/subscriptions/switch', [AdminBillingController::class, 'createOrSwitchSubscription'])->name('billing.subscriptions.switch');
        Route::get('/compliance/kyc', [AdminComplianceKycController::class, 'index'])->name('compliance.kyc.index');
        Route::get('/compliance/kyc/{user}', [AdminComplianceKycController::class, 'show'])->name('compliance.kyc.show');
        Route::post('/compliance/kyc/{user}/approve', [AdminComplianceKycController::class, 'approve'])->name('compliance.kyc.approve');
        Route::post('/compliance/kyc/{user}/reject', [AdminComplianceKycController::class, 'reject'])->name('compliance.kyc.reject');
        Route::post('/compliance/kyc/{user}/resubmit', [AdminComplianceKycController::class, 'resubmit'])->name('compliance.kyc.resubmit');
        Route::get('/compliance/kyc-documents/{document}/stream', [AdminComplianceKycController::class, 'streamDocument'])->name('compliance.kyc.document.stream');
        Route::get('/compliance/accounting-quality', [AdminAccountingQualityController::class, 'index'])->name('compliance.accounting-quality.index');
        Route::post('/compliance/accounting-quality/{user}/review-now', [AdminAccountingQualityController::class, 'reviewNow'])->name('compliance.accounting-quality.review-now');
        Route::get('/compliance/change-requests', [AdminAccountingChangeRequestController::class, 'index'])->name('compliance.change-requests.index');
        Route::get('/executive-dashboard', [AdminExecutiveDashboardController::class, 'index'])->name('executive.index');
        Route::get('/rbac', [AdminRbacController::class, 'index'])->name('rbac.index');
        Route::post('/rbac/{user}', [AdminRbacController::class, 'update'])->name('rbac.update');
        Route::get('/commerciale', [AdminCommercialController::class, 'index'])->name('commerciale');
        Route::get('/commercial-dashboard', [AdminCommercialDashboardController::class, 'index'])->name('commercial-dashboard');
        Route::get('/commercial-dashboard/prospects', [AdminCommercialDashboardController::class, 'prospects'])->name('commercial-dashboard.prospects');
        Route::get('/commercial-dashboard/export', [AdminCommercialDashboardController::class, 'exportCsv'])->name('commercial-dashboard.export');
        Route::get('/commercial-dashboard/{commercial}', [AdminCommercialDashboardController::class, 'showCommercial'])->name('commercial-dashboard.show');
    });

    Route::middleware(['premium.accounting', 'module.permission:accounting'])->group(function () {
        Route::get('/accounting', [AccountingController::class, 'index'])->name('accounting');
        Route::get('/accounting/index', fn () => redirect()->route('accounting'))->name('accounting.index');
        Route::post('/accounting/demo', [AccountingController::class, 'seedDemoData'])->name('accounting.demo');
        Route::get('/accounting/comptes/search', [AccountingController::class, 'searchAccounts'])->name('accounting.comptes.search');
        Route::post('/accounting/entries', [AccountingController::class, 'storeEntry'])->name('accounting.entries.store');
        Route::get('/accounting/entries/{entry}', [AccountingController::class, 'showEntry'])->name('accounting.entries.show');
        Route::get('/accounting/entries/{entry}/document', [AccountingDocumentViewerController::class, 'showEntryDocument'])->name('accounting.entries.document.viewer');
        Route::get('/accounting/entries/{entry}/document/source', [AccountingDocumentViewerController::class, 'streamEntryDocument'])->name('accounting.entries.document.stream');
        Route::get('/accounting/entries/{entry}/edit', [AccountingController::class, 'editEntry'])->name('accounting.entries.edit');
        Route::put('/accounting/entries/{entry}', [AccountingController::class, 'updateEntry'])->name('accounting.entries.update');
        Route::post('/accounting/entries/{entry}/ocr/retry', [AccountingController::class, 'retryEntryOcr'])->middleware('throttle:ocr-intensive')->name('accounting.entries.ocr.retry');
        Route::post('/accounting/entries/{entry}/ocr/auto-correct', [AccountingController::class, 'autoCorrectEntryFromOcr'])->name('accounting.entries.ocr.auto-correct');
        Route::post('/accounting/entries/{entry}/ocr/manual', [AccountingController::class, 'storeManualOcrValidation'])->name('accounting.entries.ocr.manual');
        Route::post('/accounting/entries/bulk-delete', [AccountingController::class, 'bulkDeleteEntries'])->name('accounting.entries.bulk.delete');
        Route::post('/accounting/entries/bulk-ocr-retry', [AccountingController::class, 'bulkRetryEntryOcr'])->middleware('throttle:ocr-intensive')->name('accounting.entries.bulk.ocr.retry');
        Route::delete('/accounting/entries/{entry}', [AccountingController::class, 'destroyEntry'])->name('accounting.entries.destroy');
        Route::get('/accounting/documents', [AccountingController::class, 'documents'])->name('accounting.documents');
        Route::get('/accounting/documents/comparison', [AccountingController::class, 'documentsComparison'])->name('accounting.documents.comparison');

        Route::post('/accounting/documents', [AccountingController::class, 'uploadDocuments'])->middleware('throttle:ocr-intensive')->name('accounting.documents.upload');
        Route::post('/accounting/documents/prefill', [AccountingController::class, 'uploadDocumentForEntryPrefill'])->middleware('throttle:ocr-intensive')->name('accounting.documents.prefill');
        Route::get('/accounting/documents/{document}/viewer', [AccountingDocumentViewerController::class, 'showDocument'])->name('accounting.documents.viewer');
        Route::get('/accounting/documents/{document}/source', [AccountingDocumentViewerController::class, 'streamDocument'])->name('accounting.documents.stream');
        Route::post('/accounting/documents/{document}/ocr/retry', [AccountingController::class, 'retryDocumentOcr'])->middleware('throttle:ocr-intensive')->name('accounting.documents.ocr.retry');
        Route::delete('/accounting/documents/{document}', [AccountingController::class, 'destroyDocument'])->name('accounting.documents.destroy');
        Route::get('/accounting/documents/{document}/validate', [AccountingDocumentController::class, 'showValidation'])->name('accounting.documents.validate');
        Route::post('/accounting/documents/{document}/validate', [AccountingDocumentController::class, 'storeValidation'])->name('accounting.documents.validate.store');
        Route::get('/accounting/plan-comptable', [AccountingController::class, 'planComptable'])->name('accounting.plan');
        Route::post('/accounting/plan-comptable/upload', [AccountingController::class, 'uploadPlanComptable'])->name('accounting.plan.upload');
        Route::post('/accounting/plan-comptable/update', [AccountingController::class, 'updatePlanComptable'])->name('accounting.plan.update');
        Route::post('/accounting/plan-comptable/reset', [AccountingController::class, 'resetPlanComptable'])->name('accounting.plan.reset');
        Route::get('/accounting/plan-comptable/template', [AccountingController::class, 'downloadPlanComptableTemplate'])->name('accounting.plan.download.template');
        Route::get('/accounting/plan-comptable/download-template-syscohada', [AccountingController::class, 'downloadSyscohadaTemplate'])->name('accounting.plan-comptable.download-template-syscohada');
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
        Route::get('/accounting/liasse-bceao', [AccountingController::class, 'liasseBceao'])->name('accounting.liasse-bceao');
        Route::get('/accounting/liasse-bceao/pdf/download', [AccountingController::class, 'downloadLiasseBceaoPdf'])->name('accounting.liasse-bceao.pdf.download');
        Route::get('/accounting/liasse-bceao/pdf/view', [AccountingController::class, 'viewLiasseBceaoPdf'])->name('accounting.liasse-bceao.pdf.view');
    });

    Route::middleware('module.permission:treasury')->group(function () {
        Route::redirect('/treasury', '/treasury/tracking')->name('treasury.index');
        Route::get('/treasury/tracking', [TreasuryController::class, 'tracking'])->name('treasury.tracking');
        Route::get('/treasury/export.csv', [TreasuryController::class, 'exportCsv'])->name('treasury.export.csv');
        Route::get('/treasury/dashboard-crypto', [TreasuryController::class, 'dashboardCrypto'])->name('treasury.dashboard-crypto');
        Route::get('/treasury/balance', [TreasuryController::class, 'balance'])->name('treasury.balance');
        Route::get('/treasury/forecast', [TreasuryController::class, 'forecast'])->name('treasury.forecast');
        Route::post('/treasury/forecast/period/lock', [TreasuryController::class, 'lockTreasuryPeriod'])->name('treasury.forecast.period.lock');
        Route::post('/treasury/forecast/period/unlock', [TreasuryController::class, 'unlockTreasuryPeriod'])->name('treasury.forecast.period.unlock');
        Route::post('/treasury/forecast/period/validate', [TreasuryController::class, 'validateTreasuryForecast'])->name('treasury.forecast.period.validate');
        Route::get('/treasury/create', [TreasuryController::class, 'create'])->name('treasury.create');
        Route::post('/treasury', [TreasuryController::class, 'store'])->middleware('throttle:finance-write')->name('treasury.store');
        Route::get('/treasury/{transaction}/edit', [TreasuryController::class, 'edit'])->name('treasury.edit');
        Route::put('/treasury/{transaction}', [TreasuryController::class, 'update'])->middleware('throttle:finance-write')->name('treasury.update');
        Route::delete('/treasury/{transaction}', [TreasuryController::class, 'destroy'])->middleware('throttle:finance-write')->name('treasury.destroy');
        Route::get('/treasury/{transaction}/fedapay/checkout', [TreasuryController::class, 'fedapayCheckoutForm'])->name('treasury.fedapay.checkout.form');
        Route::post('/treasury/{transaction}/fedapay/checkout', [TreasuryController::class, 'fedapayCheckoutStart'])->name('treasury.fedapay.checkout.start');
        Route::get('/treasury/{transaction}/stripe/success', [TreasuryController::class, 'stripeSuccess'])->name('treasury.stripe.success');
        Route::get('/treasury/{transaction}/stripe/cancel', [TreasuryController::class, 'stripeCancel'])->name('treasury.stripe.cancel');

        Route::get('/treasury/mobile-money', [MobileMoneyReconciliationController::class, 'index'])->name('treasury.mobile-money.index');
        Route::post('/treasury/mobile-money/import', [MobileMoneyReconciliationController::class, 'store'])->middleware('throttle:finance-write')->name('treasury.mobile-money.import');
        Route::get('/treasury/mobile-money/{import}', [MobileMoneyReconciliationController::class, 'review'])->name('treasury.mobile-money.review');
        Route::post('/treasury/mobile-money/transaction/{transaction}/match', [MobileMoneyReconciliationController::class, 'matchTransaction'])->middleware('throttle:finance-write')->name('treasury.mobile-money.match');
        Route::post('/treasury/mobile-money/transaction/{transaction}/create', [MobileMoneyReconciliationController::class, 'createTreasuryTransaction'])->middleware('throttle:finance-write')->name('treasury.mobile-money.create');
        Route::post('/treasury/mobile-money/transaction/{transaction}/ignore', [MobileMoneyReconciliationController::class, 'ignoreTransaction'])->middleware('throttle:finance-write')->name('treasury.mobile-money.ignore');
        Route::post('/treasury/mobile-money/{import}/purge', [MobileMoneyReconciliationController::class, 'purgePersonalData'])->middleware('throttle:finance-write')->name('treasury.mobile-money.purge');
    });

    // Module Paie & Paiement des Salaires
    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::get('/payroll/create', [PayrollController::class, 'create'])->name('payroll.create');
    Route::post('/payroll', [PayrollController::class, 'store'])->name('payroll.store');
    Route::get('/payroll/{payroll}', [PayrollController::class, 'show'])->name('payroll.show');
    Route::post('/payroll/{payroll}/sync', [PayrollController::class, 'sync'])->name('payroll.sync');
    Route::delete('/payroll/{payroll}', [PayrollController::class, 'destroy'])->name('payroll.destroy');

    Route::middleware('module.permission:invoicing')->group(function () {
        Route::get('/invoicing', [InvoiceController::class, 'index'])->name('invoicing.index');
        Route::get('/invoicing/create', [InvoiceController::class, 'create'])->name('invoicing.create');
        Route::post('/invoicing', [InvoiceController::class, 'store'])->middleware('throttle:finance-write')->name('invoicing.store');
        Route::get('/invoicing/{invoice}', [InvoiceController::class, 'show'])->name('invoicing.show');
        Route::get('/invoicing/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoicing.edit');
        Route::put('/invoicing/{invoice}', [InvoiceController::class, 'update'])->middleware('throttle:finance-write')->name('invoicing.update');
        Route::post('/invoicing/{invoice}/payments', [InvoiceController::class, 'storePayment'])->middleware('throttle:finance-write')->name('invoicing.payments.store');
        Route::post('/invoicing/{invoice}/cancel', [InvoiceController::class, 'cancel'])->middleware('throttle:finance-write')->name('invoicing.cancel');
        Route::delete('/invoicing/{invoice}', [InvoiceController::class, 'destroy'])->middleware('throttle:finance-write')->name('invoicing.destroy');
        Route::get('/invoicing/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoicing.pdf');
    });

    Route::middleware('module.permission:stock')->group(function () {
        Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
        Route::get('/stock/create', [StockController::class, 'create'])->name('stock.create');
        Route::post('/stock', [StockController::class, 'store'])->middleware('throttle:finance-write')->name('stock.store');
        Route::get('/stock/{product}', [StockController::class, 'show'])->name('stock.show');
        Route::get('/stock/{product}/edit', [StockController::class, 'edit'])->name('stock.edit');
        Route::put('/stock/{product}', [StockController::class, 'update'])->middleware('throttle:finance-write')->name('stock.update');
        Route::delete('/stock/{product}', [StockController::class, 'destroy'])->middleware('throttle:finance-write')->name('stock.destroy');
        Route::post('/stock/{product}/movements', [StockController::class, 'storeMovement'])->middleware('throttle:finance-write')->name('stock.movements.store');
    });

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

    Route::middleware('commercial')->prefix('commercial')->name('commercial.')->group(function () {
        Route::get('/dashboard', [CommercialController::class, 'index'])->name('dashboard');
        Route::get('/portefeuille', [CommercialController::class, 'portefeuille'])->name('portefeuille');
        Route::get('/solde', [CommercialController::class, 'balance'])->name('balance');
        Route::get('/offres', [CommercialController::class, 'showcase'])->name('showcase');
        Route::post('/offres/feedback', [CommercialController::class, 'storeFeedback'])->name('feedback.store');
        Route::get('/guides', [CommercialController::class, 'guides'])->name('guides');
        Route::get('/guides/{slug}/download', [CommercialController::class, 'downloadGuide'])->name('guides.download');
        Route::get('/club', [CommercialController::class, 'club'])->name('club');
        Route::get('/prospects', [CommercialController::class, 'prospects'])->name('prospects');
        Route::get('/import', [CommercialController::class, 'importFile'])->name('import');
        Route::post('/import', [CommercialController::class, 'storeDocument'])->name('import.store');
        Route::get('/import/documents/{document}/download', [CommercialController::class, 'downloadDocument'])->name('import.download');
        Route::delete('/import/documents/{document}', [CommercialController::class, 'destroyDocument'])->name('import.destroy');
        Route::post('/parse-company-document', [CommercialController::class, 'parseCompanyDocument'])->name('parseCompanyDocument');
        Route::post('/clients', [CommercialController::class, 'store'])->name('clients.store');
        Route::put('/clients/{user}', [CommercialController::class, 'update'])->name('clients.update');

        Route::delete('/clients/{user}', [CommercialController::class, 'destroy'])->name('clients.destroy');

        Route::post('/prospects', [CommercialController::class, 'storeProspect'])->name('prospects.store');
        Route::put('/prospects/{prospect}/status', [CommercialController::class, 'updateProspectStatus'])->name('prospects.updateStatus');
        Route::delete('/prospects/{prospect}', [CommercialController::class, 'destroyProspect'])->name('prospects.destroy');

        Route::get('/prospections', [CommercialProspectionController::class, 'index'])->name('prospections.index');
        Route::get('/prospections/create', [CommercialProspectionController::class, 'create'])->name('prospections.create');
        Route::post('/prospections', [CommercialProspectionController::class, 'store'])->name('prospections.store');
        Route::get('/prospections/{prospection}', [CommercialProspectionController::class, 'show'])->name('prospections.show');
        Route::get('/prospections/{prospection}/download', [CommercialProspectionController::class, 'download'])->name('prospections.download');
        Route::get('/prospections/{prospection}/edit', [CommercialProspectionController::class, 'edit'])->name('prospections.edit');
        Route::put('/prospections/{prospection}', [CommercialProspectionController::class, 'update'])->name('prospections.update');
        Route::delete('/prospections/{prospection}', [CommercialProspectionController::class, 'destroy'])->name('prospections.destroy');
        Route::post('/prospections/{prospection}/submit', [CommercialProspectionController::class, 'submit'])->name('prospections.submit');
    });

    Route::middleware('commercial.supervisor')->prefix('commercial-supervisor')->name('commercial-supervisor.')->group(function () {
        Route::get('/', [CommercialSupervisorController::class, 'dashboard'])->name('dashboard');
        Route::get('/export', [CommercialSupervisorController::class, 'exportCsv'])->name('export');
        Route::get('/prospects', [CommercialSupervisorController::class, 'prospects'])->name('prospects.index');
        Route::get('/prospections', [CommercialSupervisorController::class, 'prospections'])->name('prospections.index');
        Route::get('/prospections/{prospection}', [CommercialSupervisorController::class, 'showProspection'])->name('prospections.show');
        Route::get('/prospections/{prospection}/download', [CommercialSupervisorController::class, 'downloadProspectionFile'])->name('prospections.download');
        Route::get('/{commercial}', [CommercialSupervisorController::class, 'showCommercial'])->name('commercial.show');
    });

    Route::middleware('commercial')->get('/dashboard/commercial', [CommercialController::class, 'index']);
});
