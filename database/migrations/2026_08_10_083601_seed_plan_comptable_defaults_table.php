<?php

use App\Models\PlanComptableDefault;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (PlanComptableDefault::query()->exists()) {
            return;
        }

        $accounts = require base_path('database/data/syscohada_plan_comptable_default.php');
        $now = now();

        $rows = array_map(static fn (array $a) => [
            'numero_compte' => $a['numero_compte'],
            'libelle_compte' => $a['libelle_compte'],
            'prefix' => $a['prefix'],
            'classe' => $a['classe'],
            'category' => $a['category'],
            'subtype' => $a['subtype'],
            'type_compte' => $a['type_compte'],
            'observation' => $a['observation'],
            'nature' => $a['nature'] ?? null,
            'categorie_bceao' => $a['categorie_bceao'] ?? null,
            'flux_tafire' => $a['flux_tafire'] ?? null,
            'eligible_tva' => $a['eligible_tva'] ?? null,
            'eligible_echeancier' => $a['eligible_echeancier'] ?? false,
            'lie_immobilisation' => $a['lie_immobilisation'] ?? false,
            'is_actif' => $a['is_actif'],
            'sort_order' => $a['sort_order'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $accounts);

        foreach (array_chunk($rows, 250) as $chunk) {
            PlanComptableDefault::insert($chunk);
        }
    }

    public function down(): void
    {
        PlanComptableDefault::query()->delete();
    }
};
