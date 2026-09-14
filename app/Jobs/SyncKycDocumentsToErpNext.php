<?php

namespace App\Jobs;

use App\Models\KycDocumentErpNextSync;
use App\Models\User;
use App\Services\ErpNextClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncKycDocumentsToErpNext implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public User $pme)
    {
    }

    public function handle(ErpNextClient $erpNext): void
    {
        if (! $erpNext->enabled()) {
            return;
        }

        if (empty($this->pme->erpnext_company_name)) {
            return;
        }

        $documents = $this->pme->kycDocuments()->where('status', 'approved')->get();

        foreach ($documents as $document) {
            $sync = KycDocumentErpNextSync::firstOrCreate(
                ['kyc_document_id' => $document->id],
                ['status' => 'pending']
            );

            if ($sync->status === 'synced') {
                continue;
            }

            try {
                $response = $erpNext->uploadFileForPme(
                    $this->pme,
                    'public',
                    $document->stored_path,
                    $document->original_name
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
}
