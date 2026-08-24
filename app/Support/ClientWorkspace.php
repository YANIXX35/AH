<?php

namespace App\Support;

use App\Models\EnterpriseLicense;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Contexte « dossier client » (comptable / admin) et partage de données équipe entreprise (même licence).
 */
final class ClientWorkspace
{
    public const SESSION_KEY = 'accountant_workspace_user_id';

    /**
     * Identifiant utilisateur pour le stockage « dossier » (plan comptable, nouvelles écritures : compte pivot entreprise).
     * Comptable : client en session. Entreprise sous licence : utilisateur principal de la licence. Sinon : utilisateur connecté.
     */
    public static function effectiveUserId(): int
    {
        $auth = Auth::user();
        if (! $auth) {
            return 0;
        }

        $sid = session(self::SESSION_KEY);
        if ($sid !== null && self::canUseWorkspace($auth)) {
            $target = User::query()->find((int) $sid);
            if ($target && self::isAssignableClient($target)) {
                return (int) $target->id;
            }
        }

        if (self::isEnterpriseTeammate($auth)) {
            $primary = self::enterprisePrimaryWorkspaceUserId($auth);
            if ($primary !== null) {
                return $primary;
            }
        }

        return (int) $auth->id;
    }

    /**
     * Tous les user_id dont les données (écritures, trésorerie…) sont visibles pour le contexte courant (équipe = même licence).
     *
     * @return list<int>
     */
    public static function dataScopeUserIds(?User $auth = null): array
    {
        $auth = $auth ?? Auth::user();
        if (! $auth) {
            return [];
        }

        $sid = session(self::SESSION_KEY);
        if ($sid !== null && self::canUseWorkspace($auth)) {
            $target = User::query()->find((int) $sid);
            if ($target && self::isAssignableClient($target)) {
                return [(int) $target->id];
            }
        }

        if (self::isEnterpriseTeammate($auth)) {
            return User::query()
                ->clients()
                ->where('enterprise_license_id', $auth->enterprise_license_id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return [(int) $auth->id];
    }

    public static function canUseWorkspace(User $user): bool
    {
        return (bool) (
            ($user->is_accountant ?? false)
            || ($user->is_platform_admin ?? false)
            || ($user->role_key ?? null) === 'financial_analyst'
        );
    }

    /**
     * Dossiers ouvrables par le cabinet ou l'analyste financier (pas admin
     * plateforme, pas comptable, pas un autre analyste financier).
     */
    public static function isAssignableClient(User $target): bool
    {
        if ($target->is_platform_admin) {
            return false;
        }
        if ($target->is_accountant ?? false) {
            return false;
        }
        if (($target->role_key ?? null) === 'financial_analyst') {
            return false;
        }

        return true;
    }

    public static function setWorkspaceUserId(int $clientUserId): void
    {
        session([self::SESSION_KEY => $clientUserId]);
    }

    public static function clearWorkspace(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Supprime la session dossier si elle est incohérente (cible absente, non entreprise, ou compte sans droit cabinet).
     */
    public static function sanitizeStaleSession(): void
    {
        $auth = Auth::user();
        if ($auth === null) {
            return;
        }

        if (! self::canUseWorkspace($auth)) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        $sid = session(self::SESSION_KEY);
        if ($sid === null) {
            return;
        }

        $target = User::query()->find((int) $sid);
        if ($target === null || ! self::isAssignableClient($target)) {
            session()->forget(self::SESSION_KEY);
        }
    }

    public static function isViewingClient(): bool
    {
        $auth = Auth::user();
        if (! $auth || ! self::canUseWorkspace($auth)) {
            return false;
        }

        $sid = session(self::SESSION_KEY);

        return $sid !== null && (int) $sid !== (int) $auth->id;
    }

    public static function workspaceTarget(): ?User
    {
        $id = session(self::SESSION_KEY);
        if ($id === null) {
            return null;
        }

        return User::query()->find((int) $id);
    }

    protected static function isEnterpriseTeammate(User $auth): bool
    {
        if ($auth->is_platform_admin ?? false) {
            return false;
        }
        if ($auth->is_accountant ?? false) {
            return false;
        }

        return $auth->enterprise_license_id !== null;
    }

    /**
     * Compte « pivot » entreprise : toutes les écritures nouvelles y sont rattachées ; l’auteur réel est dans actor_user_id.
     */
    protected static function enterprisePrimaryWorkspaceUserId(User $auth): ?int
    {
        if ($auth->enterprise_license_id === null) {
            return null;
        }

        $license = EnterpriseLicense::query()->find($auth->enterprise_license_id);
        if ($license === null) {
            return null;
        }

        if ($license->primary_workspace_user_id) {
            return (int) $license->primary_workspace_user_id;
        }

        $minId = User::query()
            ->clients()
            ->where('enterprise_license_id', $license->id)
            ->min('id');

        return $minId ? (int) $minId : (int) $auth->id;
    }
}
