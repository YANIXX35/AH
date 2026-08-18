<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentVerification extends Model
{
    protected $fillable = [
        'reference',
        'type',
        'user_id',
        'company_name',
        'company_sigle',
        'company_tax_id',
        'exercise_year',
        'total_actif',
        'total_passif',
        'resultat_net',
        'generated_at',
    ];

    protected $casts = [
        'total_actif' => 'float',
        'total_passif' => 'float',
        'resultat_net' => 'float',
        'generated_at' => 'datetime',
    ];
}
