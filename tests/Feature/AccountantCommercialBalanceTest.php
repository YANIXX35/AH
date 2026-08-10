<?php

namespace Tests\Feature;

use App\Models\CommercialPayout;
use App\Models\SubscriptionHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountantCommercialBalanceTest extends TestCase
{
    use RefreshDatabase;

    private function markAsPaid(User $client): void
    {
        SubscriptionHistory::create([
            'user_id' => $client->id,
            'from_status' => 'free',
            'to_status' => 'active',
            'is_premium' => true,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'source' => 'cinetpay_notify',
        ]);
    }

    public function test_accountant_sees_every_commercials_earned_and_remaining_balance(): void
    {
        $accountant = User::factory()->create(['is_accountant' => true]);
        $commercial = User::factory()->create(['role_key' => 'commercial', 'name' => 'Awa Traoré']);
        $client = User::factory()->create([
            'created_by_user_id' => $commercial->id,
            'created_at' => now()->subDays(3),
        ]);
        $this->markAsPaid($client);

        $response = $this->actingAs($accountant)->get('/accountant/commercials-balance');

        $response->assertOk();
        $response->assertSee('Awa Traoré');
        $response->assertSee('11 500 F'); // 1 paying client => 10000 tier1 + 1500 renewal
    }

    public function test_accountant_can_validate_a_payout_and_it_generates_a_pdf_receipt(): void
    {
        Storage::fake('public');

        $accountant = User::factory()->create(['is_accountant' => true]);
        $commercial = User::factory()->create(['role_key' => 'commercial']);
        $client = User::factory()->create([
            'created_by_user_id' => $commercial->id,
            'created_at' => now()->subDays(3),
        ]);
        $this->markAsPaid($client);

        // Total earned = 10000 signup (tier 1) + 1500 renewal = 11500.
        $response = $this->actingAs($accountant)->post("/accountant/commercials-balance/{$commercial->id}/payouts", [
            'amount' => 11500,
            'note' => 'Virement Wave',
        ]);

        $response->assertRedirect(route('accountant.commercials-balance.show', $commercial));
        $response->assertSessionHas('status');

        $payout = CommercialPayout::where('commercial_user_id', $commercial->id)->first();
        $this->assertNotNull($payout);
        $this->assertSame(11500, $payout->amount);
        $this->assertSame($accountant->id, $payout->validated_by_user_id);
        $this->assertStringStartsWith('REC-', $payout->receipt_number);
        $this->assertNotNull($payout->pdf_path);
        Storage::disk('public')->assertExists($payout->pdf_path);

        // Fully paid now — remaining should be 0 on the detail page.
        $show = $this->actingAs($accountant)->get("/accountant/commercials-balance/{$commercial->id}");
        $show->assertOk();
        $show->assertSee('est à jour');
    }

    public function test_payout_amount_cannot_exceed_the_remaining_balance(): void
    {
        $accountant = User::factory()->create(['is_accountant' => true]);
        $commercial = User::factory()->create(['role_key' => 'commercial']);
        $client = User::factory()->create([
            'created_by_user_id' => $commercial->id,
            'created_at' => now()->subDays(3),
        ]);
        $this->markAsPaid($client);

        // Only 11500 is owed (1 paying client), trying to pay 50000 should fail.
        $response = $this->actingAs($accountant)->post("/accountant/commercials-balance/{$commercial->id}/payouts", [
            'amount' => 50000,
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, CommercialPayout::count());
    }

    public function test_payout_is_rejected_when_the_client_has_not_paid_yet(): void
    {
        $accountant = User::factory()->create(['is_accountant' => true]);
        $commercial = User::factory()->create(['role_key' => 'commercial']);
        User::factory()->create(['created_by_user_id' => $commercial->id]);

        // Client never paid -> remaining balance is 0, any payout must be rejected.
        $response = $this->actingAs($accountant)->post("/accountant/commercials-balance/{$commercial->id}/payouts", [
            'amount' => 1,
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, CommercialPayout::count());
    }

    public function test_receipt_download_serves_the_generated_pdf(): void
    {
        Storage::fake('public');

        $accountant = User::factory()->create(['is_accountant' => true]);
        $commercial = User::factory()->create(['role_key' => 'commercial']);
        $client = User::factory()->create(['created_by_user_id' => $commercial->id]);
        $this->markAsPaid($client);

        $this->actingAs($accountant)->post("/accountant/commercials-balance/{$commercial->id}/payouts", [
            'amount' => 5000,
        ]);
        $payout = CommercialPayout::where('commercial_user_id', $commercial->id)->first();

        $response = $this->actingAs($accountant)->get("/accountant/commercials-balance/payouts/{$payout->id}/receipt");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_non_accountant_cannot_access_commercials_balance(): void
    {
        $user = User::factory()->create(['role_key' => 'manager']);

        $response = $this->actingAs($user)->get('/accountant/commercials-balance');

        $response->assertStatus(403);
    }

    public function test_showing_a_non_commercial_user_as_commercial_is_not_found(): void
    {
        $accountant = User::factory()->create(['is_accountant' => true]);
        $notACommercial = User::factory()->create(['role_key' => 'manager']);

        $response = $this->actingAs($accountant)->get("/accountant/commercials-balance/{$notACommercial->id}");

        $response->assertStatus(404);
    }
}
