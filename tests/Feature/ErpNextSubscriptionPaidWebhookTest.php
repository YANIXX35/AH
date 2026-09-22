<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ErpNextSubscriptionPaidWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-webhook-token';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.erpnext.webhook_token', self::TOKEN);
    }

    private function postWebhook(array $payload, ?string $token = self::TOKEN)
    {
        $headers = $token !== null ? ['X-PME360-Webhook-Token' => $token] : [];

        return $this->withHeaders($headers)->postJson(route('webhooks.erpnext.subscription-paid'), $payload);
    }

    public function test_rejects_missing_or_wrong_token(): void
    {
        $payload = ['company' => 'Test', 'duration_months' => 1, 'paid_at' => now()->toIso8601String()];

        $this->postWebhook($payload, null)->assertForbidden();
        $this->postWebhook($payload, 'wrong-token')->assertForbidden();
    }

    public function test_requires_company_duration_and_paid_at(): void
    {
        $this->postWebhook([])->assertStatus(422);
    }

    public function test_ignores_unknown_company(): void
    {
        $payload = ['company' => 'Inconnue', 'duration_months' => 1, 'paid_at' => now()->toIso8601String()];

        $this->postWebhook($payload)->assertOk()->assertJson(['status' => 'ignored', 'reason' => 'PME introuvable']);
    }

    public function test_activates_premium_when_previously_free(): void
    {
        $pme = User::factory()->create([
            'erpnext_company_name' => 'Test SARL #1',
            'is_premium' => false,
            'premium_status' => 'free',
            'premium_ends_at' => null,
        ]);

        $paidAt = now();
        $this->postWebhook([
            'company' => 'Test SARL #1',
            'duration_months' => 1,
            'paid_at' => $paidAt->toIso8601String(),
        ])->assertOk()->assertJson(['status' => 'ok']);

        $pme->refresh();
        $this->assertTrue($pme->is_premium);
        $this->assertSame('active', $pme->premium_status);
        $this->assertTrue($pme->premium_ends_at->isSameDay($paidAt->copy()->addMonths(1)));
    }

    public function test_extends_from_current_expiry_when_renewing_early(): void
    {
        $currentExpiry = now()->addDays(10);
        $pme = User::factory()->create([
            'erpnext_company_name' => 'Test SARL #1',
            'is_premium' => true,
            'premium_status' => 'active',
            'premium_ends_at' => $currentExpiry,
        ]);

        $this->postWebhook([
            'company' => 'Test SARL #1',
            'duration_months' => 1,
            'paid_at' => now()->toIso8601String(),
        ])->assertOk();

        $pme->refresh();
        $this->assertTrue($pme->premium_ends_at->isSameDay($currentExpiry->copy()->addMonths(1)));
    }
}
