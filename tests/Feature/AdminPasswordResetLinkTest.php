<?php

namespace Tests\Feature;

use App\Models\AdminPasswordResetLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPasswordResetLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_generate_a_reset_link_for_any_user(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $target = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.password-reset-link', $target));

        $response->assertRedirect();
        $response->assertSessionHas('passwordResetLink');
        $this->assertDatabaseCount('admin_password_reset_links', 1);
    }

    public function test_a_non_admin_cannot_generate_a_reset_link(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.users.password-reset-link', $target));

        $response->assertForbidden();
    }

    public function test_visiting_a_valid_link_shows_the_password_form(): void
    {
        $target = User::factory()->create();
        $token = AdminPasswordResetLink::generateFor($target, null);

        $response = $this->get(route('password.reset-link.show', $token));

        $response->assertOk();
        $response->assertSee($target->email);
    }

    public function test_an_invalid_token_redirects_to_login_with_an_error(): void
    {
        $response = $this->get(route('password.reset-link.show', 'not-a-real-token'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('reset_link');
    }

    public function test_submitting_a_new_password_via_the_link_actually_changes_it(): void
    {
        $target = User::factory()->create(['password' => Hash::make('old-password')]);
        $token = AdminPasswordResetLink::generateFor($target, null);

        $response = $this->post(route('password.reset-link.submit', $token), [
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('brand-new-password', $target->fresh()->password));
    }

    public function test_the_link_cannot_be_reused_after_it_has_been_used_once(): void
    {
        $target = User::factory()->create();
        $token = AdminPasswordResetLink::generateFor($target, null);

        $this->post(route('password.reset-link.submit', $token), [
            'password' => 'first-password',
            'password_confirmation' => 'first-password',
        ]);

        $response = $this->post(route('password.reset-link.submit', $token), [
            'password' => 'second-password',
            'password_confirmation' => 'second-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('reset_link');
        $this->assertTrue(Hash::check('first-password', $target->fresh()->password));
    }

    public function test_an_expired_link_is_rejected(): void
    {
        $target = User::factory()->create();
        $token = AdminPasswordResetLink::generateFor($target, null, -1);

        $response = $this->post(route('password.reset-link.submit', $token), [
            'password' => 'whatever-password',
            'password_confirmation' => 'whatever-password',
        ]);

        $response->assertSessionHasErrors('reset_link');
    }
}
