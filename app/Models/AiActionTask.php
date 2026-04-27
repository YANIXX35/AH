<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiActionTask extends Model
{
    protected $fillable = [
        'title',
        'description',
        'source',
        'priority',
        'status',
        'created_by_user_id',
        'assigned_to_user_id',
        'completed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}

