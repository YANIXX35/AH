<?php

namespace App\Jobs;

use App\Models\AccountingDocument;
use App\Models\AccountingDocumentErpNextSync;
use App\Services\ErpNextClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncAccountingDocumentToErpNext implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public AccountingDocument $document)
    {
    }

    public function handle(ErpNextClient $erpNext): void
    {
        $sync = AccountingDocumentErpNextSync::firstOrCreate(
            ['accounting_document_id' => $this->document->id],
            ['status' => 'pending']
        );

        if ($sync->status === 'synced') {
            return;
        }

        if (! $erpNext->enabled()) {
            $sync->update(['status' => 'failed', 'last_error' => 'ERPNext non configuré.']);

            return;
        }

        $pme = $this->document->user;

        if (empty($pme) || empty($pme->erpnext_company_name)) {
            $sync->update([
                'status' => 'failed',
                'last_error' => 'PME non provisionnée sur ERPNext (erpnext_company_name manquant).',
            ]);

            return;
        }

        try {
            $response = $erpNext->uploadFileForPme(
                $pme,
                'public',
                $this->document->stored_path,
                $this->document->original_name
            );

            $sync->update([
                'status' => 'synced',
                'erpnext_file_name' => $response['name'] ?? null,
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
