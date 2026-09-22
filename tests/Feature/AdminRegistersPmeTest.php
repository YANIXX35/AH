<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminController;
use App\Jobs\ProvisionErpNextCompanyForPme;
use App\Models\PlanComptableAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminRegistersPmeTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['is_platform_admin' => true]);
    }

    public function test_guest_cannot_register_a_pme(): void
    {
        $this->post(route('admin.pme.store'), [
            'name' => 'Contact Test', 'email' => 'nouvelle-pme@example.com',
            'company_name' => 'Nouvelle PME',
        ])->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_register_a_pme(): void
    {
        $regular = User::factory()->create(['is_platform_admin' => false]);

        $this->actingAs($regular)->post(route('admin.pme.store'), [
            'name' => 'Contact Test', 'email' => 'nouvelle-pme@example.com',
            'company_name' => 'Nouvelle PME',
        ])->assertForbidden();
    }

    public function test_admin_registers_pme_with_default_password_when_left_blank(): void
    {
        Queue::fake();
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('admin.pme.store'), [
            'name' => 'Jean Kouassi',
            'email' => 'jean.kouassi@example.com',
            'phone' => '+225 0102030405',
            'company_name' => 'Kouassi Transport SARL',
            'company_tax_id' => 'CI0123456X',
            'rccm' => 'CI-ABJ-2026-B-00001',
            'city' => 'Abidjan',
        ]);

        $response->assertRedirect(route('admin.users'));
        $response->assertSessionHas('status');
        $this->assertStringContainsString(AdminController::DEFAULT_PME_PASSWORD, session('status'));

        $pme = User::where('email', 'jean.kouassi@example.com')->first();
        $this->assertNotNull($pme);
        $this->assertSame('Kouassi Transport SARL', $pme->company_name);
        $this->assertSame('manager', $pme->role_key);
        $this->assertTrue((bool) $pme->must_change_password);
        $this->assertSame($admin->id, $pme->created_by_user_id);
        $this->assertNull($pme->terms_accepted_at);
        $this->assertTrue(Hash::check(AdminController::DEFAULT_PME_PASSWORD, $pme->password));

        $this->assertDatabaseHas('plan_comptable_accounts', ['user_id' => $pme->id]);
        $this->assertGreaterThan(0, PlanComptableAccount::where('user_id', $pme->id)->count());

        Queue::assertPushed(ProvisionErpNextCompanyForPme::class, function ($job) use ($pme) {
            return $job->pme->is($pme);
        });
    }

    public function test_admin_registers_pme_with_custom_password(): void
    {
        Queue::fake();
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.pme.store'), [
            'name' => 'Awa Diallo',
            'email' => 'awa.diallo@example.com',
            'company_name' => 'Diallo Commerce',
            'password' => 'MonMotDePasse123',
        ])->assertRedirect(route('admin.users'));

        $pme = User::where('email', 'awa.diallo@example.com')->first();
        $this->assertTrue(Hash::check('MonMotDePasse123', $pme->password));
    }

    public function test_registration_requires_name_email_and_company_name(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.pme.store'), [])
            ->assertSessionHasErrors(['name', 'email', 'company_name']);

        $this->assertDatabaseCount('users', 1); // only the admin
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        $admin = $this->makeAdmin();
        User::factory()->create(['email' => 'deja-pris@example.com']);

        $this->actingAs($admin)->post(route('admin.pme.store'), [
            'name' => 'Contact', 'email' => 'deja-pris@example.com', 'company_name' => 'X',
        ])->assertSessionHasErrors(['email']);
    }
}
