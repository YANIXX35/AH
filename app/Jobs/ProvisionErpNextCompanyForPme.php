<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\ErpNextClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProvisionErpNextCompanyForPme implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public User $pme)
    {
    }

    public function handle(ErpNextClient $erpNext): void
    {
        if ($this->pme->erpnext_company_name) {
            return;
        }

        if (! $erpNext->enabled()) {
            Log::info('ERPNext non configuré : provisionnement ignoré pour la PME #'.$this->pme->id);

            return;
        }

        $result = $erpNext->provisionCompanyForPme($this->pme);

        $this->pme->update([
            'erpnext_company_name' => $result['company'],
            'erpnext_warehouse' => $result['warehouse'],
            'erpnext_tax_template' => $result['tax_template'],
            'erpnext_income_account' => $result['income_account'],
        ]);
    }
}
