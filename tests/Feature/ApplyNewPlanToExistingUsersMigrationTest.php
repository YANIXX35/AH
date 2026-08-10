<?php

namespace Tests\Feature;

use App\Models\PlanComptableAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplyNewPlanToExistingUsersMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_data_migration_upgrades_every_user_who_already_had_a_plan(): void
    {
        $userWithOldPlan = User::factory()->create();
        $userWithoutAnyPlan = User::factory()->create();

        PlanComptableAccount::create([
            'user_id' => $userWithOldPlan->id,
            'prefix' => '6',
            'label' => 'Charges (ancien plan)',
            'category' => 'resultat',
        ]);

        $migration = require base_path('database/migrations/2026_08_10_072803_apply_new_syscohada_plan_to_existing_users.php');
        $migration->up();

        $this->assertSame(1455, PlanComptableAccount::where('user_id', $userWithOldPlan->id)->count());
        $this->assertDatabaseMissing('plan_comptable_accounts', [
            'user_id' => $userWithOldPlan->id,
            'label' => 'Charges (ancien plan)',
        ]);

        $this->assertSame(0, PlanComptableAccount::where('user_id', $userWithoutAnyPlan->id)->count());
    }
}
