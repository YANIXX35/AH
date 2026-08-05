<?php

namespace Tests\Feature;

use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_view_another_users_payroll_run(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $payroll = PayrollRun::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($stranger)->get("/payroll/{$payroll->id}");

        $response->assertStatus(403);
    }

    public function test_user_cannot_sync_another_users_payroll_run(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $payroll = PayrollRun::factory()->create(['user_id' => $owner->id, 'status' => 'draft']);

        $response = $this->actingAs($stranger)->post("/payroll/{$payroll->id}/sync");

        $response->assertStatus(403);
        $this->assertSame('draft', $payroll->fresh()->status);
    }

    public function test_user_cannot_delete_another_users_payroll_run(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $payroll = PayrollRun::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($stranger)->delete("/payroll/{$payroll->id}");

        $response->assertStatus(403);
        $this->assertNotNull(PayrollRun::find($payroll->id));
    }

    public function test_owner_can_view_their_own_payroll_run(): void
    {
        $owner = User::factory()->create();
        $payroll = PayrollRun::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)->get("/payroll/{$payroll->id}");

        $response->assertStatus(200);
    }

    public function test_owner_can_delete_their_own_payroll_run(): void
    {
        $owner = User::factory()->create();
        $payroll = PayrollRun::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)->delete("/payroll/{$payroll->id}");

        $response->assertRedirect();
        $this->assertNull(PayrollRun::find($payroll->id));
    }
}
