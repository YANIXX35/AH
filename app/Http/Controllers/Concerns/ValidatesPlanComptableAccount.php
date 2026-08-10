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
}
