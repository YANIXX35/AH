@extends('layouts.app')

@section('title', 'Dashboard | Sitiame Capitale')
@section('page_title', 'Vue globale')

@push('styles')
<style>
    .dashboard-chart-wrap {
        position: relative;
        height: 260px;
    }
    .dashboard-chart-wrap--donut {
        height: 220px;
        max-width: 280px;
        margin-left: auto;
        margin-right: auto;
    }
    .dashboard-ai-live-card {
        border: 1px solid rgba(59, 125, 221, .2);
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .dashboard-ai-live-text {
        font-size: .92rem;
        line-height: 1.45;
        white-space: pre-wrap;
    }
    .dashboard-ai-inco-item + .dashboard-ai-inco-item {
        border-top: 1px solid rgba(0, 0, 0, .06);
    }
    .dashboard-ai-refresh-status {
        font-size: .78rem;
        color: #6c757d;
    }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-3">
    @php
        $u = auth()->user();
        if ($u && $u->is_platform_admin) {
            $dashPremiumActive = false;
            $dashBadgeClass = 'btn-primary';
            $dashBadgeIcon = '🛡️';
            $dashBadgeText = 'Administrateur plateforme';
            $dashShowExpiry = false;
        } else {
            $dashPremiumActive = $u
                && ($u->is_premium ?? false)
                && (empty($u->premium_ends_at) || $u->premium_ends_at->isFuture());
            $dashBadgeClass = $dashPremiumActive ? 'btn-warning text-dark' : 'btn-success';
            $dashBadgeIcon = $dashPremiumActive ? '⭐' : '🟢';
            $dashBadgeText = $dashPremiumActive ? 'Abonnement Premium' : 'Version Gratuite';
            $dashShowExpiry = true;
        }
    @endphp
    <div>
        <h1 class="h3 mb-1"><strong>Tableau de bord</strong> global</h1>
        <p class="text-muted mb-0">Synthèse opérationnelle de la comptabilité et de la trésorerie pour {{ $currentMonthLabel }}.</p>
    </div>
    <div class="btn-group">
        <span class="btn btn-sm {{ $dashBadgeClass }} disabled">
            {{ $dashBadgeIcon }} {{ $dashBadgeText }}
            @if($dashShowExpiry && $dashPremiumActive && !empty(auth()->user()->premium_ends_at))
                - jusqu'au {{ auth()->user()->premium_ends_at->format('d/m/Y') }}
            @endif
        </span>
        <a href="{{ ($u && !$u->is_platform_admin && !($u->is_accountant ?? false) && !$dashPremiumActive) ? route('payments.sandbox') : route('accounting') }}" class="btn btn-outline-primary btn-sm">Comptabilité</a>
        <a href="{{ route('treasury.tracking') }}" class="btn btn-outline-success btn-sm">Trésorerie</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-7 d-flex">
        <div class="card dashboard-ai-live-card shadow-sm w-100">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0">IA en temps reel - recommandations</h5>
                    <small class="text-muted">Analyse comptabilite + tresorerie pour booster le chiffre d'affaires</small>
                </div>
                <div class="text-end">
                    <span class="badge bg-primary">LIVE</span>
                    <div id="dashboardAiRefreshStatus" class="dashboard-ai-refresh-status mt-1">Auto-refresh: 30s</div>
                </div>
            </div>
            <div class="card-body">
                <div id="dashboardAiLiveText" class="dashboard-ai-live-text">{{ $aiLiveInsight ?? "L'IA prepare une recommandation..." }}</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5 d-flex">
        <div class="card shadow-sm w-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0">Incoherences detectees</h5>
                <small class="text-muted">Comptabilite et tresorerie</small>
            </div>
            <div class="card-body py-1" id="dashboardAiIncoBody">
                @forelse(($aiInconsistencies ?? []) as $item)
                    <div class="dashboard-ai-inco-item py-3 d-flex justify-content-between gap-2">
                        <div>
                            <div class="fw-medium">{{ $item['title'] ?? 'Incoherence' }}</div>
                            <div class="small text-muted">{{ $item['detail'] ?? '' }}</div>
                            <div class="small mt-1"><strong>Proposition :</strong> {{ $item['proposal'] ?? '' }}</div>
                        </div>
                        <span class="badge bg-{{ $item['severity'] ?? 'secondary' }} align-self-start">
                            {{ strtoupper((string) ($item['severity'] ?? 'n/a')) }}
                        </span>
                    </div>
                @empty
                    <p class="text-muted my-3">Aucune incoherence detectee.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-3 col-sm-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-1">Écritures comptables</h5>
                <h1 class="mt-1 mb-1">{{ number_format($accountingEntriesCount, 0, ',', ' ') }}</h1>
                <div class="text-muted">Total des écritures enregistrées.</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-1">Volume comptable mensuel</h5>
                <h1 class="mt-1 mb-1">{{ number_format($accountingMonthlyAmount, 0, ',', ' ') }} FCFA</h1>
                <div class="text-muted">Somme des montants des écritures du mois (une fois par ligne, pas masse débit+crédit doublée).</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-1">Documents comptables</h5>
                <h1 class="mt-1 mb-1">{{ number_format($documentsCount, 0, ',', ' ') }}</h1>
                <div class="text-muted">{{ number_format($pendingDocumentsCount, 0, ',', ' ') }} en attente de traitement.</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-1">Solde de trésorerie</h5>
                <h1 class="mt-1 mb-1 {{ $soldeActuel >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ number_format($soldeActuel, 0, ',', ' ') }} FCFA
                </h1>
                <div class="text-muted">Encaissements effectués - décaissements effectués.</div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <h5 class="card-title mb-1">Chiffre d'affaires entreprise</h5>
                        <p class="text-muted small mb-0">Calculé automatiquement à partir des écritures en produits (classe 7).</p>
                    </div>
                    <a href="{{ route('profile.company.fird') }}" class="btn btn-outline-primary btn-sm">Mettre à jour la fiche entreprise</a>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">CA du mois</div>
                            <div class="h4 text-primary mb-0">{{ number_format($turnoverMonth ?? 0, 0, ',', ' ') }} FCFA</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">CA de l'année</div>
                            <div class="h4 text-primary mb-0">{{ number_format($turnoverYear ?? 0, 0, ',', ' ') }} FCFA</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Fiche entreprise</div>
                            @php
                                $firdReady = filled(auth()->user()?->company_name)
                                    && filled(auth()->user()?->company_tax_id)
                                    && filled(auth()->user()?->fiscal_year_end_date)
                                    && filled(auth()->user()?->main_activity_description);
                            @endphp
                            <div class="h5 mb-0">
                                <span class="badge {{ $firdReady ? 'bg-success' : 'bg-warning text-dark' }}">
                                    {{ $firdReady ? 'Complète' : 'À compléter' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <hr class="my-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small mb-0">Évolution du chiffre d'affaires (12 mois)</span>
                    <span class="badge bg-light text-muted">Classe 7 — produits</span>
                </div>
                <div class="dashboard-chart-wrap" style="height: 220px;">
                    <canvas id="dashboardTurnoverBar12" aria-label="Graphique CA 12 mois"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4 g-3">
    <div class="col-12 col-xl-5 d-flex">
        <div class="card flex-fill w-100">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="card-title mb-0">Flux de trésorerie réalisés</h5>
                <span class="badge bg-light text-muted">6 derniers mois</span>
            </div>
            <div class="card-body py-3">
                <p class="text-muted small mb-2">Encaissements et décaissements au statut « effectué » par mois civil.</p>
                <div class="dashboard-chart-wrap">
                    <canvas id="dashboardTreasuryLine" aria-label="Graphique trésorerie"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4 d-flex">
        <div class="card flex-fill w-100">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="card-title mb-0">Volume comptable</h5>
                <span class="badge bg-light text-muted">6 derniers mois</span>
            </div>
            <div class="card-body py-3">
                <p class="text-muted small mb-2">Somme des montants d’écritures par mois civil.</p>
                <div class="dashboard-chart-wrap">
                    <canvas id="dashboardAccountingBar" aria-label="Graphique comptabilité"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-3 d-flex">
        <div class="card flex-fill w-100">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="card-title mb-0">Statuts OCR</h5>
                <span class="badge bg-light text-muted">Écritures</span>
            </div>
            <div class="card-body py-3 d-flex flex-column">
                <p class="text-muted small mb-2">Répartition des contrôles OCR sur l’ensemble des écritures.</p>
                @if(empty($ocrDonutData) || ($accountingEntriesCount ?? 0) === 0)
                    <p class="text-muted small mb-0 text-center my-auto py-4">Aucune écriture à afficher.</p>
                @else
                    <div class="dashboard-chart-wrap dashboard-chart-wrap--donut">
                        <canvas id="dashboardOcrDonut" aria-label="Répartition statuts OCR"></canvas>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 col-xl-8 d-flex">
        <div class="card flex-fill">
            <div class="card-header">
                <h5 class="card-title mb-0">Indicateurs de trésorerie (mois courant)</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Encaissements</div>
                            <div class="h4 text-success mb-0">{{ number_format($encaissementsMonth, 0, ',', ' ') }} FCFA</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Décaissements</div>
                            <div class="h4 text-danger mb-0">{{ number_format($decaissementsMonth, 0, ',', ' ') }} FCFA</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Solde projeté</div>
                            <div class="h4 mb-0 {{ $soldeProjete >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($soldeProjete, 0, ',', ' ') }} FCFA</div>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="small text-muted">
                    Prévision basée sur les flux planifiés :
                    +{{ number_format($encaissementsPlanifies ?? 0, 0, ',', ' ') }} FCFA encaissements planifiés,
                    -{{ number_format($decaissementsPlanifies ?? 0, 0, ',', ' ') }} FCFA décaissements planifiés.
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4 d-flex">
        <div class="card flex-fill">
            <div class="card-header">
                <h5 class="card-title mb-0">Accès rapide</h5>
            </div>
            <div class="card-body d-grid gap-2">
                <a href="{{ route('accounting.documents') }}" class="btn btn-light border text-start">Gestion des documents</a>
                <a href="{{ route('accounting.report') }}" class="btn btn-light border text-start">Rapports comptables</a>
                <a href="{{ route('treasury.forecast') }}" class="btn btn-light border text-start">Prévisions de trésorerie</a>
                <a href="{{ route('profile') }}" class="btn btn-light border text-start">Paramètres du profil</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 col-lg-6 d-flex">
        <div class="card flex-fill">
            <div class="card-header">
                <h5 class="card-title mb-0">Dernières écritures comptables</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover my-0">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Libellé</th>
                        <th class="text-end">Montant</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($latestEntries as $entry)
                        <tr>
                            <td>{{ $entry->date?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($entry->description ?? '—', 45) }}</td>
                            <td class="text-end">{{ number_format($entry->amount, 0, ',', ' ') }} FCFA</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">Aucune écriture disponible.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6 d-flex">
        <div class="card flex-fill">
            <div class="card-header">
                <h5 class="card-title mb-0">Derniers mouvements de trésorerie</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover my-0">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th class="text-end">Montant</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($latestTransactions as $tx)
                        <tr>
                            <td>{{ $tx->transaction_date?->format('d/m/Y') ?? '—' }}</td>
                            <td>
                                <span class="badge bg-{{ $tx->type === 'encaissement' ? 'success' : 'danger' }}">
                                    {{ ucfirst($tx->type) }}
                                </span>
                            </td>
                            <td class="text-end {{ $tx->type === 'encaissement' ? 'text-success' : 'text-danger' }}">
                                {{ $tx->type === 'encaissement' ? '+' : '-' }}{{ number_format($tx->amount, 0, ',', ' ') }} FCFA
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">Aucun mouvement disponible.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 d-flex">
        <div class="card flex-fill">
            <div class="card-header">
                <h5 class="card-title mb-0">Documents récents</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover my-0">
                    <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Type</th>
                        <th>Statut</th>
                        <th class="text-end">Confiance OCR</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($recentDocuments as $document)
                        <tr>
                            <td>{{ \Illuminate\Support\Str::limit($document->original_name ?? 'Document', 60) }}</td>
                            <td>{{ $document->document_type ?? '—' }}</td>
                            <td><span class="badge bg-secondary">{{ $document->status ?? '—' }}</span></td>
                            <td class="text-end">{{ number_format((float) ($document->confidence ?? 0), 1, ',', ' ') }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Aucun document importé récemment.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const chartColors = {
            success: '#22c55e',
            danger: '#ef4444',
            primary: '#3b7ddd',
        };

        const formatMoney = (value) => new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'XOF',
            minimumFractionDigits: 0,
        }).format(value);

        const labels = @json($chartLabels ?? []);
        const encData = @json($treasuryEncChart ?? []);
        const decData = @json($treasuryDecChart ?? []);
        const accData = @json($accountingBarChart ?? []);
        const ocrLabels = @json($ocrDonutLabels ?? []);
        const ocrData = @json($ocrDonutData ?? []);
        const ocrColors = @json($ocrDonutColors ?? []);
        const turnoverLabels = @json($turnoverChartLabels ?? []);
        const turnoverData = @json($turnoverChartData ?? []);
        const aiEndpoint = @json(route('dashboard.ai.live'));
        const aiLiveText = document.getElementById('dashboardAiLiveText');
        const aiIncoBody = document.getElementById('dashboardAiIncoBody');
        const aiRefreshStatus = document.getElementById('dashboardAiRefreshStatus');
        let aiCountdown = 30;
        let aiRefreshing = false;

        const severityBadgeClass = (severity) => {
            if (severity === 'danger') return 'danger';
            if (severity === 'warning') return 'warning';
            if (severity === 'success') return 'success';
            return 'secondary';
        };

        const escapeHtml = (value) => String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

        const renderAiStatus = (text) => {
            if (!aiRefreshStatus) return;
            aiRefreshStatus.textContent = text || `Auto-refresh: ${aiCountdown}s`;
        };

        const animateAiText = (text) => {
            if (!aiLiveText) return;
            const content = String(text || '');
            let i = 0;
            aiLiveText.textContent = '';
            const timer = window.setInterval(() => {
                i += 6;
                aiLiveText.textContent = content.slice(0, i);
                if (i >= content.length) {
                    window.clearInterval(timer);
                }
            }, 14);
        };

        const renderInconsistencies = (items) => {
            if (!aiIncoBody) return;
            if (!Array.isArray(items) || items.length === 0) {
                aiIncoBody.innerHTML = '<p class="text-muted my-3">Aucune incoherence detectee.</p>';
                return;
            }

            aiIncoBody.innerHTML = items.map((item) => {
                const severity = String(item.severity || 'secondary');
                return '' +
                    '<div class="dashboard-ai-inco-item py-3 d-flex justify-content-between gap-2">' +
                        '<div>' +
                            '<div class="fw-medium">' + escapeHtml(item.title || 'Incoherence') + '</div>' +
                            '<div class="small text-muted">' + escapeHtml(item.detail || '') + '</div>' +
                            '<div class="small mt-1"><strong>Proposition :</strong> ' + escapeHtml(item.proposal || '') + '</div>' +
                        '</div>' +
                        '<span class="badge bg-' + severityBadgeClass(severity) + ' align-self-start">' + escapeHtml(severity.toUpperCase()) + '</span>' +
                    '</div>';
            }).join('');
        };

        const refreshAiPanel = () => {
            if (!aiEndpoint || aiRefreshing) return;
            aiRefreshing = true;
            renderAiStatus('Mise a jour IA...');
            fetch(aiEndpoint, { cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then((response) => response.json())
                .then((json) => {
                    if (!json || !json.ok) throw new Error('ai live unavailable');
                    animateAiText(json.live_insight || '');
                    renderInconsistencies(json.inconsistencies || []);
                    aiCountdown = 30;
                    renderAiStatus();
                })
                .catch(() => {
                    if (aiLiveText) {
                        aiLiveText.textContent = "L'IA live est momentanement indisponible.";
                    }
                    renderAiStatus('Auto-refresh: echec');
                })
                .finally(() => {
                    aiRefreshing = false;
                });
        };

        const lineEl = document.getElementById('dashboardTreasuryLine');
        if (lineEl && typeof Chart !== 'undefined') {
            new Chart(lineEl.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Encaissements',
                            data: encData,
                            borderColor: chartColors.success,
                            backgroundColor: 'rgba(34, 197, 94, 0.12)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.35,
                            pointRadius: 3,
                        },
                        {
                            label: 'Décaissements',
                            data: decData,
                            borderColor: chartColors.danger,
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.35,
                            pointRadius: 3,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12 } },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => `${ctx.dataset.label}: ${formatMoney(ctx.parsed.y)}`,
                            },
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(15, 39, 71, 0.06)' },
                            ticks: { callback: (v) => formatMoney(v) },
                        },
                        x: { grid: { display: false } },
                    },
                },
            });
        }

        const barEl = document.getElementById('dashboardAccountingBar');
        if (barEl && typeof Chart !== 'undefined') {
            new Chart(barEl.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Volume (FCFA)',
                            data: accData,
                            backgroundColor: 'rgba(59, 125, 221, 0.55)',
                            borderColor: chartColors.primary,
                            borderWidth: 1,
                            borderRadius: 4,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => formatMoney(ctx.parsed.y),
                            },
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(15, 39, 71, 0.06)' },
                            ticks: { callback: (v) => formatMoney(v) },
                        },
                        x: { grid: { display: false } },
                    },
                },
            });
        }

        const turnoverEl = document.getElementById('dashboardTurnoverBar12');
        if (turnoverEl && typeof Chart !== 'undefined' && turnoverLabels.length > 0) {
            const turnoverMin = turnoverData.length
                ? Math.min(0, ...turnoverData.map((v) => Number(v) || 0))
                : 0;
            new Chart(turnoverEl.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: turnoverLabels,
                    datasets: [{
                        label: 'CA comptable (classe 7, FCFA)',
                        data: turnoverData,
                        backgroundColor: 'rgba(234, 88, 12, 0.55)',
                        borderColor: '#ea580c',
                        borderWidth: 1,
                        borderRadius: 4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => formatMoney(ctx.parsed.y),
                            },
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: turnoverMin >= 0,
                            suggestedMin: turnoverMin < 0 ? turnoverMin : undefined,
                            grid: { color: 'rgba(15, 39, 71, 0.06)' },
                            ticks: { callback: (v) => formatMoney(v) },
                        },
                        x: { grid: { display: false } },
                    },
                },
            });
        }

        const donutEl = document.getElementById('dashboardOcrDonut');
        if (donutEl && typeof Chart !== 'undefined' && ocrLabels.length > 0) {
            new Chart(donutEl.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ocrLabels,
                    datasets: [{
                        data: ocrData,
                        backgroundColor: ocrColors,
                        borderWidth: 1,
                        borderColor: '#fff',
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '58%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { boxWidth: 10, font: { size: 11 } },
                        },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => {
                                    const total = ctx.dataset.data.reduce((a, b) => a + b, 0) || 1;
                                    const n = ctx.parsed;
                                    const pct = ((n / total) * 100).toFixed(1);
                                    return `${ctx.label}: ${n} (${pct}%)`;
                                },
                            },
                        },
                    },
                },
            });
        }

        if (aiLiveText || aiIncoBody) {
            refreshAiPanel();
            window.setInterval(() => {
                if (aiCountdown <= 1) {
                    refreshAiPanel();
                    aiCountdown = 30;
                    return;
                }
                aiCountdown -= 1;
                renderAiStatus();
            }, 1000);
        }
    });
</script>
@endpush
