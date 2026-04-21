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
    ];

    public $timestamps = true;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
