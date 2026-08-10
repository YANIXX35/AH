<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanComptableDefault extends Model
{
    protected $table = 'plan_comptable_defaults';

    protected $fillable = [
        'numero_compte',
        'libelle_compte',
        'prefix',
        'classe',
        'category',
        'subtype',
        'type_compte',
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
}
