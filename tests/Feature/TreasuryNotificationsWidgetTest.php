<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreasuryNotificationsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_treasury_dashboard_shows_the_users_real_notifications_instead_of_fake_ones(): void
    {
        $user = User::factory()->create();
        AppNotification::create([
            'user_id' => $user->id,
            'title' => 'Transaction validée pour de vrai',
            'body' => 'Détail',
            'type' => 'info',
        ]);

        $response = $this->actingAs($user)->get(route('treasury.dashboard-crypto'));

        $response->assertOk();
        $response->assertSee('Transaction validée pour de vrai');
        $response->assertDontSee('Il y a 30 minutes');
        $response->assertDontSee('Solde faible alerte');
    }

    public function test_treasury_dashboard_shows_an_honest_empty_state_when_no_notifications_exist(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('treasury.dashboard-crypto'));

        $response->assertOk();
        $response->assertSee('Aucune notification pour le moment.');
    }
}
