<?php

namespace App\Services;

use App\Models\AdminAuditTrail;
use Illuminate\Http\Request;

class AdminAuditTrailService
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>|null  $meta
     */
    public function log(
        string $action,
        ?string $targetType,
        ?int $targetId,
        ?int $actorUserId,
        ?array $before = null,
        ?array $after = null,
        ?array $meta = null,
        ?Request $request = null
    ): void {
        AdminAuditTrail::query()->create([
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'actor_user_id' => $actorUserId,
            'before_data' => $before,
            'after_data' => $after,
            'meta' => $meta,
            'ip_address' => $request?->ip(),
        ]);
    }
}

