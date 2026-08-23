<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetConfirmedMail;
use App\Mail\PasswordResetOtpMail;
use App\Models\AdminPasswordResetLink;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const OTP_RESEND_COOLDOWN_SECONDS = 60;

    /**
     * Comptes autorisés à choisir manuellement leur dashboard de destination
     * après connexion (mode aperçu/démo — accès à tous les portails, quel
     * que soit leur rôle réel en base). Liste volontairement restreinte et
     * codée en dur : ce n'est pas un mécanisme de permission généralisé.
     */
    private const DASHBOARD_SELECTOR_EMAILS = [
        'kyliyanisse@sitiame-capital.com',
    ];

    /**
     * Portails accessibles depuis l'écran de sélection, et la route
     * d'accueil réelle de chacun. Utilisé à la fois pour la redirection et
     * pour valider le contexte stocké en session (voir sidebar.blade.php).
     */
    private const DASHBOARD_PORTAL_ROUTES = [
        'entreprise' => 'dashboard',
        'accountant' => 'accountant.dashboard',
        'commercial' => 'commercial.dashboard',
        'commercial_supervisor' => 'commercial-supervisor.dashboard',
        'admin' => 'admin.dashboard',
    ];

    public static function isDashboardSelectorEmail(?string $email): bool
    {
        if ($email === null || trim($email) === '') {
            return false;
        }

        return in_array(mb_strtolower(trim($email)), self::DASHBOARD_SELECTOR_EMAILS, true);
    }

    public function showLogin(): View
    {
        return view('login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = (bool) $request->boolean('remember');
        if ($remember && method_exists(Auth::guard(), 'setRememberDuration')) {
            Auth::guard()->setRememberDuration((int) config('auth.remember_minutes', 43200));
        }

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $remember)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Identifiants invalides.',
                ], 'login');
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = $request->user();
        if ($user->account_suspended) {
            // Révoque les cookies "remember me" existants pour éviter toute reconnexion persistante.
            $user->forceFill(['remember_token' => Str::random(60)])->save();
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Ce compte est suspendu. Contactez le support ou l’administrateur de la plateforme.',
                ], 'login');
        }

        // Efface toute ancienne URL 'intended' stockée en session (ex: fird) pour garantir l'arrivée directe sur le dashboard.
        $request->session()->forget('url.intended');

        if (self::isDashboardSelectorEmail($user->email)) {
            $request->session()->forget('dashboard_preview_context');

            return redirect()->route('dashboard.selector');
        }

        if ($user->is_platform_admin) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->is_accountant ?? false) {
            return redirect()->route('accountant.dashboard');
        }

        if (($user->role_key ?? null) === 'commercial_supervisor') {
            return redirect()->route('commercial-supervisor.dashboard');
        }

        return redirect()->route('dashboard');
    }

    /**
     * Écran « Choisir un dashboard » — réservé aux comptes listés dans
     * DASHBOARD_SELECTOR_EMAILS. Un accès direct par un autre compte est
     * simplement renvoyé vers son dashboard habituel plutôt que de montrer
     * un sélecteur qui n'a aucune raison de lui être destiné.
     */
    public function showDashboardSelector(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! self::isDashboardSelectorEmail($user->email ?? null)) {
            return redirect()->route('dashboard');
        }

        return view('auth.dashboard-selector');
    }

    /**
     * Bascule le compte vers le portail choisi sur l'écran de sélection.
     * Enregistre le portail en session ("contexte d'aperçu") afin que le
     * sidebar (voir layouts/partials/sidebar.blade.php) affiche la vraie
     * navigation de ce portail plutôt que celle liée au rôle réel du
     * compte — sans ça, un compte administrateur ouvrant par exemple le
     * dashboard Commercial continuait de voir le menu Admin autour, puisque
     * le sidebar se base normalement sur is_platform_admin/is_accountant/
     * role_key, pas sur la page effectivement affichée.
     */
    public function enterDashboardPreview(Request $request, string $portal): RedirectResponse
    {
        $user = $request->user();

        if (! self::isDashboardSelectorEmail($user->email ?? null) || ! array_key_exists($portal, self::DASHBOARD_PORTAL_ROUTES)) {
            return redirect()->route('dashboard');
        }

        $request->session()->put('dashboard_preview_context', $portal);

        return redirect()->route(self::DASHBOARD_PORTAL_ROUTES[$portal]);
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetOtp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = mb_strtolower(trim((string) $data['email']));
        $user = User::query()->where('email', $email)->first();
        $mailSent = true;

        if ($user !== null) {
            $existingOtp = DB::table('password_reset_otps')
                ->where('email', $user->email)
                ->orderByDesc('id')
                ->first();

            if ($existingOtp !== null) {
                $createdAt = Carbon::parse((string) $existingOtp->created_at);
                $secondsSinceLastOtp = $createdAt->diffInSeconds(now());
                if ($secondsSinceLastOtp < self::OTP_RESEND_COOLDOWN_SECONDS) {
                    $secondsLeft = self::OTP_RESEND_COOLDOWN_SECONDS - $secondsSinceLastOtp;
                    $this->logAuthEvent('otp_resend_blocked', $user->id, $user->email, $request->ip(), 'cooldown='.$secondsLeft.'s');

                    return redirect()
                        ->route('password.reset.form', ['email' => $email])
                        ->withErrors([
                            'otp' => "Veuillez patienter {$secondsLeft} seconde(s) avant de demander un nouveau code.",
                        ]);
                }
            }

            $otp = (string) random_int(100000, 999999);
            $hash = hash('sha256', $otp);
            $expiresAt = now()->addMinutes(10);

            DB::table('password_reset_otps')->where('email', $user->email)->delete();
            DB::table('password_reset_otps')->insert([
                'email' => $user->email,
                'otp_hash' => $hash,
                'attempts' => 0,
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            try {
                Mail::to($user->email)->send(new PasswordResetOtpMail($user, $otp, 10));
                Log::info('password_reset_otp_mail_sent', [
                    'email' => $user->email,
                ]);
                $this->logAuthEvent('otp_sent', $user->id, $user->email, $request->ip(), 'expires_at='.$expiresAt->toDateTimeString());
                $mailSent = true;
            } catch (\Throwable $e) {
                // Pas de secours vers le mailer "log" : il écrirait le code OTP en clair dans les
                // journaux applicatifs tout en faisant croire à tort à l'utilisateur que l'e-mail a
                // été envoyé (il ne reçoit jamais rien). On échoue franchement à la place.
                Log::warning('password_reset_otp_mail_failed', [
                    'email' => $user->email,
                    'message' => $e->getMessage(),
                ]);
                $mailSent = false;
                $this->logAuthEvent('otp_send_failed', $user->id, $user->email, $request->ip(), $e->getMessage());
            }
        } else {
            Log::info('password_reset_otp_requested_for_unknown_email', [
                'email' => $email,
            ]);
            $this->logAuthEvent('otp_requested_unknown_email', null, $email, $request->ip(), null);
        }

        if (! $mailSent) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Impossible d’envoyer l’e-mail contenant le code OTP pour le moment. Vérifiez la configuration du serveur SMTP.',
                ]);
        }

        return redirect()
            ->route('password.reset.form', ['email' => $email])
            ->with('status', 'Un code OTP de réinitialisation à 6 chiffres a été envoyé à votre adresse e-mail. Veuillez le saisir ci-dessous.');
    }

    public function showResetPassword(Request $request): View
    {
        return view('auth.reset-password-otp', [
            'prefilledEmail' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPasswordWithOtp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        // Normaliser l'OTP pour accepter un copier/coller avec espaces/tirets.
        $normalizedOtp = preg_replace('/\D+/', '', (string) $data['otp']) ?? '';
        if (strlen($normalizedOtp) !== 6) {
            throw ValidationException::withMessages([
                'otp' => 'Le code OTP doit contenir exactement 6 chiffres.',
            ]);
        }

        $record = DB::table('password_reset_otps')
            ->where('email', $data['email'])
            ->orderByDesc('id')
            ->first();

        if ($record === null || now()->greaterThan($record->expires_at)) {
            $this->logAuthEvent('otp_check_expired_or_missing', null, $data['email'], $request->ip(), null);
            throw ValidationException::withMessages([
                'otp' => 'Code OTP invalide ou expiré. Demandez un nouveau code.',
            ]);
        }

        if ((int) $record->attempts >= 5) {
            $this->logAuthEvent('otp_check_too_many_attempts', null, $data['email'], $request->ip(), 'attempts='.(int) $record->attempts);
            throw ValidationException::withMessages([
                'otp' => 'Trop de tentatives OTP. Demandez un nouveau code.',
            ]);
        }

        $valid = hash_equals((string) $record->otp_hash, hash('sha256', $normalizedOtp));
        if (! $valid) {
            DB::table('password_reset_otps')->where('id', $record->id)->update([
                'attempts' => (int) $record->attempts + 1,
                'updated_at' => now(),
            ]);
            $this->logAuthEvent('otp_check_failed', null, $data['email'], $request->ip(), 'attempts='.((int) $record->attempts + 1));
            throw ValidationException::withMessages([
                'otp' => 'Code OTP incorrect.',
            ]);
        }

        /** @var User|null $user */
        $user = User::query()->where('email', $data['email'])->first();
        if ($user === null) {
            $this->logAuthEvent('otp_check_user_not_found', null, $data['email'], $request->ip(), null);
            throw ValidationException::withMessages([
                'email' => 'Compte introuvable.',
            ]);
        }

        $user->update([
            'password' => Hash::make($data['password']),
            // Invalide toutes les sessions persistantes existantes après changement de mot de passe.
            'remember_token' => Str::random(60),
        ]);

        DB::table('password_reset_otps')->where('email', $data['email'])->delete();

        try {
            Mail::to($user->email)->send(new PasswordResetConfirmedMail($user));
            $this->logAuthEvent('password_reset_confirmed_mail_sent', $user->id, $user->email, $request->ip(), null);
        } catch (\Throwable $e) {
            Log::warning('password_reset_confirmation_mail_failed', [
                'email' => $user->email,
                'message' => $e->getMessage(),
            ]);
            $this->logAuthEvent('password_reset_confirmed_mail_failed', $user->id, $user->email, $request->ip(), $e->getMessage());
        }

        $this->logAuthEvent('password_reset_completed', $user->id, $user->email, $request->ip(), null);

        return redirect()
            ->route('login')
            ->with('status', 'Mot de passe réinitialisé avec succès. Un e-mail de confirmation a été envoyé.');
    }

    /**
     * Page de définition de mot de passe via un lien à usage unique généré
     * par un platform admin (pas d'e-mail, pas de code OTP nécessaire).
     */
    public function showPasswordResetLink(string $token): View|RedirectResponse
    {
        $link = AdminPasswordResetLink::findValidByToken($token);

        if ($link === null) {
            return redirect()->route('login')->withErrors([
                'reset_link' => 'Ce lien de réinitialisation est invalide ou a expiré. Demandez-en un nouveau à votre administrateur.',
            ], 'login');
        }

        return view('auth.reset-password-link', ['token' => $token, 'user' => $link->user]);
    }

    public function submitPasswordResetLink(Request $request, string $token): RedirectResponse
    {
        $link = AdminPasswordResetLink::findValidByToken($token);

        if ($link === null) {
            return redirect()->route('login')->withErrors([
                'reset_link' => 'Ce lien de réinitialisation est invalide ou a expiré. Demandez-en un nouveau à votre administrateur.',
            ], 'login');
        }

        $data = $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = $link->user;
        $user->update([
            'password' => Hash::make($data['password']),
            'remember_token' => Str::random(60),
        ]);

        $link->update([
            'used_at' => now(),
            'used_from_ip' => $request->ip(),
        ]);

        try {
            Mail::to($user->email)->send(new PasswordResetConfirmedMail($user));
        } catch (\Throwable $e) {
            Log::warning('password_reset_link_confirmation_mail_failed', [
                'email' => $user->email,
                'message' => $e->getMessage(),
            ]);
        }

        $this->logAuthEvent('password_reset_link_used', $user->id, $user->email, $request->ip(), null);

        return redirect()
            ->route('login')
            ->with('status', 'Mot de passe défini avec succès. Vous pouvez maintenant vous connecter.');
    }

    protected function logAuthEvent(string $event, ?int $actorUserId, ?string $email, ?string $ip, ?string $detail): void
    {
        try {
            DB::table('auth_event_logs')->insert([
                'actor_user_id' => $actorUserId,
                'email' => $email,
                'event' => $event,
                'detail' => $detail,
                'ip_address' => $ip,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            // Ne jamais bloquer l'authentification si la journalisation échoue.
        }
    }
}
