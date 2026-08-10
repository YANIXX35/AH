<?php

namespace Tests\Feature;

use App\Models\PlanComptableAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanComptableDefaultSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_defaults_for_installs_the_full_syscohada_plan(): void
    {
        $user = User::factory()->create();

        PlanComptableAccount::seedDefaultsFor($user->id);

        $count = PlanComptableAccount::where('user_id', $user->id)->count();
        $this->assertSame(1455, $count);

        $this->assertDatabaseHas('plan_comptable_accounts', [
            'user_id' => $user->id,
            'numero_compte' => '4111000',
        ]);

        $classNine = PlanComptableAccount::where('user_id', $user->id)->where('classe', '9')->first();
        $this->assertNotNull($classNine);
        $this->assertSame('analytique', $classNine->category);
    }

    public function test_seed_defaults_captures_all_eleven_source_columns(): void
    {
        $user = User::factory()->create();

        PlanComptableAccount::seedDefaultsFor($user->id);

        $capitalSocial = PlanComptableAccount::where('user_id', $user->id)
            ->where('numero_compte', '101')
            ->firstOrFail();

        $this->assertSame('Passif', $capitalSocial->type_compte);
        $this->assertSame('Compte divisionnaire', $capitalSocial->observation);
        $this->assertSame('Financement', $capitalSocial->nature);
        $this->assertSame('Bilan Passif - Capitaux propres / dettes financieres', $capitalSocial->categorie_bceao);
        $this->assertSame('Financement', $capitalSocial->flux_tafire);

        $sale = PlanComptableAccount::where('user_id', $user->id)
            ->where('numero_compte', '701')
            ->firstOrFail();
        $this->assertNotNull($sale->eligible_tva);
    }

    public function test_seed_defaults_replaces_any_existing_plan(): void
    {
        $user = User::factory()->create();
        PlanComptableAccount::create([
            'user_id' => $user->id,
            'prefix' => '9',
            'label' => 'Compte bidon',
            'category' => 'other',
        ]);

        PlanComptableAccount::seedDefaultsFor($user->id);

        $this->assertSame(1455, PlanComptableAccount::where('user_id', $user->id)->count());
        $this->assertDatabaseMissing('plan_comptable_accounts', [
            'user_id' => $user->id,
            'label' => 'Compte bidon',
        ]);
    }

    public function test_new_company_registration_gets_the_default_plan_seeded(): void
    {
        $response = $this->post('/register', [
            'name' => 'Awa Traoré',
            'email' => 'awa@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();
        $user = User::where('email', 'awa@example.com')->firstOrFail();
        $this->assertSame(1455, PlanComptableAccount::where('user_id', $user->id)->count());
    }

    public function test_reset_plan_comptable_restores_the_full_default_plan(): void
    {
        $user = User::factory()->create(['is_platform_admin' => true, 'is_premium' => true]);
        PlanComptableAccount::create([
            'user_id' => $user->id,
            'prefix' => '1',
            'label' => 'Compte modifié à la main',
            'category' => 'balance',
        ]);

        $response = $this->actingAs($user)->post('/accounting/plan-comptable/reset');

        $response->assertRedirect(route('accounting.plan'));
        $this->assertSame(1455, PlanComptableAccount::where('user_id', $user->id)->count());
    }
}
