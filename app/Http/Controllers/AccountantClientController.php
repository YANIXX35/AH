<?php

namespace App\Http\Controllers;

use App\Contracts\FinancialRatioServiceContract;
use App\Models\User;
use App\Services\UserPremiumService;
use App\Support\ClientWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountantClientController extends Controller
{
    public function __construct(
        private readonly UserPremiumService $userPremium
    ) {}
    /**
     * Liste des dossiers clients (entreprises) suivis sur la plateforme.
     */
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $user = $request->user();

        $query = User::query()->clients();

        if ($user && ! $user->is_platform_admin) {
            $query->where('created_by_user_id', $user->id);
        }

        $users = $query
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', '%'.$q.'%')
                        ->orWhere('email', 'like', '%'.$q.'%')
                        ->orWhere('company_name', 'like', '%'.$q.'%');
                });
            })
            ->orderByDesc('created_at')
            ->get();

        $enterpriseGroups = $this->groupUsersByEnterprise($users);

        return view('accountant.clients-index', [
            'enterpriseGroups' => $enterpriseGroups,
            'search' => $q,
        ]);
    }

    /**
     * Enregistre un nouveau client / entreprise depuis l'espace comptable.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_sigle' => ['nullable', 'string', 'max:255'],
            'company_tax_id' => ['nullable', 'string', 'max:255'],
            'company_logo' => ['nullable', 'image', 'max:5120'],
            'sector' => ['nullable', 'string', 'max:255'],
            'rccm' => ['nullable', 'string', 'max:255'],
            'trade_register' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'license_key' => ['nullable', 'string', 'max:64'],
        ]);

        // Valeurs par défaut si les champs obligatoires sont absents
        if (empty($validated['email'])) {
            $validated['email'] = 'client_' . uniqid() . '@sitiame-capital.com';
        }
        if (empty($validated['name'])) {
            $validated['name'] = $validated['company_name'] ?? 'Client sans nom';
        }
        $plainPassword = $validated['password'] ?? \Illuminate\Support\Str::random(12);

        if ($request->hasFile('company_logo')) {
            $validated['company_logo'] = $request->file('company_logo')->store('company-logos', 'public');
        }

        if ($request->hasFile('trade_register')) {
            $validated['trade_register_file'] = $request->file('trade_register')->store('trade-registers', 'public');
        }

        unset($validated['trade_register'], $validated['license_key']);

        $accountant = $request->user();

        DB::transaction(function () use ($validated, $accountant, $request, $plainPassword) {
            $user = User::create([
                ...$validated,
                'password' => Hash::make($plainPassword),
                'role_key' => 'manager',
                'created_by_user_id' => $accountant->id,
                'kyc_status' => 'submitted',
                'kyc_submitted_at' => now(),
            ]);

            $this->userPremium->activateForDays(
                $user,
                30,
                'accountant_creation',
                "Dossier client créé par le cabinet comptable ({$accountant->name}).",
                $accountant,
                ['accountant_id' => $accountant->id],
                $request
            );
        });

        return back()->with('status', 'Dossier client enregistré avec succès. Son compte a été initialisé avec 1 mois d’accès gratuit.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $accountant = $request->user();
        if ($accountant && ! $accountant->is_platform_admin && $user->created_by_user_id !== $accountant->id) {
            abort(403, 'Vous n’êtes pas autorisé à modifier ce dossier client.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_sigle' => ['nullable', 'string', 'max:255'],
            'company_tax_id' => ['nullable', 'string', 'max:255'],
            'company_logo' => ['nullable', 'image', 'max:5120'],
            'sector' => ['nullable', 'string', 'max:255'],
            'rccm' => ['nullable', 'string', 'max:255'],
            'trade_register' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        if ($request->hasFile('company_logo')) {
            $validated['company_logo'] = $request->file('company_logo')->store('company-logos', 'public');
        }

        if ($request->hasFile('trade_register')) {
            $validated['trade_register_file'] = $request->file('trade_register')->store('trade-registers', 'public');
        }

        unset($validated['trade_register']);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return back()->with('status', 'Informations du dossier client mises à jour avec succès.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $accountant = $request->user();
        if ($accountant && ! $accountant->is_platform_admin && $user->created_by_user_id !== $accountant->id) {
            abort(403, 'Vous n’êtes pas autorisé à supprimer ce dossier client.');
        }

        $user->delete();

        return back()->with('status', 'Dossier client supprimé avec succès.');
    }

    /**
     * Fiche dossier : synthèse et accès aux outils (compta, trésorerie, FIRD…).
     */
    public function show(User $user, FinancialRatioServiceContract $ratioService): View
    {
        $this->authorizeClient($user);

        $analysis = $ratioService->analyze($user->id);

        return view('accountant.clients-show', [
            'client' => $user,
            'analysis' => $analysis,
        ]);
    }

    /**
     * Ouvre le « dossier » en session puis redirige vers la comptabilité du client.
     */
    public function selectWorkspace(Request $request, User $user): RedirectResponse
    {
        $this->authorizeClient($user);

        ClientWorkspace::setWorkspaceUserId((int) $user->id);

        $target = $request->input('redirect', 'accounting');
        $allowed = [
            'accounting' => route('accounting'),
            'treasury' => route('treasury.tracking'),
            'fird' => route('profile.company.fird'),
            'investor' => route('investor.readiness'),
        ];

        $url = $allowed[$target] ?? route('accounting');

        return redirect()->to($url)->with('status', 'Dossier ouvert : '.$this->clientLabel($user).'.');
    }

    /**
     * Ferme le contexte dossier client.
     */
    public function clearWorkspace(Request $request): RedirectResponse
    {
        ClientWorkspace::clearWorkspace();

        return redirect()
            ->route('accountant.dashboard')
            ->with('status', 'Vous êtes revenu sur votre espace personnel.');
    }

    private function authorizeClient(User $user): void
    {
        $authUser = auth()->user();
        if (! ClientWorkspace::isAssignableClient($user)) {
            abort(404);
        }
        if ($authUser && ! $authUser->is_platform_admin && $user->created_by_user_id !== $authUser->id) {
            abort(403, 'Vous n’avez pas accès à ce dossier client.');
        }
    }

    private function clientLabel(User $user): string
    {
        $name = $user->company_name ?: $user->name;

        return $name ?: $user->email;
    }

    /**
     * Regroupe les comptes clients par entreprise (licence, NIF puis nom).
     *
     * @param  Collection<int, User>  $users
     * @return Collection<int, array<string, mixed>>
     */
    private function groupUsersByEnterprise(Collection $users): Collection
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
