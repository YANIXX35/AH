<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanComptableTemplateDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_downloaded_template_reflects_the_real_syscohada_plan_not_a_stale_file(): void
    {
        $user = User::factory()->create(['is_premium' => true]);

        $response = $this->actingAs($user)->get('/accounting/plan-comptable/template');

        $response->assertOk();
        $content = $response->streamedContent();

        $clean = str_replace('"', '', $content);
        $this->assertStringContainsString('10,CAPITAL', $clean);
        $this->assertStringContainsString('101,Capital social', $clean);
        // L'ancien fichier statique associait le compte 10 au mauvais libellé.
        $this->assertStringNotContainsString('10,Capital et réserves', $clean);
        $this->assertStringNotContainsString('102,' . "Primes d'émission", $clean);

        $lineCount = substr_count($content, "\n");
        $this->assertGreaterThan(1400, $lineCount);
    }
}
