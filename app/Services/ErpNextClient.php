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

        $items = [];
        foreach ($lines as $line) {
            $itemCode = $this->findOrCreateItem($line['description']);
            $items[] = [
                'item_code' => $itemCode,
                'qty' => $line['quantity'],
                'rate' => $line['unit_price'],
                'warehouse' => $resolvedWarehouse,
            ];
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
        }

        return $this->post('/api/resource/Sales Invoice', $payload);
    }
}
