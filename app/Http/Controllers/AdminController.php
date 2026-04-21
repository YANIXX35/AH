<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminUpdateUserRequest;
use App\Models\AccountingDocument;
use App\Models\AccountingEntry;
use App\Models\EnterpriseLicense;
use App\Models\InvestmentRequest;
use App\Models\SupportTicket;
use App\Models\TreasuryTransaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminController extends Controller
{
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
            'generatedAt' => now(),
        ]);
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

        // Les administrateurs plateforme ne sont pas gérés comme Gratuit / Premium.
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

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('status', 'Compte mis à jour.');
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
