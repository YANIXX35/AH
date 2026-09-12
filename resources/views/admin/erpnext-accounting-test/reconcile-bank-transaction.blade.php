@extends('layouts.app')

@section('title', 'Pointer une transaction ERPNext | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="container-fluid py-4">
    <h1 class="h3 mb-3">Pointer la transaction {{ $bankTransactionName }}</h1>

    @if (isset($result) && $result)
        <div class="alert alert-success">
            Pointage enregistré. Statut : <strong>{{ $result['status'] ?? '' }}</strong> — Montant non alloué restant : <strong>{{ number_format((float) ($result['unallocated_amount'] ?? 0), 0, ',', ' ') }} XOF</strong>
        </div>
    @endif

    @if (isset($error) && $error)
        <div class="alert alert-danger">{{ $error }}</div>
    @endif

    <form method="POST" action="{{ route('admin.erpnext-accounting-test.store-reconcile-bank-transaction') }}">
        @csrf
        <input type="hidden" name="user_id" value="{{ $userId }}">
        <input type="hidden" name="bank_transaction_name" value="{{ $bankTransactionName }}">

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label" for="voucher_type">Type de pièce</label>
                <select class="form-select" id="voucher_type" name="voucher_type" required>
                    <option value="Journal Entry">Journal Entry</option>
                    <option value="Payment Entry">Payment Entry</option>
                    <option value="Sales Invoice">Sales Invoice</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="voucher_name">Nom exact de la pièce</label>
                <input type="text" class="form-control" id="voucher_name" name="voucher_name" placeholder="ex: ACC-JV-2026-00004" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="allocated_amount">Montant à allouer</label>
                <input type="number" step="0.01" min="0.01" class="form-control" id="allocated_amount" name="allocated_amount" value="{{ $unallocatedAmount }}" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Enregistrer le pointage</button>
    </form>
</div>
@endsection
