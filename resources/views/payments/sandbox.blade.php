@extends('layouts.app')

@section('title', 'Paiement FedaPay Sandbox | ' . config('app.name'))
@section('page_title', 'Paiement FedaPay sandbox')

@push('styles')
<style>
    .crypto-shell { background: #f5f7fb; border-radius: 1rem; padding: 1rem; }
    .crypto-hero {
        background: linear-gradient(120deg, #1f2937 0%, #1d4ed8 60%, #2563eb 100%);
        border-radius: 1rem;
        color: #fff;
        padding: 1.1rem 1.2rem;
    }
    .crypto-kpi {
        border: 1px solid #e5e7eb;
        border-radius: .9rem;
        background: #fff;
        box-shadow: 0 8px 22px rgba(15, 23, 42, .06);
        padding: 1rem;
        height: 100%;
    }
    .crypto-kpi-label { font-size: .75rem; text-transform: uppercase; letter-spacing: .06em; color: #64748b; font-weight: 700; }
    .crypto-kpi-value { font-size: 1.45rem; font-weight: 800; color: #0f172a; margin-top: .2rem; }
    .crypto-chip {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        border: 1px solid #dbeafe;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: .72rem;
        font-weight: 700;
        padding: .2rem .55rem;
    }
    .crypto-card {
        border: 1px solid #e5e7eb;
        border-radius: .95rem;
        background: #fff;
        box-shadow: 0 10px 28px rgba(15, 23, 42, .06);
        overflow: hidden;
    }
    .crypto-table thead th {
        background: #f8fafc;
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #64748b;
        white-space: nowrap;
    }
    .crypto-table tbody td { vertical-align: middle; }
    .crypto-status {
        border-radius: 999px;
        font-size: .7rem;
        font-weight: 700;
        padding: .22rem .55rem;
        display: inline-flex;
        align-items: center;
    }
    .crypto-status.ok { background: #dcfce7; color: #166534; }
    .crypto-status.bad { background: #fee2e2; color: #991b1b; }
    .crypto-status.pending { background: #e2e8f0; color: #334155; }
</style>
@endpush

@section('content')
<div class="container-fluid p-0 crypto-shell">
    <div class="crypto-hero mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h4 class="mb-1 fw-bold">FedaPay Events Dashboard</h4>
                <p class="mb-0 small text-white-50">Visualisation des événements en direct avec une interface type dashboard crypto.</p>
            </div>
            <span class="badge bg-light text-primary fw-semibold px-3 py-2">{{ $isFedaPaySandboxEnabled ? 'Sandbox active' : 'Simulation locale' }}</span>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="crypto-kpi">
                <div class="crypto-kpi-label">Montant test</div>
                <div class="crypto-kpi-value">{{ number_format((float) $paymentAmount, 0, ',', ' ') }} XOF</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="crypto-kpi">
                <div class="crypto-kpi-label">Événements récupérés</div>
                <div class="crypto-kpi-value">{{ count($fedapayEvents ?? []) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="crypto-kpi d-flex flex-column justify-content-between">
                <div class="crypto-kpi-label mb-2">Paiement</div>
                <a href="{{ route('payments.redirect') }}" class="btn btn-primary w-100">Payer maintenant</a>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('subscriptions.history') }}" class="btn btn-outline-primary btn-sm">Voir l'historique des abonnements</a>
        <a href="{{ route('profile') }}" class="btn btn-outline-secondary btn-sm">Retour au profil</a>
    </div>

    <div class="crypto-card mb-3 p-3">
        <div class="small text-muted mb-2">Moyens mobiles disponibles</div>
        <div class="d-flex flex-wrap gap-2">
            @forelse($mobileMethods as $method)
                <span class="crypto-chip">{{ $method }}</span>
            @empty
                <span class="crypto-chip">WAVE</span>
            @endforelse
        </div>
    </div>

    @error('fedapay')
        <div class="alert alert-danger py-2 small mb-3">{{ $message }}</div>
    @enderror
    @if($fedapayEventsError)
        <div class="alert alert-warning py-2 small mb-3">
            Impossible de charger les événements FedaPay : {{ $fedapayEventsError }}
        </div>
    @endif

    <div class="crypto-card">
        <div class="card-header d-flex justify-content-between align-items-center bg-white border-bottom">
            <h5 class="card-title mb-0 fw-bold">Tableau des Événements FedaPay</h5>
            <span class="badge bg-primary">{{ count($fedapayEvents ?? []) }}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover crypto-table mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Référence transaction</th>
                        <th>Statut</th>
                        <th>Montant</th>
                        <th>Détail échec</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fedapayEvents as $event)
                        @php
                            $status = strtoupper((string) ($event['transaction_status'] ?? ''));
                            $statusClass = in_array($status, ['APPROVED', 'COMPLETED'], true)
                                ? 'ok'
                                : (in_array($status, ['FAILED', 'REJECTED', 'CANCELED', 'DECLINED'], true) ? 'bad' : 'pending');
                        @endphp
                        <tr>
                            <td class="small text-break">{{ $event['id'] }}</td>
                            <td class="small">{{ $event['name'] }}</td>
                            <td class="small text-break">{{ $event['transaction_reference'] !== '' ? $event['transaction_reference'] : '—' }}</td>
                            <td class="small">
                                <span class="crypto-status {{ $statusClass }}">{{ $status !== '' ? $status : '—' }}</span>
                            </td>
                            <td class="small">{{ $event['amount'] !== '' ? $event['amount'].' XOF' : '—' }}</td>
                            <td class="small text-danger text-break">{{ $event['failure_reason'] !== '' ? $event['failure_reason'] : '—' }}</td>
                            <td class="small text-nowrap">{{ $event['date'] !== '' ? $event['date'] : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Aucun événement FedaPay récupéré pour le moment.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
