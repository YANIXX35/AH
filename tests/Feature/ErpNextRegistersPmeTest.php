<?php

namespace Tests\Feature;

use App\Http\Controllers\ErpNextPmeRegistrationWebhookController;
use App\Jobs\ProvisionErpNextCompanyForPme;
use App\Models\PlanComptableAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ErpNextRegistersPmeTest extends TestCase
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

        return $this->withHeaders($headers)->postJson(route('webhooks.erpnext.register-pme'), $payload);
    }

    public function test_rejects_missing_or_wrong_token(): void
    {
        $this->postWebhook([], null)->assertForbidden();
        $this->postWebhook([], 'wrong-token')->assertForbidden();
    }

    public function test_registers_pme_with_default_password_when_left_blank(): void
    {
        Queue::fake();

        $response = $this->postWebhook([
            'name' => 'Jean Kouassi',
            'email' => 'jean.kouassi@example.com',
            'phone' => '+225 0102030405',
            'company_name' => 'Kouassi Transport SARL',
            'company_tax_id' => 'CI0123456X',
            'rccm' => 'CI-ABJ-2026-B-00001',
            'city' => 'Abidjan',
        ]);

        $response->assertStatus(201)->assertJson([
            'status' => 'ok',
            'email' => 'jean.kouassi@example.com',
            'password' => ErpNextPmeRegistrationWebhookController::DEFAULT_PASSWORD,
        ]);

        $pme = User::where('email', 'jean.kouassi@example.com')->first();
        $this->assertNotNull($pme);
        $this->assertSame('Kouassi Transport SARL', $pme->company_name);
        $this->assertSame('manager', $pme->role_key);
        $this->assertTrue((bool) $pme->must_change_password);
        $this->assertNull($pme->terms_accepted_at);
        $this->assertTrue(Hash::check(ErpNextPmeRegistrationWebhookController::DEFAULT_PASSWORD, $pme->password));

        $this->assertGreaterThan(0, PlanComptableAccount::where('user_id', $pme->id)->count());

        Queue::assertPushed(ProvisionErpNextCompanyForPme::class, function ($job) use ($pme) {
            return $job->pme->is($pme);
        });
    }

    public function test_registers_pme_with_custom_password(): void
    {
        Queue::fake();

        $response = $this->postWebhook([
            'name' => 'Awa Diallo',
            'email' => 'awa.diallo@example.com',
            'company_name' => 'Diallo Commerce',
            'password' => 'MonMotDePasse123',
        ]);

        $response->assertStatus(201)->assertJson(['password' => 'MonMotDePasse123']);

        $pme = User::where('email', 'awa.diallo@example.com')->first();
        $this->assertTrue(Hash::check('MonMotDePasse123', $pme->password));
    }

    public function test_requires_name_email_and_company_name(): void
    {
        $this->postWebhook([])->assertStatus(422);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'deja-pris@example.com']);

        $this->postWebhook([
            'name' => 'Contact', 'email' => 'deja-pris@example.com', 'company_name' => 'X',
        ])->assertStatus(422);
    }
}
