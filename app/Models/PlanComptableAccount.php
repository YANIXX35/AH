<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanComptableAccount extends Model
{
    protected $table = 'plan_comptable_accounts';

    protected $fillable = [
        'user_id',
        'prefix',
        'label',
        'category',
        'subtype',
        'numero_compte',
        'libelle_compte',
        'type_compte',
        'sous_type',
        'classe',
        'observation',
        'nature',
        'categorie_bceao',
        'flux_tafire',
        'eligible_tva',
        'eligible_echeancier',
        'lie_immobilisation',
        'is_actif',
        'sort_order',
    ];

    protected $casts = [
        'is_actif' => 'boolean',
        'eligible_echeancier' => 'boolean',
        'lie_immobilisation' => 'boolean',
        'sort_order' => 'integer',
    ];

    public $timestamps = true;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Installe le plan comptable SYSCOHADA par défaut (classes 1 à 9) pour un
     * utilisateur donné, en remplaçant tout plan existant. Utilisé à la création
     * d'un nouveau compte entreprise et par le bouton "Réinitialiser".
     */
    public static function seedDefaultsFor(int $userId): void
    {
        $defaults = require base_path('database/data/syscohada_plan_comptable_default.php');

        static::where('user_id', $userId)->delete();

        $now = now();
        $rows = array_map(static fn (array $account) => [
            'user_id' => $userId,
            'prefix' => $account['prefix'],
            'label' => $account['label'],
            'category' => $account['category'],
            'subtype' => $account['subtype'],
            'numero_compte' => $account['numero_compte'],
            'libelle_compte' => $account['libelle_compte'],
            'type_compte' => $account['type_compte'],
            'sous_type' => null,
            'classe' => $account['classe'],
            'observation' => $account['observation'],
            'nature' => $account['nature'] ?? null,
            'categorie_bceao' => $account['categorie_bceao'] ?? null,
            'flux_tafire' => $account['flux_tafire'] ?? null,
            'eligible_tva' => $account['eligible_tva'] ?? null,
            'eligible_echeancier' => $account['eligible_echeancier'] ?? false,
            'lie_immobilisation' => $account['lie_immobilisation'] ?? false,
            'is_actif' => $account['is_actif'],
            'sort_order' => $account['sort_order'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $defaults);

        foreach (array_chunk($rows, 250) as $chunk) {
            static::insert($chunk);
        }
    }
}
