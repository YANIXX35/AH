<?php

namespace App\Http\Controllers;

use App\Models\AccountingDocument;
use App\Models\AccountingEntry;
use App\Models\EnterpriseLicense;
use App\Models\MenuActionLog;
use App\Models\TreasuryAuditLog;
use App\Models\User;
use App\Services\TreasuryAudit;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Création de comptes collègues sous la même licence entreprise (sièges restants).
 */
class EnterpriseTeamController extends Controller
{
    /**
     * Formulaire et liste des comptes rattachés à la même licence.
     */
    public function index(Request $request): View
    {
        [$inviter, $license, $teammates, $teamUserIds] = $this->resolveTeamContext($request);
        $activityLogs = $this->buildCompanyLogs($teamUserIds)->take(120);
        $used = $license->seatsUsed();
        $max = $license->max_seats;
        $canAdd = $license->isUsable() && $used < $max;

        return view('profile-team', [
            'user' => $inviter,
            'license' => $license,
            'teammates' => $teammates,
            'activityLogs' => $activityLogs,
            'seatsUsed' => $used,
            'seatsMax' => $max,
            'canAdd' => $canAdd,
        ]);
    }

    /**
     * Historique dédié entreprise avec filtres (acteur, module, période, recherche).
     */
    public function history(Request $request): View
    {
        [$inviter, $license, $teammates, $teamUserIds] = $this->resolveTeamContext($request);
        $logs = $this->buildCompanyLogs($teamUserIds);
        $logs = $this->filterLogs($logs, $request, $teamUserIds);

        return view('profile-team-history', [
            'user' => $inviter,
            'license' => $license,
            'teammates' => $teammates,
            'logs' => $logs->take(500),
            'filters' => [
                'module' => (string) $request->query('module', 'all'),
                'actor_user_id' => (int) $request->query('actor_user_id', 0),
                'date_from' => (string) $request->query('date_from', ''),
                'date_to' => (string) $request->query('date_to', ''),
                'q' => trim((string) $request->query('q', '')),
            ],
        ]);
    }

    /**
     * Crée un compte collègue (même entreprise, même licence).
     */
    public function store(Request $request): RedirectResponse
    {
        $inviter = $request->user();
        $this->authorizeEnterpriseInviter($inviter);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        DB::transaction(function () use ($inviter, $validated) {
            /** @var EnterpriseLicense $license */
            $license = EnterpriseLicense::query()
                ->whereKey($inviter->enterprise_license_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $license->isUsable()) {
                throw ValidationException::withMessages([
                    'email' => 'La licence n’est plus valide (révoquée ou expirée).',
                ]);
            }

            if (! $license->hasAvailableSeat()) {
                throw ValidationException::withMessages([
                    'email' => 'Nombre maximum de comptes atteint pour cette licence ('.$license->max_seats.').',
                ]);
            }

            User::create(array_merge(
                $inviter->sharedProfileForNewTeammate(),
                [
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'enterprise_license_id' => $license->id,
                    'is_platform_admin' => false,
                    'is_accountant' => false,
                    'account_suspended' => false,
                    'suspended_at' => null,
                    'suspended_reason' => null,
                ]
            ));

            $license->syncPrimaryWorkspaceUser();
        });

        return redirect()
            ->route('profile.team')
            ->with('status', 'Compte créé. Le collaborateur peut se connecter avec l’e-mail et le mot de passe définis.');
    }

    /**
     * Met à jour le nom/e-mail d’un collaborateur rattaché à la même licence.
     */
    public function update(Request $request, User $teammate): RedirectResponse
    {
        $inviter = $request->user();
        $this->authorizeEnterpriseInviter($inviter);
        $this->authorizeTeammate($inviter, $teammate);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$teammate->id],
        ]);

        $teammate->update($validated);

        return redirect()->route('profile.team')->with('status', 'Collaborateur mis à jour.');
    }

    /**
     * Retire un collaborateur de l’équipe : suspend son compte (connexion bloquée, historique
     * conservé) plutôt qu’une suppression — un compte a déjà pu créer des écritures/documents
     * référencés en piste d’audit. La suspension libère aussitôt le siège (seatsUsed() exclut
     * déjà les comptes suspendus).
     */
    public function destroy(Request $request, User $teammate): RedirectResponse
    {
        $inviter = $request->user();
        $this->authorizeEnterpriseInviter($inviter);
        $this->authorizeTeammate($inviter, $teammate);

        $teammate->update([
            'account_suspended' => true,
            'suspended_at' => now(),
            'suspended_reason' => 'Retiré de l’équipe par '.$inviter->name.' ('.$inviter->email.').',
        ]);

        TreasuryAudit::log($inviter->id, 'profile.team.member_removed', $teammate, [
            'actor_user_id' => $inviter->id,
            'teammate_name' => $teammate->name,
            'teammate_email' => $teammate->email,
        ]);

        return redirect()->route('profile.team')->with('status', 'Collaborateur retiré de l’équipe. Le siège est de nouveau disponible.');
    }

    /**
     * Un collaborateur ne peut être géré que par un membre de la même licence, jamais lui-même.
     */
    protected function authorizeTeammate(User $inviter, User $teammate): void
    {
        if ($teammate->id === $inviter->id) {
            abort(403, 'Vous ne pouvez pas gérer votre propre compte depuis cette page.');
        }
        if ($teammate->enterprise_license_id === null || $teammate->enterprise_license_id !== $inviter->enterprise_license_id) {
            abort(403, 'Ce compte n’appartient pas à votre licence.');
        }
    }

    /**
     * Réservé aux comptes entreprise disposant d’une licence.
     */
    protected function authorizeEnterpriseInviter(?User $user): void
    {
        if ($user === null) {
            abort(403);
        }
        if ($user->isPlatformAdmin() || $user->isAccountant()) {
            abort(403);
        }
        if ($user->enterprise_license_id === null) {
            abort(403, 'Aucune licence entreprise rattachée à votre compte.');
        }
        if (! $user->hasActivePremiumPeriod()) {
            abort(403, 'Fonction Équipe (licence) réservée aux comptes Premium actifs.');
        }
    }

    /**
     * Prépare le contexte équipe entreprise.
     *
     * @return array{0: User, 1: EnterpriseLicense, 2: Collection<int, User>, 3: array<int, int>}
     */
    protected function resolveTeamContext(Request $request): array
    {
        $inviter = $request->user();
        $this->authorizeEnterpriseInviter($inviter);

        $inviter->loadMissing('enterpriseLicense');
        $license = $inviter->enterpriseLicense;
        if ($license === null) {
            abort(404);
        }
        $license->syncPrimaryWorkspaceUser();

        $teammates = User::query()
            ->clients()
            ->where('enterprise_license_id', $license->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'created_at', 'account_suspended']);
        $teamUserIds = $teammates->pluck('id')->map(fn ($id) => (int) $id)->all();

        return [$inviter, $license, $teammates, $teamUserIds];
    }

    /**
     * Assemble les logs de l’entreprise (menu, trésorerie, comptabilité).
     *
     * @param  array<int, int>  $teamUserIds
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildCompanyLogs(array $teamUserIds): Collection
    {
        $menuLogs = MenuActionLog::query()
            ->with('user:id,name,email')
            ->whereIn('user_id', $teamUserIds)
            ->latest('created_at')
            ->limit(300)
            ->get()
            ->map(function (MenuActionLog $row) {
                return [
                    'module' => 'menu',
                    'created_at' => $row->created_at,
                    'actor_id' => (int) $row->user_id,
                    'actor_name' => $row->user?->name ?? '—',
                    'actor_email' => $row->user?->email,
                    'action' => 'Navigation menu',
                    'detail' => strtoupper((string) $row->http_method).' '.$row->route_name.' · '.$row->path.' · HTTP '.$row->status_code,
                    'ip' => $row->ip,
                ];
            });

        $treasuryActionLabels = [
            'treasury.period.lock' => 'Verrouillage période',
            'treasury.period.unlock' => 'Déverrouillage période',
            'treasury.forecast.validate' => 'Validation prévision',
            'treasury.transaction.create' => 'Création transaction',
            'treasury.transaction.update' => 'Mise à jour transaction',
            'treasury.transaction.delete' => 'Suppression transaction',
        ];
        $treasuryLogs = TreasuryAuditLog::query()
            ->with(['actor:id,name,email', 'user:id,name,email'])
            ->whereIn('user_id', $teamUserIds)
            ->latest('created_at')
            ->limit(300)
            ->get()
            ->map(function (TreasuryAuditLog $row) use ($treasuryActionLabels) {
                $actor = $row->actor ?? $row->user;

                return [
                    'module' => 'treasury',
                    'created_at' => $row->created_at,
                    'actor_id' => (int) ($row->actor_user_id ?? $row->user_id),
                    'actor_name' => $actor?->name ?? '—',
                    'actor_email' => $actor?->email,
                    'action' => $treasuryActionLabels[$row->action] ?? $row->action,
                    'detail' => ! empty($row->properties)
                        ? (string) json_encode($row->properties, JSON_UNESCAPED_UNICODE)
                        : '—',
                    'ip' => $row->ip_address,
                ];
            });

        $entryLogs = AccountingEntry::query()
            ->with(['actor:id,name,email', 'user:id,name,email'])
            ->whereIn('user_id', $teamUserIds)
            ->latest('created_at')
            ->limit(300)
            ->get()
            ->map(function (AccountingEntry $row) {
                $actor = $row->actor ?? $row->user;
                $isUpdated = $row->updated_at !== null && $row->created_at !== null && $row->updated_at->gt($row->created_at);

                return [
                    'module' => 'accounting',
                    'created_at' => $isUpdated ? $row->updated_at : $row->created_at,
                    'actor_id' => (int) ($row->actor_user_id ?? $row->user_id),
                    'actor_name' => $actor?->name ?? '—',
                    'actor_email' => $actor?->email,
                    'action' => $isUpdated ? 'Mise à jour écriture' : 'Création écriture',
                    'detail' => ($row->document_type ?? 'Écriture').' · '.$row->description.' · '.$row->amount,
                    'ip' => null,
                ];
            });

        $documentLogs = AccountingDocument::query()
            ->with(['actor:id,name,email', 'user:id,name,email'])
            ->whereIn('user_id', $teamUserIds)
            ->latest('created_at')
            ->limit(300)
            ->get()
            ->map(function (AccountingDocument $row) {
                $actor = $row->actor ?? $row->user;
                $isUpdated = $row->updated_at !== null && $row->created_at !== null && $row->updated_at->gt($row->created_at);

                return [
                    'module' => 'accounting',
                    'created_at' => $isUpdated ? $row->updated_at : $row->created_at,
                    'actor_id' => (int) ($row->actor_user_id ?? $row->user_id),
                    'actor_name' => $actor?->name ?? '—',
                    'actor_email' => $actor?->email,
                    'action' => $isUpdated ? 'Mise à jour document' : 'Import document',
                    'detail' => ($row->original_name ?? 'Document').' · statut '.$row->status,
                    'ip' => null,
                ];
            });

        return $menuLogs
            ->concat($treasuryLogs)
            ->concat($entryLogs)
            ->concat($documentLogs)
            ->sortByDesc(fn ($row) => $row['created_at']?->getTimestamp() ?? 0)
            ->values();
    }

    /**
     * Applique les filtres utilisateur / module / période / recherche.
     *
     * @param  Collection<int, array<string, mixed>>  $logs
     * @param  array<int, int>  $allowedActorIds
     * @return Collection<int, array<string, mixed>>
     */
    protected function filterLogs(Collection $logs, Request $request, array $allowedActorIds): Collection
    {
        $module = (string) $request->query('module', 'all');
        $actorId = (int) $request->query('actor_user_id', 0);
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');
        $search = trim((string) $request->query('q', ''));

        if ($module !== 'all') {
            $logs = $logs->where('module', $module)->values();
        }
        if ($actorId > 0 && in_array($actorId, $allowedActorIds, true)) {
            $logs = $logs->where('actor_id', $actorId)->values();
        }
        if ($dateFrom !== '') {
            $from = Carbon::parse($dateFrom)->startOfDay();
            $logs = $logs->filter(fn ($row) => ($row['created_at'] ?? null) && $row['created_at']->gte($from))->values();
        }
        if ($dateTo !== '') {
            $to = Carbon::parse($dateTo)->endOfDay();
            $logs = $logs->filter(fn ($row) => ($row['created_at'] ?? null) && $row['created_at']->lte($to))->values();
        }
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $logs = $logs->filter(function ($row) use ($needle) {
                $text = mb_strtolower(
                    ($row['actor_name'] ?? '').' '.
                    ($row['actor_email'] ?? '').' '.
                    ($row['action'] ?? '').' '.
                    ($row['detail'] ?? '')
                );

                return str_contains($text, $needle);
            })->values();
        }

        return $logs;
    }
}
