<?php

namespace Tests\Feature;

use App\Models\CommercialFeedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_commercial_can_submit_real_feedback_instead_of_a_fake_alert(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);

        $response = $this->actingAs($commercial)->postJson('/commercial/offres/feedback', [
            'rating' => 4,
            'satisfaction_label' => 'Satisfait',
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        $this->assertDatabaseHas('commercial_feedback', [
            'user_id' => $commercial->id,
            'rating' => 4,
            'satisfaction_label' => 'Satisfait',
        ]);
    }

    public function test_feedback_rejects_an_out_of_range_rating(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);

        $response = $this->actingAs($commercial)->postJson('/commercial/offres/feedback', [
            'rating' => 9,
            'satisfaction_label' => 'Satisfait',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, CommercialFeedback::count());
    }

    public function test_feedback_rejects_an_unknown_satisfaction_label(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);

        $response = $this->actingAs($commercial)->postJson('/commercial/offres/feedback', [
            'rating' => 5,
            'satisfaction_label' => 'Excellent (pas dans la liste)',
        ]);

        $response->assertStatus(422);
    }
}
