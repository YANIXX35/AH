<?php

namespace App\Services;

use App\Exceptions\ErpNextApiException;
use App\Models\Invoice;
use App\Models\InvoiceErpNextCustomer;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ErpNextClient
{
    public function enabled(): bool
    {
        return trim((string) config('services.erpnext.base_url', '')) !== ''
            && trim((string) config('services.erpnext.api_key', '')) !== ''
            && trim((string) config('services.erpnext.api_secret', '')) !== '';
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.erpnext.base_url', ''), '/');
    }

    private function authHeader(): string
    {
        return 'token '.config('services.erpnext.api_key').':'.config('services.erpnext.api_secret');
    }

    private function timeout(): int
    {
        return max(5, (int) config('services.erpnext.timeout', 15));
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $path): array
    {
        try {
            $response = Http::withHeaders(['Authorization' => $this->authHeader()])
                ->timeout($this->timeout())
                ->get($this->baseUrl().$path);
        } catch (\Throwable $exception) {
            throw new ErpNextApiException('ERPNext injoignable: '.$exception->getMessage());
        }

        if ($response->status() === 404) {
            return [];
        }

        if ($response->failed()) {
            throw new ErpNextApiException($this->extractErrorMessage($response));
        }

        return (array) ($response->json('data') ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        try {
            $response = Http::withHeaders(['Authorization' => $this->authHeader()])
                ->timeout($this->timeout())
                ->post($this->baseUrl().$path, $payload);
        } catch (\Throwable $exception) {
            throw new ErpNextApiException('ERPNext injoignable: '.$exception->getMessage());
        }

        if ($response->failed()) {
            throw new ErpNextApiException($this->extractErrorMessage($response));
        }

        return (array) ($response->json('data') ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function postForm(string $path, array $payload): array
    {
        try {
            $response = Http::withHeaders(['Authorization' => $this->authHeader()])
                ->asForm()
                ->timeout($this->timeout())
                ->post($this->baseUrl().$path, $payload);
        } catch (\Throwable $exception) {
            throw new ErpNextApiException('ERPNext injoignable: '.$exception->getMessage());
        }

        if ($response->failed()) {
            throw new ErpNextApiException($this->extractErrorMessage($response));
        }

        return (array) ($response->json('message') ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function put(string $path, array $payload): array
    {
        try {
            $response = Http::withHeaders(['Authorization' => $this->authHeader()])
                ->timeout($this->timeout())
                ->put($this->baseUrl().$path, $payload);
        } catch (\Throwable $exception) {
            throw new ErpNextApiException('ERPNext injoignable: '.$exception->getMessage());
        }

        if ($response->failed()) {
            throw new ErpNextApiException($this->extractErrorMessage($response));
        }

        return (array) ($response->json('data') ?? []);
    }

    private function extractErrorMessage(Response $response): string
    {
        $message = $response->json('exception') ?? $response->json('message') ?? $response->json('_server_messages');

        if (is_string($message) && $message !== '') {
            return $message;
        }

        return 'ERPNext a répondu avec une erreur HTTP '.$response->status().'.';
    }

    public function findOrCreateCustomer(User $pme): string
    {
        if ($pme->erpnext_customer_id) {
            $existing = $this->get('/api/resource/Customer/'.rawurlencode($pme->erpnext_customer_id));
            if (! empty($existing)) {
                return $pme->erpnext_customer_id;
            }
        }

        $customerName = $pme->company_name ?: $pme->name;

        $created = $this->post('/api/resource/Customer', [
            'customer_name' => $customerName,
            'customer_group' => 'Commercial',
            'territory' => 'Ivory Coast',
        ]);

        $erpNextId = (string) ($created['name'] ?? '');
        if ($erpNextId === '') {
            throw new ErpNextApiException('ERPNext n\'a pas renvoyé de nom de client après création.');
        }

        $pme->erpnext_customer_id = $erpNextId;
        $pme->save();

        return $erpNextId;
    }

    public function findOrCreateCustomerForPme(User $pme, string $clientName, ?string $clientTaxId): string
    {
        $dedupKey = $this->customerDedupKey($clientName, $clientTaxId);

        $mapping = InvoiceErpNextCustomer::where('user_id', $pme->id)
            ->where('dedup_key', $dedupKey)
            ->first();

        if ($mapping) {
            $existing = $this->get('/api/resource/Customer/'.rawurlencode($mapping->erpnext_customer_name));
            if (! empty($existing)) {
                return $mapping->erpnext_customer_name;
            }
        }

        $created = $this->post('/api/resource/Customer', [
            'customer_name' => $clientName,
            'company' => $pme->erpnext_company_name,
            'customer_group' => 'Commercial',
            'territory' => 'Ivory Coast',
        ]);

        $erpNextCustomerName = (string) ($created['name'] ?? '');
        if ($erpNextCustomerName === '') {
            throw new ErpNextApiException('ERPNext n\'a pas renvoyé de nom de client après création.');
        }

        InvoiceErpNextCustomer::updateOrCreate(
            ['user_id' => $pme->id, 'dedup_key' => $dedupKey],
            ['erpnext_customer_name' => $erpNextCustomerName]
        );

        return $erpNextCustomerName;
    }

    private function customerDedupKey(string $clientName, ?string $clientTaxId): string
    {
        $source = $clientTaxId ?: $clientName;

        return Str::of($source)->lower()->squish()->value();
    }

    /**
     * @return array{company: string, warehouse: string, tax_template: string, income_account: string}
     */
    public function provisionCompanyForPme(User $pme): array
    {
        $baseName = $pme->company_name ?: $pme->name;
        $companyName = trim($baseName).' #'.$pme->id;
        $abbr = strtoupper(Str::limit(preg_replace('/[^A-Za-z]/', '', $baseName) ?: 'PME', 3, '')).$pme->id;

        $existingCompany = $this->get('/api/resource/Company/'.rawurlencode($companyName));
        if (! empty($existingCompany)) {
            $resolvedCompanyName = (string) $existingCompany['name'];
        } else {
            $company = $this->post('/api/resource/Company', [
                'company_name' => $companyName,
                'abbr' => $abbr,
                'default_currency' => 'XOF',
                'country' => 'Ivory Coast',
                'chart_of_accounts' => 'Syscohada - Plan Comptable',
            ]);

            $resolvedCompanyName = (string) ($company['name'] ?? '');
            if ($resolvedCompanyName === '') {
                throw new ErpNextApiException('ERPNext n\'a pas renvoyé de nom de société après création.');
            }
        }

        $incomeAccount = $this->findAccountByNumber($resolvedCompanyName, '7061');
        $taxAccount = $this->findAccountByNumber($resolvedCompanyName, '4431');
        $stockAccount = $this->findAccountByNumber($resolvedCompanyName, '3111');

        $expectedWarehouseName = 'Magasin principal - '.$abbr;
        $existingWarehouse = $this->get('/api/resource/Warehouse/'.rawurlencode($expectedWarehouseName));
        if (! empty($existingWarehouse)) {
            $warehouseName = (string) $existingWarehouse['name'];
        } else {
            $warehouse = $this->post('/api/resource/Warehouse', [
                'warehouse_name' => 'Magasin principal',
                'company' => $resolvedCompanyName,
                'account' => $stockAccount,
            ]);
            $warehouseName = (string) ($warehouse['name'] ?? '');
            if ($warehouseName === '') {
                throw new ErpNextApiException('ERPNext n\'a pas renvoyé de nom d\'entrepôt après création.');
            }
        }

        $expectedTaxTemplateName = 'TVA 18% - '.$abbr;
        $existingTaxTemplate = $this->get('/api/resource/'.rawurlencode('Sales Taxes and Charges Template').'/'.rawurlencode($expectedTaxTemplateName));
        if (! empty($existingTaxTemplate)) {
            $taxTemplateName = (string) $existingTaxTemplate['name'];
        } else {
            $taxTemplate = $this->post('/api/resource/'.rawurlencode('Sales Taxes and Charges Template'), [
                'title' => 'TVA 18%',
                'company' => $resolvedCompanyName,
                'taxes' => [
                    [
                        'charge_type' => 'On Net Total',
                        'account_head' => $taxAccount,
                        'description' => 'TVA 18%',
                        'rate' => 18,
                    ],
                ],
            ]);
            $taxTemplateName = (string) ($taxTemplate['name'] ?? '');
            if ($taxTemplateName === '') {
                throw new ErpNextApiException('ERPNext n\'a pas renvoyé de nom de gabarit de TVA après création.');
            }
        }

        $this->put('/api/resource/Company/'.rawurlencode($resolvedCompanyName), [
            'round_off_account' => $incomeAccount,
        ]);

        return [
            'company' => $resolvedCompanyName,
            'warehouse' => $warehouseName,
            'tax_template' => $taxTemplateName,
            'income_account' => $incomeAccount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createAndSubmitSalesInvoiceForPme(User $pme, string $erpNextCustomerName, Invoice $invoice): array
    {
        $items = [];
        foreach ($invoice->items as $line) {
            $itemCode = $this->findOrCreateItem($line->description);
            $items[] = [
                'item_code' => $itemCode,
                'qty' => (float) $line->quantity,
                'rate' => (float) $line->unit_price,
                'warehouse' => $pme->erpnext_warehouse,
                'income_account' => $pme->erpnext_income_account,
            ];
        }

        $payload = [
            'company' => $pme->erpnext_company_name,
            'customer' => $erpNextCustomerName,
            'items' => $items,
            'posting_date' => $invoice->issue_date->format('Y-m-d'),
            'due_date' => $invoice->due_date->format('Y-m-d'),
        ];

        if ((float) $invoice->tax_rate > 0 && ! empty($pme->erpnext_tax_template)) {
            $payload['taxes_and_charges'] = $pme->erpnext_tax_template;

            $template = $this->get('/api/resource/'.rawurlencode('Sales Taxes and Charges Template').'/'.rawurlencode($pme->erpnext_tax_template));
            if (! empty($template['taxes'])) {
                $payload['taxes'] = array_map(fn ($row) => [
                    'charge_type' => $row['charge_type'] ?? 'On Net Total',
                    'account_head' => $row['account_head'],
                    'description' => $row['description'] ?? $row['account_head'],
                    'rate' => $row['rate'] ?? 0,
                ], $template['taxes']);
            }
        }

        $created = $this->post('/api/resource/Sales Invoice', $payload);
        $erpNextInvoiceName = (string) ($created['name'] ?? '');
        if ($erpNextInvoiceName === '') {
            throw new ErpNextApiException('ERPNext n\'a pas renvoyé de nom de facture après création.');
        }

        $submitted = $this->put('/api/resource/Sales Invoice/'.rawurlencode($erpNextInvoiceName), [
            'docstatus' => 1,
        ]);

        return $submitted ?: $created;
    }

    private function findAccountByNumber(string $company, string $accountNumber): string
    {
        $query = http_build_query([
            'filters' => json_encode([
                ['company', '=', $company],
                ['account_name', 'like', $accountNumber.'-%'],
            ]),
            'fields' => json_encode(['name']),
            'limit_page_length' => 1,
        ]);

        $accounts = $this->get('/api/resource/Account?'.$query);
        $account = $accounts[0] ?? null;

        if (empty($account['name'])) {
            throw new ErpNextApiException("Compte $accountNumber introuvable pour la société $company.");
        }

        return (string) $account['name'];
    }

    public function findOrCreateItem(string $description): string
    {
        $itemCode = Str::limit(Str::slug($description), 140, '');
        if ($itemCode === '') {
            $itemCode = 'article-'.Str::random(8);
        }

        $existing = $this->get('/api/resource/Item/'.rawurlencode($itemCode));
        if (! empty($existing)) {
            return $itemCode;
        }

        $created = $this->post('/api/resource/Item', [
            'item_code' => $itemCode,
            'item_name' => $description,
            'item_group' => config('services.erpnext.default_item_group', 'All Item Groups'),
            'stock_uom' => 'Unit',
            'is_stock_item' => 1,
        ]);

        return (string) ($created['item_code'] ?? $itemCode);
    }

    /**
     * @return array<int, string>
     */
    public function listWarehouses(): array
    {
        $query = http_build_query([
            'fields' => json_encode(['name']),
            'filters' => json_encode([['is_group', '=', 0]]),
            'limit_page_length' => 0,
        ]);

        $data = $this->get('/api/resource/Warehouse?'.$query);

        return array_values(array_map(fn ($row) => (string) $row['name'], $data));
    }

    /**
     * @return array<int, string>
     */
    public function listTaxTemplates(): array
    {
        $query = http_build_query([
            'fields' => json_encode(['name']),
            'limit_page_length' => 0,
        ]);

        $data = $this->get('/api/resource/'.rawurlencode('Sales Taxes and Charges Template').'?'.$query);

        return array_values(array_map(fn ($row) => (string) $row['name'], $data));
    }

    /**
     * @param  array<int, array{description: string, quantity: float, unit_price: float}>  $lines
     * @return array<string, mixed>
     */
    public function createSalesInvoice(
        User $pme,
        string $erpNextCustomerName,
        array $lines,
        ?string $warehouse = null,
        ?string $taxTemplate = null,
        ?string $postingDate = null,
        ?string $dueDate = null
    ): array {
        $resolvedWarehouse = $warehouse ?: config('services.erpnext.default_warehouse');
        $incomeAccount = config('services.erpnext.default_income_account');

        $items = [];
        foreach ($lines as $line) {
            $itemCode = $this->findOrCreateItem($line['description']);
            $item = [
                'item_code' => $itemCode,
                'qty' => $line['quantity'],
                'rate' => $line['unit_price'],
                'warehouse' => $resolvedWarehouse,
            ];
            if (! empty($incomeAccount)) {
                $item['income_account'] = $incomeAccount;
            }
            $items[] = $item;
        }

        $payload = [
            'customer' => $erpNextCustomerName,
            'items' => $items,
            'posting_date' => $postingDate ?: now()->toDateString(),
            'due_date' => $dueDate ?: now()->addDays(30)->toDateString(),
        ];

        $resolvedTaxTemplate = $taxTemplate ?: config('services.erpnext.default_tax_template');
        if (! empty($resolvedTaxTemplate)) {
            $payload['taxes_and_charges'] = $resolvedTaxTemplate;

            $template = $this->get('/api/resource/'.rawurlencode('Sales Taxes and Charges Template').'/'.rawurlencode($resolvedTaxTemplate));
            if (! empty($template['taxes'])) {
                $payload['taxes'] = array_map(fn ($row) => [
                    'charge_type' => $row['charge_type'] ?? 'On Net Total',
                    'account_head' => $row['account_head'],
                    'description' => $row['description'] ?? $row['account_head'],
                    'rate' => $row['rate'] ?? 0,
                ], $template['taxes']);
            }
        }

        return $this->post('/api/resource/Sales Invoice', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function recordPaymentForPme(User $pme, string $erpNextInvoiceName, string $erpNextCustomerName, InvoicePayment $payment): array
    {
        $paidToAccount = $this->findAccountByNumber($pme->erpnext_company_name, '5711');

        $created = $this->post('/api/resource/Payment Entry', [
            'payment_type' => 'Receive',
            'company' => $pme->erpnext_company_name,
            'party_type' => 'Customer',
            'party' => $erpNextCustomerName,
            'paid_amount' => (float) $payment->amount,
            'received_amount' => (float) $payment->amount,
            'source_exchange_rate' => 1,
            'target_exchange_rate' => 1,
            'paid_to' => $paidToAccount,
            'paid_to_account_currency' => 'XOF',
            'posting_date' => $payment->paid_at->format('Y-m-d'),
            'references' => [
                [
                    'reference_doctype' => 'Sales Invoice',
                    'reference_name' => $erpNextInvoiceName,
                    'allocated_amount' => (float) $payment->amount,
                ],
            ],
        ]);

        $erpNextPaymentName = (string) ($created['name'] ?? '');
        if ($erpNextPaymentName === '') {
            throw new ErpNextApiException('ERPNext n\'a pas renvoyé de nom d\'écriture de paiement après création.');
        }

        return $this->put('/api/resource/Payment Entry/'.rawurlencode($erpNextPaymentName), [
            'docstatus' => 1,
        ]);
    }

    public function cancelSalesInvoiceForPme(string $erpNextInvoiceName): void
    {
        $this->put('/api/resource/Sales Invoice/'.rawurlencode($erpNextInvoiceName), [
            'docstatus' => 2,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getChartOfAccountsForCompany(string $company): array
    {
        $query = http_build_query([
            'filters' => json_encode([['company', '=', $company]]),
            'fields' => json_encode(['name', 'account_name', 'is_group', 'root_type', 'parent_account']),
            'limit_page_length' => 0,
            'order_by' => 'name asc',
        ]);

        return $this->get('/api/resource/Account?'.$query);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getGeneralLedgerForCompany(string $company, string $fromDate, string $toDate): array
    {
        $query = http_build_query([
            'filters' => json_encode([
                ['company', '=', $company],
                ['posting_date', '>=', $fromDate],
                ['posting_date', '<=', $toDate],
                ['is_cancelled', '=', 0],
            ]),
            'fields' => json_encode(['account', 'posting_date', 'debit', 'credit', 'voucher_type', 'voucher_no', 'remarks']),
            'limit_page_length' => 0,
            'order_by' => 'posting_date asc',
        ]);

        return $this->get('/api/resource/'.rawurlencode('GL Entry').'?'.$query);
    }

    /**
     * @return array<int, array{account: string, debit: float, credit: float, balance: float}>
     */
    public function getTrialBalanceForCompany(string $company, string $fromDate, string $toDate): array
    {
        $fiscalYear = (string) ((int) substr($toDate, 0, 4));

        $result = $this->postForm('/api/method/frappe.desk.query_report.run', [
            'report_name' => 'Trial Balance',
            'filters' => json_encode([
                'company' => $company,
                'filter_based_on' => 'Date Range',
                'period_start_date' => $fromDate,
                'period_end_date' => $toDate,
                'fiscal_year' => $fiscalYear,
            ]),
        ]);

        $rows = [];
        foreach ((array) ($result['result'] ?? []) as $row) {
            if (empty($row['account']) || str_starts_with((string) $row['account'], "'")) {
                continue;
            }

            $debit = (float) ($row['debit'] ?? 0);
            $credit = (float) ($row['credit'] ?? 0);

            $rows[] = [
                'account' => (string) ($row['account_name'] ?? $row['account']),
                'debit' => $debit,
                'credit' => $credit,
                'balance' => round($debit - $credit, 2),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFinancialStatementForCompany(string $company, string $reportName, string $fromDate, string $toDate): array
    {
        $result = $this->postForm('/api/method/frappe.desk.query_report.run', [
            'report_name' => $reportName,
            'filters' => json_encode([
                'company' => $company,
                'filter_based_on' => 'Date Range',
                'period_start_date' => $fromDate,
                'period_end_date' => $toDate,
                'periodicity' => 'Yearly',
            ]),
        ]);

        return (array) ($result['result'] ?? []);
    }

    /**
     * @param  array<int, array{account_number: string, debit: float, credit: float}>  $lines
     * @return array<string, mixed>
     */
    public function createJournalEntryForPme(
        User $pme,
        string $voucherType,
        string $postingDate,
        array $lines,
        ?string $referenceNumber = null,
        ?string $referenceDate = null
    ): array {
        $accounts = [];
        foreach ($lines as $line) {
            $resolvedAccount = $this->findAccountByNumber($pme->erpnext_company_name, $line['account_number']);
            $accounts[] = [
                'account' => $resolvedAccount,
                'debit_in_account_currency' => (float) $line['debit'],
                'credit_in_account_currency' => (float) $line['credit'],
            ];
        }

        $payload = [
            'company' => $pme->erpnext_company_name,
            'voucher_type' => $voucherType,
            'posting_date' => $postingDate,
            'accounts' => $accounts,
        ];

        if (! empty($referenceNumber)) {
            $payload['cheque_no'] = $referenceNumber;
        }
        if (! empty($referenceDate)) {
            $payload['cheque_date'] = $referenceDate;
        }

        $created = $this->post('/api/resource/'.rawurlencode('Journal Entry'), $payload);
        $journalEntryName = (string) ($created['name'] ?? '');
        if ($journalEntryName === '') {
            throw new ErpNextApiException('ERPNext n\'a pas renvoyé de nom d\'écriture après création.');
        }

        return $this->put('/api/resource/'.rawurlencode('Journal Entry').'/'.rawurlencode($journalEntryName), [
            'docstatus' => 1,
        ]);
    }
}
