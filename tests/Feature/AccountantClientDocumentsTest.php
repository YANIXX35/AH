<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountantClientDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_sees_document_status_for_each_client(): void
    {
        Storage::fake('public');

        $accountant = User::factory()->create(['is_accountant' => true]);
        $withDocs = User::factory()->create([
            'created_by_user_id' => $accountant->id,
            'company_logo' => 'company-logos/existing-attestation.pdf',
        ]);
        $withoutDocs = User::factory()->create([
            'created_by_user_id' => $accountant->id,
        ]);

        $response = $this->actingAs($accountant)->get('/accountant/documents');

        $response->assertOk();
        $response->assertSee($withDocs->name);
        $response->assertSee($withoutDocs->name);
        $response->assertSee('Fourni');
        $response->assertSee('Non fourni');
    }

    public function test_accountant_can_replace_a_client_trade_register_document(): void
    {
        Storage::fake('public');

        $accountant = User::factory()->create(['is_accountant' => true]);
        $client = User::factory()->create([
            'created_by_user_id' => $accountant->id,
            'trade_register_file' => 'trade-registers/old-file.pdf',
        ]);

        $response = $this->actingAs($accountant)->post("/accountant/documents/{$client->id}/trade_register", [
            'file' => UploadedFile::fake()->create('nouveau-registre.pdf', 200, 'application/pdf'),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $client->refresh();
        $this->assertNotNull($client->trade_register_file);
        $this->assertNotSame('trade-registers/old-file.pdf', $client->trade_register_file);
        Storage::disk('public')->assertExists($client->trade_register_file);
    }

    public function test_accountant_can_preview_a_client_company_logo_document(): void
    {
        Storage::fake('public');

        $accountant = User::factory()->create(['is_accountant' => true]);
        $client = User::factory()->create(['created_by_user_id' => $accountant->id]);

        Storage::disk('public')->put('company-logos/attestation.pdf', UploadedFile::fake()->create('attestation.pdf', 50, 'application/pdf')->get());
        $client->update(['company_logo' => 'company-logos/attestation.pdf']);

        $response = $this->actingAs($accountant)->get("/accountant/documents/{$client->id}/company_logo/view");

        $response->assertOk();
        $response->assertSee('Attestation DFE / NIF');
    }

    public function test_accountant_cannot_access_another_accountants_client_documents(): void
    {
        Storage::fake('public');

        $accountantA = User::factory()->create(['is_accountant' => true]);
        $accountantB = User::factory()->create(['is_accountant' => true]);
        $client = User::factory()->create([
            'created_by_user_id' => $accountantA->id,
            'trade_register_file' => 'trade-registers/file.pdf',
        ]);

        $response = $this->actingAs($accountantB)->post("/accountant/documents/{$client->id}/trade_register", [
            'file' => UploadedFile::fake()->create('nouveau.pdf', 50, 'application/pdf'),
        ]);

        $response->assertStatus(403);
    }
}
