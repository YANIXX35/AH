<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiBusinessAdvisorTest extends TestCase
{
    use RefreshDatabase;

    public function test_advisor_chat_greeting_shortcut(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/ai/business/chat', [
            'message' => 'Bonjour !',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('ok', true);
        $this->assertStringContainsString('Sitiame Capital', $response->json('answer'));
        $this->assertStringContainsString('OHADA', $response->json('answer'));
    }
}
