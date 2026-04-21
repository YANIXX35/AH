<?php

namespace App\Services;

use App\Models\TreasuryAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class TreasuryAudit
{
    /**
     * Enregistre une entrée de journal d'audit trésorerie.
     *
     * @param  int  $workspaceUserId  Compte « dossier » (pivot entreprise ou utilisateur seul)
     */
    public static function log(int $workspaceUserId, string $action, ?Model $subject = null, array $properties = []): void
    {
        TreasuryAuditLog::create([
            'user_id' => $workspaceUserId,
            'actor_user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip_address' => Request::ip(),
        ]);
    }
}
