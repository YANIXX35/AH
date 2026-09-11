@extends('layouts.app')

@section('title', 'ERPNext Test | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">ERPNext Test — Factures</h1>
        <a href="{{ route('admin.erpnext-test.create') }}" class="btn btn-primary">Nouvelle facture test</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>PME</th>
                        <th>Statut</th>
                        <th>N° ERPNext</th>
                        <th>Total TTC</th>
                        <th>Créée le</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td>{{ $invoice->id }}</td>
                            <td>{{ $invoice->user?->company_name ?? $invoice->user?->name }}</td>
                            <td>
                                @if ($invoice->status === 'synced')
                                    <span class="badge bg-success">Synchronisée</span>
                                @elseif ($invoice->status === 'failed')
                                    <span class="badge bg-danger">Échec</span>
                                @else
                                    <span class="badge bg-secondary">En cours</span>
                                @endif
                            </td>
                            <td>{{ $invoice->erpnext_invoice_name ?? '—' }}</td>
                            <td>{{ $invoice->grand_total !== null ? number_format((float) $invoice->grand_total, 0, ',', ' ').' XOF' : '—' }}</td>
                            <td>{{ $invoice->created_at->format('d/m/Y H:i') }}</td>
                            <td><a href="{{ route('admin.erpnext-test.show', $invoice) }}">Voir</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucune facture test pour le moment.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
