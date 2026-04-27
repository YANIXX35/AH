@extends('layouts.app')

@section('title', 'Paiement FedaPay Sandbox | ' . config('app.name'))
@section('page_title', 'Paiement FedaPay sandbox')

@push('styles')
<style>
    .sandbox-hero {
        background: linear-gradient(135deg, #3b7ddd 0%, #285eb8 100%);
        color: #fff;
        border-radius: .6rem;
        box-shadow: 0 .25rem .75rem rgba(59, 125, 221, .25);
    }
    .sandbox-kpi {
        border: 1px solid rgba(0,0,0,.06);
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .sandbox-kpi:hover {
        transform: translateY(-2px);
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.08);
    }
    .sandbox-filter-btn.active {
        background: #3b7ddd;
        color: #fff;
        border-color: #3b7ddd;
    }
    .sandbox-chip {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        border: 1px solid rgba(59,125,221,.25);
        background: rgba(59,125,221,.08);
        color: #285eb8;
        font-size: .72rem;
        font-weight: 700;
        padding: .2rem .55rem;
    }
    .sandbox-status {
        font-size: .72rem;
        font-weight: 700;
        padding: .25rem .55rem;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
    }
</style>
@endpush

@section('content')
@php
    $events = collect($fedapayEvents ?? []);
    $successStatuses = ['APPROVED', 'COMPLETED'];
    $failureStatuses = ['FAILED', 'REJECTED', 'CANCELED', 'DECLINED'];
    $successCount = $events->filter(fn ($event) => in_array(strtoupper((string) ($event['transaction_status'] ?? '')), $successStatuses, true))->count();
    $failureCount = $events->filter(fn ($event) => in_array(strtoupper((string) ($event['transaction_status'] ?? '')), $failureStatuses, true))->count();
    $pendingCount = max(0, $events->count() - $successCount - $failureCount);
@endphp

<div class="container-fluid p-0">
    <div class="mb-4">
        <div class="sandbox-hero p-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3">
                <div>
                    <h1 class="h3 mb-1 text-white"><strong>Paiements sandbox</strong> FedaPay</h1>
                    <p class="mb-0 text-white-50">Suivi des événements de paiement avec statuts opérationnels harmonisés AdminKit.</p>
                </div>
                <div class="text-lg-end d-flex flex-column gap-2">
                    <span class="badge bg-white text-primary border-0">
                        {{ $isFedaPaySandboxEnabled ? 'Sandbox active' : 'Simulation locale' }}
                    </span>
                    <span class="badge bg-light text-dark border-0">Événements: {{ $events->count() }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card shadow-sm sandbox-kpi h-100">
                <div class="card-body">
                    <p class="text-muted small text-uppercase mb-1">Tarif abonnement</p>
                    <h3 class="mb-0">{{ number_format((float) $paymentAmount, 0, ',', ' ') }} XOF</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm sandbox-kpi h-100">
                <div class="card-body">
                    <p class="text-muted small text-uppercase mb-1">Succès</p>
                    <h3 class="mb-0 text-success">{{ $successCount }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm sandbox-kpi h-100">
                <div class="card-body">
                    <p class="text-muted small text-uppercase mb-1">En attente</p>
                    <h3 class="mb-0 text-warning">{{ $pendingCount }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm sandbox-kpi h-100">
                <div class="card-body">
                    <p class="text-muted small text-uppercase mb-1">Échecs</p>
                    <h3 class="mb-0 text-danger">{{ $failureCount }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('subscriptions.history') }}" class="btn btn-outline-primary btn-sm">Voir l'historique des abonnements</a>
        <a href="{{ route('profile') }}" class="btn btn-outline-secondary btn-sm">Retour au profil</a>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
        <div class="small text-muted mb-2">Moyens mobiles disponibles</div>
        <div class="d-flex flex-wrap gap-2">
            @forelse($mobileMethods as $method)
                <span class="sandbox-chip">{{ $method }}</span>
            @empty
                <span class="sandbox-chip">WAVE</span>
            @endforelse
        </div>
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

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="card-title mb-0">Tableau des événements FedaPay</h5>
                <small class="text-muted">Filtre par statut pour suivre rapidement les transactions sensibles.</small>
            </div>
            <div class="d-flex flex-wrap gap-2" id="sandboxEventFilters">
                <button type="button" class="btn btn-sm btn-outline-secondary sandbox-filter-btn active" data-filter="all">Tous</button>
                <button type="button" class="btn btn-sm btn-outline-success sandbox-filter-btn" data-filter="ok">Succès</button>
                <button type="button" class="btn btn-sm btn-outline-warning sandbox-filter-btn" data-filter="pending">En attente</button>
                <button type="button" class="btn btn-sm btn-outline-danger sandbox-filter-btn" data-filter="bad">Échecs</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Événement</th>
                        <th>Type</th>
                        <th>Référence</th>
                        <th>Statut</th>
                        <th>Montant</th>
                        <th>Détails</th>
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
                        <tr data-status="{{ $statusClass }}">
                            <td class="small">
                                <strong class="text-dark">{{ $event['id'] }}</strong>
                            </td>
                            <td class="small">{{ $event['name'] }}</td>
                            <td class="small text-break">{{ $event['transaction_reference'] !== '' ? $event['transaction_reference'] : '—' }}</td>
                            <td class="small">
                                <span class="sandbox-status {{ $statusClass === 'ok' ? 'bg-success-subtle text-success-emphasis' : ($statusClass === 'bad' ? 'bg-danger-subtle text-danger-emphasis' : 'bg-warning-subtle text-warning-emphasis') }}">
                                    {{ $status !== '' ? $status : '—' }}
                                </span>
                            </td>
                            <td class="small">{{ $event['amount'] !== '' ? $event['amount'].' XOF' : '—' }}</td>
                            <td class="small text-break {{ $event['failure_reason'] !== '' ? 'text-danger' : 'text-muted' }}">
                                {{ $event['failure_reason'] !== '' ? $event['failure_reason'] : 'RAS' }}
                            </td>
                            <td class="small text-nowrap text-muted">{{ $event['date'] !== '' ? $event['date'] : '—' }}</td>
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

@push('scripts')
<script>
    (function () {
        const filterRoot = document.getElementById('sandboxEventFilters');
        if (!filterRoot) {
            return;
        }

        const buttons = Array.from(filterRoot.querySelectorAll('[data-filter]'));
        const rows = Array.from(document.querySelectorAll('tbody tr[data-status]'));

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const filter = button.dataset.filter || 'all';

                buttons.forEach((item) => item.classList.remove('active'));
                button.classList.add('active');

                rows.forEach((row) => {
                    const rowStatus = row.dataset.status || '';
                    row.style.display = filter === 'all' || rowStatus === filter ? '' : 'none';
                });
            });
        });
    })();
</script>
@endpush
