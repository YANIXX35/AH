<?php

namespace App\Jobs;

use App\Models\StockMovement;
use App\Models\StockMovementErpNextSync;
use App\Services\ErpNextClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncStockMovementToErpNext implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public StockMovement $movement)
    {
    }

    public function handle(ErpNextClient $erpNext): void
    {
        $sync = StockMovementErpNextSync::firstOrCreate(
            ['stock_movement_id' => $this->movement->id],
            ['status' => 'pending']
        );

        if ($sync->status === 'synced') {
            return;
        }

        if (! $erpNext->enabled()) {
            $sync->update(['status' => 'failed', 'last_error' => 'ERPNext non configuré.']);

            return;
        }

        $pme = $this->movement->user;

        if (empty($pme) || empty($pme->erpnext_company_name) || empty($pme->erpnext_warehouse)) {
            $sync->update([
                'status' => 'failed',
                'last_error' => 'PME non provisionnée sur ERPNext (erpnext_company_name/erpnext_warehouse manquant).',
            ]);

            return;
        }

        $product = $this->movement->product;

        try {
            if ($this->movement->type === 'ajustement') {
                $response = $erpNext->adjustStockForPme(
                    $pme,
                    $product->name,
                    (float) $this->movement->quantity_after
                );
                $documentType = 'Stock Reconciliation';
            } else {
                $direction = $this->movement->type === 'entree' ? 'in' : 'out';
                $unitRate = (float) ($this->movement->unit_cost ?? $this->movement->average_cost_after ?? 0);

                $response = $erpNext->createStockMovementForPme(
                    $pme,
                    $product->name,
                    abs((float) $this->movement->quantity),
                    $unitRate,
                    $direction
                );
                $documentType = 'Stock Entry';
            }

            $sync->update([
                'status' => 'synced',
                'erpnext_document_type' => $documentType,
                'erpnext_document_name' => $response['name'] ?? null,
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
