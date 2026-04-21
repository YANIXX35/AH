@extends('layouts.app')

@section('title', 'Solde Trésorerie | Sitiame Capitale')
@section('page_title', '💰 Solde de Trésorerie')

@push('styles')
<style>
    .treasury-balance-shell {
        background: linear-gradient(180deg, #f5f7fb 0%, #eef3f9 100%);
        border-radius: 1rem;
        padding: 1rem;
    }
    .crypto-kpi {
        border: 0;
        border-radius: 1rem;
        box-shadow: 0 8px 24px rgba(16, 24, 40, 0.08);
        overflow: hidden;
    }
    .crypto-kpi .kpi-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6c757d;
    }
    .crypto-kpi .kpi-value {
        font-weight: 700;
        font-size: 1.35rem;
        margin-top: 0.25rem;
    }
    .kpi-encaissement { border-left: 4px solid #22c55e; }
    .kpi-decaissement { border-left: 4px solid #ef4444; }
    .kpi-solde { border-left: 4px solid #3b82f6; }
    .kpi-neutral { border-left: 4px solid #111827; }

    .treasury-balance-card {
        border: 0;
        border-radius: 1rem;
        box-shadow: 0 10px 28px rgba(16, 24, 40, 0.08);
        overflow: hidden;
    }
    .treasury-balance-card .card-header {
        background: #0f2747;
        color: #fff;
        border-bottom: 0;
    }
    .treasury-balance-card .card-title {
        color: #fff;
        margin: 0;
        font-weight: 600;
    }
    .market-toolbar .btn {
        border-radius: 0.45rem;
        font-size: 0.72rem;
        padding: 0.2rem 0.55rem;
    }
    .market-toolbar .btn.active {
        background: #2563eb;
        border-color: #2563eb;
        color: #fff;
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
    .balance-table thead th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #344054;
        border-bottom: 1px solid #dbe3ef;
        background: #f8fbff;
    }
    .balance-table tbody tr:hover {
        background: #f5f9ff;
    }
    @media (max-width: 767.98px) {
        .balance-table thead {
            display: none;
        }
        .balance-table tbody,
        .balance-table tr,
        .balance-table td {
            display: block;
            width: 100%;
        }
        .balance-table tbody tr {
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
            background: #fff;
            margin-bottom: 0.75rem;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.04);
        }
        .balance-table tbody td {
            border-bottom: 1px solid #f1f3f5;
            padding: 0.55rem 0.75rem;
            text-align: left !important;
        }
        .balance-table tbody td:last-child {
            border-bottom: 0;
        }
        .balance-table tbody td[data-label]::before {
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
@endpush

@section('content')
<div class="page-wrapper">
    <div class="container-xl">
        @php
            $monthInflow = (float) $monthTransactions->where('type', 'encaissement')->sum('amount');
            $monthOutflow = (float) $monthTransactions->where('type', 'decaissement')->sum('amount');
            $monthNet = $monthInflow - $monthOutflow;
            $perfIndicators = $perfIndicators ?? [];
            $tauxExecution = (float) ($perfIndicators['tauxExecution'] ?? 0);
            $tauxCouverture = $perfIndicators['tauxCouvertureDecaissements'] ?? null;
            $autonomieJours = $perfIndicators['autonomieJours'] ?? null;
            $projectionFinMois = (float) ($perfIndicators['projectionFinMois'] ?? $soldeActuel);
            $encaissementsPlanifies = (float) ($perfIndicators['encaissementsPlanifies'] ?? 0);
            $decaissementsPlanifies = (float) ($perfIndicators['decaissementsPlanifies'] ?? 0);
            $monthTransactionsChart = $monthTransactions->map(function ($tx) {
                return [
                    'date' => $tx->transaction_date->format('Y-m-d'),
                    'type' => $tx->type,
                    'amount' => (float) $tx->amount,
                ];
            })->values();
        @endphp
        <div class="treasury-balance-shell">
        <!-- Cartes de résumé -->
        <div class="row mb-4">
            <div class="col-12 col-md-4">
                <div class="card crypto-kpi kpi-encaissement">
                    <div class="card-body">
                        <small class="kpi-label d-block">Encaissements effectués</small>
                        <div class="kpi-value text-success">{{ number_format($encaissementsEffectues, 0, ',', ' ') }} FCFA</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card crypto-kpi kpi-decaissement">
                    <div class="card-body">
                        <small class="kpi-label d-block">Décaissements effectués</small>
                        <div class="kpi-value text-danger">{{ number_format($decaissementsEffectues, 0, ',', ' ') }} FCFA</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card crypto-kpi kpi-solde">
                    <div class="card-body">
                        <small class="kpi-label d-block">Solde actuel</small>
                        <div class="kpi-value" style="color: {{ $soldeActuel >= 0 ? '#28a745' : '#dc3545' }}">
                            {{ number_format($soldeActuel, 0, ',', ' ') }} FCFA
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12 col-md-4">
                <div class="card crypto-kpi kpi-encaissement">
                    <div class="card-body">
                        <small class="kpi-label d-block">Encaissements du mois</small>
                        <div class="kpi-value text-success">{{ number_format($monthInflow, 0, ',', ' ') }} FCFA</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card crypto-kpi kpi-decaissement">
                    <div class="card-body">
                        <small class="kpi-label d-block">Décaissements du mois</small>
                        <div class="kpi-value text-danger">{{ number_format($monthOutflow, 0, ',', ' ') }} FCFA</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card crypto-kpi kpi-solde">
                    <div class="card-body">
                        <small class="kpi-label d-block">Performance mensuelle nette</small>
                        <div class="kpi-value {{ $monthNet >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($monthNet, 0, ',', ' ') }} FCFA
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-12">
                <div class="alert alert-info mb-0">
                    <strong>Solde d'ouverture du mois :</strong>
                    {{ number_format($soldeOuverture, 0, ',', ' ') }} FCFA
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card crypto-kpi kpi-neutral">
                    <div class="card-body">
                        <small class="kpi-label d-block">Taux d'exécution</small>
                        <div class="kpi-value">{{ number_format($tauxExecution, 1, ',', ' ') }} %</div>
                        <small class="text-muted">Part des opérations effectuées sur le mois.</small>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card crypto-kpi kpi-solde">
                    <div class="card-body">
                        <small class="kpi-label d-block">Couverture des décaissements</small>
                        <div class="kpi-value {{ ($tauxCouverture ?? 0) >= 100 ? 'text-success' : 'text-danger' }}">
                            {{ $tauxCouverture !== null ? number_format($tauxCouverture, 1, ',', ' ') . ' %' : 'N/A' }}
                        </div>
                        <small class="text-muted">Objectif: au moins 100%.</small>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card crypto-kpi kpi-decaissement">
                    <div class="card-body">
                        <small class="kpi-label d-block">Autonomie de trésorerie</small>
                        <div class="kpi-value {{ $autonomieJours !== null && $autonomieJours >= 30 ? 'text-success' : 'text-warning' }}">
                            {{ $autonomieJours !== null ? number_format($autonomieJours, 1, ',', ' ') . ' jours' : 'Confortable' }}
                        </div>
                        <small class="text-muted">Sur base de la consommation nette mensuelle.</small>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card crypto-kpi kpi-encaissement">
                    <div class="card-body">
                        <small class="kpi-label d-block">Projection de fin de mois</small>
                        <div class="kpi-value {{ $projectionFinMois >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($projectionFinMois, 0, ',', ' ') }} FCFA
                        </div>
                        <small class="text-muted">
                            Planifié: +{{ number_format($encaissementsPlanifies, 0, ',', ' ') }}
                            / -{{ number_format($decaissementsPlanifies, 0, ',', ' ') }}
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Graphique de solde journalier -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card treasury-balance-card">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h3 class="card-title">📈 Courbes de trésorerie - du {{ $monthStart->format('d/m/Y') }} au {{ $monthEnd->format('d/m/Y') }}</h3>
                        <form method="GET" action="{{ route('treasury.balance') }}" class="d-flex flex-wrap align-items-center gap-2">
                            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom ?? '' }}" style="min-width: 150px;">
                            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo ?? '' }}" style="min-width: 150px;">
                            <button type="submit" class="btn btn-sm btn-light">Filtrer</button>
                            <a href="{{ route('treasury.balance') }}" class="btn btn-sm btn-outline-light">Réinitialiser</a>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="market-chart-wrap">
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
                                $hasChartData = count($chartDates) > 0 && count($allSeriesValues) > 0;
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
                                                <stop offset="0%" stop-color="#2563eb" stop-opacity="0.22" />
                                                <stop offset="100%" stop-color="#2563eb" stop-opacity="0.02" />
                                            </linearGradient>
                                        </defs>
                                        @for($i = 0; $i <= 4; $i++)
                                            @php
                                                $gridY = $padTop + ($plotHeight / 4) * $i;
                                                $gridVal = $chartMax - ($chartSpan / 4) * $i;
                                            @endphp
                                            <line x1="{{ $padLeft }}" y1="{{ $gridY }}" x2="{{ $svgWidth - $padRight }}" y2="{{ $gridY }}" stroke="#eef2f7" stroke-width="1" />
                                            <text x="6" y="{{ $gridY + 4 }}" font-size="11" fill="#667085">{{ number_format($gridVal, 0, ',', ' ') }}</text>
                                        @endfor

                                        <polygon points="{{ $balanceAreaPoints }}" fill="url(#balanceAreaGrad)" />
                                        <polyline fill="none" stroke="#22c55e" stroke-width="2.3" points="{{ $buildPoints($seriesIn) }}" />
                                        <polyline fill="none" stroke="#ef4444" stroke-width="2.3" points="{{ $buildPoints($seriesOut) }}" />
                                        <polyline fill="none" stroke="#2563eb" stroke-width="2.5" points="{{ $balancePoints }}" />

                                        @foreach($tickIndexes as $tickIdx)
                                            @php
                                                $tickX = $xCoord($tickIdx, count($chartDates));
                                                $tickLabel = \Carbon\Carbon::parse($chartDates[$tickIdx])->format('d/m');
                                            @endphp
                                            <line x1="{{ $tickX }}" y1="{{ $padTop + $plotHeight }}" x2="{{ $tickX }}" y2="{{ $padTop + $plotHeight + 4 }}" stroke="#94a3b8" stroke-width="1" />
                                            <text x="{{ $tickX - 12 }}" y="{{ $padTop + $plotHeight + 16 }}" font-size="10" fill="#64748b">{{ $tickLabel }}</text>
                                        @endforeach

                                        @if(count($seriesBal) > 0)
                                            @php
                                                $lastIdx = count($seriesBal) - 1;
                                                $lastX = $xCoord($lastIdx, count($seriesBal));
                                                $lastY = $yCoord((float) $seriesBal[$lastIdx]);
                                                $lastVal = number_format((float) $seriesBal[$lastIdx], 0, ',', ' ');
                                            @endphp
                                            <circle cx="{{ $lastX }}" cy="{{ $lastY }}" r="4" fill="#2563eb" />
                                            <text x="{{ $lastX + 8 }}" y="{{ $lastY - 8 }}" font-size="10" fill="#1d4ed8" font-weight="600">{{ $lastVal }} FCFA</text>
                                        @endif
                                    </svg>
                                </div>
                                <div class="d-flex flex-wrap gap-2 pt-2">
                                    <span class="chart-legend-chip"><span class="chart-legend-dot" style="background:#22c55e;"></span>Encaissements</span>
                                    <span class="chart-legend-chip"><span class="chart-legend-dot" style="background:#ef4444;"></span>Décaissements</span>
                                    <span class="chart-legend-chip"><span class="chart-legend-dot" style="background:#2563eb;"></span>Solde</span>
                                </div>
                            @else
                                <div class="text-muted small text-center py-3">
                                    Aucune donnée disponible pour tracer la courbe sur cette période.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tableau des transactions du mois -->
        <div class="row">
            <div class="col-12">
                <div class="card treasury-balance-card">
                    <div class="card-header">
                        <h3 class="card-title">Transactions du mois</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover card-table table-vcenter balance-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Encaissement</th>
                                    <th>Décaissement</th>
                                    <th>Solde</th>
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
                                        <td data-label="Date">{{ $tx->transaction_date->format('d/m/Y') }}</td>
                                        <td data-label="Description">{{ Str::limit($tx->description, 50) }}</td>
                                        <td data-label="Encaissement" class="text-success">
                                            {{ $tx->type === 'encaissement' ? '+ ' . number_format($tx->amount, 0, ',', ' ') : '-' }} FCFA
                                        </td>
                                        <td data-label="Décaissement" class="text-danger">
                                            {{ $tx->type === 'decaissement' ? '- ' . number_format($tx->amount, 0, ',', ' ') : '-' }} FCFA
                                        </td>
                                        <td data-label="Solde">
                                            <strong>{{ number_format($runningBalance, 0, ',', ' ') }} FCFA</strong>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            Aucune transaction ce mois-ci
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
    </div>
</div>

@endsection
