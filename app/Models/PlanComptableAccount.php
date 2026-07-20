<?php

namespace App\Models;

use App\Models\User;
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
        'is_actif',
        'sort_order',
    ];

    protected $casts = [
        'is_actif' => 'boolean',
        'sort_order' => 'integer',
    ];

    public $timestamps = true;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
