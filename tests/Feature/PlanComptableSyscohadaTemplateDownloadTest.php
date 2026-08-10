<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanComptableSyscohadaTemplateDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_new_route_exists_and_downloads_the_real_syscohada_plan(): void
    {
        $user = User::factory()->create(['is_premium' => true]);

        $response = $this->actingAs($user)->get(route('accounting.plan-comptable.download-template-syscohada'));

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename=plan-comptable-modele-syscohada.csv');

        $content = $response->streamedContent();

        // BOM UTF-8
        $this->assertSame("\xEF\xBB\xBF", substr($content, 0, 3));

        $clean = str_replace('"', '', $content);
        $this->assertStringContainsString('10;CAPITAL', $clean);
        $this->assertStringContainsString('101;Capital social', $clean);
        $this->assertStringNotContainsString('10;Capital et réserves', $clean);

        // 11 colonnes attendues dans l'en-tête (fputcsv peut entourer certains champs de guillemets, c'est du CSV valide)
        $firstLine = str_replace('"', '', strtok(substr($content, 3), "\n"));
        $this->assertSame(
            'Classe;Compte;Intitulé;Type;Observation;Nature;Catégorie BCEAO;Flux TAFIRE;Éligible TVA;Éligible échéancier;Lié immobilisation',
            trim($firstLine)
        );

        $lineCount = substr_count($content, "\n");
        $this->assertGreaterThanOrEqual(1455, $lineCount);
    }

    public function test_response_has_strict_no_cache_headers(): void
    {
        $user = User::factory()->create(['is_premium' => true]);

        $response = $this->actingAs($user)->get(route('accounting.plan-comptable.download-template-syscohada'));

        $cacheControl = $response->headers->get('Cache-Control');
        foreach (['no-store', 'no-cache', 'must-revalidate', 'max-age=0'] as $directive) {
            $this->assertStringContainsString($directive, $cacheControl);
        }
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('Expires', '0');
    }

    public function test_the_plan_comptable_page_links_to_the_new_route_not_the_old_one(): void
    {
        $user = User::factory()->create(['is_premium' => true]);

        $response = $this->actingAs($user)->get('/accounting/plan-comptable');

        $response->assertOk();
        $response->assertSee(route('accounting.plan-comptable.download-template-syscohada'), false);
    }
}
