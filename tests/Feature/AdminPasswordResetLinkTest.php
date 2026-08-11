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

    public function test_the_admin_generated_link_expires_after_five_minutes(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $target = User::factory()->create();

        $this->actingAs($admin)->postJson(route('admin.users.password-reset-link', $target));

        $link = AdminPasswordResetLink::where('user_id', $target->id)->firstOrFail();
        $this->assertEqualsWithDelta(
            now()->addMinutes(5)->timestamp,
            $link->expires_at->timestamp,
            5
        );
    }

    public function test_an_admin_can_generate_a_reset_link_via_ajax_and_gets_json_back(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $target = User::factory()->create(['name' => 'Client JSON', 'email' => 'client-json@example.com']);

        $response = $this->actingAs($admin)->postJson(route('admin.users.password-reset-link', $target));

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'user_name' => 'Client JSON',
            'user_email' => 'client-json@example.com',
        ]);
        $response->assertJsonStructure(['url']);
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

    public function test_an_already_logged_in_admin_can_still_open_and_use_a_reset_link(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $target = User::factory()->create();
        $token = AdminPasswordResetLink::generateFor($target, $admin->id);

        $showResponse = $this->actingAs($admin)->get(route('password.reset-link.show', $token));
        $showResponse->assertOk();
        $showResponse->assertSee($target->email);

        $submitResponse = $this->actingAs($admin)->post(route('password.reset-link.submit', $token), [
            'password' => 'nouveau-mdp-123',
            'password_confirmation' => 'nouveau-mdp-123',
        ]);
        $submitResponse->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('nouveau-mdp-123', $target->fresh()->password));
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
