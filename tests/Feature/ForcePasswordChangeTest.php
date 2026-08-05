<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_created_by_a_commercial_must_change_password_before_using_the_app(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);

        $this->actingAs($commercial)->post('/commercial/clients', [
            'name' => 'Client Sécurisé',
            'email' => 'client-securise@sitiame.ci',
            'company_name' => 'Client SAS',
        ]);

        $client = User::where('email', 'client-securise@sitiame.ci')->first();
        $this->assertTrue((bool) $client->must_change_password);

        $response = $this->actingAs($client)->get('/dashboard');
        $response->assertRedirect(route('profile'));
        $response->assertSessionHas('warning');
    }

    public function test_the_profile_page_itself_stays_reachable_while_password_change_is_pending(): void
    {
        $client = User::factory()->create(['must_change_password' => true]);

        $response = $this->actingAs($client)->get('/profile');

        $response->assertOk();
    }

    public function test_setting_a_new_password_via_profile_lifts_the_restriction(): void
    {
        $client = User::factory()->create([
            'must_change_password' => true,
            'timezone' => 'Africa/Abidjan',
            'locale' => 'fr',
            'currency' => 'XOF',
        ]);

        $response = $this->actingAs($client)->post('/profile', [
            'name' => $client->name,
            'email' => $client->email,
            'timezone' => 'Africa/Abidjan',
            'locale' => 'fr',
            'currency' => 'XOF',
            'password' => 'NouveauMotDePasse2026!',
            'password_confirmation' => 'NouveauMotDePasse2026!',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertFalse((bool) $client->fresh()->must_change_password);

        $followUp = $this->actingAs($client->fresh())->get('/dashboard');
        $followUp->assertOk();
    }

    public function test_a_regularly_registered_user_is_never_blocked(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
    }
}
