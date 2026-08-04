<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountantClientFileUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_can_create_client_with_pdf_attestation_and_trade_register(): void
    {
        Storage::fake('public');

        $accountant = User::factory()->create(['is_accountant' => true]);

        $response = $this->actingAs($accountant)->post('/accountant/clients', [
            'company_name' => 'Ivoire Agro SARL',
            'email' => 'client@iagro.ci',
            'company_logo' => UploadedFile::fake()->create('attestation-dfe.pdf', 200, 'application/pdf'),
            'trade_register' => UploadedFile::fake()->create('registre-commerce.pdf', 200, 'application/pdf'),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $client = User::where('email', 'client@iagro.ci')->first();
        $this->assertNotNull($client);
        $this->assertNotNull($client->company_logo);
        $this->assertNotNull($client->trade_register_file);
        Storage::disk('public')->assertExists($client->company_logo);
        Storage::disk('public')->assertExists($client->trade_register_file);
    }

    public function test_accountant_can_update_client_with_pdf_attestation(): void
    {
        Storage::fake('public');

        $accountant = User::factory()->create(['is_accountant' => true]);
        $client = User::factory()->create(['created_by_user_id' => $accountant->id]);

        $response = $this->actingAs($accountant)->put("/accountant/clients/{$client->id}", [
            'name' => $client->name,
            'email' => $client->email,
            'company_logo' => UploadedFile::fake()->create('attestation-dfe.pdf', 200, 'application/pdf'),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $client->refresh();
        $this->assertNotNull($client->company_logo);
        Storage::disk('public')->assertExists($client->company_logo);
    }

    public function test_invalid_attestation_file_shows_error_instead_of_failing_silently(): void
    {
        $accountant = User::factory()->create(['is_accountant' => true]);

        $response = $this->actingAs($accountant)->post('/accountant/clients', [
            'company_name' => 'Ivoire Agro SARL',
            'email' => 'client2@iagro.ci',
            'company_logo' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ]);

        $response->assertSessionHasErrors('company_logo');
        $this->assertNull(User::where('email', 'client2@iagro.ci')->first());
    }

    public function test_validation_errors_render_visibly_on_the_clients_index_page(): void
    {
        $accountant = User::factory()->create(['is_accountant' => true]);

        $this->actingAs($accountant)->post('/accountant/clients', [
            'company_name' => 'Ivoire Agro SARL',
            'email' => 'client3@iagro.ci',
            'company_logo' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ]);

        $response = $this->actingAs($accountant)->get('/accountant/clients');

        $response->assertStatus(200);
        $response->assertSee("n'a pas pu être enregistré", false);
        // La traduction française du message doit être résolue, pas la clé brute
        $response->assertDontSee('validation.mimes');
        $response->assertSee('attestation DFE / NIF doit être un fichier de type', false);
    }
}
