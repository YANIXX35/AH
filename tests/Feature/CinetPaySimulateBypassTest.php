<?php

namespace Tests\Feature;

use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Preuve rouge → vert : `?simulate=accepted` sur /payments/cinetpay/return
 * permettait de créditer le Premium sans paiement réel, y compris pour un
 * visiteur non authentifié en récupérant la transaction CinetPay la plus
 * récente du système, quel qu'en soit le propriétaire.
 */
class CinetPaySimulateBypassTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unauthenticated_visitor_cannot_use_simulate_to_activate_someone_elses_premium(): void
    {
        $victim = User::factory()->create(['is_premium' => false]);
        $transaction = PaymentTransaction::query()->create([
            'user_id' => $victim->id,
            'provider' => 'cinetpay',
            'provider_reference' => 'CP-VICTIM-REF-1',
            'status' => 'PENDING',
            'amount' => 15000,
            'currency' => 'XOF',
            'correspondent' => 'ALL',
            'payer_msisdn' => '+2250700000000',
        ]);

        $response = $this->get('/payments/cinetpay/return?simulate=accepted');

        $response->assertRedirect(route('payments.sandbox'));
        $this->assertFalse($victim->fresh()->is_premium);
        $this->assertSame('PENDING', $transaction->fresh()->status);
    }

    public function test_an_unauthenticated_visitor_cannot_target_a_known_transaction_id_via_simulate(): void
    {
        $victim = User::factory()->create(['is_premium' => false]);
        PaymentTransaction::query()->create([
            'user_id' => $victim->id,
            'provider' => 'cinetpay',
            'provider_reference' => 'CP-VICTIM-REF-2',
            'status' => 'PENDING',
            'amount' => 15000,
            'currency' => 'XOF',
            'correspondent' => 'ALL',
            'payer_msisdn' => '+2250700000000',
        ]);

        $response = $this->get('/payments/cinetpay/return?transaction_id=CP-VICTIM-REF-2&simulate=accepted');

        // La transaction est trouvée (le lookup par référence reste public),
        // mais la branche "simulate" ne doit pas s'exécuter sans authentification :
        // aucun crédit Premium ne doit avoir lieu.
        $this->assertFalse($victim->fresh()->is_premium);
    }

    public function test_simulate_only_ever_affects_the_authenticated_users_own_transaction_in_non_production(): void
    {
        $attacker = User::factory()->create(['is_premium' => false]);
        $victim = User::factory()->create(['is_premium' => false]);
        PaymentTransaction::query()->create([
            'user_id' => $victim->id,
            'provider' => 'cinetpay',
            'provider_reference' => 'CP-VICTIM-REF-3',
            'status' => 'PENDING',
            'amount' => 15000,
            'currency' => 'XOF',
            'correspondent' => 'ALL',
            'payer_msisdn' => '+2250700000000',
        ]);

        // L'attaquant est connecté mais n'a lui-même aucune transaction CinetPay :
        // il ne doit jamais récupérer/créditer celle de la victime.
        $response = $this->actingAs($attacker)->get('/payments/cinetpay/return?simulate=accepted');

        $response->assertRedirect(route('payments.sandbox'));
        $this->assertFalse($victim->fresh()->is_premium);
        $this->assertFalse($attacker->fresh()->is_premium);
    }
}
