<?php

namespace Tests\Feature;

use App\Domain\Accounting\QualityControlService;
use App\Models\AccountingQualityReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AccountingQualityReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_period_is_not_checked_when_no_review_exists(): void
    {
        $service = app(QualityControlService::class);
        $sme = User::factory()->create();
        $period = $service->currentPeriod();

        $this->assertFalse($service->isQualityCheckedForPeriod($sme->id, $period['start'], $period['end']));
    }

    public function test_marking_a_period_validated_makes_it_pass_the_gate(): void
    {
        $service = app(QualityControlService::class);
        $sme = User::factory()->create();
        $reviewer = User::factory()->create(['is_accountant' => true]);
        $period = $service->currentPeriod();

        $review = $service->markPeriodReviewed(
            $sme->id,
            $period['start'],
            $period['end'],
            'validated',
            $reviewer->id,
            'Contrôle manuel de test'
        );

        $this->assertSame('validated', $review->status);
        $this->assertSame($reviewer->id, $review->reviewed_by);
        $this->assertNotNull($review->reviewed_at);
        $this->assertTrue($service->isQualityCheckedForPeriod($sme->id, $period['start'], $period['end']));
    }

    public function test_marking_a_period_flagged_does_not_pass_the_gate(): void
    {
        $service = app(QualityControlService::class);
        $sme = User::factory()->create();
        $reviewer = User::factory()->create(['is_accountant' => true]);
        $period = $service->currentPeriod();

        $service->markPeriodReviewed($sme->id, $period['start'], $period['end'], 'flagged', $reviewer->id);

        $this->assertFalse($service->isQualityCheckedForPeriod($sme->id, $period['start'], $period['end']));
    }

    public function test_gate_is_scoped_per_sme_and_does_not_leak_across_companies(): void
    {
        $service = app(QualityControlService::class);
        $smeA = User::factory()->create();
        $smeB = User::factory()->create();
        $period = $service->currentPeriod();

        $service->markPeriodReviewed($smeA->id, $period['start'], $period['end'], 'validated', null);

        $this->assertTrue($service->isQualityCheckedForPeriod($smeA->id, $period['start'], $period['end']));
        $this->assertFalse($service->isQualityCheckedForPeriod($smeB->id, $period['start'], $period['end']));
    }

    public function test_invalid_status_is_rejected(): void
    {
        $service = app(QualityControlService::class);
        $sme = User::factory()->create();
        $period = $service->currentPeriod();

        $this->expectException(\InvalidArgumentException::class);
        $service->markPeriodReviewed($sme->id, $period['start'], $period['end'], 'not-a-real-status', null);
    }

    public function test_re_marking_the_same_period_updates_rather_than_duplicates(): void
    {
        $service = app(QualityControlService::class);
        $sme = User::factory()->create();
        $period = $service->currentPeriod();

        $service->markPeriodReviewed($sme->id, $period['start'], $period['end'], 'flagged', null);
        $service->markPeriodReviewed($sme->id, $period['start'], $period['end'], 'validated', null);

        $this->assertSame(1, AccountingQualityReview::where('user_id', $sme->id)->count());
        $this->assertTrue($service->isQualityCheckedForPeriod($sme->id, $period['start'], $period['end']));
    }

    public function test_stored_review_keeps_its_method_version_even_if_config_changes_later(): void
    {
        config(['quality_review.method_version' => 'provisional-reliability-v1']);
        $service = app(QualityControlService::class);
        $sme = User::factory()->create();
        $period = $service->currentPeriod();

        $service->markPeriodReviewed($sme->id, $period['start'], $period['end'], 'validated', null);

        // Simule un changement futur de méthode : les revues déjà enregistrées ne
        // doivent jamais être réinterprétées silencieusement.
        config(['quality_review.method_version' => 'quarterly-sampling-v2']);

        $stored = AccountingQualityReview::where('user_id', $sme->id)->first();
        $this->assertSame('provisional-reliability-v1', $stored->method_version);
    }

    public function test_accountant_can_parse_company_document_and_extract_fields(): void
    {
        $accountant = User::factory()->create(['role_key' => 'accountant', 'is_accountant' => true]);
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent(
            'fiche_entreprise_cabinet.txt',
            "Raison Sociale: Cabinet Conseil SARL\nDirigeant: Marc Amon\nEmail: contact@conseil.ci\nTelephone: +2250102030405\nNIF: 9988776C\nVille: Yamoussoukro"
        );

        $response = $this->actingAs($accountant)->postJson('/accountant/parse-company-document', [
            'document' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'filename' => 'fiche_entreprise_cabinet.txt',
            'extracted' => [
                'name' => 'Marc Amon',
                'email' => 'contact@conseil.ci',
                'company_name' => 'Cabinet Conseil SARL',
                'city' => 'Yamoussoukro',
            ],
        ]);
    }

    public function test_accountant_can_view_commercials_tracking_and_timestamps(): void
    {
        $accountant = User::factory()->create(['role_key' => 'accountant', 'is_accountant' => true]);
        $commercial = User::factory()->create([
            'name' => 'Mian Diallo Commercial',
            'email' => 'mdiandiallo@sitiame-capital.com',
            'role_key' => 'commercial',
        ]);
        $client = User::factory()->create([
            'name' => 'Client Parraine',
            'company_name' => 'Entreprise Test Diallo',
            'created_by_user_id' => $commercial->id,
        ]);

        $response = $this->actingAs($accountant)->get('/accountant/commercials-tracking');

        $response->assertStatus(200);
        $response->assertSee('Mian Diallo Commercial');
        $response->assertSee('Entreprise Test Diallo');
        $response->assertSee('Suivi des Portefeuilles Commerciaux');
    }
}
