<?php

namespace Tests\Feature;

use App\Models\PaymentTransaction;
use App\Models\SubscriptionHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FedaPayCallbackIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_replaying_the_callback_does_not_credit_premium_twice(): void
    {
        config(['services.fedapay.sandbox.api_key' => 'test-key']);

        $user = User::factory()->create(['is_premium' => false]);
        $reference = 'fedapay-ref-123';

        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'provider' => 'fedapay_sandbox',
            'provider_reference' => $reference,
            'status' => 'PENDING',
            'amount' => 5000,
            'currency' => 'XOF',
            'country' => 'CIV',
            'correspondent' => 'MTN',
            'payer_msisdn' => '+22500000000',
            'request_payload' => [],
            'response_payload' => [],
        ]);

        Http::fake([
            '*/v1/transactions/*' => Http::response([
                'transaction' => ['status' => 'approved'],
            ], 200),
        ]);

        $firstResponse = $this->actingAs($user)->get("/payments/sandbox/callback?id={$reference}");
        $firstResponse->assertRedirect();

        $user->refresh();
        $this->assertTrue($user->is_premium);
        $firstEndsAt = $user->premium_ends_at;
        $this->assertNotNull($firstEndsAt);
        $this->assertSame(1, SubscriptionHistory::where('user_id', $user->id)->count());

        // Le callback est rejoué (retour navigateur dupliqué, réseau, F5 sur la page de retour...)
        $secondResponse = $this->actingAs($user)->get("/payments/sandbox/callback?id={$reference}");
        $secondResponse->assertRedirect();

        $user->refresh();
        $this->assertTrue($firstEndsAt->equalTo($user->premium_ends_at));
        $this->assertSame(1, SubscriptionHistory::where('user_id', $user->id)->count());
        $this->assertSame(1, PaymentTransaction::where('provider_reference', $reference)->count());
    }
}
