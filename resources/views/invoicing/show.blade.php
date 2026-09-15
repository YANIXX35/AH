@extends('layouts.app')

@section('title', 'Facture '.$invoice->invoice_number.' | Sitiame Capital')
@section('page_title', 'Facture '.$invoice->invoice_number)

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h3 mb-1"><strong>Facture</strong> {{ $invoice->invoice_number }}</h2>
            <p class="text-muted small mb-0">{{ $invoice->client_name }} · Émise le {{ $invoice->issue_date->format('d/m/Y') }} · Échéance {{ $invoice->due_date->format('d/m/Y') }}</p>
        </div>
        <div class="d-flex gap-2">
            <select id="invoiceExportFormat" class="form-select form-select-sm d-inline-block" style="width:auto;" aria-label="Format de téléchargement">
                <option value="pdf">PDF</option>
                <option value="xlsx">Excel (XLSX)</option>
                <option value="csv">CSV</option>
                <option value="docx">Word (DOCX)</option>
            </select>
            <a id="invoiceExportLink" href="{{ route('invoicing.export', [$invoice, 'pdf']) }}" class="btn btn-outline-secondary btn-sm">Télécharger</a>
            <a href="{{ route('invoicing.index') }}" class="btn btn-outline-secondary btn-sm">Retour</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="mb-3">Lignes</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="text-end">Qté</th>
                                    <th class="text-end">Prix unitaire</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoice->items as $item)
                                    <tr>
                                        <td>{{ $item->description }}</td>
                                        <td class="text-end">{{ $item->quantity }}</td>
                                        <td class="text-end">{{ number_format((float) $item->unit_price, 0, ',', ' ') }}</td>
                                        <td class="text-end">{{ number_format((float) $item->line_total, 0, ',', ' ') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end">
                        <table class="table table-sm" style="width: 320px;">
                            <tr><th>Sous-total</th><td class="text-end">{{ number_format((float) $invoice->subtotal, 0, ',', ' ') }} {{ $invoice->currency }}</td></tr>
                            <tr><th>TVA ({{ $invoice->tax_rate }}%)</th><td class="text-end">{{ number_format((float) $invoice->tax_amount, 0, ',', ' ') }} {{ $invoice->currency }}</td></tr>
                            <tr><th>Total</th><td class="text-end fw-bold">{{ number_format((float) $invoice->total_amount, 0, ',', ' ') }} {{ $invoice->currency }}</td></tr>
                            <tr><th>Réglé</th><td class="text-end text-success">{{ number_format((float) $invoice->amount_paid, 0, ',', ' ') }} {{ $invoice->currency }}</td></tr>
                            <tr><th>Solde dû</th><td class="text-end fw-bold {{ $invoice->balanceDue() > 0 ? 'text-danger' : '' }}">{{ number_format($invoice->balanceDue(), 0, ',', ' ') }} {{ $invoice->currency }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Encaissements</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th class="text-end">Montant</th>
                                    <th>Méthode</th>
                                    <th>Référence</th>
                                    <th>Trésorerie liée</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($invoice->payments as $payment)
                                    <tr>
                                        <td>{{ $payment->paid_at->format('d/m/Y') }}</td>
                                        <td class="text-end">{{ number_format((float) $payment->amount, 0, ',', ' ') }} {{ $invoice->currency }}</td>
                                        <td>{{ $payment->method ?? '—' }}</td>
                                        <td>{{ $payment->reference ?? '—' }}</td>
                                        <td>
                                            @if ($payment->treasuryTransaction)
                                                <a href="{{ route('treasury.edit', $payment->treasuryTransaction) }}">{{ $payment->treasuryTransaction->mobile_method ? ucfirst($payment->treasuryTransaction->mobile_method) : 'Voir' }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-3">Aucun encaissement pour l'instant.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Facturation</h5>
                    <p class="text-muted small mb-0">Les factures (création, encaissement, annulation) se créent désormais directement dans ERPNext. Les changements apparaissent automatiquement ici une fois enregistrés là-bas.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var formatSelect = document.getElementById('invoiceExportFormat');
    var link = document.getElementById('invoiceExportLink');
    var baseUrl = link.getAttribute('href').replace(/\/pdf$/, '');
    formatSelect.addEventListener('change', function () {
        link.setAttribute('href', baseUrl + '/' + formatSelect.value);
    });
})();
</script>
@endsection
