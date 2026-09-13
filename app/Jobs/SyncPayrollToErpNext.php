<?php

namespace App\Jobs;

use App\Models\PayrollErpNextSync;
use App\Models\PayrollRun;
use App\Services\ErpNextClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncPayrollToErpNext implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public PayrollRun $payroll)
    {
    }

    public function handle(ErpNextClient $erpNext): void
    {
        $sync = PayrollErpNextSync::firstOrCreate(
            ['payroll_run_id' => $this->payroll->id],
            ['status' => 'pending']
        );

        if ($sync->status === 'synced') {
            return;
        }

        if (! $erpNext->enabled()) {
            $sync->update(['status' => 'failed', 'last_error' => 'ERPNext non configuré.']);

            return;
        }

        $pme = $this->payroll->user;

        if (empty($pme) || empty($pme->erpnext_company_name)) {
            $sync->update([
                'status' => 'failed',
                'last_error' => 'PME non provisionnée sur ERPNext (erpnext_company_name manquant).',
            ]);

            return;
        }

        $postingDate = $this->payroll->payment_date->format('Y-m-d');
        $grossAmount = (float) $this->payroll->total_gross > 0
            ? (float) $this->payroll->total_gross
            : (float) $this->payroll->total_net;

        try {
            $accrualResponse = $erpNext->createJournalEntryForPme(
                $pme,
                'Journal Entry',
                $postingDate,
                [
                    ['account_number' => '6611', 'debit' => $grossAmount, 'credit' => 0],
                    ['account_number' => '422', 'debit' => 0, 'credit' => $grossAmount],
                ]
            );

            $paymentResponse = $erpNext->createJournalEntryForPme(
                $pme,
                'Journal Entry',
                $postingDate,
                [
                    ['account_number' => '422', 'debit' => (float) $this->payroll->total_net, 'credit' => 0],
                    ['account_number' => '5711', 'debit' => 0, 'credit' => (float) $this->payroll->total_net],
                ]
            );

            $sync->update([
                'status' => 'synced',
                'erpnext_accrual_entry_name' => $accrualResponse['name'] ?? null,
                'erpnext_payment_entry_name' => $paymentResponse['name'] ?? null,
                'last_error' => null,
                'last_synced_at' => now(),
                'raw_response' => [
                    'accrual' => $accrualResponse,
                    'payment' => $paymentResponse,
                ],
            ]);
        } catch (\Throwable $exception) {
            $sync->update([
                'status' => 'failed',
                'last_error' => $exception->getMessage(),
            ]);
        }
    }
}
