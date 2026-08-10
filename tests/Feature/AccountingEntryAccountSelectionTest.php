<?php

namespace Tests\Feature;

use App\Models\AccountingEntry;
use App\Models\PlanComptableAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingEntryAccountSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_accounts_returns_matches_from_the_companys_own_plan(): void
    {
        $user = User::factory()->create(['is_platform_admin' => true, 'is_premium' => true]);
        PlanComptableAccount::seedDefaultsFor($user->id);

        $response = $this->actingAs($user)->getJson('/accounting/comptes/search?q=101');

        $response->assertOk();
        $response->assertJsonFragment(['numero_compte' => '101', 'libelle_compte' => 'Capital social']);
    }

    public function test_search_accounts_can_be_filtered_by_classe(): void
    {
        $user = User::factory()->create(['is_platform_admin' => true, 'is_premium' => true]);
        PlanComptableAccount::seedDefaultsFor($user->id);

        $response = $this->actingAs($user)->getJson('/accounting/comptes/search?classe=6&q=Achats+de+marchandises');

        $response->assertOk();
        $data = $response->json();
        $this->assertNotEmpty($data);
        foreach ($data as $account) {
            $this->assertSame('6', $account['classe']);
        }
    }

    public function test_search_accounts_is_scoped_to_the_current_workspace_only(): void
    {
        $userA = User::factory()->create(['is_platform_admin' => true, 'is_premium' => true]);
        $userB = User::factory()->create(['is_platform_admin' => true, 'is_premium' => true]);
        PlanComptableAccount::seedDefaultsFor($userA->id);
        // userB n'a aucun compte installé.

        $response = $this->actingAs($userB)->getJson('/accounting/comptes/search?q=101');

        $response->assertOk();
        $response->assertJson([]);
    }

    public function test_storing_an_entry_with_a_real_account_succeeds(): void
    {
        $user = User::factory()->create(['is_platform_admin' => true, 'is_premium' => true]);
        PlanComptableAccount::seedDefaultsFor($user->id);

        $response = $this->actingAs($user)->post('/accounting/entries', [
            'date' => now()->toDateString(),
            'document_type' => 'Achat',
            'description' => 'Achat de fournitures',
            'amount' => 15000,
            'debit_account' => '601 Achats de marchandises',
            'credit_account' => '401 Fournisseurs, dettes en compte',
        ]);

        $response->assertRedirect(route('accounting'));
        $this->assertDatabaseHas('accounting_entries', [
            'debit_account' => '601 Achats de marchandises',
            'credit_account' => '401 Fournisseurs, dettes en compte',
        ]);
    }

    public function test_storing_an_entry_with_a_fake_account_is_rejected(): void
    {
        $user = User::factory()->create(['is_platform_admin' => true, 'is_premium' => true]);
        PlanComptableAccount::seedDefaultsFor($user->id);

        $response = $this->actingAs($user)->post('/accounting/entries', [
            'date' => now()->toDateString(),
            'document_type' => 'Achat',
            'description' => 'Achat de fournitures',
            'amount' => 15000,
            'debit_account' => '999999 Compte qui n\'existe pas',
            'credit_account' => '401 Fournisseurs, dettes en compte',
        ]);

        $response->assertSessionHasErrors('debit_account');
        $this->assertDatabaseMissing('accounting_entries', [
            'description' => 'Achat de fournitures',
        ]);
    }

    public function test_updating_an_entry_requires_a_real_account_too(): void
    {
        $user = User::factory()->create(['is_platform_admin' => true, 'is_premium' => true]);
        PlanComptableAccount::seedDefaultsFor($user->id);

        $entry = AccountingEntry::create([
            'user_id' => $user->id,
            'date' => now(),
            'document_type' => 'Achat',
            'description' => 'Ancienne écriture',
            'debit_account' => '601 Achats de marchandises',
            'credit_account' => '401 Fournisseurs, dettes en compte',
            'amount' => 5000,
        ]);

        $response = $this->actingAs($user)->put("/accounting/entries/{$entry->id}", [
            'date' => now()->toDateString(),
            'document_type' => 'Vente',
            'description' => 'Écriture modifiée',
            'amount' => 7000,
            'debit_account' => '411 Clients',
            'credit_account' => '701 Ventes de marchandises',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('accounting_entries', [
            'id' => $entry->id,
            'debit_account' => '411 Clients',
            'credit_account' => '701 Ventes de marchandises',
        ]);
    }
}
