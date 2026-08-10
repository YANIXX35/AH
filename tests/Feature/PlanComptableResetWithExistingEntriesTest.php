<?php

namespace Tests\Feature;

use App\Models\AccountingEntry;
use App\Models\PlanComptableAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanComptableResetWithExistingEntriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_resetting_the_plan_does_not_touch_or_break_existing_journal_entries(): void
    {
        $user = User::factory()->create(['is_platform_admin' => true, 'is_premium' => true]);

        PlanComptableAccount::create([
            'user_id' => $user->id,
            'prefix' => '6',
            'label' => 'Charges (ancien plan)',
            'category' => 'resultat',
        ]);

        $entry = AccountingEntry::create([
            'user_id' => $user->id,
            'date' => now(),
            'document_type' => 'facture',
            'description' => 'Achat fournitures',
            'debit_account' => '601',
            'credit_account' => '401',
            'amount' => 15000,
        ]);

        $response = $this->actingAs($user)->post('/accounting/plan-comptable/reset');
        $response->assertRedirect(route('accounting.plan'));

        $this->assertDatabaseHas('accounting_entries', [
            'id' => $entry->id,
            'debit_account' => '601',
            'credit_account' => '401',
            'amount' => 15000,
        ]);

        $this->assertSame(1455, PlanComptableAccount::where('user_id', $user->id)->count());

        $reportResponse = $this->actingAs($user)->get('/accounting/report/journal');
        $reportResponse->assertOk();
    }
}
