<?php

namespace Tests\Feature;

use App\Models\SubscriptionHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_three_clients_earn_10000_and_the_rest_earn_7000(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);

        $clients = collect();
        for ($i = 0; $i < 5; $i++) {
            $clients->push(User::factory()->create([
                'created_by_user_id' => $commercial->id,
                'created_at' => now()->subDays(5 - $i),
            ]));
        }

        $response = $this->actingAs($commercial)->get('/commercial/solde');

        $response->assertOk();
        // 3 x 10000 + 2 x 7000 = 44000
        $response->assertSee('44 000 FCFA');
        $response->assertSee('44 000 FCFA');
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
