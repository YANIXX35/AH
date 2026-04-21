@extends('layouts.app')

@section('title', 'Trésorerie | Sitiame Capitale')
@section('page_title', '💰 Module Trésorerie')

@push('styles')
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/treasury.js') }}" defer></script>
@endpush

@section('content')
<style>
    .treasury-dashboard {
        background: linear-gradient(180deg, #f5f7fb 0%, #edf2f8 100%);
        border-radius: 1rem;
        padding: 1rem;
    }
    .dashboard-hero {
        background: linear-gradient(120deg, #0f2747 0%, #19477d 100%);
        color: #fff;
        border-radius: 1rem;
        padding: 1.2rem 1.25rem;
        box-shadow: 0 10px 28px rgba(16, 24, 40, 0.18);
    }
    .hero-label {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: rgba(255, 255, 255, 0.78);
    }
    .hero-title {
        font-size: 1.2rem;
        font-weight: 700;
        margin: 0.4rem 0 0.25rem;
    }
    .hero-subtitle {
        margin: 0;
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.86);
    }
    .hero-pill {
        border: 1px solid rgba(255, 255, 255, 0.22);
        background: rgba(255, 255, 255, 0.1);
        border-radius: 999px;
        font-size: 0.75rem;
        padding: 0.28rem 0.7rem;
        color: #fff;
    }
    .tracking-card {
        border: 0;
        border-radius: 1rem;
        box-shadow: 0 10px 28px rgba(16, 24, 40, 0.08);
        overflow: hidden;
    }
    .tracking-card .card-header {
        background: #0f2747;
        color: #fff;
        border-bottom: 0;
    }
    .tracking-card .card-title {
        color: #fff;
        margin: 0;
        font-weight: 600;
    }
    .crypto-kpi {
        border: 0;
        border-radius: 1rem;
        box-shadow: 0 8px 24px rgba(16, 24, 40, 0.08);
        overflow: hidden;
        height: 100%;
    }
    .crypto-kpi .card-body {
        padding: 1rem;
    }
    .crypto-kpi .kpi-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6c757d;
    }
    .crypto-kpi .kpi-value {
        font-weight: 700;
        font-size: 1.3rem;
        margin-top: 0.2rem;
    }
    .kpi-note {
        margin-top: 0.25rem;
        font-size: 0.78rem;
        color: #667085;
    }
    .kpi-encaissement { border-left: 4px solid #22c55e; }
    .kpi-decaissement { border-left: 4px solid #ef4444; }
    .kpi-solde { border-left: 4px solid #3b82f6; }
    .kpi-projection { border-left: 4px solid #f59e0b; }
    .kpi-performance { border-left: 4px solid #111827; }
    .chart-container {
        position: relative;
        height: 300px;
        margin: 0.25rem 0 0.4rem;
    }
    .legend-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.6rem;
    }
    .legend-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        background: #f8fbff;
        border: 1px solid #dbe3ef;
        color: #344054;
        border-radius: 999px;
        padding: 0.22rem 0.65rem;
        font-size: 0.76rem;
        font-weight: 600;
    }
    .legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
    }
    .nav-card {
        border: 1px solid #e4e9f2;
        border-radius: 1rem;
        transition: all 0.2s ease;
        background: #fff;
        height: 100%;
    }
    .nav-card:hover {
        border-color: #c8d6ea;
        transform: translateY(-3px);
        box-shadow: 0 10px 22px rgba(16, 24, 40, 0.08);
    }
    .nav-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }
    .nav-icon-blue { background: rgba(59, 130, 246, 0.12); color: #2563eb; }
    .nav-icon-green { background: rgba(34, 197, 94, 0.14); color: #16a34a; }
    .nav-icon-amber { background: rgba(245, 158, 11, 0.14); color: #d97706; }
    .nav-icon-cyan { background: rgba(6, 182, 212, 0.14); color: #0891b2; }
    .treasury-recent-table thead th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #344054;
        background: #f8fbff;
        border-bottom: 1px solid #dbe3ef;
    }
    .treasury-recent-table tbody tr:hover {
        background: #f5f9ff;
    }
    .status-badge {
        border-radius: 999px;
        padding: 0.24rem 0.55rem;
        font-size: 0.72rem;
        font-weight: 600;
    }
    .table-actions {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }
    .icon-btn {
        width: 30px;
        height: 30px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
    }
    @media (max-width: 991.98px) {
        .dashboard-hero {
            padding: 1rem;
        }
    }
    @media (max-width: 767.98px) {
        .treasury-recent-table thead {
            display: none;
        }
        .treasury-recent-table tbody,
        .treasury-recent-table tr,
        .treasury-recent-table td {
            display: block;
            width: 100%;
        }
        .treasury-recent-table tbody tr {
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
            background: #fff;
            margin: 0.75rem;
            overflow: hidden;
        }
        .treasury-recent-table tbody td {
            border-bottom: 1px solid #f1f3f5;
            padding: 0.55rem 0.75rem;
            text-align: left !important;
        }
        .treasury-recent-table tbody td:last-child {
            border-bottom: 0;
        }
        .treasury-recent-table tbody td[data-label]::before {
            content: attr(data-label);
            display: block;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.35px;
            color: #6c757d;
            margin-bottom: 0.2rem;
        }
    }
</style>

<div class="page-wrapper">
    <div class="container-xl">
        <div class="treasury-dashboard">
            @php
                $totalEncaissements = $totalEncaissements ?? 0;
                $totalDecaissements = $totalDecaissements ?? 0;
                $solde = $solde ?? 0;
                $soldeProjecte = $soldeProjecte ?? 0;
                $monthEncaissements = $monthEncaissements ?? 0;
                $monthDecaissements = $monthDecaissements ?? 0;
                $monthNet = ($monthEncaissements ?? 0) - ($monthDecaissements ?? 0);
                $soldeDelta = ($soldeProjecte ?? 0) - ($solde ?? 0);
                $performancePct = ($monthEncaissements ?? 0) > 0
                    ? round(($monthNet / max(1, $monthEncaissements)) * 100, 1)
                    : 0;
                $topCategories = $topCategories ?? [];
            @endphp

            <div class="dashboard-hero mb-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div>
                        <span class="hero-label"><i class="fas fa-shield-alt"></i> Pilotage trésorerie</span>
                        <h2 class="hero-title">Tableau de bord financier</h2>
                        <p class="hero-subtitle">Vue consolidée des flux, de la performance du mois et des actions prioritaires.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="hero-pill">Dernière mise à jour: {{ now()->format('d/m/Y H:i') }}</span>
                        <a href="{{ route('treasury.create') }}" class="btn btn-sm btn-light">
                            <i class="fas fa-plus me-1"></i>Nouvelle transaction
                        </a>
                    </div>
                </div>
            </div>

            @include('treasury._partials._quick_actions')
            @include('treasury._partials._filters')

            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6 col-xl-2">
                    <div class="card crypto-kpi kpi-encaissement">
                        <div class="card-body">
                            <small class="kpi-label d-block">Encaissements</small>
                            <div class="kpi-value text-success">{{ number_format($totalEncaissements, 0, ',', ' ') }} FCFA</div>
                            <div class="kpi-note">Flux entrants réalisés</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <div class="card crypto-kpi kpi-decaissement">
                        <div class="card-body">
                            <small class="kpi-label d-block">Décaissements</small>
                            <div class="kpi-value text-danger">{{ number_format($totalDecaissements, 0, ',', ' ') }} FCFA</div>
                            <div class="kpi-note">Sorties de trésorerie</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <div class="card crypto-kpi kpi-solde">
                        <div class="card-body">
                            <small class="kpi-label d-block">Solde actuel</small>
                            <div class="kpi-value {{ $solde >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($solde, 0, ',', ' ') }} FCFA
                            </div>
                            <div class="kpi-note">{{ $solde >= 0 ? 'Position de trésorerie saine' : 'Position sous tension' }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <div class="card crypto-kpi kpi-projection">
                        <div class="card-body">
                            <small class="kpi-label d-block">Solde projeté</small>
                            <div class="kpi-value {{ $soldeProjecte >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($soldeProjecte, 0, ',', ' ') }} FCFA
                            </div>
                            <div class="kpi-note">Projection court terme</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <div class="card crypto-kpi kpi-performance">
                        <div class="card-body">
                            <small class="kpi-label d-block">Net du mois</small>
                            <div class="kpi-value {{ $monthNet >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($monthNet, 0, ',', ' ') }} FCFA
                            </div>
                            <div class="kpi-note">Encaissements - décaissements</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <div class="card crypto-kpi {{ $soldeDelta >= 0 ? 'kpi-encaissement' : 'kpi-decaissement' }}">
                        <div class="card-body">
                            <small class="kpi-label d-block">Variation projetée</small>
                            <div class="kpi-value {{ $soldeDelta >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $soldeDelta >= 0 ? '+' : '-' }}{{ number_format(abs($soldeDelta), 0, ',', ' ') }} FCFA
                            </div>
                            <div class="kpi-note">Écart solde projeté / actuel</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-12 col-lg-8">
                    <div class="card tracking-card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title">Analyse des flux</h3>
                            <span class="badge bg-light text-dark">Mensuel</span>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="mainTreasuryChart"></canvas>
                            </div>
                            <div class="legend-list">
                                <span class="legend-chip"><span class="legend-dot" style="background:#22c55e;"></span> Encaissements</span>
                                <span class="legend-chip"><span class="legend-dot" style="background:#ef4444;"></span> Décaissements</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="card tracking-card h-100">
                        <div class="card-header">
                            <h3 class="card-title">Performance & structure</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 230px;">
                                <canvas id="categoryChart"></canvas>
                            </div>
                            <div class="mt-3">
                                <div class="d-flex justify-content-between py-1 border-bottom">
                                    <span class="text-muted">Taux de performance mensuelle</span>
                                    <strong class="{{ $performancePct >= 0 ? 'text-success' : 'text-danger' }}">{{ $performancePct }} %</strong>
                                </div>
                                <div class="d-flex justify-content-between py-1 border-bottom">
                                    <span class="text-muted">Encaissements du mois</span>
                                    <strong>{{ number_format($monthEncaissements, 0, ',', ' ') }} FCFA</strong>
                                </div>
                                <div class="d-flex justify-content-between py-1">
                                    <span class="text-muted">Décaissements du mois</span>
                                    <strong>{{ number_format($monthDecaissements, 0, ',', ' ') }} FCFA</strong>
                                </div>
                            </div>
                            @if(!empty($topCategories))
                                <hr>
                                <small class="text-muted d-block mb-2">Top catégories</small>
                                @foreach($topCategories as $category)
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="badge" style="background-color: {{ ($category['color'] ?? '#3b82f6') }}20; color: {{ $category['color'] ?? '#3b82f6' }}">
                                            {{ $category['name'] ?? 'Catégorie' }}
                                        </span>
                                        <strong>{{ number_format($category['amount'] ?? 0, 0, ',', ' ') }} FCFA</strong>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-12">
                    <h3 class="mb-2">Navigation métier</h3>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <a href="{{ route('treasury.tracking') }}" class="text-decoration-none">
                        <div class="nav-card p-3">
                            <div class="nav-icon nav-icon-blue mb-3"><i class="fas fa-chart-line"></i></div>
                            <h5 class="text-dark mb-1">Suivi des flux</h5>
                            <p class="text-muted small mb-2">Pilotage quotidien des encaissements et décaissements.</p>
                            <span class="badge bg-primary">Accéder</span>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <a href="{{ route('treasury.balance') }}" class="text-decoration-none">
                        <div class="nav-card p-3">
                            <div class="nav-icon nav-icon-green mb-3"><i class="fas fa-balance-scale"></i></div>
                            <h5 class="text-dark mb-1">Bilan trésorerie</h5>
                            <p class="text-muted small mb-2">Analyse des soldes et mouvements nets par période.</p>
                            <span class="badge bg-success">Accéder</span>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <a href="{{ route('treasury.forecast') }}" class="text-decoration-none">
                        <div class="nav-card p-3">
                            <div class="nav-icon nav-icon-amber mb-3"><i class="fas fa-wand-magic-sparkles"></i></div>
                            <h5 class="text-dark mb-1">Prévisions</h5>
                            <p class="text-muted small mb-2">Projection de la position de trésorerie à court terme.</p>
                            <span class="badge bg-warning text-dark">Accéder</span>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <a href="{{ route('treasury.create') }}" class="text-decoration-none">
                        <div class="nav-card p-3">
                            <div class="nav-icon nav-icon-cyan mb-3"><i class="fas fa-plus"></i></div>
                            <h5 class="text-dark mb-1">Nouvelle transaction</h5>
                            <p class="text-muted small mb-2">Saisie rapide et fiabilisée des opérations.</p>
                            <span class="badge bg-info text-dark">Créer</span>
                        </div>
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card tracking-card">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h3 class="card-title">Dernières transactions</h3>
                            <a href="{{ route('treasury.tracking') }}" class="btn btn-sm btn-outline-light">Voir tout</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover card-table table-vcenter treasury-recent-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th class="text-end">Montant</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($transactions as $tx)
                                        <tr>
                                            <td data-label="Date">
                                                {{ !empty($tx->transaction_date) ? \Illuminate\Support\Carbon::parse($tx->transaction_date)->format('d/m/Y') : '-' }}
                                            </td>
                                            <td data-label="Type">
                                                <span class="status-badge badge {{ $tx->type === 'encaissement' ? 'bg-success' : 'bg-danger' }}">
                                                    {{ ucfirst($tx->type) }}
                                                </span>
                                            </td>
                                            <td data-label="Description">{{ Str::limit((string) ($tx->description ?? ''), 38) }}</td>
                                            <td data-label="Montant" class="text-end">
                                                <strong class="{{ $tx->type === 'encaissement' ? 'text-success' : 'text-danger' }}">
                                                    {{ $tx->type === 'encaissement' ? '+' : '-' }} {{ number_format($tx->amount, 0, ',', ' ') }} FCFA
                                                </strong>
                                            </td>
                                            <td data-label="Statut">
                                                <span class="status-badge badge bg-{{ $tx->status === 'effectue' ? 'success' : ($tx->status === 'planifie' ? 'warning' : 'secondary') }}">
                                                    {{ ucfirst($tx->status) }}
                                                </span>
                                            </td>
                                            <td data-label="Actions">
                                                <div class="table-actions">
                                                    <a href="{{ route('treasury.edit', $tx) }}" class="btn btn-sm btn-outline-primary icon-btn" title="Modifier">
                                                        <i class="fas fa-pen-to-square"></i>
                                                    </a>
                                                    <form action="{{ route('treasury.destroy', $tx) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger icon-btn" title="Supprimer" onclick="return confirm('Confirmer la suppression de cette transaction ?');">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                Aucune transaction disponible pour le moment.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if($transactions->hasPages())
                            <div class="card-footer d-flex align-items-center">
                                {{ $transactions->links('pagination::simple-bootstrap-5') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
</div>

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const chartColors = {
            primary: '#2563eb',
            success: '#22c55e',
            danger: '#ef4444',
            warning: '#f59e0b',
            info: '#06b6d4',
            secondary: '#6b7280'
        };

        const formatMoney = (value) => new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'XOF',
            minimumFractionDigits: 0
        }).format(value);

        const flowCanvas = document.getElementById('mainTreasuryChart');
        if (flowCanvas) {
            const flowCtx = flowCanvas.getContext('2d');
            new Chart(flowCtx, {
                type: 'line',
                data: {
                    labels: @json($chartLabels ?? []),
                    datasets: [{
                        label: 'Encaissements',
                        data: @json($encaissementsData ?? []),
                        borderColor: chartColors.success,
                        backgroundColor: 'rgba(34, 197, 94, 0.12)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3
                    }, {
                        label: 'Décaissements',
                        data: @json($decaissementsData ?? []),
                        borderColor: chartColors.danger,
                        backgroundColor: 'rgba(239, 68, 68, 0.12)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => `${context.dataset.label}: ${formatMoney(context.parsed.y)}`
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(15, 39, 71, 0.08)' },
                            ticks: { callback: (value) => formatMoney(value) }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        const categoryCanvas = document.getElementById('categoryChart');
        if (categoryCanvas) {
            const categoryCtx = categoryCanvas.getContext('2d');
            const labels = @json($categoryLabels ?? []);
            const values = @json($categoryData ?? []);
            new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: labels.length ? labels : ['Aucune donnée'],
                    datasets: [{
                        data: values.length ? values : [1],
                        backgroundColor: values.length ? [
                            chartColors.success,
                            chartColors.danger,
                            chartColors.warning,
                            chartColors.info,
                            chartColors.secondary,
                            '#8b5cf6',
                            '#ec4899'
                        ] : ['#d1d5db'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => values.length
                                    ? `${context.label}: ${formatMoney(context.parsed)}`
                                    : 'Aucune donnée exploitable'
                            }
                        }
                    }
                }
            });
        }

        window.addEventListener('load', function() {
            document.querySelectorAll('.crypto-kpi').forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.6s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 100);
            });
        });
    </script>
@endpush
@endsection
