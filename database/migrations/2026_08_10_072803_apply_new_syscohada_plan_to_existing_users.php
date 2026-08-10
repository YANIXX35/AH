<?php

use App\Models\PlanComptableAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remplace le plan comptable de tous les comptes qui en avaient déjà un
     * (ancien plan skeleton ou import précédent) par le nouveau plan SYSCOHADA
     * complet (1455 comptes, classes 1 à 9). Les écritures comptables existantes
     * ne sont pas concernées : elles stockent le numéro de compte en texte libre,
     * pas en référence vers plan_comptable_accounts.
     *
     * seedDefaultsFor() dépend de colonnes/table ajoutées par des migrations
     * postérieures à celle-ci (timestamp oblige). Si elles n'existent pas
     * encore, on ne fait rien ici : la migration
     * 2026_08_10_083701_reapply_syscohada_defaults_to_existing_users est le
     * point garanti où le rattrapage a réellement lieu, une fois toutes les
     * dépendances en place.
     */
    public function up(): void
    {
        if (! Schema::hasTable('plan_comptable_defaults') || ! Schema::hasColumn('plan_comptable_accounts', 'nature')) {
            return;
        }

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
