@extends('layouts.app')

@section('title', 'Prévisions Trésorerie | Sitiame Capital')
@section('page_title', 'Prévisions de trésorerie')

@push('styles')
<style>
    .tracking-crypto {
        background: linear-gradient(180deg, #f5f7fb 0%, #eef3f9 100%);
        border-radius: 1rem;
        padding: 1rem;
    }
    .crypto-headline {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        gap: .75rem;
    }
    .crypto-actions .btn { border-radius: .65rem; }
    .crypto-kpi {
        border: 1px solid #e5e7eb;
        border-radius: .95rem;
        background: #fff;
        box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
        height: 100%;
    }
    .crypto-kpi .kpi-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6c757d;
    }
    .crypto-kpi .kpi-value {
        font-weight: 700;
        font-size: 1.25rem;
        margin-top: 0.2rem;
    }
    .kpi-solde { border-left: 4px solid #3b82f6; }
    .kpi-in { border-left: 4px solid #22c55e; }
    .kpi-out { border-left: 4px solid #ef4444; }
    .kpi-net { border-left: 4px solid #f59e0b; }
    .kpi-end { border-left: 4px solid #111827; }
    .treasury-forecast-card {
        border: 1px solid #e5e7eb;
        border-radius: .95rem;
        background: #fff;
        box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
    }
    .treasury-forecast-card .card-header {
        background: #f8fafc;
        border-bottom: 1px solid #e5e7eb;
    }
    .treasury-forecast-card .card-title {
        color: #0f172a;
        margin: 0;
        font-weight: 600;
    }
    .market-chart-wrap {
        background: #fff;
        border: 1px solid #e9edf5;
        border-radius: 0.8rem;
        padding: 0.45rem 0.55rem;
    }
    .chart-legend-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        border: 1px solid #e6ebf3;
        border-radius: 999px;
        padding: 0.2rem 0.65rem;
        background: #fafcff;
        color: #344054;
        font-size: 0.74rem;
        font-weight: 600;
    }
    .chart-legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
    }
    .forecast-table thead th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #344054;
        background: #f8fbff;
        border-bottom: 1px solid #dbe3ef;
    }
    .forecast-table tbody tr:hover {
        background: #f5f9ff;
    }
    @media (max-width: 767.98px) {
        .forecast-table thead { display: none; }
        .forecast-table tbody, .forecast-table tr, .forecast-table td {
            display: block;
            width: 100%;
        }
        .forecast-table tbody tr {
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
            margin-bottom: 0.75rem;
            overflow: hidden;
        }
        .forecast-table tbody td {
            border-bottom: 1px solid #f1f3f5;
            padding: 0.55rem 0.75rem;
            text-align: left !important;
        }
        .forecast-table tbody td[data-label]::before {
            content: attr(data-label);
            display: block;
            font-size: 0.68rem;
            text-transform: uppercase;
            color: #6c757d;
            margin-bottom: 0.2rem;
        }
    }
    .governance-audit-table td {
        font-size: 0.82rem;
        vertical-align: top;
    }
    .governance-audit-table code {
        font-size: 0.72rem;
        word-break: break-word;
    }
    .scenario-compact {
        border: 1px solid #e5e7eb;
        border-radius: .95rem;
        background: #fff;
        box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
        padding: 0.9rem 1rem;
    }
    .scenario-compact .scenario-title {
        font-size: .75rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #64748b;
        font-weight: 700;
        margin-bottom: .45rem;
    }
    .scenario-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .75rem;
    }
    .scenario-item {
        border: 1px solid #e6ebf3;
        border-radius: .7rem;
        padding: .6rem .65rem;
        background: #fafcff;
    }
    .scenario-item-label {
        font-size: .68rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #667085;
        font-weight: 700;
        margin-bottom: .2rem;
    }
    .scenario-item-value {
        font-weight: 700;
        font-size: 1rem;
        line-height: 1.2;
    }
    @media (max-width: 991.98px) {
        .scenario-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 575.98px) {
        .scenario-grid { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid p-0 tracking-crypto">
            <div class="crypto-headline">
                <div>
                    <h2 class="h3 mb-1"><strong>Tableau de bord</strong> Prévisions trésorerie</h2>
                    <p class="text-muted mb-0">
                        Horizon {{ $horizon }} jours — jusqu'au {{ $periodEnd->format('d/m/Y') }}.
                    </p>
                </div>
                <div class="crypto-actions d-flex gap-2">
                    <a href="{{ route('treasury.tracking') }}" class="btn btn-primary btn-sm">Retour suivi</a>
                    <a href="{{ route('treasury.create') }}" class="btn btn-outline-secondary btn-sm">Nouvelle transaction</a>
                </div>
            </div>

            @error('period_month')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror

            <form method="GET" action="{{ route('treasury.forecast') }}" class="row g-2 align-items-end mb-4">
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label small text-muted mb-1">Horizon de prévision</label>
                    <select name="horizon" class="form-select" onchange="this.form.submit()">
                        <option value="30" {{ (int) $horizon === 30 ? 'selected' : '' }}>30 jours</option>
                        <option value="60" {{ (int) $horizon === 60 ? 'selected' : '' }}>60 jours</option>
                        <option value="90" {{ (int) $horizon === 90 ? 'selected' : '' }}>90 jours</option>
                    </select>
                </div>
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label small text-muted mb-1">Scénario</label>
                    <select name="scenario" class="form-select" onchange="this.form.submit()">
                        <option value="prudent" {{ ($scenario ?? 'realiste') === 'prudent' ? 'selected' : '' }}>Prudent</option>
                        <option value="realiste" {{ ($scenario ?? 'realiste') === 'realiste' ? 'selected' : '' }}>Réaliste</option>
                        <option value="optimiste" {{ ($scenario ?? 'realiste') === 'optimiste' ? 'selected' : '' }}>Optimiste</option>
                    </select>
                </div>
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label small text-muted mb-1">Seuil de sécurité (FCFA)</label>
                    <input type="number" class="form-control" name="safety_threshold" min="0" step="1000" value="{{ (int) ($safetyThreshold ?? 0) }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Actualiser</button>
                </div>
                @if(request()->filled('governance_month'))
                    <input type="hidden" name="governance_month" value="{{ request('governance_month') }}">
                @endif
            </form>

            @php
                $auditActionLabels = [
                    'treasury.period.lock' => 'Verrouillage période',
                    'treasury.period.unlock' => 'Déverrouillage période',
                    'treasury.forecast.validate' => 'Validation prévision',
                    'treasury.transaction.create' => 'Création transaction',
                    'treasury.transaction.update' => 'Mise à jour transaction',
                    'treasury.transaction.delete' => 'Suppression transaction',
                ];
            @endphp
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card treasury-forecast-card">
                        <div class="card-header">
                            <h3 class="card-title mb-0">Gouvernance (verrouillage &amp; audit)</h3>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Un mois <strong>verrouillé</strong> interdit toute création ou modification de transactions dont la date tombe dans ce mois civil.
                                La <strong>validation</strong> fige définitivement la période : le déverrouillage n'est plus possible depuis l'interface.
                            </p>
                            <form method="post" class="border rounded p-3 mb-4 bg-light">
                                @csrf
                                <input type="hidden" name="horizon" value="{{ $horizon }}">
                                <input type="hidden" name="scenario" value="{{ $scenario }}">
                                <input type="hidden" name="safety_threshold" value="{{ (int) ($safetyThreshold ?? 0) }}">
                                <div class="row g-3 align-items-end">
                                    <div class="col-12 col-md-4">
                                        <label class="form-label small text-muted mb-1">Mois civil concerné</label>
                                        <input type="month" name="period_month" class="form-control" value="{{ $governancePeriodMonth ?? now()->format('Y-m') }}" required>
                                    </div>
                                    <div class="col-12 col-md-5">
                                        <label class="form-label small text-muted mb-1">Notes (optionnel, verrouillage)</label>
                                        <input type="text" name="notes" class="form-control" maxlength="500" placeholder="Référence interne, commentaire…">
                                    </div>
                                    <div class="col-12 col-md-3 d-flex flex-wrap gap-2">
                                        <button type="submit" class="btn btn-warning" formaction="{{ route('treasury.forecast.period.lock') }}" formmethod="post">Verrouiller</button>
                                        <button type="submit" class="btn btn-outline-secondary" formaction="{{ route('treasury.forecast.period.unlock') }}" formmethod="post">Déverrouiller</button>
                                        <button type="submit" class="btn btn-success" formaction="{{ route('treasury.forecast.period.validate') }}" formmethod="post">Valider</button>
                                    </div>
                                </div>
                            </form>

                            <div class="row g-3">
                                <div class="col-12 col-lg-6">
                                    <h4 class="h6 text-muted mb-2">Périodes verrouillées</h4>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0 governance-audit-table">
                                            <thead>
                                                <tr>
                                                    <th>Mois</th>
                                                    <th>Verrouillé le</th>
                                                    <th>Validé</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse(($periodLocks ?? []) as $lock)
                                                    <tr>
                                                        <td><strong>{{ $lock->period_month }}</strong></td>
                                                        <td>{{ $lock->locked_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                                        <td>
                                                            @if($lock->validated_at)
                                                                <span class="badge bg-success">{{ $lock->validated_at->format('d/m/Y H:i') }}</span>
                                                            @else
                                                                <span class="text-muted">Non</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="3" class="text-muted">Aucune période verrouillée.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <h4 class="h6 text-muted mb-2">Journal d'audit (30 derniers)</h4>
                                    <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                                        <table class="table table-sm table-hover mb-0 governance-audit-table">
                                            <thead class="position-sticky top-0 bg-white">
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Action</th>
                                                    <th>Utilisateur connecté</th>
                                                    <th>Détail</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse(($auditLogs ?? []) as $log)
                                                    <tr>
                                                        <td>{{ $log->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                                        <td>{{ $auditActionLabels[$log->action] ?? $log->action }}</td>
                                                        <td>
                                                            @php
                                                                $actorName = $log->actor?->name ?? $log->user?->name ?? '—';
                                                                $actorEmail = $log->actor?->email ?? $log->user?->email ?? null;
                                                            @endphp
                                                            <div class="small fw-medium">{{ $actorName }}</div>
                                                            @if($actorEmail)
                                                                <div class="small text-muted">{{ $actorEmail }}</div>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if(!empty($log->properties))
                                                                <code class="d-block">{{ \Illuminate\Support\Str::limit(json_encode($log->properties, JSON_UNESCAPED_UNICODE), 120) }}</code>
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="4" class="text-muted">Aucune entrée d'audit.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if(!empty($riskAlerts))
                <div class="alert alert-danger mb-4">
                    <strong>Alertes de risque ({{ $scenarioLabel ?? 'Réaliste' }})</strong>
                    <ul class="mb-0 mt-1">
                        @foreach($riskAlerts as $alert)
                            <li>{{ $alert }}</li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="alert alert-success mb-4">
                    Aucun signal critique détecté sur le scénario <strong>{{ $scenarioLabel ?? 'Réaliste' }}</strong>.
                </div>
            @endif

            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6 col-xl">
                    <div class="card crypto-kpi kpi-solde">
                        <div class="card-body py-3">
                            <small class="kpi-label d-block" title="Toutes les opérations marquées « effectué », y compris un chèque pas encore crédité en banque.">Solde réalisé (à date, théorique)</small>
                            <div class="kpi-value {{ $currentBalance >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($currentBalance, 0, ',', ' ') }} FCFA
                            </div>
                            <small class="d-block text-muted mt-1" title="Ne compte que les fonds dont la date de valeur est déjà passée.">
                                Réel disponible : <strong class="{{ $currentBalanceReel >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($currentBalanceReel, 0, ',', ' ') }} FCFA</strong>
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl">
                    <div class="card crypto-kpi kpi-in">
                        <div class="card-body py-3">
                            <small class="kpi-label d-block">Encaissements prévus</small>
                            <div class="kpi-value text-success">{{ number_format($totalPlannedIn, 0, ',', ' ') }} FCFA</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl">
                    <div class="card crypto-kpi kpi-out">
                        <div class="card-body py-3">
                            <small class="kpi-label d-block">Décaissements prévus</small>
                            <div class="kpi-value text-danger">{{ number_format($totalPlannedOut, 0, ',', ' ') }} FCFA</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl">
                    <div class="card crypto-kpi kpi-net">
                        <div class="card-body py-3">
                            <small class="kpi-label d-block">Impact net planifié</small>
                            <div class="kpi-value {{ $netPlanned >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $netPlanned >= 0 ? '+' : '' }}{{ number_format($netPlanned, 0, ',', ' ') }} FCFA
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl">
                    <div class="card crypto-kpi kpi-end">
                        <div class="card-body py-3">
                            <small class="kpi-label d-block">Solde projeté fin de période</small>
                            <div class="kpi-value {{ $projectedEndBalance >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($projectedEndBalance, 0, ',', ' ') }} FCFA
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-12">
                    <div class="scenario-compact">
                        <div class="scenario-title">Vue scénario : {{ $scenarioLabel ?? 'Réaliste' }}</div>
                        <div class="scenario-grid">
                            <div class="scenario-item">
                                <div class="scenario-item-label">Encaissements</div>
                                <div class="scenario-item-value text-success">{{ number_format($scenarioPlannedIn ?? 0, 0, ',', ' ') }} FCFA</div>
                            </div>
                            <div class="scenario-item">
                                <div class="scenario-item-label">Décaissements</div>
                                <div class="scenario-item-value text-danger">{{ number_format($scenarioPlannedOut ?? 0, 0, ',', ' ') }} FCFA</div>
                            </div>
                            <div class="scenario-item">
                                <div class="scenario-item-label">Net</div>
                                <div class="scenario-item-value {{ ($scenarioNet ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ ($scenarioNet ?? 0) >= 0 ? '+' : '' }}{{ number_format($scenarioNet ?? 0, 0, ',', ' ') }} FCFA
                                </div>
                            </div>
                            <div class="scenario-item">
                                <div class="scenario-item-label">Solde minimum</div>
                                <div class="scenario-item-value {{ ($minScenarioBalance ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ number_format($minScenarioBalance ?? 0, 0, ',', ' ') }} FCFA
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @php
                $projCollection = collect($scenarioWeeklyProjections ?? $weeklyProjections ?? []);
                $balances = $projCollection->pluck('balance')->map(fn ($v) => (float) $v)->all();
                $inflows = $projCollection->pluck('inflow')->map(fn ($v) => (float) $v)->all();
                $outflows = $projCollection->pluck('outflow')->map(fn ($v) => (float) $v)->all();
                $weekLabels = $projCollection->pluck('week')->all();
                $n = count($balances);
                $allVals = array_merge($balances, $inflows, $outflows);
                $hasChart = $n > 0 && count($allVals) > 0;
                $chartMin = $hasChart ? min(min($allVals), 0) : 0;
                $chartMax = $hasChart ? max(max($allVals), 1) : 1;
                $chartSpan = max(1, $chartMax - $chartMin);
                $svgW = 1100;
                $svgH = 320;
                $padT = 18;
                $padR = 20;
                $padB = 26;
                $padL = 56;
                $plotW = $svgW - $padL - $padR;
                $plotH = $svgH - $padT - $padB;
                $xAt = function ($i, $total) use ($padL, $plotW) {
                    if ($total <= 1) {
                        return $padL;
                    }
                    return $padL + ($i * ($plotW / ($total - 1)));
                };
                $yAt = function ($v) use ($padT, $plotH, $chartMax, $chartSpan) {
                    return $padT + (($chartMax - $v) / $chartSpan) * $plotH;
                };
                $linePoints = function ($series) use ($xAt, $yAt, $n) {
                    $pts = [];
                    foreach ($series as $idx => $val) {
                        $pts[] = round($xAt($idx, $n), 2) . ',' . round($yAt((float) $val), 2);
                    }
                    return implode(' ', $pts);
                };
                $balPts = $linePoints($balances);
                $inPts = $linePoints($inflows);
                $outPts = $linePoints($outflows);
                $balArea = $balPts;
                if ($n > 1) {
                    $firstX = round($xAt(0, $n), 2);
                    $lastX = round($xAt($n - 1, $n), 2);
                    $baseY = round($padT + $plotH, 2);
                    $balArea .= ' ' . $lastX . ',' . $baseY . ' ' . $firstX . ',' . $baseY;
                }
                $tickIdx = array_values(array_unique(array_filter([
                    0,
                    $n > 2 ? (int) floor(($n - 1) / 2) : null,
                    $n > 1 ? $n - 1 : null,
                ], fn ($v) => $v !== null)));
            @endphp

            <div class="row mb-4">
                <div class="col-12">
                    <div class="card treasury-forecast-card">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <h3 class="card-title">Évolution hebdomadaire (solde cumulé après flux planifiés)</h3>
                        </div>
                        <div class="card-body">
                            @if($hasChart)
                                <div class="market-chart-wrap">
                                    <div class="w-100" style="height: 320px;">
                                        <svg viewBox="0 0 {{ $svgW }} {{ $svgH }}" width="100%" height="100%" preserveAspectRatio="none" aria-label="Prévision trésorerie">
                                            <defs>
                                                <linearGradient id="forecastBalGrad" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0%" stop-color="#2563eb" stop-opacity="0.2" />
                                                    <stop offset="100%" stop-color="#2563eb" stop-opacity="0.02" />
                                                </linearGradient>
                                            </defs>
                                            @for($i = 0; $i <= 4; $i++)
                                                @php
                                                    $gy = $padT + ($plotH / 4) * $i;
                                                    $gv = $chartMax - ($chartSpan / 4) * $i;
                                                @endphp
                                                <line x1="{{ $padL }}" y1="{{ $gy }}" x2="{{ $svgW - $padR }}" y2="{{ $gy }}" stroke="#eef2f7" stroke-width="1" />
                                                <text x="6" y="{{ $gy + 4 }}" font-size="11" fill="#667085">{{ number_format($gv, 0, ',', ' ') }}</text>
                                            @endfor
                                            @if($n > 1)
                                                <polygon points="{{ $balArea }}" fill="url(#forecastBalGrad)" />
                                            @endif
                                            <polyline fill="none" stroke="#22c55e" stroke-width="2" points="{{ $inPts }}" />
                                            <polyline fill="none" stroke="#ef4444" stroke-width="2" points="{{ $outPts }}" />
                                            <polyline fill="none" stroke="#2563eb" stroke-width="2.5" points="{{ $balPts }}" />
                                            @foreach($tickIdx as $ti)
                                                @if(isset($weekLabels[$ti]))
                                                    @php $tx = $xAt($ti, $n); @endphp
                                                    <text x="{{ $tx - 18 }}" y="{{ $padT + $plotH + 16 }}" font-size="9" fill="#64748b">{{ Str::limit($weekLabels[$ti], 14) }}</text>
                                                @endif
                                            @endforeach
                                        </svg>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 pt-2">
                                        <span class="chart-legend-chip"><span class="chart-legend-dot" style="background:#22c55e;"></span>Encaissements (sem.)</span>
                                        <span class="chart-legend-chip"><span class="chart-legend-dot" style="background:#ef4444;"></span>Décaissements (sem.)</span>
                                        <span class="chart-legend-chip"><span class="chart-legend-dot" style="background:#2563eb;"></span>Solde cumulé</span>
                                    </div>
                                </div>
                            @else
                                <p class="text-muted mb-0">Aucune donnée planifiée sur cette période : ajoutez des mouvements au statut « planifié » pour voir la courbe.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-12">
                    <div class="card treasury-forecast-card">
                        <div class="card-header">
                            <h3 class="card-title">Détail par semaine</h3>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover card-table table-vcenter forecast-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Semaine</th>
                                        <th>Encaissements prévus</th>
                                        <th>Décaissements prévus</th>
                                        <th>Solde cumulé projeté</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($weeklyProjections as $projection)
                                        <tr>
                                            <td data-label="Semaine"><strong>{{ $projection['week'] }}</strong></td>
                                            <td data-label="Encaissements" class="text-success">
                                                {{ $projection['inflow'] > 0 ? '+ ' . number_format($projection['inflow'], 0, ',', ' ') : '—' }} @if($projection['inflow'] > 0) FCFA @endif
                                            </td>
                                            <td data-label="Décaissements" class="text-danger">
                                                {{ $projection['outflow'] > 0 ? '- ' . number_format($projection['outflow'], 0, ',', ' ') : '—' }} @if($projection['outflow'] > 0) FCFA @endif
                                            </td>
                                            <td data-label="Solde">
                                                <strong class="{{ ($projection['balance'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                                    {{ number_format($projection['balance'], 0, ',', ' ') }} FCFA
                                                </strong>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                Aucune projection sur cette période.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-12 col-md-4">
                    <div class="card crypto-kpi kpi-solde">
                        <div class="card-body py-3">
                            <small class="kpi-label d-block">Fiabilité prévisionnelle</small>
                            <div class="kpi-value {{ ($forecastReliabilityScore ?? 0) >= 70 ? 'text-success' : 'text-warning' }}">
                                {{ number_format($forecastReliabilityScore ?? 0, 1, ',', ' ') }} %
                            </div>
                            <small class="text-muted">Score sur 8 semaines glissantes.</small>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card crypto-kpi kpi-net">
                        <div class="card-body py-3">
                            <small class="kpi-label d-block">Écart cumulé (réalisé - prévu)</small>
                            <div class="kpi-value {{ ($forecastGapTotal ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ ($forecastGapTotal ?? 0) >= 0 ? '+' : '' }}{{ number_format($forecastGapTotal ?? 0, 0, ',', ' ') }} FCFA
                            </div>
                            <small class="text-muted">Mesure de dérive globale du budget de trésorerie.</small>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card crypto-kpi kpi-out">
                        <div class="card-body py-3">
                            <small class="kpi-label d-block">Semaines en alerte</small>
                            <div class="kpi-value {{ ($alertWeeksCount ?? 0) > 0 ? 'text-danger' : 'text-success' }}">
                                {{ $alertWeeksCount ?? 0 }}
                            </div>
                            <small class="text-muted">Alerte si écart hebdomadaire > 30%.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-12">
                    <div class="card treasury-forecast-card">
                        <div class="card-header">
                            <h3 class="card-title">Contrôle de fiabilité (Prévu vs Réalisé)</h3>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover card-table table-vcenter forecast-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Semaine</th>
                                        <th>Prévu net</th>
                                        <th>Réalisé net</th>
                                        <th>Écart</th>
                                        <th>Erreur</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse(($accuracyRows ?? []) as $row)
                                        <tr>
                                            <td data-label="Semaine"><strong>{{ $row['week'] }}</strong></td>
                                            <td data-label="Prévu net" class="{{ ($row['planned'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ ($row['planned'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($row['planned'] ?? 0, 0, ',', ' ') }} FCFA
                                            </td>
                                            <td data-label="Réalisé net" class="{{ ($row['actual'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ ($row['actual'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($row['actual'] ?? 0, 0, ',', ' ') }} FCFA
                                            </td>
                                            <td data-label="Écart" class="{{ ($row['gap'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ ($row['gap'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($row['gap'] ?? 0, 0, ',', ' ') }} FCFA
                                            </td>
                                            <td data-label="Erreur">
                                                @if(($row['errorPct'] ?? null) !== null)
                                                    <span class="badge {{ $row['errorPct'] > 30 ? 'bg-danger' : 'bg-success' }}">
                                                        {{ number_format($row['errorPct'], 1, ',', ' ') }} %
                                                    </span>
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                Aucune donnée historique exploitable pour le contrôle de fiabilité.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card treasury-forecast-card">
                        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <h3 class="card-title">Transactions planifiées</h3>
                            <a href="{{ route('treasury.create') }}" class="btn btn-light btn-sm">Nouvelle opération</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover card-table table-vcenter forecast-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Montant</th>
                                        <th>Référence</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($plannedTransactions as $tx)
                                        <tr>
                                            <td data-label="Date">{{ $tx->transaction_date->format('d/m/Y') }}</td>
                                            <td data-label="Type">
                                                <span class="badge bg-{{ $tx->type === 'encaissement' ? 'success' : 'danger' }}">
                                                    {{ ucfirst($tx->type) }}
                                                </span>
                                            </td>
                                            <td data-label="Description">{{ Str::limit($tx->description ?? '', 40) }}</td>
                                            <td data-label="Montant">
                                                <strong class="{{ $tx->type === 'encaissement' ? 'text-success' : 'text-danger' }}">
                                                    {{ $tx->type === 'encaissement' ? '+' : '-' }} {{ number_format($tx->amount, 0, ',', ' ') }} FCFA
                                                </strong>
                                            </td>
                                            <td data-label="Référence">{{ $tx->reference ?? '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                Aucune transaction planifiée sur cet horizon.
                                            </td>
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
