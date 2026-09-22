<?php

namespace Tests\Feature;

use App\Models\FinancingDossier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ErpNextFinancingDossierTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-webhook-token';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.erpnext.webhook_token', self::TOKEN);
    }

    private function getWebhook(?string $company = null, ?string $token = self::TOKEN)
    {
        $headers = $token !== null ? ['X-PME360-Webhook-Token' => $token] : [];
        $query = $company !== null ? '?'.http_build_query(['company' => $company]) : '';

        return $this->withHeaders($headers)->getJson(route('webhooks.erpnext.financing-dossier').$query);
    }

    public function test_rejects_missing_or_wrong_token(): void
    {
        $this->getWebhook('Test', null)->assertForbidden();
        $this->getWebhook('Test', 'wrong-token')->assertForbidden();
    }

    public function test_requires_company_query_param(): void
    {
        $this->getWebhook(null)->assertStatus(422);
    }

    public function test_ignores_unknown_company(): void
    {
        $this->getWebhook('Inconnue')->assertOk()->assertJson(['status' => 'ignored', 'reason' => 'PME introuvable']);
    }

    public function test_ignores_pme_without_financing_dossier(): void
    {
        User::factory()->create(['erpnext_company_name' => 'Test SARL #1']);

        $this->getWebhook('Test SARL #1')
            ->assertOk()
            ->assertJson(['status' => 'ignored', 'reason' => 'aucun dossier de financement']);
    }

    public function test_returns_the_latest_dossier_fields(): void
    {
        $pme = User::factory()->create(['erpnext_company_name' => 'Test SARL #1']);
        $analyst = User::factory()->create();

        FinancingDossier::create([
            'user_id' => $pme->id,
            'analyst_user_id' => $analyst->id,
            'reference' => 'DF-2026-000001',
            'status' => 'draft',
            'financing_type' => 'credit_investissement',
            'financing_purpose' => 'acquisition_equipement',
            'amount_requested' => 5000000,
            'desired_term_months' => 24,
            'grace_period_months' => 3,
            'repayment_frequency' => 'mensuelle',
            'promoter_contribution' => 1000000,
            'desired_disbursement_date' => '2026-11-01',
            'financing_summary_data' => [
                'financing_summary' => 'Achat de machines',
                'repayment_source' => 'Chiffre affaires',
            ],
        ]);

        $response = $this->getWebhook('Test SARL #1');

        $response->assertOk()->assertJson([
            'status' => 'ok',
            'reference' => 'DF-2026-000001',
            'dossier_status' => 'draft',
            'financing_type' => 'credit_investissement',
            'desired_term_months' => 24,
            'financing_summary' => 'Achat de machines',
            'repayment_source' => 'Chiffre affaires',
        ]);
        $this->assertEquals(5000000, (float) $response->json('amount_requested'));
    }
}
