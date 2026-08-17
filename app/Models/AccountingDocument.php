<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingDocument extends Model
{
    protected $fillable = [
        'user_id',
        'actor_user_id',
        'original_name',
        'stored_path',
        'document_type',
        'status',
        'document_hash',
        'extracted_data',
        'confidence',
        'compliance_rate',
        'last_reminder_sent_at',
    ];

    protected $casts = [
        'extracted_data' => 'array',
        'confidence' => 'decimal:2',
        'compliance_rate' => 'decimal:2',
        'last_reminder_sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(AccountingEntry::class, 'document_id');
    }
}
