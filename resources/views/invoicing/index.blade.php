@extends('layouts.app')

@section('title', 'Facturation | Sitiame Capitale')
@section('page_title', 'Facturation')

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h3 mb-1"><strong>Facturation</strong> client</h2>
            <p class="text-muted small mb-0">Émets tes factures, suis les créances et rapproche les encaissements avec la trésorerie.</p>
        </div>
        <a href="{{ route('invoicing.create') }}" class="btn btn-primary btn-sm">+ Nouvelle facture</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card"><div class="card-body">
                <div class="text-muted small">Impayées</div>
                <div class="h4 mb-0">{{ number_format((float) $totals['unpaid'], 0, ',', ' ') }} FCFA</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card"><div class="card-body">
                <div class="text-muted small">Partiellement réglées</div>
                <div class="h4 mb-0">{{ number_format((float) $totals['partially_paid'], 0, ',', ' ') }} FCFA</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card"><div class="card-body">
                <div class="text-muted small">En retard</div>
                <div class="h4 mb-0 text-danger">{{ $totals['overdue'] }}</div>
            </div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex gap-2 mb-3">
                @foreach (['' => 'Toutes', 'unpaid' => 'Impayées', 'partially_paid' => 'Partielles', 'paid' => 'Réglées', 'cancelled' => 'Annulées'] as $value => $label)
                    <a href="{{ route('invoicing.index', $value ? ['status' => $value] : []) }}"
                       class="btn btn-sm {{ (string) $currentStatus === (string) $value ? 'btn-primary' : 'btn-outline-secondary' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>N° facture</th>
                            <th>Client</th>
                            <th>Émission</th>
                            <th>Échéance</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Réglé</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            @php
                                $badge = ['unpaid' => 'warning', 'partially_paid' => 'info', 'paid' => 'success', 'cancelled' => 'secondary'][$invoice->status] ?? 'secondary';
                            @endphp
                            <tr>
                                <td><a href="{{ route('invoicing.show', $invoice) }}">{{ $invoice->invoice_number }}</a></td>
                                <td>{{ $invoice->client_name }}</td>
                                <td>{{ $invoice->issue_date->format('d/m/Y') }}</td>
                                <td class="{{ $invoice->isOverdue() ? 'text-danger fw-bold' : '' }}">{{ $invoice->due_date->format('d/m/Y') }}</td>
                                <td class="text-end">{{ number_format((float) $invoice->total_amount, 0, ',', ' ') }} {{ $invoice->currency }}</td>
                                <td class="text-end">{{ number_format((float) $invoice->amount_paid, 0, ',', ' ') }} {{ $invoice->currency }}</td>
                                <td><span class="badge bg-{{ $badge }}">{{ $invoice->status }}</span></td>
                                <td>
                                    <a href="{{ route('invoicing.show', $invoice) }}" class="btn btn-sm btn-outline-primary">Détail</a>
                                    @if ((float) $invoice->amount_paid <= 0 && $invoice->status !== 'cancelled')
                                        <a href="{{ route('invoicing.edit', $invoice) }}" class="btn btn-sm btn-outline-secondary">Modifier</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Aucune facture pour l'instant.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $invoices->links() }}
        </div>
    </div>
</div>
@endsection
