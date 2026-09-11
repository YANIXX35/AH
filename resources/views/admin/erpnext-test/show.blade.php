@extends('layouts.app')

@section('title', 'Facture test #' . $invoice->id . ' | ERPNext Test | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Facture test #{{ $invoice->id }}</h1>
        <a href="{{ route('admin.erpnext-test.index') }}">← Retour à la liste</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <p><strong>PME :</strong> {{ $invoice->user?->company_name ?? $invoice->user?->name }}</p>
            <p><strong>Statut :</strong>
                @if ($invoice->status === 'synced')
                    <span class="badge bg-success">Synchronisée</span>
                @elseif ($invoice->status === 'failed')
                    <span class="badge bg-danger">Échec</span>
                @else
                    <span class="badge bg-secondary">En cours</span>
                @endif
            </p>

            @if ($invoice->status === 'failed')
                <div class="alert alert-danger">{{ $invoice->error_message }}</div>
            @endif

            @if ($invoice->status === 'synced')
                <p><strong>N° facture ERPNext :</strong> {{ $invoice->erpnext_invoice_name }}</p>
                <p><strong>Total avant taxes :</strong> {{ number_format((float) $invoice->total_before_tax, 0, ',', ' ') }} XOF</p>
                <p><strong>Total taxes :</strong> {{ number_format((float) $invoice->total_taxes, 0, ',', ' ') }} XOF</p>
                <p><strong>Total TTC :</strong> {{ number_format((float) $invoice->grand_total, 0, ',', ' ') }} XOF</p>
                <p><strong>Montant dû :</strong> {{ number_format((float) $invoice->outstanding_amount, 0, ',', ' ') }} XOF</p>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Lignes saisies dans PME360</div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Désignation</th><th>Quantité</th><th>Prix unitaire</th><th>Montant</th></tr></thead>
                <tbody>
                    @foreach ($invoice->items as $item)
                        <tr>
                            <td>{{ $item->description }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ number_format((float) $item->unit_price, 0, ',', ' ') }} XOF</td>
                            <td>{{ number_format((float) $item->amount, 0, ',', ' ') }} XOF</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($invoice->raw_response)
        <div class="card">
            <div class="card-header">Réponse brute ERPNext (debug)</div>
            <div class="card-body">
                <pre class="mb-0" style="white-space: pre-wrap;">{{ json_encode($invoice->raw_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </div>
    @endif
</div>
@endsection
