@extends('layouts.app')

@section('title', 'Dashboard CEO/CFO')
@section('page_title', 'Dashboard exécutif CEO/CFO')

@section('content')
<div class="container-fluid p-0">
    <div class="row g-3 mb-3">
        <div class="col-md-2"><div class="card"><div class="card-body"><div class="text-muted small">MRR</div><div class="h5 mb-0">{{ number_format((float) $kpis['mrr'], 0, ',', ' ') }} XOF</div></div></div></div>
        <div class="col-md-2"><div class="card"><div class="card-body"><div class="text-muted small">Churn</div><div class="h5 mb-0">{{ number_format((float) $kpis['churn'], 2, ',', ' ') }}%</div></div></div></div>
        <div class="col-md-2"><div class="card"><div class="card-body"><div class="text-muted small">Impayés</div><div class="h5 mb-0">{{ (int) $kpis['unpaid_invoices'] }}</div></div></div></div>
        <div class="col-md-2"><div class="card"><div class="card-body"><div class="text-muted small">DSO</div><div class="h5 mb-0">{{ number_format((float) $kpis['dso'], 2, ',', ' ') }} j</div></div></div></div>
        <div class="col-md-2"><div class="card"><div class="card-body"><div class="text-muted small">Cash forecast</div><div class="h5 mb-0">{{ number_format((float) $kpis['cash_forecast'], 0, ',', ' ') }} XOF</div></div></div></div>
        <div class="col-md-2"><div class="card"><div class="card-body"><div class="text-muted small">Croissance CA</div><div class="h5 mb-0">{{ number_format((float) $kpis['revenue_growth'], 2, ',', ' ') }}%</div></div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Top risques clients (échecs paiements)</h5></div>
                <div class="card-body table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Client</th><th>Email</th><th>Risque</th></tr></thead>
                        <tbody>
                        @forelse($topRiskClients as $row)
                            <tr>
                                <td>{{ $row->user?->company_name ?: $row->user?->name ?: 'N/A' }}</td>
                                <td>{{ $row->user?->email ?: '-' }}</td>
                                <td><span class="badge bg-danger">{{ $row->cnt }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted">Aucun risque détecté.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Alertes intelligentes</h5></div>
                <div class="card-body">
                    @foreach($alerts as $alert)
                        <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                            <div>
                                <div class="fw-semibold">{{ $alert['label'] }}</div>
                                <small class="text-muted">Seuil: {{ $alert['threshold'] }}</small>
                            </div>
                            <span class="badge bg-{{ $alert['status'] }}">{{ $alert['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
