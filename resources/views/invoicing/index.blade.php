@extends('layouts.app')

@section('title', 'Facturation | Sitiame Capitale')
@section('page_title', 'Facturation Client')

@push('styles')
    <style>
        .mondays-container { background-color: #f8fafc; min-height: 100vh; }
        .mondays-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); transition: all 0.2s ease; }
        .mondays-card:hover { border-color: #cbd5e1; box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
        .mondays-hero-date { font-size: 0.85rem; font-weight: 500; color: #64748b; }
        .mondays-hero-title { font-size: 1.85rem; font-weight: 700; color: #0f172a; margin-top: 2px; margin-bottom: 12px; }
        .mondays-pill-bar { display: inline-flex; align-items: center; gap: 16px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 9999px; padding: 6px 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); flex-wrap: wrap; }
        .mondays-pill-item { font-size: 0.84rem; font-weight: 600; color: #334155; display: flex; align-items: center; gap: 6px; }
        .mondays-badge { display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .mondays-badge-success { background: #dcfce7; color: #15803d; }
        .mondays-badge-pending { background: #f3e8ff; color: #7e22ce; }
        .mondays-badge-info { background: #dbeafe; color: #1d4ed8; }
        .mondays-badge-warning { background: #ffedd5; color: #c2410c; }
        .mondays-metric-val { font-size: 1.65rem; font-weight: 700; color: #0f172a; line-height: 1.2; }
    </style>
@endpush

@section('content')
<div class="mondays-container pb-4">
    <!-- HERO MONDAYS HEADER -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2">
            <div>
                <div class="mondays-hero-date">
                    <i data-feather="calendar" class="me-1" style="width:14px; height:14px;"></i>
                    {{ \Carbon\Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </div>
                <h1 class="mondays-hero-title">
                    Facturation Client — {{ explode(' ', auth()->user()?->name ?? 'Utilisateur')[0] }} 👋
                </h1>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="{{ route('invoicing.create') }}" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold">
                    <i data-feather="plus" class="me-1" style="width:14px; height:14px;"></i> Nouvelle Facture
                </a>
            </div>
        </div>

        <!-- BARRE DE PILULES KPI EN-TÊTE -->
        <div class="mondays-pill-bar">
            <div class="mondays-pill-item">
                <span class="text-warning">💳</span> <strong>Impayées :</strong> {{ number_format((float) $totals['unpaid'], 0, ',', ' ') }} FCFA
            </div>
            <div class="mondays-pill-item text-muted">|</div>
            <div class="mondays-pill-item">
                <span class="text-info">⏳</span> <strong>Partielles :</strong> {{ number_format((float) $totals['partially_paid'], 0, ',', ' ') }} FCFA
            </div>
            <div class="mondays-pill-item text-muted">|</div>
            <div class="mondays-pill-item">
                <span class="text-danger">🚨</span> <strong>En retard :</strong> {{ $totals['overdue'] }} factures
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success border-0 rounded-3 mb-3 shadow-sm">{{ session('success') }}</div>
    @endif

    <!-- METRICS CARDS GRID -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
            <div class="card mondays-card h-100 border-0 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Factures Impayées</span>
                    <span class="mondays-badge mondays-badge-warning">À encaisser</span>
                </div>
                <div class="mondays-metric-val text-warning mb-1">{{ number_format((float) $totals['unpaid'], 0, ',', ' ') }} <small class="fs-6 fw-normal text-muted">FCFA</small></div>
                <div class="text-muted small">Créances clients restantes.</div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card mondays-card h-100 border-0 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Règlements Partiels</span>
                    <span class="mondays-badge mondays-badge-info">En cours</span>
                </div>
                <div class="mondays-metric-val text-primary mb-1">{{ number_format((float) $totals['partially_paid'], 0, ',', ' ') }} <small class="fs-6 fw-normal text-muted">FCFA</small></div>
                <div class="text-muted small">Avances et acomptes perçus.</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card mondays-card h-100 border-0 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Factures en Retard</span>
                    <span class="mondays-badge mondays-badge-warning">Alerte relance</span>
                </div>
                <div class="mondays-metric-val text-danger mb-1">{{ $totals['overdue'] }} <small class="fs-6 fw-normal text-muted">factures</small></div>
                <div class="text-muted small">Échéances dépassées.</div>
            </div>
        </div>
    </div>

    <!-- MAIN TABLE CARD -->
    <div class="card mondays-card border-0">
        <div class="card-body p-4">
            <div class="d-flex gap-2 mb-3 flex-wrap">
                @foreach (['' => 'Toutes', 'unpaid' => 'Impayées', 'partially_paid' => 'Partielles', 'paid' => 'Réglées', 'cancelled' => 'Annulées'] as $value => $label)
                    <a href="{{ route('invoicing.index', $value ? ['status' => $value] : []) }}"
                       class="btn btn-sm rounded-pill px-3 {{ (string) $currentStatus === (string) $value ? 'btn-primary' : 'btn-light border' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>N° facture</th>
                            <th>Client</th>
                            <th>Émission</th>
                            <th>Échéance</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Réglé</th>
                            <th>Statut</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            @php
                                $badgeClass = match($invoice->status) {
                                    'paid' => 'mondays-badge-success',
                                    'partially_paid' => 'mondays-badge-info',
                                    'unpaid' => 'mondays-badge-warning',
                                    default => 'mondays-badge-secondary',
                                };
                            @endphp
                            <tr>
                                <td><a href="{{ route('invoicing.show', $invoice) }}" class="fw-bold text-primary">{{ $invoice->invoice_number }}</a></td>
                                <td>{{ $invoice->client_name }}</td>
                                <td>{{ $invoice->issue_date->format('d/m/Y') }}</td>
                                <td class="{{ $invoice->isOverdue() ? 'text-danger fw-bold' : '' }}">{{ $invoice->due_date->format('d/m/Y') }}</td>
                                <td class="text-end fw-semibold">{{ number_format((float) $invoice->total_amount, 0, ',', ' ') }} {{ $invoice->currency }}</td>
                                <td class="text-end text-success">{{ number_format((float) $invoice->amount_paid, 0, ',', ' ') }} {{ $invoice->currency }}</td>
                                <td><span class="mondays-badge {{ $badgeClass }}">{{ ucfirst($invoice->status) }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('invoicing.show', $invoice) }}" class="btn btn-sm btn-outline-primary rounded-pill">Détail</a>
                                    @if ((float) $invoice->amount_paid <= 0 && $invoice->status !== 'cancelled')
                                        <a href="{{ route('invoicing.edit', $invoice) }}" class="btn btn-sm btn-outline-secondary rounded-pill">Modifier</a>
                                        <form action="{{ route('invoicing.destroy', $invoice) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Supprimer définitivement cette facture ? Cette action est irréversible.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill">Supprimer</button>
                                        </form>
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
            <div class="mt-3">
                {{ $invoices->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
