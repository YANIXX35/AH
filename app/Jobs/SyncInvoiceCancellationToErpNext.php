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
use Illuminate\Support\Facades\Log;

class SyncInvoiceCancellationToErpNext implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Invoice $invoice)
    {
    }

    public function handle(ErpNextClient $erpNext): void
    {
        if (! $erpNext->enabled()) {
            return;
        }

        $sync = InvoiceErpNextSync::where('invoice_id', $this->invoice->id)
            ->where('status', 'synced')
            ->first();

        if (! $sync || empty($sync->erpnext_invoice_name)) {
            Log::warning('Annulation non synchronisée vers ERPNext : facture #'.$this->invoice->id.' jamais synchronisée.');

            return;
        }

        try {
            $erpNext->cancelSalesInvoiceForPme($sync->erpnext_invoice_name);
        } catch (\Throwable $exception) {
            Log::warning('Échec de synchronisation de l\'annulation de la facture #'.$this->invoice->id.' vers ERPNext: '.$exception->getMessage());
        }
    }
}
