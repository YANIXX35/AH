<?php

use App\Models\PlanComptableAccount;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Remplace le plan comptable de tous les comptes qui en avaient déjà un
     * (ancien plan skeleton ou import précédent) par le nouveau plan SYSCOHADA
     * complet (1455 comptes, classes 1 à 9). Les écritures comptables existantes
     * ne sont pas concernées : elles stockent le numéro de compte en texte libre,
     * pas en référence vers plan_comptable_accounts.
     */
    public function up(): void
    {
        $userIds = PlanComptableAccount::query()->distinct()->pluck('user_id');

        foreach ($userIds as $userId) {
            if ($userId === null) {
                continue;
            }

            PlanComptableAccount::seedDefaultsFor((int) $userId);
        }
    }

    public function down(): void
    {
        // Non réversible : on ne peut pas reconstituer les anciens plans écrasés.
    }
};
