<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialAnalystNote extends Model
{
    protected $fillable = [
        'user_id',
        'analyst_user_id',
        'note',
    ];

    /**
     * PME concernée par la note.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function analyst(): BelongsTo
    {
        return $this->belongsTo(User::class, 'analyst_user_id');
    }
}
