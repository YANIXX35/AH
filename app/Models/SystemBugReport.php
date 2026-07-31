<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class SystemBugReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'dashboard',
        'page_url',
        'route_name',
        'error_class',
        'message',
        'file',
        'line',
        'stack_trace',
        'severity',
        'status',
        'resolved_at',
        'resolved_by_user_id',
        'resolution_note',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'line' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public static function resolveDashboardName(?User $user, ?Request $request = null): string
    {
        $path = $request ? $request->path() : '';

        if (str_contains($path, 'admin')) {
            return 'Dashboard Administration';
        }
        if (str_contains($path, 'accountant') || str_contains($path, 'accounting')) {
            return 'Dashboard Cabinet Comptable';
        }
        if (str_contains($path, 'commercial')) {
            return 'Dashboard Commercial';
        }

        if ($user) {
            if ($user->isPlatformAdmin()) {
                return 'Dashboard Administration';
            }
            if ($user->isAccountant()) {
                return 'Dashboard Cabinet Comptable';
            }
        }

        return 'Dashboard Entreprise (PME)';
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'OPEN');
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'CRITICAL');
    }
}
