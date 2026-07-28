@extends('layouts.app')

@section('title', 'Administration plateforme | ' . config('app.name'))
@section('page_title', 'Administration')

@push('styles')
<style>
    .admin-kpi-card { transition: transform .15s ease, box-shadow .15s ease; border: 1px solid rgba(0,0,0,.06); }
    .admin-kpi-card:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.08) !important; }
    .admin-stat-trend { font-size: .78rem; }
    .admin-chart-wrap { position: relative; height: 260px; }
    .admin-hero {
        background: linear-gradient(135deg, #3b7ddd 0%, #285eb8 100%);
        color: #fff;
        border-radius: .5rem;
        box-shadow: 0 .25rem .75rem rgba(59, 125, 221, .25);
    }
    .admin-kpi-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(59, 125, 221, .1);
        color: #3b7ddd;
    }
    .admin-mini-card {
        border: 1px solid rgba(0,0,0,.06);
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .admin-mini-card:hover {
        border-color: rgba(59, 125, 221, .35);
        box-shadow: 0 .25rem .8rem rgba(0,0,0,.06);
    }
    .risk-cell {
        min-width: 54px;
        text-align: center;
        font-weight: 600;
        border-radius: .375rem;
        padding: .3rem .4rem;
    }
    .risk-cell-1 { background: rgba(25, 135, 84, .12); color: #198754; }
    .risk-cell-2 { background: rgba(32, 201, 151, .14); color: #0f766e; }
    .risk-cell-3 { background: rgba(255, 193, 7, .18); color: #8a6d00; }
    .risk-cell-4 { background: rgba(255, 145, 77, .2); color: #a14900; }
    .risk-cell-5 { background: rgba(220, 53, 69, .16); color: #b02a37; }
    .incident-item + .incident-item { border-top: 1px solid rgba(0,0,0,.06); }
    .admin-quick-pill {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        border-radius: 999px;
        font-size: .82rem;
        padding: .38rem .72rem;
        border: 1px solid rgba(0,0,0,.08);
        background: #fff;
        color: #495057;
        text-decoration: none;
        transition: all .15s ease;
    }
    .admin-quick-pill:hover {
        color: #1f2d3d;
        border-color: rgba(59, 125, 221, .45);
        box-shadow: 0 .25rem .6rem rgba(0,0,0,.06);
        transform: translateY(-1px);
    }
    .admin-quick-grid {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
    }
    .admin-live-dot {
        width: .55rem;
        height: .55rem;
        border-radius: 50%;
        display: inline-block;
        margin-right: .35rem;
    }
    .admin-alert-btn {
        display: inline-flex;
        align-items: center;
        justify-content: space-between;
        gap: .65rem;
        min-width: 280px;
        border-radius: .6rem;
        border: 1px solid rgba(0,0,0,.08);
        padding: .55rem .7rem;
        text-decoration: none;
        color: inherit;
        background: #fff;
        transition: all .15s ease;
    }
    .admin-alert-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 .35rem .9rem rgba(0,0,0,.06);
        border-color: rgba(59, 125, 221, .4);
        color: inherit;
    }
    .admin-alert-btn .count {
        min-width: 1.8rem;
        text-align: center;
        border-radius: 999px;
        font-weight: 700;
        font-size: .78rem;
        padding: .15rem .5rem;
    }
    .admin-alert-btn-danger .count { background: rgba(220,53,69,.14); color: #b02a37; }
    .admin-alert-btn-warning .count { background: rgba(255,193,7,.24); color: #8a6d00; }
    .admin-alert-btn-info .count { background: rgba(13,202,240,.18); color: #0f6f7c; }
    .live-refresh-indicator {
        font-size: .75rem;
        color: #6c757d;
    }
    .admin-ai-live-card {
        border: 1px solid rgba(59, 125, 221, .2);
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
    }
    .admin-ai-live-text {
        font-size: .92rem;
        line-height: 1.45;
        white-space: pre-wrap;
    }
    .admin-ai-inco-item + .admin-ai-inco-item {
        border-top: 1px solid rgba(0,0,0,.06);
    }

    /* Soft-UI & Glassmorphic Design System (Matching Mockup) */
    .soft-bg {
        background-color: #f1f5f9;
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        color: #1e293b;
    }
    .soft-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.03), 0 4px 12px rgba(15, 23, 42, 0.02);
        transition: all 0.25s ease;
    }
    .soft-card:hover {
        box-shadow: 0 16px 36px -4px rgba(15, 23, 42, 0.06);
        border-color: #cbd5e1;
    }
    .pill-header-bar {
        background: #ffffff;
        border-radius: 9999px;
        padding: 8px 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
    }
    .soft-pill-btn {
        border-radius: 9999px;
        font-weight: 600;
        font-size: 0.85rem;
        padding: 8px 20px;
        border: none;
        transition: all 0.2s ease;
    }
    .soft-pill-btn-active {
        background: #0f172a;
        color: #ffffff !important;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.25);
    }
    .soft-pill-btn-inactive {
        background: transparent;
        color: #64748b;
    }
    .pill-badge-emerald { background: #d1fae5; color: #047857; }
    .pill-badge-purple { background: #f3e8ff; color: #6b21a8; }
    .pill-badge-blue { background: #dbeafe; color: #1d4ed8; }
    .pill-badge-amber { background: #fef3c7; color: #b45309; }

    .ai-copilot-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        position: relative;
        overflow: hidden;
    }
    .copilot-orb {
        width: 80px;
        height: 80px;
        background: radial-gradient(circle at 30% 30%, #60a5fa, #2563eb 60%, #1e40af);
        border-radius: 50%;
        box-shadow: 0 12px 32px rgba(37, 99, 235, 0.35), inset -6px -6px 12px rgba(0, 0, 0, 0.2), inset 6px 6px 12px rgba(255, 255, 255, 0.6);
        animation: floatOrb 4s ease-in-out infinite alternate;
    }
</style>
@endpush

@section('content')
@php
    $openTicketRate = $ticketsTotal > 0 ? round(($ticketsOpenCount / $ticketsTotal) * 100, 1) : 0.0;
    $pendingInvestmentRate = $investmentRequestsCount > 0 ? round(($investmentRequestsPendingCount / $investmentRequestsCount) * 100, 1) : 0.0;
    $entriesPerUser = $userCount > 0 ? round($entriesCount / $userCount, 1) : 0.0;
    $docsPerUser = $userCount > 0 ? round($documentsCount / $userCount, 1) : 0.0;

    $healthScore = 100.0;
    $healthScore -= min(30.0, $openTicketRate * 0.35);
    $healthScore -= min(20.0, max(0, 55 - $pctTradeRegister) * 0.30);
    $healthScore -= min(20.0, max(0, 30 - $pctPremium) * 0.40);
    $healthScore -= min(15.0, $pendingInvestmentRate * 0.18);
    $healthScore = max(0.0, round($healthScore, 1));

    $healthState = 'Critique';
    $healthBadgeClass = 'bg-danger';
    if ($healthScore >= 80) {
        $healthState = 'Stable';
        $healthBadgeClass = 'bg-success';
    } elseif ($healthScore >= 60) {
        $healthState = 'Sous contrôle';
        $healthBadgeClass = 'bg-warning text-dark';
    }

    $priorityActions = collect([
        [
            'label' => 'Tickets support ouverts',
            'value' => $ticketsOpenCount,
            'context' => $ticketsTotal.' au total',
            'severity' => $openTicketRate >= 40 ? 'danger' : ($openTicketRate >= 20 ? 'warning' : 'success'),
        ],
        [
            'label' => 'Demandes d’investissement en attente',
            'value' => $investmentRequestsPendingCount,
            'context' => $investmentRequestsCount.' demandes',
            'severity' => $pendingInvestmentRate >= 45 ? 'danger' : ($pendingInvestmentRate >= 20 ? 'warning' : 'success'),
        ],
        [
            'label' => 'Comptes sans registre joint',
            'value' => max(0, $userCount - $withTradeRegister),
            'context' => $pctTradeRegister.'% conformes',
            'severity' => $pctTradeRegister < 50 ? 'danger' : ($pctTradeRegister < 75 ? 'warning' : 'success'),
        ],
    ]);

    $nextLicenseExpiryAt = !empty($licenseAlerts['next_expiry'] ?? null) ? \Carbon\Carbon::parse($licenseAlerts['next_expiry']) : null;
    $nextSubscriptionExpiryAt = !empty($subscriptionAlerts['next_expiry'] ?? null) ? \Carbon\Carbon::parse($subscriptionAlerts['next_expiry']) : null;
    $licenseCountdown = $nextLicenseExpiryAt ? (int) max(0, now()->diffInDays($nextLicenseExpiryAt, false)) : null;
    $subscriptionCountdown = $nextSubscriptionExpiryAt ? (int) max(0, now()->diffInDays($nextSubscriptionExpiryAt, false)) : null;
@endphp

<!-- Top Floating Pill Header Bar (Soft-UI Style) -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 pill-header-bar">
    <div class="d-flex align-items-center gap-2">
        <div class="bg-dark text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width:38px; height:38px;">
            <i data-feather="shield" style="width:18px; height:18px;"></i>
        </div>
        <div class="nav nav-pills border-0">
            <a href="{{ route('admin.dashboard') }}" class="soft-pill-btn soft-pill-btn-active me-1 text-decoration-none">
                <i data-feather="layout" class="me-1" style="width:14px; height:14px;"></i> Vue Générale
            </a>
            <a href="{{ route('admin.users.index') }}" class="soft-pill-btn soft-pill-btn-inactive me-1 text-decoration-none">
                <i data-feather="users" class="me-1" style="width:14px; height:14px;"></i> Utilisateurs ({{ $userCount }})
            </a>
            <a href="{{ route('admin.licenses.index') }}" class="soft-pill-btn soft-pill-btn-inactive me-1 text-decoration-none">
                <i data-feather="key" class="me-1" style="width:14px; height:14px;"></i> Licences
            </a>
        </div>
    </div>

    <div class="d-flex align-items-center gap-3">
        <span class="badge bg-light text-dark border rounded-pill px-3 py-2">
            <i data-feather="activity" class="me-1 text-success" style="width:14px; height:14px;"></i>
            Santé Plateforme: {{ $healthScore }}/100 ({{ $healthState }})
        </span>
        <a href="{{ route('admin.users.index') }}" class="btn btn-dark rounded-pill px-4 py-2 fw-bold text-sm shadow-sm">
            + Administrer Utilisateurs
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small text-uppercase mb-1">Charge support</p>
                <h2 class="h3 mb-1">{{ $openTicketRate }}%</h2>
                <p class="mb-0 small text-muted">{{ $ticketsOpenCount }} ticket(s) ouvert(s) sur {{ $ticketsTotal }}.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small text-uppercase mb-1">Densité comptable</p>
                <h2 class="h3 mb-1">{{ number_format($entriesPerUser, 1, ',', ' ') }}</h2>
                <p class="mb-0 small text-muted">Écritures moyennes par compte inscrit.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small text-uppercase mb-1">Maturité documentaire</p>
                <h2 class="h3 mb-1">{{ number_format($docsPerUser, 1, ',', ' ') }}</h2>
                <p class="mb-0 small text-muted">Documents comptables moyens par compte.</p>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3">
            <div>
                <h5 class="mb-1">Centre de commande</h5>
                <p class="text-muted small mb-0">Toutes les actions d’administration en un clic, avec indicateurs en direct.</p>
            </div>
            <div class="admin-quick-grid">
                <a class="admin-quick-pill" href="{{ route('admin.users') }}"><i data-feather="users" style="width:14px;height:14px;"></i> Utilisateurs <span class="badge bg-light text-dark">{{ number_format($userCount, 0, ',', ' ') }}</span></a>
                <a class="admin-quick-pill" href="{{ route('admin.licenses.index') }}"><i data-feather="key" style="width:14px;height:14px;"></i> Licences</a>
                <a class="admin-quick-pill" href="{{ route('admin.payments.index') }}"><i data-feather="credit-card" style="width:14px;height:14px;"></i> Paiements</a>
                <a class="admin-quick-pill" href="{{ route('admin.investment-requests.index') }}"><i data-feather="trending-up" style="width:14px;height:14px;"></i> Investissement <span class="badge bg-warning text-dark">{{ $investmentRequestsPendingCount }}</span></a>
                <a class="admin-quick-pill" href="{{ route('admin.financial-analysis') }}"><i data-feather="bar-chart-2" style="width:14px;height:14px;"></i> Analyse</a>
                <a class="admin-quick-pill" href="{{ route('admin.scoring-parameters.index') }}"><i data-feather="sliders" style="width:14px;height:14px;"></i> Scoring</a>
                <a class="admin-quick-pill" href="{{ route('admin.ops.index') }}"><i data-feather="cpu" style="width:14px;height:14px;"></i> Ops Center</a>
                <a class="admin-quick-pill" href="{{ route('admin.logs.index') }}"><i data-feather="activity" style="width:14px;height:14px;"></i> Logs <span class="badge bg-danger">{{ $menuErrors24h }}</span></a>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
            <div>
                <h6 class="mb-0">Alertes licences & abonnements</h6>
                <small class="text-muted">Décompte en cours, à surveiller et expirés</small>
            </div>
            <div class="small text-muted">
                @if($licenseCountdown !== null || $subscriptionCountdown !== null)
                    Prochaine échéance :
                    @if($licenseCountdown !== null)
                        licence dans <strong>{{ (int) $licenseCountdown }} j</strong>
                    @endif
                    @if($licenseCountdown !== null && $subscriptionCountdown !== null)
                        ·
                    @endif
                    @if($subscriptionCountdown !== null)
                        abonnement dans <strong>{{ (int) $subscriptionCountdown }} j</strong>
                    @endif
                @else
                    Aucune échéance datée à venir.
                @endif
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.licenses.index') }}" class="admin-alert-btn admin-alert-btn-info">
                <span><i data-feather="key" style="width:14px;height:14px;" class="me-1"></i> Licences actives</span>
                <span class="count">{{ (int) ($licenseAlerts['active'] ?? 0) }}</span>
            </a>
            <a href="{{ route('admin.licenses.index') }}" class="admin-alert-btn admin-alert-btn-warning">
                <span><i data-feather="clock" style="width:14px;height:14px;" class="me-1"></i> Licences expirent &lt; 7j</span>
                <span class="count">{{ (int) ($licenseAlerts['expiring_7'] ?? 0) }}</span>
            </a>
            <a href="{{ route('admin.licenses.index') }}" class="admin-alert-btn admin-alert-btn-danger">
                <span><i data-feather="alert-triangle" style="width:14px;height:14px;" class="me-1"></i> Licences expirées</span>
                <span class="count">{{ (int) ($licenseAlerts['expired'] ?? 0) }}</span>
            </a>

            <a href="{{ route('admin.payments.index') }}" class="admin-alert-btn admin-alert-btn-info">
                <span><i data-feather="star" style="width:14px;height:14px;" class="me-1"></i> Abonnements actifs</span>
                <span class="count">{{ (int) ($subscriptionAlerts['active'] ?? 0) }}</span>
            </a>
            <a href="{{ route('admin.payments.index') }}" class="admin-alert-btn admin-alert-btn-warning">
                <span><i data-feather="clock" style="width:14px;height:14px;" class="me-1"></i> Abonnements expirent &lt; 7j</span>
                <span class="count">{{ (int) ($subscriptionAlerts['expiring_7'] ?? 0) }}</span>
            </a>
            <a href="{{ route('admin.payments.index') }}" class="admin-alert-btn admin-alert-btn-danger">
                <span><i data-feather="alert-octagon" style="width:14px;height:14px;" class="me-1"></i> Abonnements expirés</span>
                <span class="count">{{ (int) ($subscriptionAlerts['expired'] ?? 0) }}</span>
            </a>
        </div>
    </div>
</div>

{{-- KPI principaux --}}
<div class="row g-3 mb-2">
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm admin-kpi-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Comptes inscrits</p>
                        <p class="h3 mb-0 text-primary">{{ number_format($userCount, 0, ',', ' ') }}</p>
                        <p class="admin-stat-trend text-success mb-0 mt-1">
                            +{{ $usersNewLast7Days }} sur 7 j. · +{{ $usersNewLast30Days }} sur 30 j.
                        </p>
                    </div>
                    <span class="admin-kpi-icon"><i data-feather="users" style="width:20px;height:20px;"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm admin-kpi-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Abonnements Premium</p>
                        <p class="h3 mb-0 text-warning">{{ number_format($premiumCount, 0, ',', ' ') }}</p>
                        <p class="admin-stat-trend text-muted mb-0 mt-1">{{ $pctPremium }}&nbsp;% des comptes</p>
                    </div>
                    <span class="admin-kpi-icon"><i data-feather="star" style="width:20px;height:20px;"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm admin-kpi-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Écritures comptables</p>
                        <p class="h3 mb-0 text-success">{{ number_format($entriesCount, 0, ',', ' ') }}</p>
                        <p class="admin-stat-trend text-muted mb-0 mt-1">Tous espaces clients</p>
                    </div>
                    <span class="admin-kpi-icon"><i data-feather="book" style="width:20px;height:20px;"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm admin-kpi-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Registre de commerce</p>
                        <p class="h3 mb-0 text-info">{{ number_format($withTradeRegister, 0, ',', ' ') }}</p>
                        <p class="admin-stat-trend text-muted mb-0 mt-1">{{ $pctTradeRegister }}&nbsp;% avec pièce jointe</p>
                    </div>
                    <span class="admin-kpi-icon"><i data-feather="file-text" style="width:20px;height:20px;"></i></span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Activité & support --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm admin-mini-card h-100">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <p class="text-muted small mb-0">Documents comptables</p>
                    <i data-feather="file" class="text-secondary" style="width:16px;height:16px;"></i>
                </div>
                <p class="h4 mb-0 fw-bold">{{ number_format($documentsCount, 0, ',', ' ') }}</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm admin-mini-card h-100">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <p class="text-muted small mb-0">Mouvements trésorerie</p>
                    <i data-feather="credit-card" class="text-secondary" style="width:16px;height:16px;"></i>
                </div>
                <p class="h4 mb-0 fw-bold">{{ number_format($treasuryCount, 0, ',', ' ') }}</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm admin-mini-card h-100">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <p class="text-muted small mb-0">Tickets support (ouverts)</p>
                    <i data-feather="help-circle" class="text-secondary" style="width:16px;height:16px;"></i>
                </div>
                <p class="h4 mb-0 fw-bold">{{ $ticketsOpenCount }} <span class="text-muted fs-6">/ {{ $ticketsTotal }}</span></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <a href="{{ route('admin.investment-requests.index') }}" class="text-decoration-none text-reset">
            <div class="card shadow-sm admin-mini-card h-100">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <p class="text-muted small mb-0">Demandes investissement</p>
                        <i data-feather="trending-up" class="text-secondary" style="width:16px;height:16px;"></i>
                    </div>
                    <p class="h4 mb-0 fw-bold">{{ number_format($investmentRequestsCount, 0, ',', ' ') }}</p>
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
                <h5 class="card-title mb-0">Qualité & priorités opérationnelles</h5>
                <small class="text-muted">Signaux conformité et backlog à traiter</small>
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
                <hr class="my-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">File de priorités</h6>
                    <span class="badge bg-light text-muted">IT Ops</span>
                </div>
                <div class="list-group list-group-flush">
                    @foreach($priorityActions as $action)
                        <div class="list-group-item px-0 d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-medium">{{ $action['label'] }}</div>
                                <small class="text-muted">{{ $action['context'] }}</small>
                            </div>
                            <span class="badge bg-{{ $action['severity'] }}">{{ $action['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" id="adminLiveNowCard">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0">Tout ce qui se passe maintenant</h5>
            <small class="text-muted">Vue synthétique des derniers événements opérationnels</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="live-refresh-indicator" id="adminLiveRefreshStatus">Auto-refresh: 30s</span>
            <a href="{{ route('admin.logs.index') }}" class="btn btn-sm btn-outline-secondary">Flux complet</a>
        </div>
    </div>
    <div class="card-body py-2" id="adminLiveNowBody">
        @forelse(collect($incidentTimeline ?? [])->take(6) as $event)
            @php
                $dotClass = ($event['severity'] ?? 'secondary') === 'danger'
                    ? 'bg-danger'
                    : (($event['severity'] ?? '') === 'warning'
                    
                        ? 'bg-warning'
                        : (($event['severity'] ?? '') === 'info' ? 'bg-info' : 'bg-secondary'));
            @endphp
            <div class="d-flex align-items-start justify-content-between py-2 border-bottom">
                <div class="pe-3">
                    <div class="small fw-medium">
                        <span class="admin-live-dot {{ $dotClass }}"></span>
                        {{ $event['title'] ?? 'Événement' }}
                    </div>
                    <div class="small text-muted">{{ $event['detail'] ?? '' }}</div>
                </div>
                <div class="text-end">
                    <div class="small text-muted">{{ isset($event['at']) && $event['at'] ? \Carbon\Carbon::parse($event['at'])->diffForHumans() : 'n/a' }}</div>
                    @if(!empty($event['url']))
                        <a href="{{ $event['url'] }}" class="small">ouvrir</a>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-muted my-3">Aucun événement récent.</p>
        @endforelse
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <div class="card admin-ai-live-card shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0">IA en temps réel - Propositions business</h5>
                    <small class="text-muted">Analyse comptabilité + trésorerie pour améliorer le chiffre d’affaires</small>
                </div>
                <span class="badge bg-primary">LIVE</span>
            </div>
            <div class="card-body">
                <div id="adminAiLiveText" class="admin-ai-live-text">{{ $aiLiveInsight ?? 'L’IA prépare une recommandation...' }}</div>
                <div class="small text-muted mt-2">Mise à jour automatique toutes les 30 secondes.</div>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0">Incohérences comptables & trésorerie</h5>
                <small class="text-muted">Détection automatique et suggestions de correction</small>
            </div>
            <div class="card-body py-1" id="adminAiIncoBody">
                @forelse(($aiInconsistencies ?? []) as $item)
                    <div class="admin-ai-inco-item py-3 d-flex justify-content-between gap-2">
                        <div>
                            <div class="fw-medium">{{ $item['title'] ?? 'Incohérence' }}</div>
                            <div class="small text-muted">{{ $item['detail'] ?? '' }}</div>
                            <div class="small mt-1"><strong>Proposition :</strong> {{ $item['proposal'] ?? '' }}</div>
                        </div>
                        <span class="badge bg-{{ $item['severity'] ?? 'secondary' }} align-self-start">{{ strtoupper((string) ($item['severity'] ?? 'n/a')) }}</span>
                    </div>
                @empty
                    <p class="text-muted my-3">Aucune incohérence détectée.</p>
                @endforelse
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

<div class="row g-4 mb-4">
    <div class="col-xl-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0">Heatmap des risques</h5>
                <small class="text-muted">Priorisation Probabilité × Impact (1 à 5)</small>
            </div>
            <div class="card-body" id="adminRiskHeatmapBody">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Domaine</th>
                                <th>Risque</th>
                                <th class="text-center">P</th>
                                <th class="text-center">I</th>
                                <th class="text-center">Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(($riskHeatmap ?? []) as $risk)
                                @php
                                    $p = (int) ($risk['probability'] ?? 1);
                                    $i = (int) ($risk['impact'] ?? 1);
                                    $score = (int) ($risk['score'] ?? ($p * $i));
                                @endphp
                                <tr>
                                    <td><strong>{{ $risk['domain'] ?? '—' }}</strong></td>
                                    <td>
                                        <div>{{ $risk['risk'] ?? '—' }}</div>
                                        <small class="text-muted">{{ $risk['detail'] ?? '' }}</small>
                                    </td>
                                    <td class="text-center"><span class="risk-cell risk-cell-{{ min(5, max(1, $p)) }}">{{ $p }}</span></td>
                                    <td class="text-center"><span class="risk-cell risk-cell-{{ min(5, max(1, $i)) }}">{{ $i }}</span></td>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $risk['severity'] ?? 'secondary' }}">{{ $score }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mt-3 mb-0">P = probabilité, I = impact. Score élevé = traitement prioritaire.</p>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0">Timeline d’incidents</h5>
                    <small class="text-muted">Signaux récents : support, financement, HTTP et trésorerie</small>
                </div>
                <a href="{{ route('admin.logs.index') }}" class="btn btn-sm btn-outline-primary">Voir les logs</a>
            </div>
            <div class="card-body py-2" id="adminIncidentTimelineBody">
                @forelse(($incidentTimeline ?? []) as $incident)
                    <div class="incident-item py-3 d-flex gap-3 align-items-start">
                        <span class="badge bg-{{ $incident['severity'] ?? 'secondary' }} mt-1">{{ strtoupper((string) ($incident['module'] ?? 'evt')) }}</span>
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <strong>{{ $incident['title'] ?? 'Événement' }}</strong>
                                <small class="text-muted">
                                    {{ isset($incident['at']) && $incident['at'] ? \Carbon\Carbon::parse($incident['at'])->format('d/m H:i') : 'n/a' }}
                                </small>
                            </div>
                            <p class="text-muted small mb-1">{{ $incident['detail'] ?? '' }}</p>
                            @if(!empty($incident['url']))
                                <a href="{{ $incident['url'] }}" class="small">Ouvrir</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-muted my-3">Aucun incident récent.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm overflow-hidden">
    <div class="card-header bg-white border-bottom">
        <h5 class="card-title mb-0">Actions rapides administrateur</h5>
    </div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-4">
                <a href="{{ route('admin.users') }}" class="btn btn-primary w-100 text-start">
                    <i data-feather="users" style="width:16px;height:16px;" class="me-1"></i> Utilisateurs &amp; entreprises
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('admin.financial-analysis') }}" class="btn btn-outline-primary w-100 text-start">Analyse financière PME</a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('admin.financial-ranking') }}" class="btn btn-outline-primary w-100 text-start">Classement solvabilité</a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('admin.scoring-parameters.index') }}" class="btn btn-outline-primary w-100 text-start">Paramètres scoring 360</a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('admin.investment-requests.index') }}" class="btn btn-outline-primary w-100 text-start">Demandes d’investissement</a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('admin.logs.index') }}" class="btn btn-outline-primary w-100 text-start">Journalisation plateforme</a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('dashboard') }}" class="btn btn-light border w-100 text-start">Tableau de bord métier</a>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0">Actions du jour (priorisation automatique)</h5>
            <small class="text-muted">Classées dynamiquement selon pression opérationnelle et risque</small>
        </div>
        <span class="badge bg-light text-dark">{{ count($actionsOfDay ?? []) }} action(s)</span>
    </div>
    <div class="card-body">
        @forelse(($actionsOfDay ?? []) as $index => $action)
            <div class="{{ $index > 0 ? 'pt-3 mt-3 border-top' : '' }}">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <strong>{{ $action['title'] ?? 'Action' }}</strong>
                        <p class="small text-muted mb-0">{{ $action['detail'] ?? '' }}</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        @php $prio = (int) ($action['priority'] ?? 0); @endphp
                        <span class="badge bg-{{ $prio >= 70 ? 'danger' : ($prio >= 40 ? 'warning text-dark' : 'info') }}">Priorité {{ $prio }}</span>
                        @if(!empty($action['route']))
                            <a href="{{ $action['route'] }}" class="btn btn-sm btn-outline-primary">Traiter</a>
                        @endif
                    </div>
                </div>
                <div class="progress mt-2" style="height: 8px;">
                    <div class="progress-bar bg-{{ $prio >= 70 ? 'danger' : ($prio >= 40 ? 'warning' : 'info') }}" role="progressbar" style="width: {{ min(100, max(0, $prio)) }}%;" aria-valuenow="{{ $prio }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        @empty
            <p class="text-muted mb-0">Aucune action prioritaire pour aujourd’hui.</p>
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('adminRegistrationsChart');
    if (el && typeof Chart !== 'undefined') {
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
    }

    var liveBody = document.getElementById('adminLiveNowBody');
    var riskHeatmapBody = document.getElementById('adminRiskHeatmapBody');
    var incidentTimelineBody = document.getElementById('adminIncidentTimelineBody');
    var liveStatus = document.getElementById('adminLiveRefreshStatus');
    var aiLiveText = document.getElementById('adminAiLiveText');
    var aiIncoBody = document.getElementById('adminAiIncoBody');
    var aiLiveEndpoint = @json(route('admin.dashboard.ai.live'));
    var refreshEveryMs = 30000;
    var countdown = 30;
    var isRefreshing = false;

    function renderRefreshStatus(extra) {
        if (!liveStatus) return;
        if (extra) {
            liveStatus.textContent = extra;
            return;
        }
        liveStatus.textContent = 'Auto-refresh: ' + countdown + 's';
    }

    function refreshLiveNowBlock() {
        if (!liveBody || isRefreshing) return;
        isRefreshing = true;
        renderRefreshStatus('Mise à jour...');

        fetch(window.location.href, { cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.text(); })
            .then(function (html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var newLiveBody = doc.getElementById('adminLiveNowBody');
                if (newLiveBody && liveBody) {
                    liveBody.innerHTML = newLiveBody.innerHTML;
                }

                var newRiskHeatmapBody = doc.getElementById('adminRiskHeatmapBody');
                if (newRiskHeatmapBody && riskHeatmapBody) {
                    riskHeatmapBody.innerHTML = newRiskHeatmapBody.innerHTML;
                }

                var newIncidentTimelineBody = doc.getElementById('adminIncidentTimelineBody');
                if (newIncidentTimelineBody && incidentTimelineBody) {
                    incidentTimelineBody.innerHTML = newIncidentTimelineBody.innerHTML;
                }

                if ((newLiveBody && liveBody) || (newRiskHeatmapBody && riskHeatmapBody) || (newIncidentTimelineBody && incidentTimelineBody)) {
                    if (window.feather) {
                        window.feather.replace();
                    }
                }
                countdown = 30;
                renderRefreshStatus();
            })
            .catch(function () {
                renderRefreshStatus('Auto-refresh: échec');
            })
            .finally(function () {
                isRefreshing = false;
            });
    }

    function severityBadgeClass(severity) {
        if (severity === 'danger') return 'danger';
        if (severity === 'warning') return 'warning';
        if (severity === 'success') return 'success';
        return 'secondary';
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function renderInconsistencies(items) {
        if (!aiIncoBody) return;
        if (!Array.isArray(items) || items.length === 0) {
            aiIncoBody.innerHTML = '<p class="text-muted my-3">Aucune incohérence détectée.</p>';
            return;
        }

        aiIncoBody.innerHTML = items.map(function (item) {
            var severity = String(item.severity || 'secondary');
            return '' +
                '<div class="admin-ai-inco-item py-3 d-flex justify-content-between gap-2">' +
                    '<div>' +
                        '<div class="fw-medium">' + escapeHtml(item.title || 'Incohérence') + '</div>' +
                        '<div class="small text-muted">' + escapeHtml(item.detail || '') + '</div>' +
                        '<div class="small mt-1"><strong>Proposition :</strong> ' + escapeHtml(item.proposal || '') + '</div>' +
                    '</div>' +
                    '<span class="badge bg-' + severityBadgeClass(severity) + ' align-self-start">' + escapeHtml(severity.toUpperCase()) + '</span>' +
                '</div>';
        }).join('');
    }

    function animateAiText(text) {
        if (!aiLiveText) return;
        var content = String(text || '');
        if (!content) {
            aiLiveText.textContent = '';
            return;
        }
        var index = 0;
        aiLiveText.textContent = '';
        var timer = window.setInterval(function () {
            index += 6;
            aiLiveText.textContent = content.slice(0, index);
            if (index >= content.length) {
                window.clearInterval(timer);
            }
        }, 14);
    }

    function refreshAiLivePanel() {
        if (!aiLiveEndpoint) return;
        fetch(aiLiveEndpoint, { cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (json) {
                if (!json || !json.ok) {
                    throw new Error('AI live unavailable');
                }
                animateAiText(json.live_insight || '');
                renderInconsistencies(json.inconsistencies || []);
            })
            .catch(function () {
                if (aiLiveText) {
                    aiLiveText.textContent = "L'IA live est momentanément indisponible.";
                }
            });
    }

    setInterval(function () {
        if (countdown <= 1) {
            refreshLiveNowBlock();
            refreshAiLivePanel();
            countdown = 30;
            return;
        }
        countdown -= 1;
        renderRefreshStatus();
    }, 1000);

    refreshAiLivePanel();

    if (window.feather) {
        window.feather.replace();
    }
});
</script>
@endpush
