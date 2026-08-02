<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminApprovalRequest extends Model
{
    protected $fillable = [
        'action_key',
        'target_type',
        'target_id',
        'payload',
        'status',
        'requested_by_user_id',
        'approved_by_user_id',
        'rejected_by_user_id',
        'approval_note',
        'rejection_note',
        'approved_at',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AdminApprovalAction::class, 'admin_approval_request_id');
    }
}
