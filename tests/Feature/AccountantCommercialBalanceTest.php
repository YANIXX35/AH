<?php

namespace Tests\Feature;

use App\Models\CommercialPayout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountantCommercialBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_sees_every_commercials_earned_and_remaining_balance(): void
    {
        $accountant = User::factory()->create(['is_accountant' => true]);
        $commercial = User::factory()->create(['role_key' => 'commercial', 'name' => 'Awa Traoré']);
        User::factory()->create([
            'created_by_user_id' => $commercial->id,
            'created_at' => now()->subDays(3),
        ]);

        $response = $this->actingAs($accountant)->get('/accountant/commercials-balance');

        $response->assertOk();
        $response->assertSee('Awa Traoré');
        $response->assertSee('10 000 F'); // 1 client => tier1 bonus
    }

    public function test_accountant_can_validate_a_payout_and_it_generates_a_pdf_receipt(): void
    {
        Storage::fake('public');

        $accountant = User::factory()->create(['is_accountant' => true]);
        $commercial = User::factory()->create(['role_key' => 'commercial']);
        User::factory()->create([
            'created_by_user_id' => $commercial->id,
            'created_at' => now()->subDays(3),
        ]);

        // Total earned = 10000 (1 client, tier 1, no renewals yet).
        $response = $this->actingAs($accountant)->post("/accountant/commercials-balance/{$commercial->id}/payouts", [
            'amount' => 10000,
            'note' => 'Virement Wave',
        ]);

        $response->assertRedirect(route('accountant.commercials-balance.show', $commercial));
        $response->assertSessionHas('status');

        $payout = CommercialPayout::where('commercial_user_id', $commercial->id)->first();
        $this->assertNotNull($payout);
        $this->assertSame(10000, $payout->amount);
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
        User::factory()->create([
            'created_by_user_id' => $commercial->id,
            'created_at' => now()->subDays(3),
        ]);

        // Only 10000 is owed (1 client), trying to pay 50000 should fail.
        $response = $this->actingAs($accountant)->post("/accountant/commercials-balance/{$commercial->id}/payouts", [
            'amount' => 50000,
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, CommercialPayout::count());
    }

    public function test_receipt_download_serves_the_generated_pdf(): void
    {
        Storage::fake('public');

        $accountant = User::factory()->create(['is_accountant' => true]);
        $commercial = User::factory()->create(['role_key' => 'commercial']);
        User::factory()->create(['created_by_user_id' => $commercial->id]);

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
