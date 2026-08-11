<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CommercialImportPageRedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_import_page_renders_correctly(): void
    {
        $user = User::factory()->create(['role_key' => 'commercial']);

        $response = $this->actingAs($user)->get('/commercial/import');

        $response->assertOk();
        $response->assertSee('Importateur');
        $response->assertSee('Glissez-déposez votre fichier ici');
        $response->assertSee(route('commercial.import.store'), false);
    }

    public function test_uploading_a_file_still_works_with_the_redesigned_form(): void
    {
        $user = User::factory()->create(['role_key' => 'commercial']);
        $file = UploadedFile::fake()->create('rapport.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user)->post(route('commercial.import.store'), [
            'document_file' => $file,
            'notes' => 'Test note',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('commercial_documents', [
            'user_id' => $user->id,
            'original_name' => 'rapport.pdf',
        ]);
    }
}
