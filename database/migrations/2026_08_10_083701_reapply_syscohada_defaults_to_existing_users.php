<?php

use App\Models\PlanComptableAccount;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Point garanti de rattrapage : à ce stade, plan_comptable_defaults existe
     * et est peuplée, et plan_comptable_accounts a bien toutes ses colonnes
     * étendues. On (re)propage le plan de référence à tous les comptes qui en
     * ont déjà un, que la migration 2026_08_10_072803 ait pu le faire ou non
     * lors de son propre passage.
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
