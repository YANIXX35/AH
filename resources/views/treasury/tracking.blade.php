@extends('layouts.app')

@section('title', 'Suivi Trésorerie | Sitiame Capital')
@section('page_title', 'Tableau de bord Trésorerie')

@push('styles')
    <style>
        .mondays-container {
            background-color: #f8fafc;
            min-height: 100vh;
        }
        .mondays-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03), 0 4px 12px rgba(0, 0, 0, 0.02);
            transition: all 0.2s ease-in-out;
        }
        .mondays-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
        }
        .mondays-hero-date { font-size: 0.85rem; font-weight: 500; color: #64748b; }
        .mondays-hero-title { font-size: 1.85rem; font-weight: 700; color: #0f172a; margin-top: 2px; margin-bottom: 12px; }
        .mondays-pill-bar {
            display: inline-flex;
            align-items: center;
            gap: 16px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 9999px;
            padding: 6px 20px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
            flex-wrap: wrap;
        }
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
                    Suivi de Trésorerie — {{ explode(' ', auth()->user()?->name ?? 'Utilisateur')[0] }} 👋
                </h1>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="{{ route('treasury.create') }}" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold">
                    <i data-feather="plus" class="me-1" style="width:14px; height:14px;"></i> Nouvelle Transaction
                </a>
                <a href="{{ route('treasury.forecast') }}" class="btn btn-sm btn-light border rounded-pill px-3 fw-semibold text-dark">
                    <i data-feather="trending-up" class="me-1" style="width:14px; height:14px;"></i> Prévisions
                </a>
                <a href="{{ route('treasury.export.csv') }}" class="btn btn-sm btn-light border rounded-pill px-3 fw-semibold text-dark">
                    <i data-feather="download" class="me-1" style="width:14px; height:14px;"></i> Exporter CSV
                </a>
            </div>
        </div>

        <!-- BARRE DE PILULES KPI EN-TÊTE -->
        <div class="mondays-pill-bar">
            <div class="mondays-pill-item">
                <span class="text-primary">💰</span> <strong>Solde Nette :</strong> {{ number_format($soldeNetEffectue, 0, ',', ' ') }} FCFA
            </div>
            <div class="mondays-pill-item text-muted">|</div>
            <div class="mondays-pill-item">
                <span class="text-success">📈</span> <strong>Encaissements :</strong> {{ number_format($encaissementsTotal, 0, ',', ' ') }} FCFA
            </div>
            <div class="mondays-pill-item text-muted">|</div>
            <div class="mondays-pill-item">
                <span class="text-danger">📉</span> <strong>Décaissements :</strong> {{ number_format($decaissementsTotal, 0, ',', ' ') }} FCFA
            </div>
        </div>
    </div>

    <!-- METRICS CARDS GRID -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-4">
            <div class="card mondays-card h-100 border-0 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Solde Global Effectué</span>
                    <span class="mondays-badge mondays-badge-info">Global</span>
                </div>
                <div class="mondays-metric-val text-primary mb-1">{{ number_format($soldeNetEffectue, 0, ',', ' ') }} <small class="fs-6 fw-normal text-muted">FCFA</small></div>
                <div class="small {{ $soldeNetFiltre >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ $soldeNetFiltre >= 0 ? '+' : '' }}{{ number_format($soldeNetFiltre, 0, ',', ' ') }} FCFA sur la période
                </div>
                <div class="small text-muted mt-1" title="Ne compte que les fonds dont la date de valeur est déjà passée — ce qui est réellement disponible en banque.">
                    Réel disponible : <strong class="{{ $soldeNetReel >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($soldeNetReel, 0, ',', ' ') }} FCFA</strong>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-4">
            <div class="card mondays-card h-100 border-0 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Encaissements Totaux</span>
                    <span class="mondays-badge mondays-badge-success">Entrées</span>
                </div>
                <div class="mondays-metric-val text-success mb-1">{{ number_format($encaissementsTotal, 0, ',', ' ') }} <small class="fs-6 fw-normal text-muted">FCFA</small></div>
                <div class="text-muted small">Flux crédités.</div>
            </div>
        </div>
        <div class="col-6 col-xl-4">
            <div class="card mondays-card h-100 border-0 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Décaissements Totaux</span>
                    <span class="mondays-badge mondays-badge-warning">Sorties</span>
                </div>
                <div class="mondays-metric-val text-danger mb-1">{{ number_format($decaissementsTotal, 0, ',', ' ') }} <small class="fs-6 fw-normal text-muted">FCFA</small></div>
                <div class="text-muted small">Flux débités.</div>
            </div>
        </div>
    </div>
        <div class="col-6 col-xl-2">
            <div class="crypto-card">
                <div class="card-body">
                    <div class="crypto-label">Décaissements</div>
                    <div class="crypto-value">{{ number_format($decaissementsTotal, 0, ',', ' ') }}</div>
                    <div class="crypto-sub">Décaissements</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="crypto-card">
                <div class="card-body">
                    <div class="crypto-label">Planifié net</div>
                    <div class="crypto-value">{{ number_format($engagementsPlanifies, 0, ',', ' ') }}</div>
                    <div class="crypto-sub">Planifié net</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="crypto-card">
                <div class="card-body">
                    <div class="crypto-label">Clôture période</div>
                    <div class="crypto-value">{{ number_format($soldeCloturePeriode, 0, ',', ' ') }}</div>
                    <div class="crypto-sub">Clôture période</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-8">
            <div class="crypto-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Transactions</h5>
                        <small class="text-muted">View all</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover crypto-table mb-0">
                            <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Module</th>
                                <th>Description</th>
                                <th>Montant</th>
                                <th>Référence</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($transactions as $tx)
                                @php
                                    $statusClass = $tx->status === 'effectue'
                                        ? 'ok'
                                        : ($tx->status === 'annule' ? 'bad' : 'pending');
                                @endphp
                                <tr>
                                    <td class="small text-nowrap">{{ $tx->transaction_date->format('d/m/Y') }}</td>
                                    <td class="small">{{ ucfirst($tx->type) }}</td>
                                    <td class="small">
                                        @if($tx->payment_module === 'stripe')
                                            @if($tx->stripe_payment_channel === 'bank_debit')
                                                <span class="badge bg-info-subtle text-info">Stripe {{ strtoupper((string) ($tx->stripe_bank_scheme ?? 'SEPA')) }}</span>
                                            @else
                                                <span class="badge bg-dark-subtle text-dark">Stripe Carte</span>
                                            @endif
                                        @elseif($tx->payment_module === 'fedapay_mobile')
                                            <span class="badge bg-warning-subtle text-warning">FedaPay Mobile</span>
                                        @else
                                            <span class="badge bg-light text-muted">Non défini</span>
                                        @endif
                                    </td>
                                    <td class="small">{{ \Illuminate\Support\Str::limit($tx->description, 45) }}</td>
                                    <td class="small fw-semibold {{ $tx->type === 'encaissement' ? 'text-success' : 'text-danger' }}">
                                        {{ $tx->type === 'encaissement' ? '+' : '-' }}{{ number_format($tx->amount, 0, ',', ' ') }} FCFA
                                    </td>
                                    <td class="small text-break">
                                        {{ $tx->reference ?? '-' }}
                                        @if($tx->payment_module === 'stripe' && $tx->stripe_status)
                                            <div class="text-muted">Stripe: {{ $tx->stripe_status }}</div>
                                        @endif
                                        @if($tx->payment_module === 'stripe' && $tx->stripe_payout_id)
                                            <div class="text-muted">Payout: {{ $tx->stripe_payout_id }}</div>
                                        @endif
                                        @if($tx->payment_module === 'fedapay_mobile' && $tx->bank_reference)
                                            <div class="text-muted">FedaPay: {{ $tx->bank_reference }}</div>
                                        @endif
                                    </td>
                                    <td class="small"><span class="crypto-status {{ $statusClass }}">{{ strtoupper($tx->status) }}</span></td>
                                    <td class="small text-nowrap">
                                        @if($tx->payment_module === 'fedapay_mobile' && $tx->type === 'encaissement' && $tx->status !== 'annule')
                                            <a href="{{ route('treasury.fedapay.checkout.form', ['transaction' => $tx->id, 'country' => 'CIV']) }}" class="btn btn-sm btn-outline-success">Payer mobile</a>
                                        @endif
                                        <a href="{{ route('treasury.edit', $tx) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form action="{{ route('treasury.destroy', $tx) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Confirmer la suppression de cette transaction ?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Aucune transaction ne correspond aux critères.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($transactions->hasPages())
                        <div class="mt-3">{{ $transactions->links('pagination::simple-bootstrap-5') }}</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="crypto-card h-100">
                <div class="card-body">
                    <h5 class="mb-3">Filtres</h5>
                    <form method="GET" class="row g-2">
                        <div class="col-12">
                            <label class="form-label small">Type</label>
                            <select name="type" class="form-select form-select-sm">
                                <option value="all" {{ $type === 'all' ? 'selected' : '' }}>All</option>
                                <option value="encaissement" {{ $type === 'encaissement' ? 'selected' : '' }}>Encaissement</option>
                                <option value="decaissement" {{ $type === 'decaissement' ? 'selected' : '' }}>Décaissement</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All</option>
                                <option value="planifie" {{ $status === 'planifie' ? 'selected' : '' }}>Planifiée</option>
                                <option value="effectue" {{ $status === 'effectue' ? 'selected' : '' }}>Effectuée</option>
                                <option value="annule" {{ $status === 'annule' ? 'selected' : '' }}>Annulée</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Module paiement</label>
                            <select name="payment_module" class="form-select form-select-sm">
                                <option value="all" {{ ($paymentModule ?? 'all') === 'all' ? 'selected' : '' }}>Tous</option>
                                <option value="stripe" {{ ($paymentModule ?? 'all') === 'stripe' ? 'selected' : '' }}>Stripe</option>
                                <option value="fedapay_mobile" {{ ($paymentModule ?? 'all') === 'fedapay_mobile' ? 'selected' : '' }}>FedaPay Mobile</option>
                                <option value="none" {{ ($paymentModule ?? 'all') === 'none' ? 'selected' : '' }}>Non défini</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">From</label>
                            <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">To</label>
                            <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 d-grid mt-2">
                            <button class="btn btn-primary btn-sm" type="submit">Appliquer les filtres</button>
                        </div>
                    </form>
                    <hr>
                    <div class="small text-muted">
                        Solde d'ouverture: <strong>{{ number_format($soldeOuverturePeriode, 0, ',', ' ') }} FCFA</strong><br>
                        Solde de clôture: <strong>{{ number_format($soldeCloturePeriode, 0, ',', ' ') }} FCFA</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
@endsection
