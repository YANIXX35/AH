@extends('layouts.app')

@section('title', 'Administration plateforme | ' . config('app.name'))
@section('page_title', 'Administration')

@push('styles')
<style>
    .admin-kpi-card { transition: transform .15s ease, box-shadow .15s ease; }
    .admin-kpi-card:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.08) !important; }
    .admin-stat-trend { font-size: .75rem; }
    .admin-chart-wrap { position: relative; height: 260px; }
</style>
@endpush

@section('content')
<div class="mb-4">  
    <nav aria-label="Fil d’Ariane admin" class="mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">Administration</li>
            <li class="breadcrumb-item active" aria-current="page">Tableau de bord admin</li>
        </ol>
    </nav>
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3">
        <div>
            <h1 class="h3 mb-1"><strong>Tableau de bord</strong> administrateur</h1>
            <p class="text-muted mb-0">Pilotage transversal : comptes, activité métier et signaux support / financement.</p>
        </div>
        <div class="text-lg-end">
            <span class="badge bg-light text-dark border">Mis à jour {{ $generatedAt->format('d/m/Y à H:i') }}</span>
        </div>
    </div>
</div>

{{-- KPI principaux --}}
<div class="row g-3 mb-2">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm admin-kpi-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Comptes inscrits</p>
                        <p class="h3 mb-0 text-primary">{{ number_format($userCount, 0, ',', ' ') }}</p>
                        <p class="admin-stat-trend text-success mb-0 mt-1">
                            +{{ $usersNewLast7Days }} sur 7 j. · +{{ $usersNewLast30Days }} sur 30 j.
                        </p>
                    </div>
                    <span class="text-primary opacity-50"><i data-feather="users" style="width:28px;height:28px;"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm admin-kpi-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Abonnements Premium</p>
                        <p class="h3 mb-0 text-warning">{{ number_format($premiumCount, 0, ',', ' ') }}</p>
                        <p class="admin-stat-trend text-muted mb-0 mt-1">{{ $pctPremium }}&nbsp;% des comptes</p>
                    </div>
                    <span class="text-warning opacity-50"><i data-feather="star" style="width:28px;height:28px;"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm admin-kpi-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Écritures comptables</p>
                        <p class="h3 mb-0 text-success">{{ number_format($entriesCount, 0, ',', ' ') }}</p>
                        <p class="admin-stat-trend text-muted mb-0 mt-1">Tous espaces clients</p>
                    </div>
                    <span class="text-success opacity-50"><i data-feather="book" style="width:28px;height:28px;"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm admin-kpi-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Registre de commerce</p>
                        <p class="h3 mb-0 text-info">{{ number_format($withTradeRegister, 0, ',', ' ') }}</p>
                        <p class="admin-stat-trend text-muted mb-0 mt-1">{{ $pctTradeRegister }}&nbsp;% avec pièce jointe</p>
                    </div>
                    <span class="text-info opacity-50"><i data-feather="file-text" style="width:28px;height:28px;"></i></span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Activité & support --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <p class="text-muted small mb-0">Documents comptables</p>
                <p class="h4 mb-0">{{ number_format($documentsCount, 0, ',', ' ') }}</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <p class="text-muted small mb-0">Mouvements trésorerie</p>
                <p class="h4 mb-0">{{ number_format($treasuryCount, 0, ',', ' ') }}</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <p class="text-muted small mb-0">Tickets support (ouverts)</p>
                <p class="h4 mb-0">{{ $ticketsOpenCount }} <span class="text-muted fs-6">/ {{ $ticketsTotal }}</span></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <a href="{{ route('admin.investment-requests.index') }}" class="text-decoration-none text-reset">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <p class="text-muted small mb-0">Demandes investissement</p>
                    <p class="h4 mb-0">{{ number_format($investmentRequestsCount, 0, ',', ' ') }}</p>
                    @if(($investmentRequestsPendingCount ?? 0) > 0)
                        <p class="small mb-0 mt-1"><span class="badge bg-warning text-dark">{{ $investmentRequestsPendingCount }} en attente</span></p>
                    @endif
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0">Inscriptions (7 derniers jours)</h5>
                    <small class="text-muted">Nombre de comptes créés par jour</small>
                </div>
            </div>
            <div class="card-body">
                <div class="admin-chart-wrap">
                    <canvas id="adminRegistrationsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0">Qualité des dossiers</h5>
                <small class="text-muted">Taux de complétion des pièces clés</small>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2">Registre de commerce joint</p>
                <div class="progress mb-4" style="height: 10px;">
                    <div class="progress-bar bg-info" role="progressbar" style="width: {{ $pctTradeRegister }}%;" aria-valuenow="{{ $pctTradeRegister }}" aria-valuemin="0" aria-valuemax="100">{{ $pctTradeRegister }}&nbsp;%</div>
                </div>
                <p class="small text-muted mb-2">Comptes Premium</p>
                <div class="progress mb-4" style="height: 10px;">
                    <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $pctPremium }}%;" aria-valuenow="{{ $pctPremium }}" aria-valuemin="0" aria-valuemax="100">{{ $pctPremium }}&nbsp;%</div>
                </div>
                <div class="d-flex align-items-center gap-2 p-3 rounded bg-light border">
                    <i data-feather="shield" class="text-primary flex-shrink-0"></i>
                    <div>
                        <strong class="d-block">Administrateurs plateforme</strong>
                        <span class="text-muted small">{{ $platformAdminCount }} compte(s) avec accès /admin</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="card-title mb-0">Derniers comptes créés</h5>
                    <small class="text-muted">Accès rapide avant la liste complète</small>
                </div>
                <a href="{{ route('admin.users') }}" class="btn btn-sm btn-primary">Voir tout</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Entreprise / contact</th>
                                <th class="d-none d-md-table-cell">E-mail</th>
                                <th>Inscription</th>
                                <th class="text-end">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentUsers as $u)
                                <tr>
                                    <td>
                                        <div class="fw-medium">{{ $u->company_name ?? $u->name }}</div>
                                        @if($u->company_name)
                                            <small class="text-muted">{{ $u->name }}</small>
                                        @endif
                                    </td>
                                    <td class="d-none d-md-table-cell"><small>{{ $u->email }}</small></td>
                                    <td><small>{{ $u->created_at->format('d/m/Y H:i') }}</small></td>
                                    <td class="text-end">
                                        @if($u->is_platform_admin)
                                            <span class="badge bg-danger">Admin plateforme</span>
                                        @elseif($u->is_premium)
                                            <span class="badge bg-warning text-dark">Premium</span>
                                        @else
                                            <span class="badge bg-secondary">Gratuit</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Aucun compte pour l’instant.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm bg-primary text-white overflow-hidden">
    <div class="card-body py-4">
        <div class="row align-items-center">
            <div class="col-md-8 mb-3 mb-md-0">
                <h5 class="mb-1">Actions rapides</h5>
                <p class="mb-0 opacity-90 small">Annuaire complet des entreprises, retour au tableau de bord métier de votre session.</p>
            </div>
            <div class="col-md-4 text-md-end d-flex flex-wrap gap-2 justify-content-md-end">
                <a href="{{ route('admin.users') }}" class="btn btn-light">
                    <i data-feather="list" style="width:16px;height:16px;" class="me-1"></i> Utilisateurs &amp; entreprises
                </a>
                <a href="{{ route('admin.financial-analysis') }}" class="btn btn-outline-light">Analyse financière PME</a>
                <a href="{{ route('admin.financial-ranking') }}" class="btn btn-outline-light">Classement solvabilité</a>
                <a href="{{ route('admin.investment-requests.index') }}" class="btn btn-outline-light">Demandes d’investissement</a>
                <a href="{{ route('dashboard') }}" class="btn btn-outline-light">Tableau de bord métier</a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('adminRegistrationsChart');
    if (!el || typeof Chart === 'undefined') return;

    var labels = @json(collect($registrationSeries)->pluck('label'));
    var data = @json(collect($registrationSeries)->pluck('count'));

    new Chart(el.getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Nouveaux comptes',
                data: data,
                backgroundColor: 'rgba(13, 110, 253, 0.35)',
                borderColor: 'rgba(13, 110, 253, 1)',
                borderWidth: 1,
                borderRadius: 4,
                maxBarThickness: 36
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, precision: 0 }
                }
            }
        }
    });

    if (window.feather) {
        window.feather.replace();
    }
});
</script>
@endpush
