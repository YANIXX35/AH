@extends('layouts.app')

@section('title', 'Factures | Sitiame Capitale')
@section('page_title', 'Facturation & abonnement')

@section('content')
<div class="container-fluid p-0">
    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Abonnement actuel</h6>
                    @if($subscription)
                        <p class="mb-1"><strong>{{ $subscription->plan->name ?? 'Plan' }}</strong></p>
                        <p class="small text-muted mb-1">Statut: {{ strtoupper($subscription->status) }}</p>
                        <p class="small text-muted mb-0">Prochaine echeance: {{ optional($subscription->next_billing_at)->format('d/m/Y H:i') ?? 'N/A' }}</p>
                    @else
                        <p class="text-muted mb-0">Aucun abonnement professionnel actif.</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Relance paiement</h6>
                    @if(($unpaidCount ?? 0) > 0)
                        <p class="mb-2">Vous avez <strong>{{ $unpaidCount }}</strong> facture(s) non reglee(s).</p>
                        <a href="{{ route('payments.sandbox') }}" class="btn btn-sm btn-primary">Regler maintenant</a>
                        @if($overdueInvoice)
                            <p class="small text-muted mt-2 mb-0">Derniere facture en retard: {{ $overdueInvoice->invoice_number }}</p>
                        @endif
                    @else
                        <p class="text-muted mb-0">Aucune facture impayee. Votre compte est a jour.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Historique des factures</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Facture</th>
                            <th>Statut</th>
                            <th>Emission</th>
                            <th>Echeance</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoices as $invoice)
                            <tr>
                                <td>{{ $invoice->invoice_number }}</td>
                                <td><span class="badge bg-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'overdue' ? 'danger' : 'warning text-dark') }}">{{ strtoupper($invoice->status) }}</span></td>
                                <td>{{ optional($invoice->issued_at)->format('d/m/Y H:i') ?? '-' }}</td>
                                <td>{{ optional($invoice->due_at)->format('d/m/Y H:i') ?? '-' }}</td>
                                <td>{{ number_format((float) $invoice->total_amount, 0, ',', ' ') }} {{ $invoice->currency }}</td>
                                <td class="text-end">
                                    <a href="{{ route('billing.invoices.pdf.view', $invoice) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">Visualiser</a>
                                    <a href="{{ route('billing.invoices.pdf', $invoice) }}" class="btn btn-sm btn-outline-primary">Telecharger</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">Aucune facture pour le moment.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $invoices->links() }}</div>
        </div>
    </div>
</div>
@endsection
