<?php

namespace Tests\Feature;

use App\Models\SystemBugReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBugReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_signalements_page(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $response = $this->actingAs($admin)->get('/admin/signalements');

        $response->assertStatus(200);
        $response->assertSee('Signalements');
        $response->assertSee('Base de Données');
    }

    public function test_admin_can_resolve_bug_report(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $bug = SystemBugReport::create([
            'dashboard' => 'Dashboard Administration',
            'page_url' => 'https://sitiame-capital.com/admin/users',
            'route_name' => 'admin.users',
            'error_class' => 'QueryException',
            'message' => 'SQLSTATE[HY000]: General error: 1364 Field user_id has no default value',
            'file' => 'app/Http/Controllers/AdminController.php',
            'line' => 142,
            'severity' => 'CRITICAL',
            'status' => 'OPEN',
        ]);

        $response = $this->actingAs($admin)->post("/admin/signalements/{$bug->id}/resolve", [
            'resolution_note' => 'Corrigé en ajoutant nullable() sur la migration user_id.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $this->assertEquals('RESOLVED', $bug->fresh()->status);
        $this->assertEquals($admin->id, $bug->fresh()->resolved_by_user_id);
    }

    public function test_admin_can_delete_bug_report(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $bug = SystemBugReport::create([
            'dashboard' => 'Dashboard Entreprise (PME)',
            'page_url' => 'https://sitiame-capital.com/accounting',
            'error_class' => 'ErrorException',
            'message' => 'Undefined array key "company_name"',
            'severity' => 'MEDIUM',
            'status' => 'OPEN',
        ]);

        $response = $this->actingAs($admin)->delete("/admin/signalements/{$bug->id}");

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $this->assertDatabaseMissing('system_bug_reports', ['id' => $bug->id]);
    }
}
