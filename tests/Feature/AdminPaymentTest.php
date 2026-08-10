<?php

namespace Tests\Feature;

use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_payments_page(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $response = $this->actingAs($admin)->get('/admin/payments');

        $response->assertStatus(200);
        $response->assertSee('complète des paiements');
        $response->assertSee('Simuler un Paiement Test');
    }

    public function test_admin_can_simulate_payment_and_generate_receipt(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $client = User::factory()->create([
            'name' => 'Kemon Gounglouin',
            'company_name' => 'BERAKHA SERVICES INTELLECT SARL',
            'is_premium' => false,
        ]);

        $response = $this->actingAs($admin)->post('/admin/payments/simulate', [
            'user_id' => $client->id,
            'amount' => 25000,
            'correspondent' => 'ORANGE_MONEY',
            'country' => 'CI',
            'payer_msisdn' => '+2250700000000',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $transaction = PaymentTransaction::where('user_id', $client->id)->first();
        $this->assertNotNull($transaction);
        $this->assertEquals(25000, $transaction->amount);
        $this->assertEquals('COMPLETED', $transaction->status);
        $this->assertTrue($client->fresh()->hasActivePremiumPeriod());

        // Test de la route du reçu PDF
        $receiptResponse = $this->actingAs($admin)->get("/admin/payments/{$transaction->id}/receipt");
        $receiptResponse->assertStatus(200);
        $receiptResponse->assertSee('REÇU DE PAIEMENT OFFICIEL');
        $receiptResponse->assertSee('BERAKHA SERVICES INTELLECT SARL');
        $receiptResponse->assertSee('25 000 XOF');
    }

    public function test_admin_cannot_delete_a_paid_payment_transaction(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $transaction = PaymentTransaction::create([
            'user_id' => $admin->id,
            'amount' => 15000,
            'currency' => 'XOF',
            'status' => 'COMPLETED',
            'country' => 'CI',
            'correspondent' => 'ORANGE_MONEY',
            'payer_msisdn' => '+2250700000000',
            'provider' => 'SIMULATOR',
            'provider_reference' => 'TX-SIM-TESTDELETE',
        ]);

        $response = $this->actingAs($admin)->delete("/admin/payments/{$transaction->id}");

        $response->assertRedirect();
        $response->assertSessionHasErrors('payment');
        $this->assertDatabaseHas('payment_transactions', ['id' => $transaction->id]);
    }

    public function test_admin_can_soft_delete_a_non_paid_payment_transaction(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $transaction = PaymentTransaction::create([
            'user_id' => $admin->id,
            'amount' => 15000,
            'currency' => 'XOF',
            'status' => 'FAILED',
            'country' => 'CI',
            'correspondent' => 'ORANGE_MONEY',
            'payer_msisdn' => '+2250700000000',
            'provider' => 'SIMULATOR',
            'provider_reference' => 'TX-SIM-TESTDELETE2',
        ]);

        $response = $this->actingAs($admin)->delete("/admin/payments/{$transaction->id}");

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $this->assertSoftDeleted('payment_transactions', ['id' => $transaction->id]);
    }
}
