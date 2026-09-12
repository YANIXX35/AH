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
            $bankTransactions = null;

            return view('admin.erpnext-accounting-test.show', compact(
                'pme', 'fromDate', 'toDate',
                'chartOfAccounts', 'generalLedger', 'trialBalance', 'balanceSheet', 'profitAndLoss', 'bankTransactions'
            ) + [
                'chartOfAccountsError' => $error,
                'generalLedgerError' => $error,
                'trialBalanceError' => $error,
                'balanceSheetError' => $error,
                'profitAndLossError' => $error,
                'bankTransactionsError' => $error,
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

        $bankTransactions = null;
        $bankTransactionsError = null;

        try {
            $bankTransactions = $erpNext->getBankTransactionsForCompany($company);
        } catch (\Throwable $e) {
            $bankTransactionsError = $e->getMessage();
        }

        return view('admin.erpnext-accounting-test.show', compact(
            'pme', 'fromDate', 'toDate',
            'chartOfAccounts', 'chartOfAccountsError',
            'generalLedger', 'generalLedgerError',
            'trialBalance', 'trialBalanceError',
            'balanceSheet', 'balanceSheetError',
            'profitAndLoss', 'profitAndLossError',
            'bankTransactions', 'bankTransactionsError'
        ));
    }

    public function createEntry(Request $request, ErpNextClient $erpNext): View
    {
        $pmes = User::whereNotNull('erpnext_company_name')->orderBy('company_name')->get();

        $accounts = [];
        $selectedUserId = $request->query('user_id');
        if ($selectedUserId) {
            $pme = User::find($selectedUserId);
            if ($pme && $pme->erpnext_company_name) {
                try {
                    $accounts = $erpNext->getChartOfAccountsForCompany($pme->erpnext_company_name);
                } catch (\Throwable) {
                    $accounts = [];
                }
            }
        }

        return view('admin.erpnext-accounting-test.create-entry', [
            'pmes' => $pmes,
            'accounts' => $accounts,
            'selectedUserId' => $selectedUserId,
        ]);
    }

    public function storeEntry(Request $request, ErpNextClient $erpNext): View
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'voucher_type' => ['required', 'string', 'max:255'],
            'posting_date' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'reference_date' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_number' => ['required', 'string', 'max:255'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
        ]);

        $pme = User::findOrFail($validated['user_id']);

        $result = null;
        $error = null;

        try {
            $result = $erpNext->createJournalEntryForPme(
                $pme,
                $validated['voucher_type'],
                $validated['posting_date'],
                $validated['lines'],
                $validated['reference_number'] ?? null,
                $validated['reference_date'] ?? null
            );
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('admin.erpnext-accounting-test.create-entry', [
            'pmes' => User::whereNotNull('erpnext_company_name')->orderBy('company_name')->get(),
            'accounts' => [],
            'selectedUserId' => $validated['user_id'],
            'result' => $result,
            'error' => $error,
        ]);
    }

    public function createBankTransaction(Request $request, ErpNextClient $erpNext): View
    {
        $pmes = User::whereNotNull('erpnext_company_name')->orderBy('company_name')->get();

        $accounts = [];
        $selectedUserId = $request->query('user_id');
        if ($selectedUserId) {
            $pme = User::find($selectedUserId);
            if ($pme && $pme->erpnext_company_name) {
                try {
                    $accounts = $erpNext->getChartOfAccountsForCompany($pme->erpnext_company_name);
                } catch (\Throwable) {
                    $accounts = [];
                }
            }
        }

        return view('admin.erpnext-accounting-test.create-bank-transaction', [
            'pmes' => $pmes,
            'accounts' => $accounts,
            'selectedUserId' => $selectedUserId,
        ]);
    }

    public function storeBankTransaction(Request $request, ErpNextClient $erpNext): View
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'account_number' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'direction' => ['required', 'in:deposit,withdrawal'],
            'description' => ['required', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
        ]);

        $pme = User::findOrFail($validated['user_id']);

        $result = null;
        $error = null;

        try {
            $result = $erpNext->createBankTransactionForPme(
                $pme,
                $validated['account_number'],
                $validated['date'],
                (float) $validated['amount'],
                $validated['direction'],
                $validated['description'],
                $validated['reference_number'] ?? null
            );
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('admin.erpnext-accounting-test.create-bank-transaction', [
            'pmes' => User::whereNotNull('erpnext_company_name')->orderBy('company_name')->get(),
            'accounts' => [],
            'selectedUserId' => $validated['user_id'],
            'result' => $result,
            'error' => $error,
        ]);
    }

    public function reconcileBankTransaction(Request $request): View
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'bank_transaction_name' => ['required', 'string', 'max:255'],
            'unallocated_amount' => ['required', 'numeric'],
        ]);

        return view('admin.erpnext-accounting-test.reconcile-bank-transaction', [
            'userId' => $validated['user_id'],
            'bankTransactionName' => $validated['bank_transaction_name'],
            'unallocatedAmount' => $validated['unallocated_amount'],
        ]);
    }

    public function storeReconcileBankTransaction(Request $request, ErpNextClient $erpNext): View
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'bank_transaction_name' => ['required', 'string', 'max:255'],
            'voucher_type' => ['required', 'in:Journal Entry,Payment Entry,Sales Invoice'],
            'voucher_name' => ['required', 'string', 'max:255'],
            'allocated_amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $result = null;
        $error = null;

        try {
            $result = $erpNext->reconcileBankTransactionForPme(
                $validated['bank_transaction_name'],
                $validated['voucher_type'],
                $validated['voucher_name'],
                (float) $validated['allocated_amount']
            );
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('admin.erpnext-accounting-test.reconcile-bank-transaction', [
            'userId' => $validated['user_id'],
            'bankTransactionName' => $validated['bank_transaction_name'],
            'unallocatedAmount' => $result['unallocated_amount'] ?? $validated['allocated_amount'],
            'result' => $result,
            'error' => $error,
        ]);
    }
}
