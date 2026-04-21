<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanComptableImport extends Model
{
    protected $table = 'plan_comptable_imports';

    protected $fillable = [
        'user_id',
        'original_filename',
        'status',
        'message',
        'valid_rows',
        'invalid_rows',
        'invalid_details',
    ];

    protected $casts = [
        'invalid_details' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
