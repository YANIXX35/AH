@extends('layouts.app')

@section('title', 'Cabinet comptable | Sitiame Capitale')
@section('page_title', 'Cabinet comptable')

@push('styles')
<style>
    .accountant-ai-live-card {
        border: 1px solid rgba(255, 255, 255, 0.45) !important;
        background: rgba(255, 255, 255, 0.85) !important;
        backdrop-filter: blur(12px);
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(15, 39, 71, 0.04);
    }
    .accountant-ai-live-text {
        font-size: .92rem;
        line-height: 1.45;
        white-space: pre-wrap;
    }
    .accountant-ai-inco-item + .accountant-ai-inco-item {
        border-top: 1px solid rgba(0, 0, 0, .06);
    }
    .accountant-ai-refresh-status {
        font-size: .78rem;
        color: #6c757d;
    }

    .dashboard-metric-card {
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.45) !important;
        background: rgba(255, 255, 255, 0.85) !important;
        backdrop-filter: blur(12px);
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(15, 39, 71, 0.04);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .dashboard-metric-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 18px 38px rgba(15, 39, 71, 0.1);
        border-color: rgba(15, 39, 71, 0.15) !important;
    }
    .dashboard-metric-card .card-title {
        color: #0F2747;
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .dashboard-metric-value {
        font-size: 1.6rem;
        font-weight: 700;
        margin: 0.5rem 0;
        background: linear-gradient(135deg, #0F2747 0%, #1d4ed8 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        display: block;
    }
    .dashboard-metric-value-orange {
        background: linear-gradient(135deg, #ea580c 0%, #f97316 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        display: block;
    }
    .dashboard-metric-value-green {
        background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        display: block;
    }
    .dashboard-metric-value-danger {
        background: linear-gradient(135deg, #dc2626 0%, #f87171 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        display: block;
    }
</style>
@endpush

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1"><strong>Tableau de bord</strong> cabinet</h1>
    <p class="text-muted mb-0">Vue d’ensemble des dossiers entreprises : volumes, anomalies et raccourcis vers les dossiers clients.</p>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card dashboard-metric-card shadow-sm h-100">
            <div class="card-body">
                <div class="card-title text-muted small text-uppercase">Dossiers clients</div>
                <span class="dashboard-metric-value">{{ number_format($clientCount) }}</span>
                <p class="small text-muted mb-0">Comptes entreprise (hors admin / cabinet).</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card dashboard-metric-card shadow-sm h-100">
            <div class="card-body">
                <div class="card-title text-muted small text-uppercase">Écritures (tous dossiers)</div>
                <span class="dashboard-metric-value">{{ number_format($entriesTotal) }}</span>
                <p class="small text-muted mb-0">Grand livre agrégé.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card dashboard-metric-card shadow-sm h-100">
            <div class="card-body">
                <div class="card-title text-muted small text-uppercase">Documents à traiter</div>
                <span class="dashboard-metric-value-orange">{{ number_format($documentsPending) }}</span>
                <p class="small text-muted mb-0">En attente ou OCR à corriger.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card dashboard-metric-card shadow-sm h-100">
            <div class="card-body">
                <div class="card-title text-muted small text-uppercase">Écritures OCR « stress »</div>
                <span class="dashboard-metric-value-danger">{{ number_format($ocrStressEntries) }}</span>
                <p class="small text-muted mb-0">À rapprocher ou saisir manuellement.</p>
            </div>
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
                            <tr>
                                <td>
                                    <strong>{{ $u->company_name ?: $u->name }}</strong>
                                    @if($u->company_name)
                                        <div class="small text-muted">{{ $u->name }}</div>
                                    @endif
                                </td>
                                <td class="small">{{ $u->email }}</td>
                                <td class="text-end small">{{ $u->created_at?->format('d/m/Y') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('accountant.clients.show', $u) }}" class="btn btn-sm btn-outline-primary">Fiche</a>
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
@endsection
