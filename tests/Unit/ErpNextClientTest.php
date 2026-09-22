<?php

namespace Tests\Unit;

use App\Exceptions\ErpNextApiException;
use App\Models\Invoice;
use App\Models\InvoiceErpNextCustomer;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Services\ErpNextClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ErpNextClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.erpnext.base_url', 'https://erp.test.local');
        Config::set('services.erpnext.api_key', 'test-key');
        Config::set('services.erpnext.api_secret', 'test-secret');
        Config::set('services.erpnext.timeout', 5);
        Config::set('services.erpnext.default_item_group', 'Services');
    }

    private function makePme(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'company_name' => 'Test SARL',
            'erpnext_company_name' => 'Test SARL #1',
            'erpnext_warehouse' => 'Magasin principal - TES1',
            'erpnext_tax_template' => 'TVA 18% - TES1',
            'erpnext_income_account' => '7061 - Dans la Région - TES1',
        ], $overrides));
    }

    public function test_enabled_is_false_when_any_credential_is_missing(): void
    {
        Config::set('services.erpnext.api_key', '');
        $client = new ErpNextClient;

        $this->assertFalse($client->enabled());
    }

    public function test_enabled_is_true_when_all_credentials_present(): void
    {
        $client = new ErpNextClient;

        $this->assertTrue($client->enabled());
    }

    public function test_find_or_create_customer_creates_new_customer_when_none_stored(): void
    {
        Http::fake([
            'https://erp.test.local/api/resource/Customer' => Http::response([
                'data' => ['name' => 'Test SARL'],
            ], 200),
        ]);

        $pme = $this->makePme(['erpnext_customer_id' => null]);
        $client = new ErpNextClient;

        $result = $client->findOrCreateCustomer($pme);

        $this->assertSame('Test SARL', $result);
        $this->assertSame('Test SARL', $pme->fresh()->erpnext_customer_id);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/api/resource/Customer')
                && $request['customer_name'] === 'Test SARL';
        });
    }

    public function test_find_or_create_customer_reuses_existing_id_when_still_valid(): void
    {
        Http::fake([
            'https://erp.test.local/api/resource/Customer/Existing*' => Http::response([
                'data' => ['name' => 'Existing'],
            ], 200),
        ]);

        $pme = $this->makePme(['erpnext_customer_id' => 'Existing']);
        $client = new ErpNextClient;

        $result = $client->findOrCreateCustomer($pme);

        $this->assertSame('Existing', $result);
        Http::assertSentCount(1);
    }

    public function test_find_or_create_customer_throws_when_erpnext_returns_no_name(): void
    {
        Http::fake([
            'https://erp.test.local/api/resource/Customer' => Http::response(['data' => []], 200),
        ]);

        $pme = $this->makePme(['erpnext_customer_id' => null]);
        $client = new ErpNextClient;

        $this->expectException(ErpNextApiException::class);
        $client->findOrCreateCustomer($pme);
    }

    public function test_get_throws_erpnext_exception_on_http_failure(): void
    {
        Http::fake([
            'https://erp.test.local/api/resource/Customer/*' => Http::response([
                'exception' => 'frappe.exceptions.PermissionError: Not permitted',
            ], 403),
        ]);

        $pme = $this->makePme(['erpnext_customer_id' => 'Blocked']);
        $client = new ErpNextClient;

        $this->expectException(ErpNextApiException::class);
        $this->expectExceptionMessage('PermissionError');
        $client->findOrCreateCustomer($pme);
    }

    public function test_get_returns_empty_array_on_404_instead_of_throwing(): void
    {
        // 404 on the customer lookup must fall back to creating a new customer,
        // not bubble up as an ErpNextApiException.
        Http::fake([
            'https://erp.test.local/api/resource/Customer/Missing*' => Http::response([], 404),
            'https://erp.test.local/api/resource/Customer' => Http::response(['data' => ['name' => 'Test SARL']], 200),
        ]);

        $pme = $this->makePme(['erpnext_customer_id' => 'Missing']);
        $client = new ErpNextClient;

        $result = $client->findOrCreateCustomer($pme);
        $this->assertSame('Test SARL', $result);
    }

    public function test_get_throws_when_connection_fails(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $pme = $this->makePme(['erpnext_customer_id' => 'Any']);
        $client = new ErpNextClient;

        $this->expectException(ErpNextApiException::class);
        $this->expectExceptionMessage('injoignable');
        $client->findOrCreateCustomer($pme);
    }

    public function test_provision_company_for_pme_resolves_accounts_by_number_not_name(): void
    {
        Http::fake([
            'https://erp.test.local/api/resource/Company/*' => function ($request) {
                // GET is the "does it already exist" lookup (none does, in this test);
                // PUT is the final round_off_account/stock_adjustment_account update.
                return $request->method() === 'PUT'
                    ? Http::response(['data' => ['name' => 'Test SARL #1']], 200)
                    : Http::response([], 404);
            },
            'https://erp.test.local/api/resource/Company' => Http::response(['data' => ['name' => 'Test SARL #1']], 200),
            'https://erp.test.local/api/resource/Account?*' => function ($request) {
                $decodedUrl = urldecode($request->url());
                $map = [
                    '7061' => '7061 - Dans la Région - TES1',
                    '4431' => '4431 - TVA facturée - TES1',
                    '3111' => '3111 - Stock - TES1',
                    '6031' => '6031 - Variation de stock - TES1',
                ];
                foreach ($map as $number => $accountName) {
                    if (str_contains($decodedUrl, '"'.$number.'"')) {
                        return Http::response(['data' => [['name' => $accountName]]], 200);
                    }
                }

                return Http::response(['data' => []], 200);
            },
            'https://erp.test.local/api/resource/Warehouse/*' => Http::response([], 404),
            'https://erp.test.local/api/resource/Warehouse' => Http::response(['data' => ['name' => 'Magasin principal - TES1']], 200),
            'https://erp.test.local/api/resource/Sales%20Taxes%20and%20Charges%20Template/*' => Http::response([], 404),
            'https://erp.test.local/api/resource/Sales%20Taxes%20and%20Charges%20Template' => Http::response(['data' => ['name' => 'TVA 18% - TES1']], 200),
        ]);

        $pme = $this->makePme(['erpnext_company_name' => null]);
        $client = new ErpNextClient;

        $result = $client->provisionCompanyForPme($pme);

        $this->assertSame('Test SARL #1', $result['company']);
        $this->assertSame('Magasin principal - TES1', $result['warehouse']);
        $this->assertSame('TVA 18% - TES1', $result['tax_template']);

        // The critical regression check for the F-13 fix: account lookups must
        // filter by the account_number field, never by an account_name LIKE pattern.
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/api/resource/Account?')) {
                return false;
            }
            $filters = urldecode((string) parse_url($request->url(), PHP_URL_QUERY));

            return str_contains($filters, 'account_number') && ! str_contains($filters, 'account_name');
        });
    }

    public function test_find_account_by_number_throws_when_account_missing(): void
    {
        Http::fake([
            'https://erp.test.local/api/resource/Account?*' => Http::response(['data' => []], 200),
        ]);

        $pme = $this->makePme();
        $client = new ErpNextClient;

        $this->expectException(ErpNextApiException::class);
        $this->expectExceptionMessage('introuvable');

        // createJournalEntryForPme calls findAccountByNumber internally.
        $client->createJournalEntryForPme($pme, 'Journal Entry', now()->toDateString(), [
            ['account_number' => '9999', 'debit' => 100, 'credit' => 0],
        ]);
    }

    public function test_create_and_submit_sales_invoice_sends_items_and_submits_docstatus(): void
    {
        Http::fake([
            'https://erp.test.local/api/resource/Item/*' => Http::response([], 404),
            'https://erp.test.local/api/resource/Item' => Http::response(['data' => ['item_code' => 'conseil-comptable']], 200),
            'https://erp.test.local/api/resource/Sales%20Invoice' => Http::response(['data' => ['name' => 'ACC-SINV-2026-00001']], 200),
            'https://erp.test.local/api/resource/Sales%20Invoice/*' => Http::response(['data' => ['name' => 'ACC-SINV-2026-00001', 'docstatus' => 1]], 200),
        ]);

        $pme = $this->makePme();
        $invoice = Invoice::create([
            'user_id' => $pme->id,
            'invoice_number' => 'FAC-TEST-001',
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'client_name' => 'Client Test',
            'currency' => 'XOF',
            'subtotal' => 10000,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total_amount' => 10000,
            'amount_paid' => 0,
            'status' => 'unpaid',
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Conseil comptable',
            'quantity' => 1,
            'unit_price' => 10000,
            'line_total' => 10000,
            'position' => 1,
        ]);

        $client = new ErpNextClient;
        $result = $client->createAndSubmitSalesInvoiceForPme($pme, 'Test SARL', $invoice->fresh(['items']));

        $this->assertSame(1, $result['docstatus']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/resource/Sales%20Invoice')
                && $request->method() === 'POST'
                && ($request['items'][0]['item_code'] ?? null) === 'conseil-comptable';
        });
    }

    public function test_record_payment_uses_cash_account_5711_and_submits_it(): void
    {
        Http::fake([
            'https://erp.test.local/api/resource/Account?*' => Http::response(['data' => [['name' => '5711 - Caisse en monnaie nationale - TES1']]], 200),
            'https://erp.test.local/api/resource/Payment%20Entry' => Http::response(['data' => ['name' => 'PAY-0001']], 200),
            'https://erp.test.local/api/resource/Payment%20Entry/*' => Http::response(['data' => ['name' => 'PAY-0001', 'docstatus' => 1]], 200),
        ]);

        $pme = $this->makePme();
        $invoice = Invoice::create([
            'user_id' => $pme->id,
            'invoice_number' => 'FAC-TEST-002',
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'client_name' => 'Client Test',
            'currency' => 'XOF',
            'subtotal' => 5000,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total_amount' => 5000,
            'amount_paid' => 0,
            'status' => 'unpaid',
        ]);
        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'amount' => 5000,
            'paid_at' => now(),
            'method' => 'cash',
        ]);

        $client = new ErpNextClient;
        $result = $client->recordPaymentForPme($pme, 'ACC-SINV-2026-00001', 'Test SARL', $payment);

        $this->assertSame(1, $result['docstatus']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/resource/Payment%20Entry')
                && $request->method() === 'POST'
                && $request['paid_to'] === '5711 - Caisse en monnaie nationale - TES1';
        });
    }

    public function test_find_or_create_customer_for_pme_reuses_mapping_by_dedup_key(): void
    {
        $pme = $this->makePme();
        InvoiceErpNextCustomer::create([
            'user_id' => $pme->id,
            'dedup_key' => 'client existant',
            'erpnext_customer_name' => 'Client Existant',
        ]);

        Http::fake([
            'https://erp.test.local/api/resource/Customer/Client%20Existant*' => Http::response(['data' => ['name' => 'Client Existant']], 200),
        ]);

        $client = new ErpNextClient;
        $result = $client->findOrCreateCustomerForPme($pme, 'Client Existant', null);

        $this->assertSame('Client Existant', $result);
        Http::assertSentCount(1);
    }

    public function test_cancel_sales_invoice_sends_docstatus_2(): void
    {
        Http::fake([
            'https://erp.test.local/api/resource/Sales%20Invoice/*' => Http::response(['data' => []], 200),
        ]);

        $client = new ErpNextClient;
        $client->cancelSalesInvoiceForPme('ACC-SINV-2026-00001');

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT' && ($request['docstatus'] ?? null) === 2;
        });
    }

    public function test_resolve_local_account_code_matches_exact_and_padded_codes(): void
    {
        $pme = $this->makePme();
        \App\Models\PlanComptableAccount::create([
            'user_id' => $pme->id,
            'prefix' => '4',
            'label' => 'Comptes de tiers',
            'numero_compte' => '4111',
            'libelle_compte' => 'Clients',
            'classe' => 4,
        ]);
        \App\Models\PlanComptableAccount::create([
            'user_id' => $pme->id,
            'prefix' => '6',
            'label' => 'Comptes de charges',
            'numero_compte' => '6011000',
            'libelle_compte' => 'Achats de marchandises',
            'classe' => 6,
        ]);

        $client = new ErpNextClient;

        $this->assertSame('4111', $client->resolveLocalAccountCode($pme, '4111 - Clients - TES1'));
        $this->assertSame('6011000', $client->resolveLocalAccountCode($pme, '6011 - Achats - TES1'));
        $this->assertNull($client->resolveLocalAccountCode($pme, '9999 - Inconnu - TES1'));
    }
}
