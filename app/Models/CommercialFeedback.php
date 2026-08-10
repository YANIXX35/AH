<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialFeedback extends Model
{
    protected $table = 'commercial_feedback';

    protected $fillable = [
        'user_id',
        'rating',
        'satisfaction_label',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
