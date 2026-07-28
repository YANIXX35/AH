<?php

namespace App\Http\Controllers;

use App\Contracts\FinancialRatioServiceContract;
use App\Models\User;
use App\Support\ClientWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AccountantClientController extends Controller
{
    /**
     * Liste des dossiers clients (entreprises) suivis sur la plateforme.
     */
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->clients()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', '%'.$q.'%')
                        ->orWhere('email', 'like', '%'.$q.'%')
                        ->orWhere('company_name', 'like', '%'.$q.'%');
                });
            })
            ->orderBy('company_name')
            ->orderBy('name')
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'company_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $accountant = $request->user();

        DB::transaction(function () use ($validated, $accountant, $request) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'company_name' => $validated['company_name'],
                'password' => Hash::make($validated['password']),
                'role_key' => 'manager',
                'created_by_user_id' => $accountant->id,
                'kyc_status' => 'submitted',
                'kyc_submitted_at' => now(),
            ]);

            app(\App\Services\UserPremiumService::class)->activateForDays(
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
        if (! ClientWorkspace::isAssignableClient($user)) {
            abort(404);
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
