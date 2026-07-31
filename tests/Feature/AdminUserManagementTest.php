<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reset_user_password(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $targetUser = User::factory()->create(['password' => Hash::make('oldpassword')]);

        $response = $this->actingAs($admin)->post("/admin/users/{$targetUser->id}/reset-password", [
            'password' => 'newpassword123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $this->assertTrue(Hash::check('newpassword123', $targetUser->fresh()->password));
    }

    public function test_admin_can_delete_user(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $targetUser = User::factory()->create(['role_key' => 'commercial']);

        $response = $this->actingAs($admin)->delete("/admin/users/{$targetUser->id}");

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $this->assertDatabaseMissing('users', ['id' => $targetUser->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $response = $this->actingAs($admin)->delete("/admin/users/{$admin->id}");

        $response->assertRedirect();
        $response->assertSessionHasErrors(['user']);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
