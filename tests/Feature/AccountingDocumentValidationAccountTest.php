<?php

namespace Tests\Feature;

use App\Models\AccountingDocument;
use App\Models\PlanComptableAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingDocumentValidationAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_validating_a_document_with_a_real_account_generates_the_entry(): void
    {
        $user = User::factory()->create(['is_platform_admin' => true, 'is_premium' => true]);
        PlanComptableAccount::seedDefaultsFor($user->id);

        $document = AccountingDocument::create([
            'user_id' => $user->id,
            'document_type' => 'Achat',
            'status' => 'pending',
            'original_name' => 'facture.pdf',
            'stored_path' => 'accounting-documents/facture.pdf',
            'document_hash' => md5(uniqid('', true)),
        ]);

        $response = $this->actingAs($user)->post(route('accounting.documents.validate.store', $document), [
            'partner' => 'Fournisseur Test',
            'invoice_date' => now()->toDateString(),
            'invoice_number' => 'FAC-2026-001',
            'amount_ttc' => 10000,
            'currency' => 'FCFA',
            'document_type' => 'Achat',
            'debit_account' => '601 Achats de marchandises',
            'credit_account' => '401 Fournisseurs, dettes en compte',
        ]);

        $response->assertRedirect(route('accounting.documents'));
        $this->assertDatabaseHas('accounting_entries', [
            'document_id' => $document->id,
            'debit_account' => '601 Achats de marchandises',
            'credit_account' => '401 Fournisseurs, dettes en compte',
        ]);
    }

    public function test_validating_a_document_with_a_fake_account_is_rejected(): void
    {
        $user = User::factory()->create(['is_platform_admin' => true, 'is_premium' => true]);
        PlanComptableAccount::seedDefaultsFor($user->id);

        $document = AccountingDocument::create([
            'user_id' => $user->id,
            'document_type' => 'Achat',
            'status' => 'pending',
            'original_name' => 'facture.pdf',
            'stored_path' => 'accounting-documents/facture.pdf',
            'document_hash' => md5(uniqid('', true)),
        ]);

        $response = $this->actingAs($user)->post(route('accounting.documents.validate.store', $document), [
            'partner' => 'Fournisseur Test',
            'invoice_date' => now()->toDateString(),
            'amount_ttc' => 10000,
            'currency' => 'FCFA',
            'document_type' => 'Achat',
            'debit_account' => '999999 Compte inexistant',
            'credit_account' => '401 Fournisseurs, dettes en compte',
        ]);

        $response->assertSessionHasErrors('debit_account');
        $this->assertDatabaseMissing('accounting_entries', ['document_id' => $document->id]);
    }
}
