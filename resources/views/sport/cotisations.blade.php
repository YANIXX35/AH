@extends('layouts.app')

@section('title', 'Cotisations | Sitiame Capital')
@section('page_title', 'Cotisations')

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h3 mb-0">Cotisations</h2>
        <a href="{{ route('sport.index') }}" class="btn btn-outline-secondary btn-sm">Retour</a>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-sm">
                <thead><tr><th>N° facture</th><th>Membre</th><th>Émission</th><th>Montant</th><th>Statut</th></tr></thead>
                <tbody>
                    @forelse ($cotisations as $invoice)
                        <tr>
                            <td><a href="{{ route('invoicing.show', $invoice) }}">{{ $invoice->invoice_number }}</a></td>
                            <td>{{ $invoice->client_name }}</td>
                            <td>{{ $invoice->issue_date->format('d/m/Y') }}</td>
                            <td>{{ number_format((float) $invoice->total_amount, 0, ',', ' ') }} {{ $invoice->currency }}</td>
                            <td>{{ ucfirst($invoice->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucune cotisation pour l'instant.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
