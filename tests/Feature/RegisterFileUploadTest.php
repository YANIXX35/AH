<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegisterFileUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_company_can_register_with_a_pdf_logo(): void
    {
        Storage::fake('public');

        $response = $this->post('/register', [
            'name' => 'Awa Traoré',
            'email' => 'awa@ivoire-agro.ci',
            'password' => 'Sitiame2026!',
            'password_confirmation' => 'Sitiame2026!',
            'company_name' => 'Ivoire Agro SARL',
            'company_logo' => UploadedFile::fake()->create('logo-entreprise.pdf', 200, 'application/pdf'),
        ]);

        $response->assertSessionHasNoErrors();

        $user = User::where('email', 'awa@ivoire-agro.ci')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->company_logo);
        Storage::disk('public')->assertExists($user->company_logo);
    }
}
