@extends('layouts.app')

@section('title', 'Solde Trésorerie | Sitiame Capital')
@section('page_title', 'Solde de Trésorerie')

@push('styles')
<style>
    /* Fintech Electric Indigo & Cyan Theme */
    .fintech-container { background: #f0f9ff; min-height: 100vh; }
    .fintech-card { background: #ffffff; border: 1px solid #e0f2fe; border-radius: 16px; box-shadow: 0 4px 20px rgba(6, 182, 212, 0.05); transition: all 0.2s ease; }
    .fintech-card:hover { border-color: #06b6d4; box-shadow: 0 6px 24px rgba(79, 70, 229, 0.1); }
    .fintech-hero-date { font-size: 0.85rem; font-weight: 600; color: #0284c7; }
    .fintech-hero-title { font-size: 1.85rem; font-weight: 800; color: #1e1b4b; margin-top: 2px; margin-bottom: 12px; }
    .fintech-pill-bar { display: inline-flex; align-items: center; gap: 16px; background: #ffffff; border: 1px solid #bae6fd; border-radius: 9999px; padding: 6px 20px; box-shadow: 0 2px 8px rgba(6, 182, 212, 0.08); flex-wrap: wrap; }
    .fintech-pill-item { font-size: 0.84rem; font-weight: 700; color: #4338ca; display: flex; align-items: center; gap: 6px; }
</style>
@endpush

@section('content')
<div class="container-fluid p-0">
    @php

        $monthInflow = (float) $monthTransactions->where('type', 'encaissement')->sum('amount');
        $monthOutflow = (float) $monthTransactions->where('type', 'decaissement')->sum('amount');
        $monthNet = $monthInflow - $monthOutflow;
        $monthCount = (int) $monthTransactions->count();
        $avgOperation = $monthCount > 0 ? (($monthInflow + $monthOutflow) / $monthCount) : 0;
        $perfIndicators = $perfIndicators ?? [];
        $tauxExecution = (float) ($perfIndicators['tauxExecution'] ?? 0);
        $tauxCouverture = $perfIndicators['tauxCouvertureDecaissements'] ?? null;
        $autonomieJours = $perfIndicators['autonomieJours'] ?? null;
        $projectionFinMois = (float) ($perfIndicators['projectionFinMois'] ?? $soldeActuel);
        $encaissementsPlanifies = (float) ($perfIndicators['encaissementsPlanifies'] ?? 0);
        $decaissementsPlanifies = (float) ($perfIndicators['decaissementsPlanifies'] ?? 0);
        $projectionDelta = $projectionFinMois - (float) $soldeActuel;
        $projectionDeltaPct = (float) $soldeActuel !== 0.0
            ? ($projectionDelta / abs((float) $soldeActuel)) * 100
            : null;
        $executionProgress = max(0.0, min(100.0, $tauxExecution));
        $couvertureProgress = max(0.0, min(140.0, (float) ($tauxCouverture ?? 0)));
        $healthCode = 'ok';
        $healthLabel = 'Saine';
        if ($projectionFinMois < 0 || $tauxExecution < 45) {
            $healthCode = 'risk';
            $healthLabel = 'Sous tension';
        } elseif (($tauxCouverture !== null && $tauxCouverture < 100) || ($autonomieJours !== null && $autonomieJours < 30)) {
            $healthCode = 'warn';
            $healthLabel = 'À surveiller';
        }
        $monthTransactionsChart = $monthTransactions->map(function ($tx) {
            return [
                'date' => $tx->transaction_date?->format('Y-m-d'),
                'type' => $tx->type,
                'amount' => (float) $tx->amount,
            ];
        })->values();
    @endphp

    <div class="balance-header-card mb-4">
        <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3">
            <div>
                <h4 class="mb-1">Tableau de bord trésorerie</h4>
                <p class="text-muted mb-0">Suivi du solde, des flux mensuels, de l’exécution opérationnelle et de la projection de fin de période.</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="balance-health-chip {{ $healthCode }}">
                    <i data-feather="{{ $healthCode === 'ok' ? 'check-circle' : ($healthCode === 'warn' ? 'alert-triangle' : 'alert-octagon') }}"></i>
                    Santé : {{ $healthLabel }}
                </span>
                <a href="{{ route('treasury.tracking') }}" class="btn btn-outline-primary btn-sm">Suivi des flux</a>
                <a href="{{ route('treasury.forecast') }}" class="btn btn-primary btn-sm">Prévisions</a>
            </div>
        </div>
    </div>

    <div class="balance-panel mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('treasury.balance') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label mb-1">Date de début</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom ?? '' }}">
                </div>
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label mb-1">Date de fin</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo ?? '' }}">
                </div>
                <div class="col-12 col-md-4 col-lg-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
                    <a href="{{ route('treasury.balance') }}" class="btn btn-outline-secondary btn-sm">Réinitialiser</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="balance-kpi-card kpi-primary">
                <div class="card-body d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="balance-kpi-title" title="Toutes les opérations marquées « effectué », y compris un chèque pas encore crédité en banque.">Solde actuel (théorique)</div>
                        <div class="balance-kpi-value {{ $soldeActuel >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($soldeActuel, 0, ',', ' ') }} FCFA</div>
                        <div class="balance-kpi-meta" title="Ne compte que les fonds dont la date de valeur est déjà passée — ce qui est réellement disponible en banque aujourd'hui.">
                            Trésorerie réelle disponible : <strong class="{{ $soldeActuelReel >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($soldeActuelReel, 0, ',', ' ') }} FCFA</strong>
                        </div>
                        <div class="balance-kpi-meta">Ouverture période : {{ number_format($soldeOuverture, 0, ',', ' ') }} FCFA</div>
                    </div>
                    <span class="balance-kpi-icon icon-primary"><i data-feather="dollar-sign" style="width:20px;height:20px;"></i></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="balance-kpi-card kpi-success">
                <div class="card-body d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="balance-kpi-title">Encaissements du mois</div>
                        <div class="balance-kpi-value text-success">{{ number_format($monthInflow, 0, ',', ' ') }} FCFA</div>
                        <div class="balance-kpi-meta">Cumul effectué</div>
                    </div>
                    <span class="balance-kpi-icon icon-success"><i data-feather="trending-up" style="width:20px;height:20px;"></i></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="balance-kpi-card kpi-danger">
                <div class="card-body d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="balance-kpi-title">Décaissements du mois</div>
                        <div class="balance-kpi-value text-danger">{{ number_format($monthOutflow, 0, ',', ' ') }} FCFA</div>
                        <div class="balance-kpi-meta">Cumul effectué</div>
                    </div>
                    <span class="balance-kpi-icon icon-danger"><i data-feather="trending-down" style="width:20px;height:20px;"></i></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="balance-kpi-card {{ $monthNet >= 0 ? 'kpi-success' : 'kpi-warning' }}">
                <div class="card-body d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="balance-kpi-title">Net mensuel</div>
                        <div class="balance-kpi-value {{ $monthNet >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($monthNet, 0, ',', ' ') }} FCFA</div>
                        <div class="balance-kpi-meta">Entrées - sorties du mois</div>
                    </div>
                    <span class="balance-kpi-icon {{ $monthNet >= 0 ? 'icon-success' : 'icon-warning' }}"><i data-feather="activity" style="width:20px;height:20px;"></i></span>
                </div>
            </div>
        </div>
    </div>

    @if($projectionFinMois < 0)
        <div class="alert alert-danger d-flex align-items-start gap-2 mb-4">
            <i data-feather="alert-triangle" class="mt-1"></i>
            <div>
                <strong>Projection négative détectée.</strong>
                <div class="small mb-0">La fin de période est projetée en déficit. Priorise le suivi des décaissements et les relances d’encaissement.</div>
            </div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-8">
            <div class="balance-panel h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Courbes de trésorerie</h5>
                        <small class="text-muted">{{ $monthStart?->format('d/m/Y') }} - {{ $monthEnd?->format('d/m/Y') }}</small>
                    </div>
                    <div class="balance-chart-wrap">
                        @php
                            $chartDates = array_keys($dailyBalances ?? []);
                            $txByDay = collect($monthTransactionsChart ?? [])->groupBy('date');
                            $runningChart = (float) $soldeOuverture;
                            $seriesIn = [];
                            $seriesOut = [];
                            $seriesBal = [];

                            foreach ($chartDates as $chartDate) {
                                $dayRows = $txByDay->get($chartDate, collect());
                                $dayIn = (float) $dayRows->where('type', 'encaissement')->sum('amount');
                                $dayOut = (float) $dayRows->where('type', 'decaissement')->sum('amount');
                                $runningChart += $dayIn - $dayOut;
                                $seriesIn[] = $dayIn;
                                $seriesOut[] = $dayOut;
                                $seriesBal[] = $runningChart;
                            }

                            $allSeriesValues = array_merge($seriesIn, $seriesOut, $seriesBal);
                            $hasRealValues = count($chartDates) > 0 && count($allSeriesValues) > 0;
                            $hasChartData = $hasRealValues && (max($allSeriesValues) > 0 || min($allSeriesValues) < 0);
                            $chartMin = $hasChartData ? min(min($allSeriesValues), 0) : 0;
                            $chartMax = $hasChartData ? max(max($allSeriesValues), 1) : 1;
                            $chartSpan = max(1, $chartMax - $chartMin);

                            $svgWidth = 1100;
                            $svgHeight = 340;
                            $padTop = 18;
                            $padRight = 24;
                            $padBottom = 30;
                            $padLeft = 56;
                            $plotWidth = $svgWidth - $padLeft - $padRight;
                            $plotHeight = $svgHeight - $padTop - $padBottom;

                            $xCoord = function ($index, $total) use ($padLeft, $plotWidth) {
                                if ($total <= 1) {
                                    return $padLeft;
                                }
                                return $padLeft + ($index * ($plotWidth / ($total - 1)));
                            };
                            $yCoord = function ($value) use ($padTop, $plotHeight, $chartMax, $chartSpan) {
                                return $padTop + (($chartMax - $value) / $chartSpan) * $plotHeight;
                            };
                            $buildPoints = function ($series) use ($xCoord, $yCoord) {
                                $total = count($series);
                                $points = [];
                                foreach ($series as $idx => $val) {
                                    $points[] = round($xCoord($idx, $total), 2) . ',' . round($yCoord((float) $val), 2);
                                }
                                return implode(' ', $points);
                            };
                            $balancePoints = $buildPoints($seriesBal);
                            $balanceAreaPoints = $balancePoints;
                            if (count($seriesBal) > 1) {
                                $firstX = round($xCoord(0, count($seriesBal)), 2);
                                $lastX = round($xCoord(count($seriesBal) - 1, count($seriesBal)), 2);
                                $baseY = round($padTop + $plotHeight, 2);
                                $balanceAreaPoints .= ' ' . $lastX . ',' . $baseY . ' ' . $firstX . ',' . $baseY;
                            }
                            $tickIndexes = array_unique(array_filter([
                                0,
                                count($chartDates) > 2 ? (int) floor((count($chartDates) - 1) / 2) : null,
                                count($chartDates) > 0 ? count($chartDates) - 1 : null,
                            ], fn ($v) => $v !== null));
                        @endphp

                        @if($hasChartData)
                            <div class="w-100" style="height: 340px;">
                                <svg viewBox="0 0 {{ $svgWidth }} {{ $svgHeight }}" width="100%" height="100%" preserveAspectRatio="none" aria-label="Courbes de trésorerie">
                                    <defs>
                                        <linearGradient id="balanceAreaGrad" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#0d6efd" stop-opacity="0.22" />
                                            <stop offset="100%" stop-color="#0d6efd" stop-opacity="0.02" />
                                        </linearGradient>
                                    </defs>
                                    @for($i = 0; $i <= 4; $i++)
                                        @php
                                            $gridY = $padTop + ($plotHeight / 4) * $i;
                                            $gridVal = $chartMax - ($chartSpan / 4) * $i;
                                        @endphp
                                        <line x1="{{ $padLeft }}" y1="{{ $gridY }}" x2="{{ $svgWidth - $padRight }}" y2="{{ $gridY }}" stroke="#edf2f7" stroke-width="1" />
                                        <text x="6" y="{{ $gridY + 4 }}" font-size="11" fill="#6c757d">{{ number_format($gridVal, 0, ',', ' ') }}</text>
                                    @endfor

                                    <polygon points="{{ $balanceAreaPoints }}" fill="url(#balanceAreaGrad)" />
                                    <polyline fill="none" stroke="#198754" stroke-width="2.3" points="{{ $buildPoints($seriesIn) }}" />
                                    <polyline fill="none" stroke="#dc3545" stroke-width="2.3" points="{{ $buildPoints($seriesOut) }}" />
                                    <polyline fill="none" stroke="#0d6efd" stroke-width="2.5" points="{{ $balancePoints }}" />

                                    @foreach($tickIndexes as $tickIdx)
                                        @php
                                            $tickX = $xCoord($tickIdx, count($chartDates));
                                            $tickLabel = \Carbon\Carbon::parse($chartDates[$tickIdx])->format('d/m');
                                        @endphp
                                        <line x1="{{ $tickX }}" y1="{{ $padTop + $plotHeight }}" x2="{{ $tickX }}" y2="{{ $padTop + $plotHeight + 4 }}" stroke="#adb5bd" stroke-width="1" />
                                        <text x="{{ $tickX - 12 }}" y="{{ $padTop + $plotHeight + 16 }}" font-size="10" fill="#6c757d">{{ $tickLabel }}</text>
                                    @endforeach
                                </svg>
                            </div>
                            <div class="d-flex flex-wrap gap-2 pt-2">
                                <span class="balance-legend"><span class="balance-legend-dot" style="background:#198754;"></span>Encaissements</span>
                                <span class="balance-legend"><span class="balance-legend-dot" style="background:#dc3545;"></span>Décaissements</span>
                                <span class="balance-legend"><span class="balance-legend-dot" style="background:#0d6efd;"></span>Solde cumulé</span>
                            </div>
                        @else
                            <div class="text-muted small text-center py-4">
                                Aucune donnée disponible pour tracer la courbe sur cette période.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="balance-panel h-100">
                <div class="card-body">
                    <h5 class="mb-3">Indicateurs clés</h5>
                    <div class="balance-progress-label">
                        <span>Taux d’exécution</span>
                        <strong>{{ number_format($tauxExecution, 1, ',', ' ') }} %</strong>
                    </div>
                    <div class="balance-progress mb-3">
                        <span style="width: {{ $executionProgress }}%; background: #0d6efd;"></span>
                    </div>
                    <div class="balance-progress-label">
                        <span>Couverture des décaissements</span>
                        <strong class="{{ ($tauxCouverture ?? 0) >= 100 ? 'text-success' : 'text-danger' }}">
                            {{ $tauxCouverture !== null ? number_format($tauxCouverture, 1, ',', ' ') . ' %' : 'N/A' }}
                        </strong>
                    </div>
                    <div class="balance-progress mb-3">
                        <span style="width: {{ min($couvertureProgress, 100) }}%; background: {{ ($tauxCouverture ?? 0) >= 100 ? '#198754' : '#dc3545' }};"></span>
                    </div>
                    <div class="balance-insight-item">
                        <span>Autonomie de trésorerie</span>
                        <strong class="{{ $autonomieJours !== null && $autonomieJours >= 30 ? 'text-success' : 'text-warning' }}">
                            {{ $autonomieJours !== null ? number_format($autonomieJours, 1, ',', ' ') . ' jours' : 'Confortable' }}
                        </strong>
                    </div>
                    <div class="balance-insight-item">
                        <span>Volume d’opérations</span>
                        <strong>{{ $monthCount }}</strong>
                    </div>
                    <div class="balance-insight-item">
                        <span>Montant moyen / opération</span>
                        <strong>{{ number_format($avgOperation, 0, ',', ' ') }} FCFA</strong>
                    </div>
                    <div class="mt-3 p-3 rounded bg-light">
                        <div class="small text-muted mb-1">Projection de fin de mois</div>
                        <div class="fw-bold {{ $projectionFinMois >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($projectionFinMois, 0, ',', ' ') }} FCFA
                        </div>
                        <div class="small text-muted mb-1">Variation projetée</div>
                        <div class="fw-bold {{ $projectionDelta >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $projectionDelta >= 0 ? '+' : '' }}{{ number_format($projectionDelta, 0, ',', ' ') }} FCFA
                            @if($projectionDeltaPct !== null)
                                <span class="small">({{ $projectionDelta >= 0 ? '+' : '' }}{{ number_format($projectionDeltaPct, 1, ',', ' ') }} %)</span>
                            @endif
                        </div>
                        <div class="small text-muted mt-1">
                            Planifié : +{{ number_format($encaissementsPlanifies, 0, ',', ' ') }}
                            / -{{ number_format($decaissementsPlanifies, 0, ',', ' ') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="balance-panel">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h5 class="mb-0">Transactions de la période</h5>
                <small class="text-muted">{{ $monthTransactions->count() }} opération(s)</small>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-striped balance-table mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th class="text-end">Encaissement</th>
                            <th class="text-end">Décaissement</th>
                            <th class="text-end">Solde cumulé</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $runningBalance = $soldeOuverture; @endphp
                        @forelse($monthTransactions as $tx)
                            @php
                                if ($tx->type === 'encaissement') {
                                    $runningBalance += $tx->amount;
                                } else {
                                    $runningBalance -= $tx->amount;
                                }
                            @endphp
                            <tr>
                                <td data-label="Date">{{ $tx->transaction_date?->format('d/m/Y') }}</td>
                                <td data-label="Description">{{ \Illuminate\Support\Str::limit($tx->description, 65) }}</td>
                                <td data-label="Encaissement" class="text-md-end">
                                    @if($tx->type === 'encaissement')
                                        <span class="balance-amount-badge in">+ {{ number_format($tx->amount, 0, ',', ' ') }} FCFA</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td data-label="Décaissement" class="text-md-end">
                                    @if($tx->type === 'decaissement')
                                        <span class="balance-amount-badge out">- {{ number_format($tx->amount, 0, ',', ' ') }} FCFA</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td data-label="Solde cumulé" class="text-md-end fw-bold">{{ number_format($runningBalance, 0, ',', ' ') }} FCFA</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Aucune transaction sur la période sélectionnée.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="balance-mobile-note mt-2">Astuce mobile : chaque ligne est affichée en carte pour faciliter la lecture sur petit écran.</div>
        </div>
    </div>
</div>
@endsection
