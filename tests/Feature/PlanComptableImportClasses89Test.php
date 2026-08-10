<?php

namespace Tests\Feature;

use App\Models\PlanComptableAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PlanComptableImportClasses89Test extends TestCase
{
    use RefreshDatabase;

    public function test_uploading_a_csv_with_classes_8_and_9_no_longer_drops_them_silently(): void
    {
        $user = User::factory()->create(['is_premium' => true]);

        $csv = "compte;intitule\n"
            ."601000;Achats de marchandises\n"
            ."811000;Valeurs comptables cedees\n"
            ."901000;Comptes reflechis\n";

        $file = UploadedFile::fake()->createWithContent('plan.csv', $csv);

        $response = $this->actingAs($user)->post('/accounting/plan-comptable/upload', [
            'plan_comptable' => $file,
        ]);

        $response->assertRedirect(route('accounting.plan'));
        $this->assertDatabaseHas('plan_comptable_accounts', [
            'user_id' => $user->id,
            'numero_compte' => '811000',
        ]);
        $this->assertDatabaseHas('plan_comptable_accounts', [
            'user_id' => $user->id,
            'numero_compte' => '901000',
        ]);
    }
}
