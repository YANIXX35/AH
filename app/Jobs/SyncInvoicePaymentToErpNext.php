<?php

namespace App\Jobs;

use App\Models\InvoiceErpNextSync;
use App\Models\InvoicePayment;
use App\Services\ErpNextClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncInvoicePaymentToErpNext implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public InvoicePayment $payment)
    {
    }

    public function handle(ErpNextClient $erpNext): void
    {
        if (! $erpNext->enabled()) {
            return;
        }

        $sync = InvoiceErpNextSync::where('invoice_id', $this->payment->invoice_id)
            ->where('status', 'synced')
            ->first();

        if (! $sync || empty($sync->erpnext_invoice_name) || empty($sync->erpnext_customer_name)) {
            Log::warning('Paiement non synchronisé vers ERPNext : facture #'.$this->payment->invoice_id.' jamais synchronisée.');

            return;
        }

        $pme = $this->payment->invoice->user;

        try {
            $erpNext->recordPaymentForPme($pme, $sync->erpnext_invoice_name, $sync->erpnext_customer_name, $this->payment);
        } catch (\Throwable $exception) {
            Log::warning('Échec de synchronisation du paiement #'.$this->payment->id.' vers ERPNext: '.$exception->getMessage());
        }
    }
}
