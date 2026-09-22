<?php

namespace Tests\Feature;

use App\Models\AccountingEntry;
use App\Models\Invoice;
use App\Models\InvoiceErpNextSync;
use App\Models\PlanComptableAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ErpNextWebhooksTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-webhook-token';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.erpnext.webhook_token', self::TOKEN);
        Config::set('services.erpnext.base_url', 'https://erp.test.local');
        Config::set('services.erpnext.api_key', 'k');
        Config::set('services.erpnext.api_secret', 's');
    }

    private function makePme(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'company_name' => 'Test SARL',
            'erpnext_company_name' => 'Test SARL #1',
        ], $overrides));
    }

    private function postWebhook(string $route, array $payload, ?string $token = self::TOKEN)
    {
        $headers = $token !== null ? ['X-PME360-Webhook-Token' => $token] : [];

        return $this->withHeaders($headers)->postJson(route($route), $payload);
    }

    // --- Accounting Entry webhook ---------------------------------------

    public function test_accounting_webhook_rejects_missing_or_wrong_token(): void
    {
        $this->postWebhook('webhooks.erpnext.accounting-entry', [], null)->assertForbidden();
        $this->postWebhook('webhooks.erpnext.accounting-entry', [], 'wrong-token')->assertForbidden();
    }

    public function test_accounting_webhook_ignores_unknown_company(): void
    {
        $response = $this->postWebhook('webhooks.erpnext.accounting-entry', [
            'doctype' => 'Journal Entry',
            'name' => 'JE-0001',
            'company' => 'Société inconnue',
        ]);

        $response->assertOk()->assertJson(['status' => 'ignored', 'reason' => 'PME introuvable']);
    }

    public function test_accounting_webhook_creates_entry_with_real_erpnext_account_name_format(): void
    {
        $pme = $this->makePme();
        PlanComptableAccount::create([
            'user_id' => $pme->id, 'prefix' => '6', 'label' => 'Charges', 'classe' => 6,
            'numero_compte' => '6011000', 'libelle_compte' => 'Achats de marchandises',
        ]);
        PlanComptableAccount::create([
            'user_id' => $pme->id, 'prefix' => '5', 'label' => 'Trésorerie', 'classe' => 5,
            'numero_compte' => '5711', 'libelle_compte' => 'Caisse',
        ]);

        // This mirrors exactly what ERPNext's real API returns: account names with
        // a "NNNN - Label - ABBR" format, the case that exposed the trim() bug.
        Http::fake([
            'https://erp.test.local/api/resource/Journal%20Entry/JE-0001' => Http::response(['data' => [
                'user_remark' => 'Achat fournitures',
                'posting_date' => '2026-09-22',
                'accounts' => [
                    ['account' => '6011 - Achats de marchandises - TES1', 'debit_in_account_currency' => 5000, 'credit_in_account_currency' => 0],
                    ['account' => '5711 - Caisse en monnaie nationale - TES1', 'debit_in_account_currency' => 0, 'credit_in_account_currency' => 5000],
                ],
            ]], 200),
        ]);

        $response = $this->postWebhook('webhooks.erpnext.accounting-entry', [
            'doctype' => 'Journal Entry',
            'name' => 'JE-0001',
            'company' => 'Test SARL #1',
        ]);

        $response->assertOk()->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('accounting_entries', [
            'user_id' => $pme->id,
            'document_reference' => 'JE-0001',
            'debit_account' => '6011000',
            'credit_account' => '5711',
            'amount' => 5000,
        ]);
    }

    public function test_accounting_webhook_skips_entries_created_by_pme360_itself(): void
    {
        $pme = $this->makePme();

        Http::fake([
            'https://erp.test.local/api/resource/Journal%20Entry/JE-0002' => Http::response(['data' => [
                'user_remark' => 'PME360_SYNC something',
                'accounts' => [
                    ['account' => '6011 - Achats - TES1', 'debit_in_account_currency' => 100, 'credit_in_account_currency' => 0],
                    ['account' => '5711 - Caisse - TES1', 'debit_in_account_currency' => 0, 'credit_in_account_currency' => 100],
                ],
            ]], 200),
        ]);

        $this->postWebhook('webhooks.erpnext.accounting-entry', [
            'doctype' => 'Journal Entry',
            'name' => 'JE-0002',
            'company' => 'Test SARL #1',
        ])->assertOk()->assertJson(['status' => 'ignored', 'reason' => 'créée par PME360 elle-même']);

        $this->assertDatabaseCount('accounting_entries', 0);
    }

    public function test_accounting_webhook_is_idempotent_on_replay(): void
    {
        $pme = $this->makePme();
        AccountingEntry::create([
            'user_id' => $pme->id, 'actor_user_id' => $pme->id, 'date' => '2026-09-22',
            'document_type' => 'ecriture_erpnext', 'document_reference' => 'JE-0003',
            'description' => 'Déjà traitée', 'debit_account' => '6011000', 'credit_account' => '5711',
            'amount' => 100,
        ]);

        $response = $this->postWebhook('webhooks.erpnext.accounting-entry', [
            'doctype' => 'Journal Entry',
            'name' => 'JE-0003',
            'company' => 'Test SARL #1',
        ]);

        $response->assertOk()->assertJson(['status' => 'ignored', 'reason' => 'déjà traité']);
        $this->assertDatabaseCount('accounting_entries', 1);
    }

    public function test_accounting_webhook_ignores_unresolvable_accounts_without_crashing(): void
    {
        $pme = $this->makePme();
        // No PlanComptableAccount rows exist locally: nothing will resolve.

        Http::fake([
            'https://erp.test.local/api/resource/Journal%20Entry/JE-0004' => Http::response(['data' => [
                'user_remark' => 'Achat',
                'accounts' => [
                    ['account' => '6011 - Achats - TES1', 'debit_in_account_currency' => 100, 'credit_in_account_currency' => 0],
                    ['account' => '5711 - Caisse - TES1', 'debit_in_account_currency' => 0, 'credit_in_account_currency' => 100],
                ],
            ]], 200),
        ]);

        $this->postWebhook('webhooks.erpnext.accounting-entry', [
            'doctype' => 'Journal Entry',
            'name' => 'JE-0004',
            'company' => 'Test SARL #1',
        ])->assertOk()->assertJson(['status' => 'ignored', 'reason' => 'compte non trouvé dans le plan comptable local']);

        $this->assertDatabaseCount('accounting_entries', 0);
    }

    // --- Invoicing webhook ------------------------------------------------

    public function test_invoicing_webhook_rejects_missing_token(): void
    {
        $this->postWebhook('webhooks.erpnext.invoicing', [], null)->assertForbidden();
    }

    public function test_invoicing_webhook_creates_local_invoice_from_sales_invoice_document(): void
    {
        $pme = $this->makePme();

        Http::fake([
            'https://erp.test.local/api/resource/Sales%20Invoice/ACC-SINV-0001' => Http::response(['data' => [
                'docstatus' => 1,
                'customer' => 'Client ERPNext SARL',
                'customer_name' => 'Client ERPNext SARL',
                'posting_date' => '2026-09-22',
                'due_date' => '2026-10-22',
                'currency' => 'XOF',
                'items' => [
                    ['item_code' => 'conseil', 'item_name' => 'Conseil', 'qty' => 2, 'rate' => 5000],
                ],
                'taxes' => [['rate' => 18]],
            ]], 200),
        ]);

        $response = $this->postWebhook('webhooks.erpnext.invoicing', [
            'doctype' => 'Sales Invoice',
            'name' => 'ACC-SINV-0001',
            'company' => 'Test SARL #1',
        ]);

        $response->assertOk()->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('invoice_erpnext_syncs', [
            'erpnext_invoice_name' => 'ACC-SINV-0001',
            'status' => 'synced',
        ]);

        $invoice = Invoice::where('user_id', $pme->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('Client ERPNext SARL', $invoice->client_name);
        $this->assertEquals(10000, (float) $invoice->subtotal);
    }

    public function test_invoicing_webhook_is_idempotent_on_replay(): void
    {
        $pme = $this->makePme();
        $invoice = Invoice::create([
            'user_id' => $pme->id, 'invoice_number' => 'FAC-0001', 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'client_name' => 'X', 'currency' => 'XOF', 'subtotal' => 100, 'tax_rate' => 0, 'tax_amount' => 0,
            'total_amount' => 100, 'amount_paid' => 0, 'status' => 'unpaid',
        ]);
        InvoiceErpNextSync::create([
            'invoice_id' => $invoice->id, 'status' => 'synced', 'erpnext_invoice_name' => 'ACC-SINV-0002',
        ]);

        Http::fake([
            'https://erp.test.local/api/resource/Sales%20Invoice/ACC-SINV-0002' => Http::response(['data' => [
                'docstatus' => 1, 'customer' => 'X', 'items' => [['item_code' => 'a', 'qty' => 1, 'rate' => 100]],
            ]], 200),
        ]);

        $this->postWebhook('webhooks.erpnext.invoicing', [
            'doctype' => 'Sales Invoice',
            'name' => 'ACC-SINV-0002',
            'company' => 'Test SARL #1',
        ])->assertOk()->assertJson(['status' => 'ok']);

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_invoicing_webhook_cancels_local_invoice_when_erpnext_invoice_is_cancelled(): void
    {
        $pme = $this->makePme();
        $invoice = Invoice::create([
            'user_id' => $pme->id, 'invoice_number' => 'FAC-0002', 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'client_name' => 'X', 'currency' => 'XOF', 'subtotal' => 100, 'tax_rate' => 0, 'tax_amount' => 0,
            'total_amount' => 100, 'amount_paid' => 0, 'status' => 'unpaid',
        ]);
        InvoiceErpNextSync::create([
            'invoice_id' => $invoice->id, 'status' => 'synced', 'erpnext_invoice_name' => 'ACC-SINV-0003',
        ]);

        Http::fake([
            'https://erp.test.local/api/resource/Sales%20Invoice/ACC-SINV-0003' => Http::response(['data' => [
                'docstatus' => 2,
            ]], 200),
        ]);

        $this->postWebhook('webhooks.erpnext.invoicing', [
            'doctype' => 'Sales Invoice',
            'name' => 'ACC-SINV-0003',
            'company' => 'Test SARL #1',
        ])->assertOk()->assertJson(['status' => 'ok']);

        $this->assertSame('cancelled', $invoice->fresh()->status);
    }

    public function test_invoicing_webhook_records_payment_and_resolves_treasury_account_with_real_account_name_format(): void
    {
        $pme = $this->makePme();
        PlanComptableAccount::create([
            'user_id' => $pme->id, 'prefix' => '5', 'label' => 'Trésorerie', 'classe' => 5,
            'numero_compte' => '5711', 'libelle_compte' => 'Caisse',
        ]);
        $invoice = Invoice::create([
            'user_id' => $pme->id, 'invoice_number' => 'FAC-0003', 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'client_name' => 'X', 'currency' => 'XOF', 'subtotal' => 1000, 'tax_rate' => 0, 'tax_amount' => 0,
            'total_amount' => 1000, 'amount_paid' => 0, 'status' => 'unpaid',
        ]);
        InvoiceErpNextSync::create([
            'invoice_id' => $invoice->id, 'status' => 'synced', 'erpnext_invoice_name' => 'ACC-SINV-0004',
        ]);

        Http::fake([
            'https://erp.test.local/api/resource/Payment%20Entry/ACC-PAY-0001' => Http::response(['data' => [
                'payment_type' => 'Receive',
                'paid_to' => '5711 - Caisse en monnaie nationale - TES1',
                'posting_date' => '2026-09-22',
                'mode_of_payment' => 'Cash',
                'references' => [
                    ['reference_doctype' => 'Sales Invoice', 'reference_name' => 'ACC-SINV-0004', 'allocated_amount' => 1000],
                ],
            ]], 200),
        ]);

        $response = $this->postWebhook('webhooks.erpnext.invoicing', [
            'doctype' => 'Payment Entry',
            'name' => 'ACC-PAY-0001',
            'company' => 'Test SARL #1',
        ]);

        $response->assertOk()->assertJson(['status' => 'ok']);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoice->id,
            'amount' => 1000,
            'reference' => 'ACC-PAY-0001',
        ]);
    }
}
