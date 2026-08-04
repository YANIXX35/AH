<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialMobileMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_commercial_sidebar_shows_mobile_header_and_secondary_items_only(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial', 'name' => 'Awa Traoré']);

        $response = $this->actingAs($commercial)->get('/commercial/dashboard');

        $response->assertStatus(200);

        // Mobile-only avatar header
        $response->assertSee('class="commercial-mobile-header d-lg-none"', false);
        $response->assertSee('Awa Traoré');

        // Section divider grouping secondary items
        $response->assertSee('AUTRES SERVICES');

        // Primary items (already in the bottom nav) are desktop-only in the sidebar
        $response->assertSee('sidebar-item d-none d-lg-block', false);

        // Secondary items appear after the divider
        $html = $response->getContent();
        $dividerPos = strpos($html, 'AUTRES SERVICES');
        $this->assertNotFalse($dividerPos);
        $this->assertGreaterThan($dividerPos, strpos($html, 'Offres Marketing & Service'));
        $this->assertGreaterThan($dividerPos, strpos($html, 'Inscrire Client / PME'));

        // Exactly the 2 pre-existing logout buttons (navbar dropdown + sidebar) — no extra mobile-only duplicate
        $this->assertSame(2, substr_count($html, 'Déconnexion'));
    }

    public function test_non_commercial_dashboard_has_no_commercial_mobile_header(): void
    {
        $manager = User::factory()->create(['role_key' => 'manager']);

        $response = $this->actingAs($manager)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertDontSee('commercial-mobile-header', false);
    }

    public function test_commercial_dashboard_body_has_bottom_nav_class(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);

        $response = $this->actingAs($commercial)->get('/commercial/dashboard');

        $response->assertStatus(200);
        $response->assertSee('<body class="has-bottom-nav">', false);
    }

    public function test_non_commercial_dashboard_body_has_no_bottom_nav_class(): void
    {
        $manager = User::factory()->create(['role_key' => 'manager']);

        $response = $this->actingAs($manager)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('<body class="">', false);
        $response->assertDontSee('has-bottom-nav', false);
    }

    public function test_commercial_dashboard_shows_bottom_nav_with_four_items(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);

        $response = $this->actingAs($commercial)->get('/commercial/dashboard');

        $response->assertStatus(200);
        $response->assertSee('id="commercialBottomNav"', false);
        $response->assertSee('class="bottom-nav-item js-sidebar-toggle"', false);

        $html = $response->getContent();
        $navStart = strpos($html, 'id="commercialBottomNav"');
        $this->assertNotFalse($navStart);
        $navHtml = substr($html, $navStart, 1200);
        $this->assertStringContainsString('Tableau de bord', $navHtml);
        $this->assertStringContainsString('Portefeuille', $navHtml);
        $this->assertStringContainsString('Prospects', $navHtml);
        $this->assertStringContainsString('Menu', $navHtml);
    }

    public function test_commercial_portefeuille_bottom_nav_marks_portefeuille_active(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);

        $response = $this->actingAs($commercial)->get('/commercial/portefeuille');

        $response->assertStatus(200);
        $html = $response->getContent();
        $navStart = strpos($html, 'id="commercialBottomNav"');
        $navHtml = substr($html, $navStart, 1200);
        $this->assertStringContainsString('bottom-nav-item active', $navHtml);
    }

    public function test_non_commercial_dashboard_has_no_bottom_nav(): void
    {
        $manager = User::factory()->create(['role_key' => 'manager']);

        $response = $this->actingAs($manager)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertDontSee('commercialBottomNav', false);
    }
}
