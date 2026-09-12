<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ErpNextClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ErpNextAccountingTestController extends Controller
{
    public function index(): View
    {
        $pmes = User::whereNotNull('erpnext_company_name')->orderBy('company_name')->get();

        return view('admin.erpnext-accounting-test.index', compact('pmes'));
    }

    public function show(Request $request, ErpNextClient $erpNext): View
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);

        $pme = User::findOrFail($validated['user_id']);
        $company = $pme->erpnext_company_name;
        $fromDate = $validated['from_date'];
        $toDate = $validated['to_date'];

        $chartOfAccounts = null;
        $chartOfAccountsError = null;
        $generalLedger = null;
        $generalLedgerError = null;
        $trialBalance = null;
        $trialBalanceError = null;
        $balanceSheet = null;
        $balanceSheetError = null;
        $profitAndLoss = null;
        $profitAndLossError = null;

        if (empty($company)) {
            $error = 'Cette PME n\'a pas de société ERPNext provisionnée (erpnext_company_name manquant).';

            return view('admin.erpnext-accounting-test.show', compact(
                'pme', 'fromDate', 'toDate',
                'chartOfAccounts', 'generalLedger', 'trialBalance', 'balanceSheet', 'profitAndLoss'
            ) + [
                'chartOfAccountsError' => $error,
                'generalLedgerError' => $error,
                'trialBalanceError' => $error,
                'balanceSheetError' => $error,
                'profitAndLossError' => $error,
            ]);
        }

        try {
            $chartOfAccounts = $erpNext->getChartOfAccountsForCompany($company);
        } catch (\Throwable $e) {
            $chartOfAccountsError = $e->getMessage();
        }

        try {
            $generalLedger = $erpNext->getGeneralLedgerForCompany($company, $fromDate, $toDate);
        } catch (\Throwable $e) {
            $generalLedgerError = $e->getMessage();
        }

        try {
            $trialBalance = $erpNext->getTrialBalanceForCompany($company, $fromDate, $toDate);
        } catch (\Throwable $e) {
            $trialBalanceError = $e->getMessage();
        }

        try {
            $balanceSheet = $erpNext->getFinancialStatementForCompany($company, 'Balance Sheet', $fromDate, $toDate);
        } catch (\Throwable $e) {
            $balanceSheetError = $e->getMessage();
        }

        try {
            $profitAndLoss = $erpNext->getFinancialStatementForCompany($company, 'Profit and Loss Statement', $fromDate, $toDate);
        } catch (\Throwable $e) {
            $profitAndLossError = $e->getMessage();
        }

        return view('admin.erpnext-accounting-test.show', compact(
            'pme', 'fromDate', 'toDate',
            'chartOfAccounts', 'chartOfAccountsError',
            'generalLedger', 'generalLedgerError',
            'trialBalance', 'trialBalanceError',
            'balanceSheet', 'balanceSheetError',
            'profitAndLoss', 'profitAndLossError'
        ));
    }
}
