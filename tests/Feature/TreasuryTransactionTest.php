<?php

namespace Tests\Feature;

use App\Models\TreasuryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreasuryTransactionTest extends TestCase
{
    use RefreshDatabase;

    private function transactionPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'encaissement',
            'transaction_type' => 'Paiement client',
            'payment_module' => 'stripe',
            'stripe_payment_channel' => 'card',
            'amount' => 150000,
            'description' => 'Vente de marchandises',
            'transaction_date' => now()->toDateString(),
            'status' => 'planifie',
        ], $overrides);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/treasury/tracking')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_treasury_tracking(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/treasury/tracking')
            ->assertOk();
    }

    public function test_user_can_create_a_treasury_transaction(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/treasury', $this->transactionPayload());

        $response->assertRedirect(route('treasury.index'));
        $this->assertDatabaseHas('treasury_transactions', [
            'user_id' => $user->id,
            'description' => 'Vente de marchandises',
            'amount' => 150000,
        ]);
    }

    public function test_creating_a_transaction_requires_valid_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/treasury', $this->transactionPayload([
            'amount' => 0,
        ]));

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('treasury_transactions', 0);
    }

    public function test_user_can_update_their_own_transaction(): void
    {
        $user = User::factory()->create();
        $transaction = TreasuryTransaction::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->put(
            "/treasury/{$transaction->id}",
            $this->transactionPayload(['description' => 'Description mise à jour'])
        );

        $response->assertRedirect(route('treasury.index'));
        $this->assertDatabaseHas('treasury_transactions', [
            'id' => $transaction->id,
            'description' => 'Description mise à jour',
        ]);
    }

    public function test_user_can_delete_their_own_transaction(): void
    {
        $user = User::factory()->create();
        $transaction = TreasuryTransaction::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->delete("/treasury/{$transaction->id}")
            ->assertRedirect(route('treasury.index'));

        $this->assertDatabaseMissing('treasury_transactions', ['id' => $transaction->id]);
    }

    public function test_user_cannot_edit_another_users_transaction(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $transaction = TreasuryTransaction::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->put("/treasury/{$transaction->id}", $this->transactionPayload())
            ->assertForbidden();
    }

    public function test_user_cannot_delete_another_users_transaction(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $transaction = TreasuryTransaction::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->delete("/treasury/{$transaction->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('treasury_transactions', ['id' => $transaction->id]);
    }
}
