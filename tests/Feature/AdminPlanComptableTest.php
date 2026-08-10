<?php

namespace Tests\Feature;

use App\Models\PlanComptableAccount;
use App\Models\PlanComptableDefault;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminPlanComptableTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_admin_cannot_access_the_admin_plan_comptable_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/plan-comptable');

        $response->assertForbidden();
    }

    public function test_an_admin_can_view_the_reference_plan_page(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $response = $this->actingAs($admin)->get('/admin/plan-comptable');

        $response->assertOk();
        $response->assertSee('Plan comptable');
        $response->assertSee('1455');
    }

    public function test_an_admin_can_upload_a_new_reference_plan_replacing_the_old_one(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $csv = "Classe;Compte;Intitulé;Type\n"
            ."1;10;CAPITAL TEST;Passif\n"
            ."6;601;Achats de marchandises TEST;Charge\n";
        $file = UploadedFile::fake()->createWithContent('plan.csv', $csv);

        $response = $this->actingAs($admin)->post('/admin/plan-comptable/upload', [
            'plan_file' => $file,
        ]);

        $response->assertRedirect(route('admin.plan-comptable.index'));
        $this->assertSame(2, PlanComptableDefault::count());
        $this->assertDatabaseHas('plan_comptable_defaults', [
            'numero_compte' => '10',
            'libelle_compte' => 'CAPITAL TEST',
        ]);
    }

    public function test_applying_to_existing_replaces_every_companys_plan(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $company = User::factory()->create();
        PlanComptableAccount::create([
            'user_id' => $company->id,
            'prefix' => '1',
            'label' => 'Ancien compte',
            'category' => 'balance',
        ]);

        $response = $this->actingAs($admin)->post('/admin/plan-comptable/apply-to-existing');

        $response->assertRedirect(route('admin.plan-comptable.index'));
        $this->assertSame(1455, PlanComptableAccount::where('user_id', $company->id)->count());
    }
}
