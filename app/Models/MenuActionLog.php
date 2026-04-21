<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trace des actions liées aux zones du menu (persistée pour affichage dans l’interface).
 */
class MenuActionLog extends Model
{
    protected $fillable = [
        'user_id',
        'route_name',
        'http_method',
        'path',
        'status_code',
        'ip',
        'was_platform_admin',
    ];

    protected function casts(): array
    {
        return [
            'was_platform_admin' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
