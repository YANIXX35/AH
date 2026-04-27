@extends('layouts.app')

@section('title', 'Ops Center IT Manager | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@push('styles')
<style>
    .ai-live-hero {
        background: #fff;
        color: #212529;
        border: 1px solid #e9ecef;
        border-left: 4px solid #3b7ddd;
        border-radius: 1rem;
        padding: 1.25rem;
        position: relative;
        overflow: hidden;
        box-shadow: 0 .2rem .7rem rgba(0, 0, 0, .05);
    }
    .ai-live-title {
        letter-spacing: .06em;
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #3b7ddd;
    }
    .ai-live-text {
        font-size: .95rem;
        line-height: 1.52;
        font-weight: 600;
        margin: .65rem 0 .5rem;
        min-height: 98px;
        white-space: pre-wrap;
        color: #1f2937;
        position: relative;
        padding-right: .35rem;
    }
    .ai-live-text.is-typing::after {
        content: "";
        display: inline-block;
        width: 2px;
        height: 1.05em;
        background: #3b7ddd;
        margin-left: .25rem;
        vertical-align: text-bottom;
        animation: aiLiveCaret .95s step-end infinite;
    }
    @keyframes aiLiveCaret {
        0%, 45% { opacity: 1; }
        46%, 100% { opacity: 0; }
    }
    .ai-live-meta {
        font-size: .72rem;
        color: #6c757d;
    }
    .ai-live-pulse {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
    }
    .ai-live-pulse-dot {
        width: 9px;
        height: 9px;
        border-radius: 999px;
        background: #3b7ddd;
        box-shadow: 0 0 0 0 rgba(59, 125, 221, .55);
        animation: aiLivePulse 1.6s infinite;
    }
    .ai-live-badge {
        font-size: .7rem;
        letter-spacing: .05em;
    }
    .ai-live-badge.bg-success {
        background-color: #198754 !important;
    }
    .ai-live-badge.bg-warning {
        background-color: #f59f00 !important;
        color: #1f2937 !important;
    }
    .ai-live-badge.bg-danger {
        background-color: #dc3545 !important;
    }
    .ai-live-hero.live-success {
        border-left-color: #198754;
    }
    .ai-live-hero.live-warning {
        border-left-color: #f59f00;
    }
    .ai-live-hero.live-danger {
        border-left-color: #dc3545;
    }
    @keyframes aiLivePulse {
        0% { box-shadow: 0 0 0 0 rgba(59, 125, 221, .55); }
        70% { box-shadow: 0 0 0 10px rgba(59, 125, 221, 0); }
        100% { box-shadow: 0 0 0 0 rgba(59, 125, 221, 0); }
    }
    .ai-live-chart-wrap {
        position: relative;
        min-height: 270px;
    }
    .ai-live-chart-wrap canvas {
        width: 100% !important;
        height: 270px !important;
    }
    .ai-live-chart-wrap.is-updating {
        animation: aiLiveChartFlash .5s ease;
    }
    @keyframes aiLiveChartFlash {
        0% { opacity: .88; transform: scale(1); }
        60% { opacity: 1; transform: scale(1.01); }
        100% { opacity: 1; transform: scale(1); }
    }
    @media (prefers-color-scheme: dark) {
        .ai-live-hero {
            background: #1f2937;
            color: #e5e7eb;
            border-color: #374151;
        }
        .ai-live-title {
            color: #93c5fd;
        }
        .ai-live-text {
            color: #f3f4f6;
        }
        .ai-live-meta,
        .ai-live-pulse .small {
            color: #9ca3af !important;
        }
    }
    @media (max-width: 768px) {
        .ai-live-text {
            font-size: .88rem;
            min-height: 86px;
        }
    }
    .ai-step-card {
        border: 1px solid #e9ecef;
        border-radius: .65rem;
        padding: .65rem;
        background: #fff;
        transition: box-shadow .15s ease;
    }
    .ai-step-card:hover {
        box-shadow: 0 .15rem .5rem rgba(0, 0, 0, .08);
    }
</style>
@endpush

@section('content')
<div class="mb-4">
    <nav aria-label="Fil d’Ariane admin" class="mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Administration</a></li>
            <li class="breadcrumb-item active" aria-current="page">Ops Center</li>
        </ol>
    </nav>
    <h1 class="h3 mb-1"><strong>Ops Center</strong> IT Manager</h1>
    <p class="text-muted mb-0">SLA/SLO en temps réel, centre d’alertes, NOC, supervision des files, feature flags, workflow d’approbation et audit exportable.</p>
</div>

@if(session('status'))
    <div class="alert alert-success border-0 shadow-sm">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger border-0 shadow-sm">
        <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0">SLA / SLO en temps réel</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Indicateur</th>
                                <th>Réel</th>
                                <th>Cible</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(($slo ?? []) as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td>{{ number_format((float) $row['actual_hours'], 2, ',', ' ') }} h</td>
                                    <td>{{ number_format((float) $row['target_hours'], 0, ',', ' ') }} h</td>
                                    <td><span class="badge bg-{{ $row['status'] }}">{{ strtoupper((string) $row['status']) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0">Centre d’alertes intelligent</h5>
            </div>
            <div class="card-body">
                @foreach(($alertCenter ?? []) as $alert)
                    <div class="d-flex justify-content-between align-items-start {{ !$loop->last ? 'mb-3 pb-3 border-bottom' : '' }}">
                        <div>
                            <div class="fw-medium">{{ $alert['label'] }}</div>
                            <small class="text-muted">Seuil {{ $alert['threshold'] }} · escalade niveau {{ $alert['escalation'] }}</small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-{{ $alert['severity'] }}">{{ $alert['value'] }}</span>
                            <div><a class="small" href="{{ $alert['url'] }}">Traiter</a></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="ai-live-hero shadow-sm">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="ai-live-title">IA EN DIRECT - Propositions pour accroître le chiffre d’affaires</div>
                <div class="d-flex align-items-center gap-2">
                    <span id="aiLiveBadge" class="badge text-bg-primary ai-live-badge">LIVE</span>
                    <div class="ai-live-pulse"><span class="ai-live-pulse-dot"></span><span class="small text-muted">Analyse IA active</span></div>
                </div>
            </div>
            <div id="aiLiveBigText" class="ai-live-text">
                L’IA prépare des recommandations business en direct...
            </div>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="ai-live-meta"><span id="aiLiveState">Synchronisation...</span> · Mise à jour automatique toutes les 30 secondes.</div>
                <div class="ai-live-meta">Dernière actualisation : <span id="aiLiveUpdatedAt">—</span></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0">Graphique KPI business (temps réel)</h5>
            </div>
            <div class="card-body">
                <div class="ai-live-chart-wrap">
                    <canvas id="opsBusinessKpiChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0">Comparaison CA 30 jours</h5>
            </div>
            <div class="card-body">
                <div class="ai-live-chart-wrap">
                    <canvas id="opsRevenueCompareChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Étapes IA actionnables</h5>
                <small class="text-muted">Cliquer pour créer des tâches</small>
            </div>
            <div id="aiLiveStepsContainer" class="card-body">
                <p class="text-muted mb-0">En attente d’une proposition IA...</p>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0">Backlog d’exécution IA</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Tâche</th>
                                <th>Statut</th>
                                <th>Assignation</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($aiTasks ?? []) as $task)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $task->title }}</div>
                                        @if(!empty($task->description))
                                            <small class="text-muted">{{ \Illuminate\Support\Str::limit($task->description, 110) }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $taskStatusClass = match($task->status) {
                                                'done' => 'success',
                                                'in_progress' => 'warning text-dark',
                                                default => 'secondary',
                                            };
                                        @endphp
                                        <span class="badge bg-{{ $taskStatusClass }}">{{ strtoupper((string) $task->status) }}</span>
                                    </td>
                                    <td>
                                        <form method="post" action="{{ route('admin.ops.ai.tasks.assign', $task) }}" class="d-flex gap-1">
                                            @csrf
                                            <select class="form-select form-select-sm" name="assigned_to_user_id" required>
                                                <option value="" disabled {{ $task->assigned_to_user_id ? '' : 'selected' }}>Assigner...</option>
                                                @foreach(($platformAdmins ?? []) as $admin)
                                                    <option value="{{ $admin->id }}" @selected($task->assigned_to_user_id === $admin->id)>
                                                        {{ $admin->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-sm btn-outline-primary" type="submit">Assigner</button>
                                        </form>
                                    </td>
                                    <td class="text-end">
                                        @if($task->status !== 'done')
                                            <form method="post" action="{{ route('admin.ops.ai.tasks.complete', $task) }}">
                                                @csrf
                                                <button class="btn btn-sm btn-success" type="submit">Marquer terminé</button>
                                            </form>
                                        @else
                                            <small class="text-muted">Terminé</small>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted text-center">Aucune tâche IA pour le moment.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0">KPI croissance & revenus</h5>
            </div>
            <div class="card-body">
                @php $growthKpis = $businessKpis ?? []; @endphp
                @forelse($growthKpis as $kpi)
                    <div class="d-flex justify-content-between align-items-start {{ !$loop->last ? 'mb-3 pb-3 border-bottom' : '' }}">
                        <div>
                            <div class="fw-semibold">{{ $kpi['label'] ?? 'KPI' }}</div>
                            @if(isset($kpi['target']) || isset($kpi['target_delta_pct']))
                                <small class="text-muted">
                                    Cible:
                                    @if(($kpi['unit'] ?? '') === 'pct')
                                        {{ number_format((float) ($kpi['target'] ?? $kpi['target_delta_pct'] ?? 0), 2, ',', ' ') }}%
                                    @elseif(($kpi['unit'] ?? '') === 'montant')
                                        {{ number_format((float) ($kpi['target'] ?? 0), 0, ',', ' ') }}
                                    @else
                                        {{ (int) ($kpi['target'] ?? 0) }}
                                    @endif
                                </small>
                            @endif
                        </div>
                        <div class="text-end">
                            <div class="fw-bold">
                                @if(($kpi['unit'] ?? '') === 'pct')
                                    {{ number_format((float) ($kpi['value'] ?? 0), 2, ',', ' ') }}%
                                @elseif(($kpi['unit'] ?? '') === 'montant')
                                    {{ number_format((float) ($kpi['value'] ?? 0), 0, ',', ' ') }}
                                @else
                                    {{ (int) ($kpi['value'] ?? 0) }}
                                @endif
                            </div>
                            @if(array_key_exists('delta_pct', $kpi))
                                <small class="text-{{ (($kpi['delta_pct'] ?? 0) >= 0) ? 'success' : 'danger' }}">
                                    {{ (($kpi['delta_pct'] ?? 0) >= 0) ? '+' : '' }}{{ number_format((float) ($kpi['delta_pct'] ?? 0), 2, ',', ' ') }}%
                                </small>
                            @endif
                            <div><span class="badge bg-{{ $kpi['status'] ?? 'secondary' }}">{{ strtoupper((string) ($kpi['status'] ?? 'n/a')) }}</span></div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">Aucun KPI de croissance disponible.</p>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0">Propositions IA pour accroître le chiffre d’affaires</h5>
            </div>
            <div class="card-body">
                @forelse(($growthIdeas ?? []) as $idea)
                    <div class="{{ !$loop->last ? 'mb-3 pb-3 border-bottom' : '' }}">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h6 class="mb-0">{{ $idea['title'] }}</h6>
                            <span class="badge bg-{{ ($idea['priority'] ?? '') === 'haute' ? 'danger' : 'warning text-dark' }}">
                                Priorité {{ strtoupper((string) ($idea['priority'] ?? 'moyenne')) }}
                            </span>
                        </div>
                        <p class="small text-muted mb-1">{{ $idea['why'] ?? '' }}</p>
                        <ul class="small mb-1">
                            @foreach(($idea['actions'] ?? []) as $action)
                                <li>{{ $action }}</li>
                            @endforeach
                        </ul>
                        <p class="small mb-0"><strong>KPI cible:</strong> {{ $idea['kpi_target'] ?? '—' }} · <strong>Impact:</strong> {{ $idea['impact'] ?? '—' }}</p>
                    </div>
                @empty
                    <p class="text-muted mb-0">Aucune proposition IA disponible pour le moment.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0">NOC dashboard</h5>
            </div>
            <div class="card-body">
                <p class="mb-2"><strong>Incidents 24h:</strong> {{ $noc['incidents_24h'] ?? 0 }}</p>
                <p class="mb-2"><strong>Disponibilité:</strong> {{ number_format((float) ($noc['availability_pct'] ?? 0), 2, ',', ' ') }}%</p>
                <p class="mb-2"><strong>Erreurs 5xx (24h):</strong> {{ $noc['http_5xx_24h'] ?? 0 }}</p>
                <p class="mb-0"><strong>Saturation (proxy):</strong> {{ number_format((float) ($noc['saturation_hint'] ?? 0), 2, ',', ' ') }}%</p>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Queue monitor</h5>
                <form method="post" action="{{ route('admin.ops.queue.retry-all') }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-warning" type="submit">Retry intelligent</button>
                </form>
            </div>
            <div class="card-body">
                <p class="mb-2"><strong>Jobs en attente:</strong> {{ $queue['pending'] ?? 0 }}</p>
                <p class="mb-0"><strong>Jobs en échec:</strong> {{ $queue['failed'] ?? 0 }}</p>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Health checks</h5>
                <form method="post" action="{{ route('admin.ops.health-checks.run') }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-primary" type="submit">Relancer</button>
                </form>
            </div>
            <div class="card-body">
                @foreach(($healthChecks ?? []) as $check)
                    <div class="d-flex justify-content-between {{ !$loop->last ? 'mb-2' : '' }}">
                        <span>{{ $check['name'] }}</span>
                        <span class="badge bg-{{ ($check['status'] ?? 'warn') === 'ok' ? 'success' : (($check['status'] ?? '') === 'ko' ? 'danger' : 'warning text-dark') }}">{{ strtoupper((string) $check['status']) }}</span>
                    </div>
                    <small class="text-muted d-block {{ !$loop->last ? 'mb-2' : '' }}">{{ $check['detail'] }}</small>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0">Feature flags (global)</h5>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.ops.feature-flags.update') }}">
                    @csrf
                    @php $modules = $featureFlags['modules'] ?? []; @endphp
                    @foreach(['accounting' => 'Comptabilité', 'treasury' => 'Trésorerie', 'investor' => 'Investisseur', 'payments' => 'Paiements', 'ops_center' => 'Ops Center'] as $key => $label)
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="flag_{{ $key }}" name="modules[{{ $key }}]" value="1" @checked((bool) ($modules[$key] ?? false))>
                            <label class="form-check-label" for="flag_{{ $key }}">{{ $label }}</label>
                        </div>
                    @endforeach
                    <button class="btn btn-primary btn-sm mt-2" type="submit">Enregistrer</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0">Workflow d’approbation (2 étapes)</h5>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.ops.approvals.store') }}" class="row g-2 mb-3">
                    @csrf
                    <div class="col-md-4">
                        <input class="form-control form-control-sm" type="text" name="action_key" placeholder="action_key" required>
                    </div>
                    <div class="col-md-4">
                        <input class="form-control form-control-sm" type="text" name="target_type" placeholder="target_type">
                    </div>
                    <div class="col-md-2">
                        <input class="form-control form-control-sm" type="number" name="target_id" placeholder="id">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-outline-primary w-100" type="submit">Demander</button>
                    </div>
                    <div class="col-12">
                        <textarea class="form-control form-control-sm" name="payload_json" rows="2" placeholder='{"note":"..."}'></textarea>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Action</th>
                                <th>Demandeur</th>
                                <th>Statut</th>
                                <th class="text-end">Validation</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($approvalsPending as $approval)
                                <tr>
                                    <td>{{ $approval->action_key }}</td>
                                    <td>{{ $approval->requestedBy?->name ?? '—' }}</td>
                                    <td><span class="badge bg-warning text-dark">{{ $approval->status }}</span></td>
                                    <td class="text-end">
                                        <form method="post" action="{{ route('admin.ops.approvals.approve', $approval) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-success" type="submit">Approuver</button>
                                        </form>
                                        <form method="post" action="{{ route('admin.ops.approvals.reject', $approval) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Rejeter</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted text-center">Aucune demande en attente.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0">IA autonome (sous autorisation administrateur)</h5>
            </div>
            <div class="card-body">
                @php
                    $aiAuto = $aiAutonomousApprovals ?? [];
                    $aiAutoThresholds = (array) ($aiAuto['thresholds'] ?? []);
                @endphp
                <form method="post" action="{{ route('admin.ops.ai.autonomous-approvals.update') }}" class="row g-3">
                    @csrf
                    <div class="col-md-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="aiAutoEnabled" name="enabled" value="1" @checked((bool) ($aiAuto['enabled'] ?? false))>
                            <label class="form-check-label" for="aiAutoEnabled">
                                Autoriser l’IA à ouvrir automatiquement des demandes d’approbation pour actions sensibles
                            </label>
                        </div>
                        <small class="text-muted">
                            L’IA n’exécute pas les actions sensibles. Elle ouvre uniquement une demande d’approbation à valider par un administrateur.
                        </small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Seuil tickets bloqués &gt; 48h</label>
                        <input class="form-control form-control-sm" type="number" min="1" max="200" name="thresholds[blocked_tickets_48h]" value="{{ (int) ($aiAutoThresholds['blocked_tickets_48h'] ?? 3) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Seuil premium expirant (7 jours)</label>
                        <input class="form-control form-control-sm" type="number" min="1" max="500" name="thresholds[expiring_premium_7d]" value="{{ (int) ($aiAutoThresholds['expiring_premium_7d'] ?? 5) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Seuil taux succès paiement (%)</label>
                        <input class="form-control form-control-sm" type="number" min="0" max="100" step="0.1" name="thresholds[payment_success_rate_pct]" value="{{ (float) ($aiAutoThresholds['payment_success_rate_pct'] ?? 90) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Seuil croissance CA (%)</label>
                        <input class="form-control form-control-sm" type="number" min="-100" max="300" step="0.1" name="thresholds[revenue_growth_pct]" value="{{ (float) ($aiAutoThresholds['revenue_growth_pct'] ?? 0) }}">
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-sm btn-primary" type="submit">Enregistrer la politique d’autonomie IA</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Journal d’audit renforcé (avant / après)</h5>
                <a href="{{ route('admin.ops.audit.export') }}" class="btn btn-sm btn-outline-secondary">Exporter CSV</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Action</th>
                                <th>Acteur</th>
                                <th>Cible</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($auditRecent as $audit)
                                <tr>
                                    <td><small>{{ optional($audit->created_at)->format('d/m H:i') }}</small></td>
                                    <td>{{ $audit->action }}</td>
                                    <td>{{ $audit->actor?->name ?? 'Système' }}</td>
                                    <td><small>{{ $audit->target_type }}#{{ $audit->target_id }}</small></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted text-center">Aucune trace audit.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0">Runbooks intégrés</h5>
            </div>
            <div class="card-body">
                @foreach($runbooks as $runbook)
                    <div class="{{ !$loop->last ? 'mb-3 pb-3 border-bottom' : '' }}">
                        <h6 class="mb-1">{{ $runbook['title'] }}</h6>
                        <ol class="small text-muted mb-2 ps-3">
                            @foreach($runbook['steps'] as $step)
                                <li>{{ $step }}</li>
                            @endforeach
                        </ol>
                        <a href="{{ $runbook['action_url'] }}" class="btn btn-sm btn-outline-primary">Résoudre</a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const liveEndpoint = @json(route('admin.ops.ai.live'));
    const liveHero = document.querySelector('.ai-live-hero');
    const liveBadge = document.getElementById('aiLiveBadge');
    const liveTextEl = document.getElementById('aiLiveBigText');
    const liveStepsContainer = document.getElementById('aiLiveStepsContainer');
    const liveStateEl = document.getElementById('aiLiveState');
    const liveUpdatedAtEl = document.getElementById('aiLiveUpdatedAt');
    const kpiCanvas = document.getElementById('opsBusinessKpiChart');
    const revenueCanvas = document.getElementById('opsRevenueCompareChart');
    const kpiChartWrap = kpiCanvas ? kpiCanvas.closest('.ai-live-chart-wrap') : null;
    const revenueChartWrap = revenueCanvas ? revenueCanvas.closest('.ai-live-chart-wrap') : null;
    let kpiChart = null;
    let revenueChart = null;
    let typingTimer = null;
    let lastLiveText = '';
    let lastChartHash = '';
    const csrf = @json(csrf_token());
    const createTaskEndpoint = @json(route('admin.ops.ai.tasks.store'));

    const formatDateTime = (isoString) => {
        if (!isoString) return '—';
        const d = new Date(isoString);
        if (Number.isNaN(d.getTime())) return '—';
        return d.toLocaleString('fr-FR', { hour12: false });
    };

    const animateLiveText = (text, force) => {
        if (!liveTextEl) return;
        const target = String(text || '');
        if (!force && target === lastLiveText) {
            return;
        }
        lastLiveText = target;
        if (typingTimer) {
            window.clearTimeout(typingTimer);
            typingTimer = null;
        }
        liveTextEl.textContent = '';
        liveTextEl.classList.add('is-typing');
        let idx = 0;
        const step = () => {
            idx += Math.max(1, Math.ceil(target.length / 200));
            liveTextEl.textContent = target.slice(0, idx);
            if (idx < target.length) {
                typingTimer = window.setTimeout(step, 24);
            } else {
                liveTextEl.classList.remove('is-typing');
            }
        };
        step();
    };

    const extractActionSteps = (text) => {
        const content = String(text || '');
        const lines = content.split(/\r?\n/).map(line => line.trim()).filter(Boolean);
        const numbered = lines
            .filter(line => /^\d+[\)\.\-]\s+/.test(line))
            .map(line => line.replace(/^\d+[\)\.\-]\s+/, '').trim());
        if (numbered.length > 0) return numbered.slice(0, 6);

        const actionBlock = lines.filter(line =>
            !line.startsWith('**Priorité') &&
            !line.startsWith('**KPI') &&
            !line.startsWith('**Impact') &&
            line.length > 20
        );
        return actionBlock.slice(0, 4);
    };

    const renderActionableSteps = (text) => {
        if (!liveStepsContainer) return;
        const steps = extractActionSteps(text);
        if (steps.length === 0) {
            liveStepsContainer.innerHTML = '<p class="text-muted mb-0">Aucune étape actionnable détectée.</p>';
            return;
        }

        liveStepsContainer.innerHTML = steps.map((step, idx) => `
            <div class="ai-step-card mb-2">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="fw-semibold small text-muted mb-1">Étape ${idx + 1}</div>
                        <div>${step}</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary js-ai-create-task"
                        data-title="${String(step).replace(/"/g, '&quot;')}"
                        data-description="${String(step).replace(/"/g, '&quot;')}">
                        Créer tâche
                    </button>
                </div>
            </div>
        `).join('');
    };

    const createTaskFromStep = async (title, description) => {
        const response = await fetch(createTaskEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                title,
                description,
                priority: 'medium',
                source: 'ops_live_step',
            }),
        });
        const json = await response.json();
        if (!response.ok || !json.ok) {
            throw new Error(json.error || 'Création de tâche impossible');
        }
        return json;
    };

    const statusToColor = (status) => {
        if (status === 'success') return '#198754';
        if (status === 'warning') return '#f59f00';
        if (status === 'danger') return '#dc3545';
        return '#6c757d';
    };

    const severityRank = (status) => {
        if (status === 'danger') return 3;
        if (status === 'warning') return 2;
        if (status === 'success') return 1;
        return 0;
    };

    const flashWrap = (wrap) => {
        if (!wrap) return;
        wrap.classList.remove('is-updating');
        void wrap.offsetWidth;
        wrap.classList.add('is-updating');
    };

    const applySeverityTheme = (statuses) => {
        if (!liveHero || !liveBadge) return;
        const level = (Array.isArray(statuses) ? statuses : []).reduce((max, status) => Math.max(max, severityRank(status)), 0);
        liveHero.classList.remove('live-success', 'live-warning', 'live-danger');
        liveBadge.classList.remove('bg-success', 'bg-warning', 'bg-danger');
        if (level >= 3) {
            liveHero.classList.add('live-danger');
            liveBadge.classList.add('bg-danger');
            return;
        }
        if (level === 2) {
            liveHero.classList.add('live-warning');
            liveBadge.classList.add('bg-warning');
            return;
        }
        liveHero.classList.add('live-success');
        liveBadge.classList.add('bg-success');
    };

    const updateCharts = (charts) => {
        if (!charts || !window.Chart || !kpiCanvas || !revenueCanvas) return;

        const kpiLabels = Array.isArray(charts.kpi_labels) ? charts.kpi_labels : [];
        const kpiValues = Array.isArray(charts.kpi_values) ? charts.kpi_values : [];
        const kpiStatuses = Array.isArray(charts.kpi_statuses) ? charts.kpi_statuses : [];
        const kpiColors = kpiStatuses.map(statusToColor);

        const revenueLabels = Array.isArray(charts.revenue_compare?.labels) ? charts.revenue_compare.labels : [];
        const revenueValues = Array.isArray(charts.revenue_compare?.values) ? charts.revenue_compare.values : [];
        const currentHash = JSON.stringify([kpiLabels, kpiValues, kpiStatuses, revenueLabels, revenueValues]);
        const hasChanged = currentHash !== lastChartHash;
        lastChartHash = currentHash;
        applySeverityTheme(kpiStatuses);

        if (!kpiChart) {
            kpiChart = new window.Chart(kpiCanvas, {
                type: 'bar',
                data: {
                    labels: kpiLabels,
                    datasets: [{
                        label: 'Valeur KPI',
                        data: kpiValues,
                        backgroundColor: kpiColors,
                        borderRadius: 8,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                },
            });
        } else {
            kpiChart.data.labels = kpiLabels;
            kpiChart.data.datasets[0].data = kpiValues;
            kpiChart.data.datasets[0].backgroundColor = kpiColors;
            kpiChart.update('active');
        }

        if (!revenueChart) {
            revenueChart = new window.Chart(revenueCanvas, {
                type: 'line',
                data: {
                    labels: revenueLabels,
                    datasets: [{
                        label: 'CA',
                        data: revenueValues,
                        borderColor: '#3b7ddd',
                        backgroundColor: 'rgba(59, 125, 221, .15)',
                        fill: true,
                        tension: 0.35,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                },
            });
        } else {
            revenueChart.data.labels = revenueLabels;
            revenueChart.data.datasets[0].data = revenueValues;
            revenueChart.update('active');
        }

        if (hasChanged) {
            flashWrap(kpiChartWrap);
            flashWrap(revenueChartWrap);
        }
    };

    const refreshLive = async () => {
        try {
            if (liveStateEl) {
                liveStateEl.textContent = 'Analyse en cours';
            }
            const response = await fetch(liveEndpoint, { headers: { 'Accept': 'application/json' } });
            const json = await response.json();
            if (!response.ok || !json.ok) {
                throw new Error(json.error || 'Erreur live');
            }
            animateLiveText(json.live_text || '', false);
            renderActionableSteps(json.live_text || '');
            if (liveUpdatedAtEl) {
                liveUpdatedAtEl.textContent = formatDateTime(json.generated_at);
            }
            if (liveStateEl) {
                liveStateEl.textContent = 'IA synchronisée';
            }
            updateCharts(json.charts || {});
        } catch (error) {
            if (liveStateEl) {
                liveStateEl.textContent = 'Mode dégradé';
            }
            animateLiveText('Flux live indisponible temporairement. L’IA reprend dès que possible.', true);
            renderActionableSteps('');
        }
    };

    if (liveStepsContainer) {
        liveStepsContainer.addEventListener('click', async function (event) {
            const btn = event.target.closest('.js-ai-create-task');
            if (!btn) return;
            const title = btn.getAttribute('data-title') || 'Tâche IA';
            const description = btn.getAttribute('data-description') || '';
            btn.disabled = true;
            const initial = btn.textContent;
            btn.textContent = 'Création...';
            try {
                await createTaskFromStep(title, description);
                btn.textContent = 'Créée';
                btn.classList.remove('btn-outline-primary');
                btn.classList.add('btn-success');
                window.setTimeout(() => window.location.reload(), 500);
            } catch (error) {
                btn.disabled = false;
                btn.textContent = initial || 'Créer tâche';
                window.alert("Impossible de créer la tâche IA pour le moment.");
            }
        });
    }

    refreshLive();
    window.setInterval(refreshLive, 30000);
});
</script>
@endpush

