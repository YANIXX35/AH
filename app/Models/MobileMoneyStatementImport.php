<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MobileMoneyStatementImport extends Model
{
    protected $fillable = [
        'user_id',
        'actor_user_id',
        'operator',
        'original_filename',
        'stored_path',
        'treasury_account_code',
        'consent_given_at',
        'consent_ip',
        'personal_data_purged_at',
        'rows_total',
        'rows_imported',
        'rows_duplicate',
        'rows_matched',
        'status',
        'error_message',
    ];

    protected $casts = [
        'consent_given_at' => 'datetime',
        'personal_data_purged_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MobileMoneyTransaction::class, 'statement_import_id');
    }
}
