@extends('layouts.app')

@section('title', 'Comptabilité ERPNext Test | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">{{ $pme->company_name ?? $pme->name }} — {{ $fromDate }} au {{ $toDate }}</h1>
        <a href="{{ route('admin.erpnext-accounting-test.index') }}">← Retour</a>
    </div>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-coa" type="button">Plan comptable</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-gl" type="button">Grand livre</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-tb" type="button">Balance générale</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-bs" type="button">Bilan</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-pl" type="button">Compte de résultat</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-bank" type="button">Rapprochement bancaire</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-coa">
            @if ($chartOfAccountsError)
                <div class="alert alert-danger">{{ $chartOfAccountsError }}</div>
            @else
                <table class="table table-sm">
                    <thead><tr><th>Compte</th><th>Type racine</th><th>Groupe ?</th></tr></thead>
                    <tbody>
                        @foreach ($chartOfAccounts as $account)
                            <tr>
                                <td>{{ $account['account_name'] }}</td>
                                <td>{{ $account['root_type'] }}</td>
                                <td>{{ $account['is_group'] ? 'Oui' : 'Non' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="tab-pane fade" id="tab-gl">
            @if ($generalLedgerError)
                <div class="alert alert-danger">{{ $generalLedgerError }}</div>
            @else
                <table class="table table-sm">
                    <thead><tr><th>Date</th><th>Compte</th><th>Débit</th><th>Crédit</th><th>Pièce</th></tr></thead>
                    <tbody>
                        @foreach ($generalLedger as $entry)
                            <tr>
                                <td>{{ $entry['posting_date'] }}</td>
                                <td>{{ $entry['account'] }}</td>
                                <td>{{ number_format((float) $entry['debit'], 0, ',', ' ') }}</td>
                                <td>{{ number_format((float) $entry['credit'], 0, ',', ' ') }}</td>
                                <td>{{ $entry['voucher_type'] }} {{ $entry['voucher_no'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="tab-pane fade" id="tab-tb">
            @if ($trialBalanceError)
                <div class="alert alert-danger">{{ $trialBalanceError }}</div>
            @else
                <table class="table table-sm">
                    <thead><tr><th>Compte</th><th>Débit</th><th>Crédit</th><th>Solde</th></tr></thead>
                    <tbody>
                        @foreach ($trialBalance as $row)
                            <tr>
                                <td>{{ $row['account'] }}</td>
                                <td>{{ number_format($row['debit'], 0, ',', ' ') }}</td>
                                <td>{{ number_format($row['credit'], 0, ',', ' ') }}</td>
                                <td>{{ number_format($row['balance'], 0, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="tab-pane fade" id="tab-bs">
            @if ($balanceSheetError)
                <div class="alert alert-danger">{{ $balanceSheetError }}</div>
            @else
                <table class="table table-sm">
                    <thead><tr><th>Compte</th><th>Montant</th></tr></thead>
                    <tbody>
                        @foreach ($balanceSheet as $row)
                            <tr>
                                <td style="padding-left: {{ ($row['indent'] ?? 0) * 20 }}px">{{ $row['account_name'] ?? $row['account'] ?? '' }}</td>
                                <td>{{ isset($row['total']) ? number_format((float) $row['total'], 0, ',', ' ') : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="tab-pane fade" id="tab-pl">
            @if ($profitAndLossError)
                <div class="alert alert-danger">{{ $profitAndLossError }}</div>
            @else
                <table class="table table-sm">
                    <thead><tr><th>Compte</th><th>Montant</th></tr></thead>
                    <tbody>
                        @foreach ($profitAndLoss as $row)
                            <tr>
                                <td style="padding-left: {{ ($row['indent'] ?? 0) * 20 }}px">{{ $row['account_name'] ?? $row['account'] ?? '' }}</td>
                                <td>{{ isset($row['total']) ? number_format((float) $row['total'], 0, ',', ' ') : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="tab-pane fade" id="tab-bank">
            @if ($bankTransactionsError)
                <div class="alert alert-danger">{{ $bankTransactionsError }}</div>
            @else
                <div class="mb-2">
                    <a href="{{ route('admin.erpnext-accounting-test.create-bank-transaction') }}" class="btn btn-outline-primary btn-sm">+ Nouvelle ligne de relevé</a>
                </div>
                <table class="table table-sm">
                    <thead><tr><th>Date</th><th>Compte bancaire</th><th>Dépôt</th><th>Retrait</th><th>Description</th><th>Statut</th><th>Montant non alloué</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($bankTransactions as $tx)
                            <tr>
                                <td>{{ $tx['date'] }}</td>
                                <td>{{ $tx['bank_account'] }}</td>
                                <td>{{ number_format((float) $tx['deposit'], 0, ',', ' ') }}</td>
                                <td>{{ number_format((float) $tx['withdrawal'], 0, ',', ' ') }}</td>
                                <td>{{ $tx['description'] }}</td>
                                <td>
                                    @if ($tx['status'] === 'Reconciled')
                                        <span class="badge bg-success">{{ $tx['status'] }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $tx['status'] }}</span>
                                    @endif
                                </td>
                                <td>{{ number_format((float) $tx['unallocated_amount'], 0, ',', ' ') }}</td>
                                <td>
                                    @if ($tx['status'] !== 'Reconciled' && (float) $tx['unallocated_amount'] > 0)
                                        <a href="{{ route('admin.erpnext-accounting-test.reconcile-bank-transaction', ['user_id' => $pme->id, 'bank_transaction_name' => $tx['name'], 'unallocated_amount' => $tx['unallocated_amount']]) }}">Pointer</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
@endsection
