@extends('layouts.app')

@section('title', 'Dashboard | Sitiame Capitale')
@section('page_title', 'Vue globale')

@push('styles')
<style>
    /* Mondays Design System - Light & Premium */
    .mondays-container {
        background-color: #f8fafc;
        min-height: 100vh;
        font-family: inherit;
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
        letter-spacing: 0.2px;
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
        letter-spacing: 0.2px;
    }
    .mondays-badge-success { background: #dcfce7; color: #15803d; }
    .mondays-badge-pending { background: #f3e8ff; color: #7e22ce; }
    .mondays-badge-info { background: #dbeafe; color: #1d4ed8; }
    .mondays-badge-warning { background: #ffedd5; color: #c2410c; }
    .mondays-badge-secondary { background: #f1f5f9; color: #475569; }

    .mondays-metric-val {
        font-size: 1.65rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.2;
    }

    .mondays-week-calendar {
        display: flex;
        justify-content: space-between;
        gap: 6px;
        background: #f8fafc;
        padding: 8px;
        border-radius: 12px;
        border: 1px solid #f1f5f9;
    }
    .mondays-week-day {
        flex: 1;
        text-align: center;
        padding: 6px 4px;
        border-radius: 8px;
        font-size: 0.76rem;
        font-weight: 600;
        color: #64748b;
    }
    .mondays-week-day.active {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 2px 6px rgba(37, 99, 235, 0.3);
    }

    .dashboard-chart-wrap { position: relative; height: 250px; }
    .dashboard-chart-wrap--donut { height: 220px; max-width: 280px; margin: 0 auto; }
    .dashboard-ai-live-text { font-size: .92rem; line-height: 1.45; white-space: pre-wrap; }
    .dashboard-ai-inco-item + .dashboard-ai-inco-item { border-top: 1px solid #f1f5f9; }
</style>
@endpush

@section('content')
<div class="mondays-container pb-4">
    @php
        $u = auth()->user();
        if ($u && $u->is_platform_admin) {
            $dashPremiumActive = false;
            $dashBadgeClass = 'mondays-badge-info';
            $dashBadgeIcon = '🛡️';
            $dashBadgeText = 'Administrateur plateforme';
            $dashShowExpiry = false;
        } else {
            $dashPremiumActive = $u
                && ($u->is_premium ?? false)
                && (empty($u->premium_ends_at) || $u->premium_ends_at->isFuture());
            $dashBadgeClass = $dashPremiumActive ? 'mondays-badge-warning' : 'mondays-badge-success';
            $dashBadgeIcon = $dashPremiumActive ? '⭐' : '🟢';
            $dashBadgeText = $dashPremiumActive ? 'Abonnement Premium' : 'Version Gratuite';
            $dashShowExpiry = true;
        }
    @endphp

    <!-- HERO MONDAYS HEADER -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2">
            <div>
                <div class="mondays-hero-date">
                    <i data-feather="calendar" class="me-1" style="width:14px; height:14px;"></i>
                    {{ \Carbon\Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </div>
                <h1 class="mondays-hero-title">
                    Bonjour, {{ explode(' ', auth()->user()?->name ?? 'Utilisateur')[0] }} 👋
                </h1>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="mondays-badge {{ $dashBadgeClass }}">
                    {{ $dashBadgeIcon }} {{ $dashBadgeText }}
                    @if($dashShowExpiry && $dashPremiumActive && !empty(auth()->user()->premium_ends_at))
                        - {{ auth()->user()->premium_ends_at->format('d/m/Y') }}
                    @endif
                </span>
                <a href="{{ route('accounting.documents') }}" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold">
                    <i data-feather="plus" class="me-1" style="width:14px; height:14px;"></i> Nouveau Document
                </a>
                <a href="{{ route('accounting.report') }}" class="btn btn-sm btn-light border rounded-pill px-3 fw-semibold text-dark">
                    <i data-feather="file-text" class="me-1" style="width:14px; height:14px;"></i> Rapports
                </a>
            </div>
        </div>

        <!-- BARRE DE PILULES KPI EN-TÊTE -->
        <div class="mondays-pill-bar">
            <div class="mondays-pill-item">
                <span class="text-primary">⏱️</span> <strong>Gain de temps :</strong> 12h économisées ce mois
            </div>
            <div class="mondays-pill-item text-muted">|</div>
            <div class="mondays-pill-item">
                <span class="text-success">🎯</span> <strong>Écritures :</strong> {{ number_format($accountingEntriesCount, 0, ',', ' ') }} enregistrées
            </div>
            <div class="mondays-pill-item text-muted">|</div>
            <div class="mondays-pill-item">
                <span class="text-warning">⌛</span> <strong>Traitement OCR :</strong> {{ number_format($pendingDocumentsCount, 0, ',', ' ') }} en attente
            </div>
        </div>
    </div>

    <!-- METRICS CARDS GRID (MONDAYS STYLE) -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="card mondays-card h-100 border-0 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Écritures Comptables</span>
                    <span class="mondays-badge mondays-badge-info">Total</span>
                </div>
                <div class="mondays-metric-val text-primary mb-1">{{ number_format($accountingEntriesCount, 0, ',', ' ') }}</div>
                <div class="text-muted small">Écritures validées en base.</div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card mondays-card h-100 border-0 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Volume Mensuel</span>
                    <span class="mondays-badge mondays-badge-warning">{{ $currentMonthLabel }}</span>
                </div>
                <div class="mondays-metric-val text-warning mb-1">{{ number_format($accountingMonthlyAmount, 0, ',', ' ') }} <small class="fs-6 fw-normal text-muted">FCFA</small></div>
                <div class="text-muted small">Somme des flux du mois.</div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card mondays-card h-100 border-0 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Pièces & Documents</span>
                    <span class="mondays-badge mondays-badge-pending">{{ number_format($pendingDocumentsCount, 0, ',', ' ') }} en attente</span>
                </div>
                <div class="mondays-metric-val text-dark mb-1">{{ number_format($documentsCount, 0, ',', ' ') }}</div>
                <div class="text-muted small">Documents numérisés OCR.</div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card mondays-card h-100 border-0 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Trésorerie Nette</span>
                    <span class="mondays-badge {{ $soldeActuel >= 0 ? 'mondays-badge-success' : 'mondays-badge-warning' }}">
                        {{ $soldeActuel >= 0 ? 'Positif' : 'Alerte' }}
                    </span>
                </div>
                <div class="mondays-metric-val {{ $soldeActuel >= 0 ? 'text-success' : 'text-danger' }} mb-1">
                    {{ number_format($soldeActuel, 0, ',', ' ') }} <small class="fs-6 fw-normal text-muted">FCFA</small>
                </div>
                <div class="text-muted small">Encaissements − Décaissements.</div>
            </div>
        </div>
    </div>

    <!-- IA & RECOMMANDATIONS SECTION -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-7 d-flex">
            <div class="card mondays-card w-100 border-0">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i data-feather="cpu" class="text-primary me-1"></i> IA Recommandations en direct
                        </h5>
                        <small class="text-muted">Analyse en temps réel des opportunités de trésorerie & fiscalité</small>
                    </div>
                    <div class="text-end">
                        <span class="mondays-badge mondays-badge-info">LIVE</span>
                        <div id="dashboardAiRefreshStatus" class="small text-muted mt-1" style="font-size:0.75rem;">Maj: 30s</div>
                    </div>
                </div>
                <div class="card-body">
                    <div id="dashboardAiLiveText" class="dashboard-ai-live-text text-dark">{{ $aiLiveInsight ?? "L'assistant IA analyse votre dossier..." }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5 d-flex">
            <div class="card mondays-card w-100 border-0">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="card-title mb-0 fw-bold text-dark">Incohérences & Alertes</h5>
                    <small class="text-muted">Contrôles automatisés comptabilité et banque</small>
                </div>
                <div class="card-body py-1" id="dashboardAiIncoBody">
                    @forelse(($aiInconsistencies ?? []) as $item)
                        <div class="dashboard-ai-inco-item py-3 d-flex justify-content-between gap-2">
                            <div>
                                <div class="fw-semibold text-dark">{{ $item['title'] ?? 'Incohérence' }}</div>
                                <div class="small text-muted">{{ $item['detail'] ?? '' }}</div>
                                <div class="small mt-1 text-primary"><strong>Action conseillée :</strong> {{ $item['proposal'] ?? '' }}</div>
                            </div>
                            <span class="mondays-badge mondays-badge-warning align-self-start">
                                {{ strtoupper((string) ($item['severity'] ?? 'n/a')) }}
                            </span>
                        </div>
                    @empty
                        <p class="text-muted my-3 text-center py-3">
                            <i data-feather="check-circle" class="text-success me-1"></i> Aucune incohérence détectée.
                        </p>
                    @endforelse
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
