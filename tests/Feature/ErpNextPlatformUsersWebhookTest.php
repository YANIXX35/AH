<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ErpNextPlatformUsersWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-webhook-token';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.erpnext.webhook_token', self::TOKEN);
    }

    private function getWebhook(?string $token = self::TOKEN)
    {
        $headers = $token !== null ? ['X-PME360-Webhook-Token' => $token] : [];

        return $this->withHeaders($headers)->getJson(route('webhooks.erpnext.platform-users'));
    }

    public function test_rejects_missing_or_wrong_token(): void
    {
        $this->getWebhook(null)->assertForbidden();
        $this->getWebhook('wrong-token')->assertForbidden();
    }

    public function test_excludes_users_without_an_erpnext_company(): void
    {
        User::factory()->create(['erpnext_company_name' => null]);

        $response = $this->getWebhook();

        $response->assertOk()->assertJsonCount(0, 'users');
    }

    public function test_returns_pme_with_payment_status_and_dates(): void
    {
        $pme = User::factory()->create([
            'email' => 'pme@test.local',
            'company_name' => 'Test SARL',
            'erpnext_company_name' => 'Test SARL #1',
            'is_premium' => true,
            'premium_status' => 'active',
            'premium_trial_ends_at' => null,
            'premium_ends_at' => now()->addDays(20),
        ]);

        // created_at is not mass-assignable on UserLoginLog, so create()
        // would silently stamp the real current time instead of the
        // backdated value -- forceFill() bypasses that protection.
        UserLoginLog::create(['user_id' => $pme->id, 'event' => 'login'])
            ->forceFill(['created_at' => now()->subDay()])->save();
        UserLoginLog::create(['user_id' => $pme->id, 'event' => 'login'])
            ->forceFill(['created_at' => now()->subHour()])->save();

        $response = $this->getWebhook();

        $response->assertOk()->assertJsonCount(1, 'users');
        $row = $response->json('users.0');

        $this->assertSame('pme@test.local', $row['email']);
        $this->assertSame('Test SARL #1', $row['erpnext_company_name']);
        $this->assertTrue($row['is_premium']);
        $this->assertSame('active', $row['premium_status']);
        $this->assertNotNull($row['registered_at']);
        // The most recent of the two login rows, not the oldest.
        $this->assertSame(
            $pme->fresh()->created_at->toIso8601String(),
            $row['registered_at']
        );
        $this->assertTrue(
            \Illuminate\Support\Carbon::parse($row['last_login_at'])->isSameMinute(now()->subHour())
        );
    }
}
