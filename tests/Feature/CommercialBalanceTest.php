<?php

namespace Tests\Feature;

use App\Models\SubscriptionHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_bonus_is_zero_until_the_client_actually_pays(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);
        User::factory()->create([
            'created_by_user_id' => $commercial->id,
            'created_at' => now()->subDays(40),
        ]);

        $response = $this->actingAs($commercial)->get('/commercial/solde');

        $response->assertOk();
        $response->assertSee('0 FCFA');
        $response->assertSee('Pas encore payé');
    }

    public function test_signup_bonus_tier_follows_order_of_first_payment_not_order_added(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);

        // Added in order A, B, C, D — but only D ever pays.
        $clientA = User::factory()->create(['created_by_user_id' => $commercial->id, 'created_at' => now()->subDays(40)]);
        $clientB = User::factory()->create(['created_by_user_id' => $commercial->id, 'created_at' => now()->subDays(30)]);
        $clientC = User::factory()->create(['created_by_user_id' => $commercial->id, 'created_at' => now()->subDays(20)]);
        $clientD = User::factory()->create(['created_by_user_id' => $commercial->id, 'created_at' => now()->subDays(10)]);

        SubscriptionHistory::create([
            'user_id' => $clientD->id,
            'from_status' => 'free',
            'to_status' => 'active',
            'is_premium' => true,
            'starts_at' => now()->subDays(1),
            'ends_at' => now()->addDays(29),
            'source' => 'cinetpay_notify',
        ]);

        $response = $this->actingAs($commercial)->get('/commercial/solde');

        $response->assertOk();
        // D is the first (and only) client to have ever paid, so it takes tier 1 (10000)
        // despite being added last. Its single payment also counts as 1 renewal (1500).
        // A, B, C never paid -> 0 F each. Total = 10000 + 1500 = 11500.
        $response->assertSee('11 500 FCFA');
    }

    public function test_first_three_paying_clients_earn_10000_and_the_rest_earn_7000(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);

        $clients = collect();
        for ($i = 0; $i < 4; $i++) {
            $clients->push(User::factory()->create([
                'created_by_user_id' => $commercial->id,
                'created_at' => now()->subDays(40 - $i),
            ]));
        }

        // Every client pays once, in the same order as they were added.
        foreach ($clients as $index => $client) {
            SubscriptionHistory::create([
                'user_id' => $client->id,
                'from_status' => 'free',
                'to_status' => 'active',
                'is_premium' => true,
                'starts_at' => now()->subDays(10 - $index),
                'ends_at' => now()->addDays(20 - $index),
                'source' => 'cinetpay_notify',
            ]);
        }

        $response = $this->actingAs($commercial)->get('/commercial/solde');

        $response->assertOk();
        // 3 x 10000 (tier 1) + 1 x 7000 (tier 2) signup, + 4 x 1500 renewal = 43000.
        $response->assertSee('43 000 FCFA');
    }

    public function test_only_real_cinetpay_payments_count_as_renewals_not_the_trial_grant(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);
        $client = User::factory()->create([
            'created_by_user_id' => $commercial->id,
            'created_at' => now()->subDays(40),
        ]);

        // Trial grant — must NOT count as a renewal.
        SubscriptionHistory::create([
            'user_id' => $client->id,
            'from_status' => 'free',
            'to_status' => 'active',
            'is_premium' => true,
            'starts_at' => now()->subDays(40),
            'ends_at' => now()->subDays(10),
            'source' => 'commercial_referral',
        ]);

        // Real renewal payments — must count, 1500 F each.
        SubscriptionHistory::create([
            'user_id' => $client->id,
            'from_status' => 'free',
            'to_status' => 'active',
            'is_premium' => true,
            'starts_at' => now()->subDays(9),
            'ends_at' => now()->addDays(21),
            'source' => 'cinetpay_notify',
        ]);
        SubscriptionHistory::create([
            'user_id' => $client->id,
            'from_status' => 'active',
            'to_status' => 'active',
            'is_premium' => true,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'source' => 'cinetpay_return',
        ]);

        $response = $this->actingAs($commercial)->get('/commercial/solde');

        $response->assertOk();
        // 1 client => 10000 signup + 2 renewals x 1500 = 13000 total.
        $response->assertSee('13 000 FCFA');
        $response->assertSee('3 000 FCFA'); // renewal earnings breakdown
    }

    public function test_commercial_cannot_see_another_commercials_balance_data(): void
    {
        $commercialA = User::factory()->create(['role_key' => 'commercial']);
        $commercialB = User::factory()->create(['role_key' => 'commercial']);
        User::factory()->create(['created_by_user_id' => $commercialA->id]);

        $response = $this->actingAs($commercialB)->get('/commercial/solde');

        $response->assertOk();
        $response->assertSee('0 FCFA');
        $response->assertSee('Aucun client ajouté');
    }

    public function test_non_commercial_users_cannot_access_the_balance_page(): void
    {
        $user = User::factory()->create(['role_key' => 'manager']);

        $response = $this->actingAs($user)->get('/commercial/solde');

        $response->assertStatus(403);
    }
}
