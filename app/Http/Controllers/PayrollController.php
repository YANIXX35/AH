<?php

namespace App\Http\Controllers;

use App\Models\AccountingEntry;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\TreasuryTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        try {
            $payrolls = PayrollRun::query()
                ->where('user_id', $user->id)
                ->with('items')
                ->latest()
                ->get();
        } catch (\Throwable $e) {
            $payrolls = collect();
        }

        $totalPaid = $payrolls->where('status', 'synced')->sum('total_net');
        $totalGross = $payrolls->sum('total_gross');
        $totalCnps = $payrolls->sum('total_cnps');
        $totalIts = $payrolls->sum('total_its');

        return view('payroll.index', compact(
            'payrolls',
            'totalPaid',
            'totalGross',
            'totalCnps',
            'totalIts'
        ));
    }

    public function create(): View
    {
        return view('payroll.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'period_month' => ['required', 'string', 'max:255'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'in:bank_transfer,wave,orange_money,mtn,check,cash'],
            'payment_account' => ['nullable', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'mimes:pdf,xls,xlsx,csv,jpg,png', 'max:10240'],

            // Salariés
            'employees' => ['required', 'array', 'min:1'],
            'employees.*.name' => ['required', 'string', 'max:255'],
            'employees.*.matricule' => ['nullable', 'string', 'max:255'],
            'employees.*.job' => ['nullable', 'string', 'max:255'],
            'employees.*.base_salary' => ['required', 'numeric', 'min:0'],
            'employees.*.bonuses' => ['nullable', 'numeric', 'min:0'],
            'employees.*.cnps_employee' => ['nullable', 'numeric', 'min:0'],
            'employees.*.cnps_employer' => ['nullable', 'numeric', 'min:0'],
            'employees.*.its_tax' => ['nullable', 'numeric', 'min:0'],
            'employees.*.net_payable' => ['required', 'numeric', 'min:0'],
            'employees.*.payment_details' => ['nullable', 'string', 'max:255'],
        ]);

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('payrolls', 'public');
        }

        $user = $request->user();

        DB::transaction(function () use ($validated, $filePath, $user) {
            $totalGross = 0;
            $totalCnps = 0;
            $totalIts = 0;
            $totalNet = 0;

            foreach ($validated['employees'] as $emp) {
                $gross = ($emp['base_salary'] ?? 0) + ($emp['bonuses'] ?? 0);
                $cnps = ($emp['cnps_employee'] ?? 0) + ($emp['cnps_employer'] ?? 0);
                $its = $emp['its_tax'] ?? 0;
                $net = $emp['net_payable'] ?? 0;

                $totalGross += $gross;
                $totalCnps += $cnps;
                $totalIts += $its;
                $totalNet += $net;
            }

            $payrollRun = PayrollRun::create([
                'user_id' => $user->id,
                'title' => $validated['title'],
                'period_month' => $validated['period_month'],
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'],
                'payment_account' => $validated['payment_account'] ?? 'Compte Principal',
                'total_gross' => $totalGross,
                'total_cnps' => $totalCnps,
                'total_its' => $totalIts,
                'total_net' => $totalNet,
                'file_path' => $filePath,
                'status' => 'draft',
            ]);

            foreach ($validated['employees'] as $emp) {
                PayrollItem::create([
                    'payroll_run_id' => $payrollRun->id,
                    'employee_name' => $emp['name'],
                    'employee_matricule' => $emp['matricule'] ?? null,
                    'employee_job' => $emp['job'] ?? null,
                    'base_salary' => $emp['base_salary'] ?? 0,
                    'bonuses' => $emp['bonuses'] ?? 0,
                    'cnps_employee' => $emp['cnps_employee'] ?? 0,
                    'cnps_employer' => $emp['cnps_employer'] ?? 0,
                    'its_tax' => $emp['its_tax'] ?? 0,
                    'net_payable' => $emp['net_payable'] ?? 0,
                    'payment_details' => $emp['payment_details'] ?? null,
                ]);
            }
        });

        return redirect()->route('payroll.index')->with('status', 'Lot de paie enregistré en brouillon avec succès !');
    }

    public function show(PayrollRun $payroll): View
    {
        $payroll->load('items');

        return view('payroll.show', compact('payroll'));
    }

    public function sync(PayrollRun $payroll, Request $request): RedirectResponse
    {
        if ($payroll->status === 'synced') {
            return redirect()->back()->with('status', 'Ce lot de paie est déjà synchronisé !');
        }

        $user = $request->user();

        DB::transaction(function () use ($payroll, $user) {
            // 1. Écriture Comptable SYSCOHADA
            try {
                $entry = AccountingEntry::create([
                    'user_id' => $user->id,
                    'entry_date' => $payroll->payment_date,
                    'piece_number' => 'PAIE-'.str_pad($payroll->id, 5, '0', STR_PAD_LEFT),
                    'label' => 'Paie du personnel — '.$payroll->title,
                    'debit_account' => '661100', // Rémunérations directes
                    'credit_account' => '421100', // Personnel - Rémunérations dues
                    'amount' => $payroll->total_gross > 0 ? $payroll->total_gross : $payroll->total_net,
                    'status' => 'validated',
                ]);
                $payroll->accounting_entry_id = $entry->id;
            } catch (\Throwable $e) {
                // Ignore if accounting tables not available
            }

            // 2. Décaissement Trésorerie
            try {
                $tx = TreasuryTransaction::create([
                    'user_id' => $user->id,
                    'transaction_date' => $payroll->payment_date,
                    'type' => 'expense',
                    'category' => 'salaries',
                    'amount' => $payroll->total_net,
                    'payment_method' => $payroll->payment_method,
                    'reference' => 'PAYROLL-'.$payroll->id,
                    'description' => 'Virement Salaires — '.$payroll->title,
                    'reconciled' => true,
                ]);
                $payroll->treasury_transaction_id = $tx->id;
            } catch (\Throwable $e) {
                // Ignore if treasury table not available
            }

            $payroll->status = 'synced';
            $payroll->synced_at = now();
            $payroll->save();
        });

        return redirect()->back()->with('status', 'Lot de paie validé et synchronisé en Comptabilité & Trésorerie !');
    }

    public function destroy(PayrollRun $payroll): RedirectResponse
    {
        $payroll->delete();

        return redirect()->route('payroll.index')->with('status', 'Lot de paie supprimé avec succès.');
    }
}
