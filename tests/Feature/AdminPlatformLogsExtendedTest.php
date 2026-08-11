<?php

namespace Tests\Feature;

use App\Models\PaymentTransaction;
use App\Models\PlanComptableAccount;
use App\Models\PlanComptableImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPlatformLogsExtendedTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_password_reset_link_generation_appears_in_the_platform_logs(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $target = User::factory()->create(['name' => 'Client Test Log']);

        $this->actingAs($admin)->post(route('admin.users.password-reset-link', $target));

        $response = $this->actingAs($admin)->get('/admin/platform-logs');

        $response->assertOk();
        $response->assertSee('Lien de réinitialisation généré');
        $response->assertSee('Client Test Log');
    }

    public function test_plan_comptable_defaults_replacement_appears_in_the_logs(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $csv = "Classe;Compte;Intitulé\n1;10;CAPITAL TEST\n";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('plan.csv', $csv);
        $this->actingAs($admin)->post('/admin/plan-comptable/upload', ['plan_file' => $file]);

        $response = $this->actingAs($admin)->get('/admin/platform-logs?module=admin');

        $response->assertOk();
        $response->assertSee('Plan comptable de référence remplacé');
    }

    public function test_template_download_appears_in_the_logs(): void
    {
        $user = User::factory()->create(['is_platform_admin' => true, 'is_premium' => true]);

        $this->actingAs($user)->get(route('accounting.plan-comptable.download-template-syscohada'));

        $response = $this->actingAs($user)->get('/admin/platform-logs?module=admin');

        $response->assertOk();
        $response->assertSee('plan_comptable.template_downloaded');
    }

    public function test_plan_comptable_import_appears_in_the_logs(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        PlanComptableImport::create([
            'user_id' => $admin->id,
            'original_filename' => 'mon-plan.xlsx',
            'status' => 'success',
            'message' => 'ok',
            'valid_rows' => 10,
            'invalid_rows' => 0,
        ]);

        $response = $this->actingAs($admin)->get('/admin/platform-logs?module=accounting');

        $response->assertOk();
        $response->assertSee('mon-plan.xlsx');
    }

    public function test_a_payment_appears_in_the_logs(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $client = User::factory()->create();
        PaymentTransaction::create([
            'user_id' => $client->id,
            'provider' => 'CINETPAY',
            'provider_reference' => 'TX-TEST-123',
            'status' => 'ACCEPTED',
            'amount' => 15000,
            'currency' => 'XOF',
            'country' => 'CI',
            'correspondent' => 'ORANGE_MONEY',
            'payer_msisdn' => '+2250700000000',
        ]);

        $response = $this->actingAs($admin)->get('/admin/platform-logs?module=payments');

        $response->assertOk();
        $response->assertSee('TX-TEST-123');
    }
}
