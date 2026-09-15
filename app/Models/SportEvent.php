<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SportEvent extends Model
{
    protected $fillable = [
        'user_id',
        'erpnext_event_name',
        'subject',
        'starts_on',
        'description',
    ];

    protected $casts = [
        'starts_on' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
