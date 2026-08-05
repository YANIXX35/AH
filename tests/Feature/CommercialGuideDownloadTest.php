<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommercialGuideDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_button_tells_the_truth_when_the_guide_file_is_missing(): void
    {
        Storage::fake('public');
        $commercial = User::factory()->create(['role_key' => 'commercial']);

        $response = $this->actingAs($commercial)->get('/commercial/guides/bilan-syscohada/download');

        $response->assertRedirect(route('commercial.guides'));
        $response->assertSessionHas('error', function (string $message) {
            return str_contains($message, 'pas encore disponible');
        });
    }

    public function test_download_button_serves_the_real_file_once_an_admin_has_uploaded_it(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('guides/guide-1-bilan-syscohada.pdf', '%PDF-1.4 fake content');
        $commercial = User::factory()->create(['role_key' => 'commercial']);

        $response = $this->actingAs($commercial)->get('/commercial/guides/bilan-syscohada/download');

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    public function test_unknown_guide_slug_returns_404(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);

        $response = $this->actingAs($commercial)->get('/commercial/guides/inexistant/download');

        $response->assertNotFound();
    }
}
