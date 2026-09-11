<?php

namespace App\Services;

use App\Exceptions\ErpNextApiException;
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

    /**
     * @return array{company: string, warehouse: string, tax_template: string, income_account: string}
     */
    public function provisionCompanyForPme(User $pme): array
    {
        $baseName = $pme->company_name ?: $pme->name;
        $companyName = trim($baseName).' #'.$pme->id;
        $abbr = strtoupper(Str::limit(preg_replace('/[^A-Za-z]/', '', $baseName) ?: 'PME', 3, '')).$pme->id;

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

        $incomeAccount = $this->findAccountByNumber($resolvedCompanyName, '7061');
        $taxAccount = $this->findAccountByNumber($resolvedCompanyName, '4431');
        $stockAccount = $this->findAccountByNumber($resolvedCompanyName, '3111');

        $warehouse = $this->post('/api/resource/Warehouse', [
            'warehouse_name' => 'Magasin principal',
            'company' => $resolvedCompanyName,
            'account' => $stockAccount,
        ]);
        $warehouseName = (string) ($warehouse['name'] ?? '');
        if ($warehouseName === '') {
            throw new ErpNextApiException('ERPNext n\'a pas renvoyé de nom d\'entrepôt après création.');
        }

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

        return [
            'company' => $resolvedCompanyName,
            'warehouse' => $warehouseName,
            'tax_template' => $taxTemplateName,
            'income_account' => $incomeAccount,
        ];
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
}
