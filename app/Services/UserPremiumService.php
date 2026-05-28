<?php

namespace App\Services;

use App\Models\SubscriptionHistory;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Activation / désactivation Premium (essais clients, administrateurs).
 */
class UserPremiumService
{
    public function __construct(
        private readonly AdminAuditTrailService $auditTrail
    ) {}

    /**
     * Active ou prolonge le Premium pour N jours (comptes entreprise).
     */
    public function activateForDays(
        User $user,
        int $days,
        string $source,
        string $note,
        ?User $actor = null,
        array $meta = [],
        ?Request $request = null,
        bool $clearSuspension = true,
    ): User {
        $previousStatus = (string) ($user->premium_status ?? 'free');
        $referenceStart = $user->premium_ends_at && $user->premium_ends_at->isFuture()
            ? $user->premium_ends_at->copy()
            : now();
        $newEndsAt = $referenceStart->copy()->addDays($days);

        $before = $user->only([
            'is_premium',
            'premium_status',
            'premium_trial_ends_at',
            'premium_ends_at',
            'account_suspended',
            'suspended_at',
            'suspended_reason',
        ]);

        $payload = [
            'is_premium' => true,
            'premium_status' => 'active',
            'premium_trial_ends_at' => null,
            'premium_ends_at' => $newEndsAt,
        ];

        if ($clearSuspension) {
            $payload['auto_suspended_for_payment'] = false;
            $payload['account_suspended'] = false;
            $payload['suspended_at'] = null;
            $payload['suspended_reason'] = null;
        }

        $user->update($payload);

        SubscriptionHistory::create([
            'user_id' => $user->id,
            'from_status' => $previousStatus,
            'to_status' => 'active',
            'is_premium' => true,
            'starts_at' => now(),
            'ends_at' => $newEndsAt,
            'source' => $source,
            'note' => $note,
            'meta' => $meta,
        ]);

        $this->auditTrail->log(
            'premium.activate',
            User::class,
            $user->id,
            $actor?->id,
            $before,
            $user->fresh()?->only([
                'is_premium',
                'premium_status',
                'premium_trial_ends_at',
                'premium_ends_at',
                'account_suspended',
                'suspended_at',
                'suspended_reason',
            ]),
            array_merge($meta, ['days' => $days]),
            $request
        );

        return $user->fresh();
    }

    /**
     * Premium sans échéance (administrateurs plateforme).
     */
    public function activateUnlimited(
        User $user,
        string $source,
        string $note,
        ?User $actor = null,
        ?Request $request = null,
    ): User {
        if ($user->is_premium && $user->premium_status === 'active' && $user->premium_ends_at === null) {
            return $user;
        }

        $previousStatus = (string) ($user->premium_status ?? 'free');
        $before = $user->only(['is_premium', 'premium_status', 'premium_trial_ends_at', 'premium_ends_at']);

        $user->update([
            'is_premium' => true,
            'premium_status' => 'active',
            'premium_trial_ends_at' => null,
            'premium_ends_at' => null,
            'auto_suspended_for_payment' => false,
            'account_suspended' => false,
            'suspended_at' => null,
            'suspended_reason' => null,
        ]);

        SubscriptionHistory::create([
            'user_id' => $user->id,
            'from_status' => $previousStatus,
            'to_status' => 'active',
            'is_premium' => true,
            'starts_at' => now(),
            'ends_at' => null,
            'source' => $source,
            'note' => $note,
            'meta' => ['unlimited' => true],
        ]);

        $this->auditTrail->log(
            'premium.activate_unlimited',
            User::class,
            $user->id,
            $actor?->id,
            $before,
            $user->fresh()?->only(['is_premium', 'premium_status', 'premium_trial_ends_at', 'premium_ends_at']),
            [],
            $request
        );

        return $user->fresh();
    }

    public function deactivate(
        User $user,
        string $source,
        string $note,
        ?User $actor = null,
        array $meta = [],
        ?Request $request = null,
    ): User {
        $previousStatus = (string) ($user->premium_status ?? 'free');
        $before = $user->only(['is_premium', 'premium_status', 'premium_trial_ends_at', 'premium_ends_at']);

        $user->update([
            'is_premium' => false,
            'premium_status' => 'free',
            'premium_trial_ends_at' => null,
            'premium_ends_at' => null,
        ]);

        SubscriptionHistory::create([
            'user_id' => $user->id,
            'from_status' => $previousStatus,
            'to_status' => 'free',
            'is_premium' => false,
            'starts_at' => now(),
            'ends_at' => null,
            'source' => $source,
            'note' => $note,
            'meta' => $meta,
        ]);

        $this->auditTrail->log(
            'premium.set_free',
            User::class,
            $user->id,
            $actor?->id,
            $before,
            $user->fresh()?->only(['is_premium', 'premium_status', 'premium_trial_ends_at', 'premium_ends_at']),
            $meta,
            $request
        );

        return $user->fresh();
    }

    public function ensurePlatformAdminPremium(User $user, ?User $actor = null, ?Request $request = null): void
    {
        if (! $user->is_platform_admin) {
            return;
        }

        $this->activateUnlimited(
            $user,
            'admin_role',
            'Premium administrateur plateforme (accès complet sans échéance).',
            $actor,
            $request
        );
    }

    public function canManageEnterprisePremium(User $user): bool
    {
        return ! $user->isPlatformAdmin() && ! ($user->is_accountant ?? false);
    }
}
