<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\InvoiceErpNextSync;
use App\Services\ErpNextClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncInvoiceToErpNext implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Invoice $invoice)
    {
    }

    public function handle(ErpNextClient $erpNext): void
    {
        $sync = InvoiceErpNextSync::firstOrCreate(
            ['invoice_id' => $this->invoice->id],
            ['status' => 'pending']
        );

        if ($sync->status === 'synced') {
            return;
        }

        if (! $erpNext->enabled()) {
            $sync->update(['status' => 'failed', 'last_error' => 'ERPNext non configuré.']);

            return;
        }

        $pme = $this->invoice->user;

        if (empty($pme->erpnext_company_name)) {
            $sync->update([
                'status' => 'failed',
                'last_error' => 'PME non provisionnée sur ERPNext (erpnext_company_name manquant).',
            ]);

            return;
        }

        try {
            $erpNextCustomerName = $erpNext->findOrCreateCustomerForPme(
                $pme,
                $this->invoice->client_name,
                $this->invoice->client_tax_id
            );
            $response = $erpNext->createAndSubmitSalesInvoiceForPme($pme, $erpNextCustomerName, $this->invoice);

            $sync->update([
                'status' => 'synced',
                'erpnext_invoice_name' => $response['name'] ?? null,
                'erpnext_customer_name' => $erpNextCustomerName,
                'last_error' => null,
                'last_synced_at' => now(),
                'raw_response' => $response,
            ]);
        } catch (\Throwable $exception) {
            $sync->update([
                'status' => 'failed',
                'last_error' => $exception->getMessage(),
            ]);
        }
    }
}
