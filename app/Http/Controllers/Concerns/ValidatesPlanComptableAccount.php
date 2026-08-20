<?php

namespace App\Http\Controllers\Concerns;

use App\Models\PlanComptableAccount;
use Illuminate\Validation\ValidationException;

trait ValidatesPlanComptableAccount
{
    /**
     * Vérifie que le compte saisi (format "CODE Libellé") correspond bien à un
     * compte réellement présent dans le plan comptable de l'entreprise
     * (plan_comptable_accounts), synchronisé depuis le référentiel SYSCOHADA.
     */
    protected function assertAccountBelongsToWorkspacePlan(string $account, string $field = 'debit_account'): void
    {
        $this->ensureWorkspacePlanSeeded();

        $code = preg_match('/^(\d[0-9]{0,8})\b/', trim($account), $matches) ? $matches[1] : null;

        $exists = $code !== null && PlanComptableAccount::where('user_id', $this->workspaceUserId())
            ->where('numero_compte', $code)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                $field => "Le compte « {$account} » n'existe pas dans le plan comptable de l'entreprise. Sélectionnez un compte dans la liste proposée.",
            ]);
        }
    }

    /**
     * Certains dossiers client n'ont jamais eu leur plan comptable importé
     * dans plan_comptable_accounts (aucun upload, aucune initialisation) :
     * getPlanAccounts() bascule alors sur un plan par défaut sommaire
     * (9 classes, sans numéro de compte détaillé) pour l'affichage, mais la
     * recherche de compte et cette validation, elles, n'avaient aucun filet
     * — la recherche ne renvoyait jamais rien et l'enregistrement échouait
     * silencieusement pour ces dossiers. On régénère ici le plan SYSCOHADA
     * complet (référentiel plan_comptable_defaults) dès qu'il est vide,
     * pour que la recherche et la validation aient toujours de vraies
     * données à interroger.
     */
    protected function ensureWorkspacePlanSeeded(): void
    {
        $userId = $this->workspaceUserId();

        if (! PlanComptableAccount::where('user_id', $userId)->exists()) {
            PlanComptableAccount::seedDefaultsFor($userId);
        }
    }
}
