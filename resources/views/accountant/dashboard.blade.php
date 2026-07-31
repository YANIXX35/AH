@extends('layouts.app')

@section('title', 'Cabinet comptable | Sitiame Capitale')
@section('page_title', 'Cabinet comptable')

@push('styles')
<style>
    /* Mondays Design System - Cabinet Comptable */
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
    .mondays-hero-date {
        font-size: 0.85rem;
        font-weight: 500;
        color: #64748b;
    }
    .mondays-hero-title {
        font-size: 1.85rem;
        font-weight: 700;
        color: #0f172a;
        margin-top: 2px;
        margin-bottom: 12px;
    }
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
    .mondays-pill-item {
        font-size: 0.84rem;
        font-weight: 600;
        color: #334155;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .mondays-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .mondays-badge-success { background: #dcfce7; color: #15803d; }
    .mondays-badge-pending { background: #f3e8ff; color: #7e22ce; }
    .mondays-badge-info { background: #dbeafe; color: #1d4ed8; }
    .mondays-badge-warning { background: #ffedd5; color: #c2410c; }
    .mondays-metric-val {
        font-size: 1.65rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.2;
    }
    .accountant-ai-live-text { font-size: .92rem; line-height: 1.45; white-space: pre-wrap; }
    .accountant-ai-inco-item + .accountant-ai-inco-item { border-top: 1px solid #f1f5f9; }
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
                    Cabinet Comptable — {{ explode(' ', auth()->user()?->name ?? 'Expert')[0] }} 👋
                </h1>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button" class="btn btn-sm btn-success rounded-pill px-3 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#smartImportAccountantClientModal">
                    <i data-feather="upload-cloud" class="me-1" style="width:14px; height:14px;"></i> Importer &amp; Lire Fichier
                </button>
                <a href="{{ route('accountant.commercials.index') }}" class="btn btn-sm btn-info text-white rounded-pill px-3 fw-semibold shadow-sm">
                    <i data-feather="bar-chart-2" class="me-1" style="width:14px; height:14px;"></i> Suivi Commerciaux
                </a>
                <a href="{{ route('accountant.clients.index', ['action' => 'add-client']) }}" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold">
                    <i data-feather="plus-circle" class="me-1" style="width:14px; height:14px;"></i> Ajouter Client / Entreprise
                </a>
                <a href="{{ route('accountant.clients.index') }}" class="btn btn-sm btn-light border rounded-pill px-3 fw-semibold text-dark">
                    <i data-feather="users" class="me-1" style="width:14px; height:14px;"></i> Dossiers Clients
                </a>
                <a href="{{ route('accounting.report') }}" class="btn btn-sm btn-light border rounded-pill px-3 fw-semibold text-dark">
                    <i data-feather="file-text" class="me-1" style="width:14px; height:14px;"></i> Rapports
                </a>
            </div>
        </div>

        <!-- BARRE DE PILULES KPI EN-TÊTE -->
        <div class="mondays-pill-bar">
            <div class="mondays-pill-item">
                <span class="text-primary">🏢</span> <strong>Portefeuille :</strong> {{ number_format($clientCount) }} dossiers clients
            </div>
            <div class="mondays-pill-item text-muted">|</div>
            <div class="mondays-pill-item">
                <span class="text-success">📑</span> <strong>Grand Livre :</strong> {{ number_format($entriesTotal) }} écritures
            </div>
            <div class="mondays-pill-item text-muted">|</div>
            <div class="mondays-pill-item">
                <span class="text-warning">⌛</span> <strong>À traiter :</strong> {{ number_format($documentsPending) }} pièces
            </div>
        </div>
    </div>

    <!-- METRICS CARDS GRID -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card mondays-card h-100 border-0 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Dossiers Clients</span>
                    <span class="mondays-badge mondays-badge-info">Actifs</span>
                </div>
                <div class="mondays-metric-val text-primary mb-1">{{ number_format($clientCount) }}</div>
                <div class="text-muted small">Comptes entreprise suivis.</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card mondays-card h-100 border-0 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Écritures Totales</span>
                    <span class="mondays-badge mondays-badge-success">Agrégé</span>
                </div>
                <div class="mondays-metric-val text-success mb-1">{{ number_format($entriesTotal) }}</div>
                <div class="text-muted small">Volume d'écritures saisies.</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card mondays-card h-100 border-0 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Documents à Traiter</span>
                    <span class="mondays-badge mondays-badge-warning">{{ number_format($documentsPending) }} en attente</span>
                </div>
                <div class="mondays-metric-val text-warning mb-1">{{ number_format($documentsPending) }}</div>
                <div class="text-muted small">Pièces OCR à corriger.</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card mondays-card h-100 border-0 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Alertes OCR « Stress »</span>
                    <span class="mondays-badge mondays-badge-warning">Attention</span>
                </div>
                <div class="mondays-metric-val text-danger mb-1">{{ number_format($ocrStressEntries) }}</div>
                <div class="text-muted small">Saisies ou rapprochements requis.</div>
            </div>
        </div>
    </div>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-7 d-flex">
        <div class="card accountant-ai-live-card shadow-sm w-100">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0">IA en temps reel - recommandations cabinet</h5>
                    <small class="text-muted">Analyse comptabilite et tresorerie des dossiers clients</small>
                </div>
                <div class="text-end">
                    <span class="badge bg-primary">LIVE</span>
                    <div id="accountantAiRefreshStatus" class="accountant-ai-refresh-status mt-1">Auto-refresh: 30s</div>
                </div>
            </div>
            <div class="card-body">
                <div id="accountantAiLiveText" class="accountant-ai-live-text">{{ $aiLiveInsight ?? "L'IA prepare une recommandation..." }}</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5 d-flex">
        <div class="card shadow-sm w-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0">Incoherences detectees</h5>
                <small class="text-muted">Portefeuille comptable</small>
            </div>
            <div class="card-body py-1" id="accountantAiIncoBody">
                @forelse(($aiInconsistencies ?? []) as $item)
                    <div class="accountant-ai-inco-item py-3 d-flex justify-content-between gap-2">
                        <div>
                            <div class="fw-medium">{{ $item['title'] ?? 'Incoherence' }}</div>
                            <div class="small text-muted">{{ $item['detail'] ?? '' }}</div>
                            <div class="small mt-1"><strong>Proposition :</strong> {{ $item['proposal'] ?? '' }}</div>
                        </div>
                        <span class="badge bg-{{ $item['severity'] ?? 'secondary' }} align-self-start">{{ strtoupper((string) ($item['severity'] ?? 'n/a')) }}</span>
                    </div>
                @empty
                    <p class="text-muted my-3">Aucune incoherence detectee.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Hub Comptabilité : même structure que le menu latéral, accès métier une fois un dossier ouvert. --}}
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm overflow-hidden accountant-compta-hub">
            <div class="card-header accountant-compta-hub__header d-flex flex-wrap align-items-center justify-content-between gap-2 py-3">
                <div class="d-flex align-items-center gap-2 text-white">
                    <span class="accountant-compta-hub__icon" aria-hidden="true">📚</span>
                    <div>
                        <div class="fw-semibold">Comptabilité</div>
                        <div class="small opacity-75">Moteur comptable et saisie — raccourcis professionnels</div>
                    </div>
                </div>
                @if($accountingWorkspaceOpen)
                    <span class="badge bg-light text-dark">Dossier : {{ \Illuminate\Support\Str::limit($accountingWorkspaceLabel, 42) }}</span>
                @else
                    <span class="badge bg-warning text-dark">Ouvrez un dossier pour activer les liens</span>
                @endif
            </div>
            <div class="card-body bg-light accountant-compta-hub__body">
                <div class="row g-4">
                    <div class="col-lg-4">
                        <div class="accountant-compta-hub__section-title">Saisie des données</div>
                        <ul class="list-unstyled mb-0 accountant-compta-hub__list">
                            <li>
                                <a href="{{ route('accounting') }}" class="accountant-compta-hub__link"><span class="me-2">✍️</span>Gestion des écritures</a>
                            </li>
                            <li>
                                <a href="{{ route('accounting.documents') }}" class="accountant-compta-hub__link"><span class="me-2">📄</span>Gestion des documents</a>
                            </li>
                            <li>
                                <a href="{{ route('accounting.plan') }}" class="accountant-compta-hub__link"><span class="me-2">📋</span>Plan comptable OHADA</a>
                            </li>
                        </ul>
                    </div>
                    <div class="col-lg-8">
                        <div class="accountant-compta-hub__section-title">Moteur comptable</div>
                        <div class="row g-2">
                            <div class="col-md-6 col-xl-4">
                                <a href="{{ route('accounting') }}#moteur-ecritures" class="accountant-compta-hub__tile">
                                    <span class="accountant-compta-hub__tile-icon">⚡</span>
                                    <span class="accountant-compta-hub__tile-text">Génération d’écritures</span>
                                    <span class="accountant-compta-hub__tile-hint">Saisie &amp; pièces</span>
                                </a>
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <a href="{{ route('accounting.report.journal') }}" class="accountant-compta-hub__tile">
                                    <span class="accountant-compta-hub__tile-icon">📖</span>
                                    <span class="accountant-compta-hub__tile-text">Journal</span>
                                    <span class="accountant-compta-hub__tile-hint">Chronologique</span>
                                </a>
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <a href="{{ route('accounting.report.grand-livre') }}" class="accountant-compta-hub__tile">
                                    <span class="accountant-compta-hub__tile-icon">📑</span>
                                    <span class="accountant-compta-hub__tile-text">Grand livre</span>
                                    <span class="accountant-compta-hub__tile-hint">Par compte</span>
                                </a>
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <a href="{{ route('accounting.report.balance') }}" class="accountant-compta-hub__tile">
                                    <span class="accountant-compta-hub__tile-icon">⚖️</span>
                                    <span class="accountant-compta-hub__tile-text">Balance</span>
                                    <span class="accountant-compta-hub__tile-hint">Équilibre</span>
                                </a>
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <a href="{{ route('accounting.bank-reconciliation') }}" class="accountant-compta-hub__tile">
                                    <span class="accountant-compta-hub__tile-icon">🏦</span>
                                    <span class="accountant-compta-hub__tile-text">Rapprochement bancaire</span>
                                    <span class="accountant-compta-hub__tile-hint">Trésorerie vs classe 5</span>
                                </a>
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <a href="{{ route('accounting.monthly-closing') }}" class="accountant-compta-hub__tile">
                                    <span class="accountant-compta-hub__tile-icon">📅</span>
                                    <span class="accountant-compta-hub__tile-text">Clôture mensuelle</span>
                                    <span class="accountant-compta-hub__tile-hint">Contrôles &amp; repère</span>
                                </a>
                            </div>
                        </div>
                        <p class="small text-muted mb-0 mt-3">
                            Les écrans comptables s’appliquent au <strong>dossier ouvert en session</strong> (menu latéral ou fiche client → « Ouvrir — Comptabilité »).
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .accountant-compta-hub__header {
        background: linear-gradient(135deg, #1e2a3a 0%, #2c3e50 100%);
        border-bottom: 1px solid rgba(255,255,255,.08);
    }
    .accountant-compta-hub__icon { font-size: 1.35rem; line-height: 1; }
    .accountant-compta-hub__section-title {
        font-size: 0.7rem;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #6c757d;
        font-weight: 600;
        margin-bottom: 0.75rem;
        padding-bottom: 0.35rem;
        border-bottom: 1px solid #dee2e6;
    }
    .accountant-compta-hub__list li + li { margin-top: 0.35rem; }
    .accountant-compta-hub__link {
        display: flex;
        align-items: center;
        padding: 0.45rem 0.65rem;
        border-radius: 0.375rem;
        color: #212529;
        text-decoration: none;
        font-weight: 500;
        transition: background .15s ease, color .15s ease;
    }
    .accountant-compta-hub__link:hover {
        background: #fff;
        color: #0d6efd;
        box-shadow: 0 1px 3px rgba(0,0,0,.06);
    }
    .accountant-compta-hub__tile {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        height: 100%;
        min-height: 5.5rem;
        padding: 0.75rem 0.85rem;
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 0.5rem;
        text-decoration: none;
        color: #212529;
        transition: border-color .15s ease, box-shadow .15s ease, transform .12s ease;
    }
    .accountant-compta-hub__tile:hover {
        border-color: #0d6efd;
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.12);
        transform: translateY(-1px);
        color: #0d6efd;
    }
    .accountant-compta-hub__tile-icon { font-size: 1.15rem; line-height: 1; margin-bottom: 0.35rem; }
    .accountant-compta-hub__tile-text { font-weight: 600; font-size: 0.9rem; }
    .accountant-compta-hub__tile-hint { font-size: 0.72rem; color: #6c757d; margin-top: 0.2rem; }
    .accountant-compta-hub__tile:hover .accountant-compta-hub__tile-hint { color: #495057; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const aiEndpoint = @json(route('accountant.dashboard.ai.live'));
    const liveText = document.getElementById('accountantAiLiveText');
    const incoBody = document.getElementById('accountantAiIncoBody');
    const refreshStatus = document.getElementById('accountantAiRefreshStatus');
    let countdown = 30;
    let isRefreshing = false;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function severityClass(severity) {
        if (severity === 'danger') return 'danger';
        if (severity === 'warning') return 'warning';
        if (severity === 'success') return 'success';
        return 'secondary';
    }

    function renderStatus(custom) {
        if (!refreshStatus) return;
        refreshStatus.textContent = custom || ('Auto-refresh: ' + countdown + 's');
    }

    function animateText(text) {
        if (!liveText) return;
        const content = String(text || '');
        let i = 0;
        liveText.textContent = '';
        const timer = window.setInterval(function () {
            i += 6;
            liveText.textContent = content.slice(0, i);
            if (i >= content.length) {
                window.clearInterval(timer);
            }
        }, 14);
    }

    function renderInconsistencies(items) {
        if (!incoBody) return;
        if (!Array.isArray(items) || items.length === 0) {
            incoBody.innerHTML = '<p class="text-muted my-3">Aucune incoherence detectee.</p>';
            return;
        }

        incoBody.innerHTML = items.map(function (item) {
            const severity = String(item.severity || 'secondary');
            return '' +
                '<div class="accountant-ai-inco-item py-3 d-flex justify-content-between gap-2">' +
                    '<div>' +
                        '<div class="fw-medium">' + escapeHtml(item.title || 'Incoherence') + '</div>' +
                        '<div class="small text-muted">' + escapeHtml(item.detail || '') + '</div>' +
                        '<div class="small mt-1"><strong>Proposition :</strong> ' + escapeHtml(item.proposal || '') + '</div>' +
                    '</div>' +
                    '<span class="badge bg-' + severityClass(severity) + ' align-self-start">' + escapeHtml(severity.toUpperCase()) + '</span>' +
                '</div>';
        }).join('');
    }

    function refreshPanel() {
        if (!aiEndpoint || isRefreshing) return;
        isRefreshing = true;
        renderStatus('Mise a jour IA...');

        fetch(aiEndpoint, { cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (json) {
                if (!json || !json.ok) {
                    throw new Error('AI unavailable');
                }
                animateText(json.live_insight || '');
                renderInconsistencies(json.inconsistencies || []);
                countdown = 30;
                renderStatus();
            })
            .catch(function () {
                if (liveText) {
                    liveText.textContent = "L'IA live est momentanement indisponible.";
                }
                renderStatus('Auto-refresh: echec');
            })
            .finally(function () {
                isRefreshing = false;
            });
    }

    refreshPanel();
    window.setInterval(function () {
        if (countdown <= 1) {
            refreshPanel();
            countdown = 30;
            return;
        }
        countdown -= 1;
        renderStatus();
    }, 1000);
});
</script>
@endpush

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">Inscriptions récentes</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th>Entreprise / contact</th>
                            <th>E-mail</th>
                            <th class="text-end">Inscrit le</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($recentClients as $u)
                            @php
                                $uCompName = is_object($u) ? ($u->company_name ?? null) : (is_array($u) ? ($u['company_name'] ?? null) : null);
                                $uName = is_object($u) ? ($u->name ?? '') : (is_array($u) ? ($u['name'] ?? '') : (is_scalar($u) ? (string)$u : ''));
                                $uEmail = is_object($u) ? ($u->email ?? '') : (is_array($u) ? ($u['email'] ?? '') : '');
                                $uId = is_object($u) ? ($u->id ?? null) : (is_array($u) ? ($u['id'] ?? null) : (is_scalar($u) ? $u : null));
                                $uCreatedAt = is_object($u) ? ($u->created_at ?? null) : (is_array($u) ? ($u['created_at'] ?? null) : null);
                                $uTargetUrl = (!empty($uId) && is_scalar($uId)) ? route('accountant.clients.show', $uId) : route('accountant.clients.index');
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $uCompName ?: $uName }}</strong>
                                    @if($uCompName)
                                        <div class="small text-muted">{{ $uName }}</div>
                                    @endif
                                </td>
                                <td class="small">{{ $uEmail }}</td>
                                <td class="text-end small">
                                    @if($uCreatedAt instanceof \Carbon\Carbon)
                                        {{ $uCreatedAt->format('d/m/Y') }}
                                    @elseif(!empty($uCreatedAt))
                                        {{ \Carbon\Carbon::parse($uCreatedAt)->format('d/m/Y') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ $uTargetUrl }}" class="btn btn-sm btn-outline-primary">Fiche</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">Aucun dossier client pour l’instant.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h6 class="fw-semibold">Volume trésorerie enregistré</h6>
                <p class="mb-0"><span class="fs-4 fw-bold">{{ number_format($treasuryVolume, 0, ',', ' ') }}</span> <span class="text-muted">FCFA (transactions effectuées, tous clients)</span></p>
            </div>
        </div>
        <div class="card border-primary border-2">
            <div class="card-body">
                <h6 class="fw-semibold mb-2">Actions</h6>
                <a href="{{ route('accountant.clients.index') }}" class="btn btn-primary w-100 mb-2">Tous les dossiers clients</a>
                <p class="small text-muted mb-0">Ouvrez un dossier depuis la fiche client pour travailler sur sa comptabilité, sa trésorerie ou sa FIRD dans le menu habituel.</p>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Modal Smart Import Client (Cabinet Comptable) -->
<div class="modal fade" id="smartImportAccountantClientModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form id="smartAccountantClientForm" action="{{ route('accountant.clients.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header border-0 bg-dark text-white p-4">
                    <div>
                        <span class="badge bg-success text-white rounded-pill px-3 py-1 mb-2 fw-semibold">CABINET COMPTABLE — EXTRACTION IA</span>
                        <h4 class="modal-title fw-bold text-white mb-0">📂 Importer &amp; Créer un Dossier Client</h4>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4" style="max-height: 70vh; overflow-y: auto;">
                    {{-- Zone Dropzone d'importation --}}
                    <div class="p-4 bg-light rounded-4 border border-dashed border-success text-center mb-4">
                        <div class="d-flex align-items-center justify-content-center gap-2 mb-2 text-success fw-bold">
                            <i data-feather="file-text" style="width:24px; height:24px;"></i>
                            <span class="fs-6">Sélectionnez la fiche d'entreprise (Word, Excel, PDF, Text, CSV)</span>
                        </div>
                        <p class="text-muted small mb-3">
                            L'IA comptable analyse le fichier et complète automatiquement les informations du dossier (Nom, Email, Téléphone, NIF, RCCM, Secteur...). Les champs non trouvés restent vierges.
                        </p>
                        <div class="d-flex justify-content-center align-items-center gap-2 flex-wrap">
                            <input type="file" id="accModalDocInput" class="d-none" accept=".docx,.doc,.xlsx,.xls,.csv,.pdf,.txt" onchange="uploadAndParseAccountantDocument(this)">
                            <button type="button" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" onclick="document.getElementById('accModalDocInput').click()">
                                <i data-feather="upload-cloud" class="me-1" style="width:16px; height:16px;"></i> Choisir et analyser un fichier
                            </button>
                            <button type="button" class="btn btn-outline-danger rounded-pill px-3 fw-semibold" onclick="resetAccountantClientForm()">
                                <i data-feather="trash-2" class="me-1" style="width:14px; height:14px;"></i> Supprimer / Effacer tout
                            </button>
                        </div>
                        <div id="parseStatusAlertAccModal" class="mt-3 d-none"></div>
                    </div>

                    <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">👤 Responsable Client</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Nom complet du dirigeant <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control rounded-3" placeholder="Ex: Jean Kouassi" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Adresse Email (Identifiant) <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control rounded-3" placeholder="dirigeant@entreprise.ci" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Numéro de Téléphone</label>
                            <input type="text" name="phone" class="form-control rounded-3" placeholder="+225 07 00 00 00 00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Mot de passe temporaire <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control rounded-3" placeholder="8 caractères minimum" minlength="8" required>
                        </div>
                    </div>

                    <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">🏢 Informations Dossier PME</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Raison Sociale / Entreprise <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control rounded-3" placeholder="Ex: Ivoire Agro SARL" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Sigle usuel</label>
                            <input type="text" name="company_sigle" class="form-control rounded-3" placeholder="Ex: IAGRO">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">NIF / Matricule Fiscal</label>
                            <input type="text" name="company_tax_id" class="form-control rounded-3" placeholder="Numéro d'identification fiscale">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Numéro RCCM / SIRET</label>
                            <input type="text" name="rccm" class="form-control rounded-3" placeholder="Ex : CI-ABJ-2026-B-1234">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Secteur d'activité</label>
                            <select name="sector" class="form-select rounded-3">
                                <option value="">Choisir un secteur...</option>
                                <option value="Agroalimentaire">Agroalimentaire</option>
                                <option value="Commerce & Distribution">Commerce &amp; Distribution</option>
                                <option value="BTP & Construction">BTP &amp; Construction</option>
                                <option value="Services aux entreprises">Services aux entreprises</option>
                                <option value="Technologies / IT">Technologies / IT</option>
                                <option value="Transport & Logistique">Transport &amp; Logistique</option>
                                <option value="Santé">Santé</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Ville</label>
                            <input type="text" name="city" class="form-control rounded-3" placeholder="Ex: Abidjan">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold text-dark">Adresse complète</label>
                            <input type="text" name="address" class="form-control rounded-3" placeholder="Ex: Plateau Rue du Commerce">
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-outline-danger rounded-pill px-4" onclick="resetAccountantClientForm()">
                        <i data-feather="trash-2" class="me-1" style="width:14px; height:14px;"></i> Supprimer la saisie
                    </button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold">✓ Créer le dossier client</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    async function uploadAndParseAccountantDocument(inputElement) {
        const file = inputElement.files[0];
        if (!file) return;

        const alertBox = document.getElementById('parseStatusAlertAccModal');

        if (alertBox) {
            alertBox.className = 'alert alert-info py-2 px-3 rounded-3 small mb-3 align-items-center d-flex justify-content-center gap-2';
            alertBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Extraction et lecture automatique du document en cours...';
            alertBox.classList.remove('d-none');
        }

        const formData = new FormData();
        formData.append('document', file);

        try {
            const response = await fetch('{{ route("accountant.parseCompanyDocument") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: formData
            });

            const json = await response.json();

            if (!response.ok || !json.ok) {
                throw new Error(json.message || 'Erreur lors de l’analyse du fichier.');
            }

            const data = json.extracted || {};
            let filledCount = 0;

            const targetForm = document.getElementById('smartAccountantClientForm');

            const fieldMapping = {
                'name': 'name',
                'email': 'email',
                'phone': 'phone',
                'company_name': 'company_name',
                'company_sigle': 'company_sigle',
                'company_tax_id': 'company_tax_id',
                'rccm': 'rccm',
                'sector': 'sector',
                'city': 'city',
                'address': 'address'
            };

            for (const [key, fieldName] of Object.entries(fieldMapping)) {
                if (data[key] !== undefined && data[key] !== null && data[key] !== '') {
                    const el = targetForm ? targetForm.querySelector(`[name="${fieldName}"]`) : null;
                    if (el) {
                        el.value = data[key];
                        el.classList.add('is-valid');
                        filledCount++;
                    }
                }
            }

            if (alertBox) {
                if (filledCount > 0) {
                    alertBox.className = 'alert alert-success py-2 px-3 rounded-3 small mb-3';
                    alertBox.innerHTML = `<strong>✓ ${filledCount} champ(s) identifié(s) et pré-rempli(s) !</strong> (${json.filename})`;
                } else {
                    alertBox.className = 'alert alert-warning py-2 px-3 rounded-3 small mb-3';
                    alertBox.innerHTML = `Aucun champ d'entreprise n'a été reconnu dans <em>${json.filename}</em>. Vous pouvez remplir les champs manuellement.`;
                }
            }
        } catch (error) {
            if (alertBox) {
                alertBox.className = 'alert alert-danger py-2 px-3 rounded-3 small mb-3';
                alertBox.innerHTML = `<strong>⚠️ Erreur :</strong> ${error.message}`;
            }
        }
    }

    function resetAccountantClientForm() {
        const form = document.getElementById('smartAccountantClientForm');
        if (form) {
            form.reset();
            form.querySelectorAll('.is-valid').forEach(el => el.classList.remove('is-valid'));
        }
        const alertBox = document.getElementById('parseStatusAlertAccModal');
        if (alertBox) {
            alertBox.classList.add('d-none');
        }
    }
</script>
@endpush
@endsection
