@extends('layouts.app')

@section('title', 'Paramètres scoring 360 | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="mb-4">
    <nav aria-label="Fil d’Ariane admin" class="mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Administration</a></li>
            <li class="breadcrumb-item active" aria-current="page">Paramètres scoring 360</li>
        </ol>
    </nav>
    <h1 class="h3 mb-1"><strong>Paramètres</strong> de scoring & décision</h1>
    <p class="text-muted mb-0">Seuils et poids alignés sur le classeur SITIAME 360 (Banque / Investisseur / Interne + score composite).</p>
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
            <div class="card-body">
                <h2 class="h6 mb-2">Importer depuis Excel</h2>
                <p class="text-muted small mb-3">Lit les onglets <code>Parametres</code> et <code>Score_Interne</code> pour mettre à jour les seuils/poids.</p>
                <form method="post" action="{{ route('admin.scoring-parameters.import') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-8">
                        <label class="form-label mb-1" for="excel">Fichier .xlsx</label>
                        <input type="file" class="form-control" id="excel" name="excel" accept=".xlsx" required>
                    </div>
                    <div class="col-4">
                        <button class="btn btn-outline-primary w-100" type="submit">Importer</button>
                    </div>
                </form>
                <p class="text-muted small mt-3 mb-0">
                    Source actuelle : <code>{{ $config['meta']['imported_from'] ?? ($config['meta']['source'] ?? '—') }}</code>
                    @if(!empty($config['meta']['imported_at']))
                        — importé le {{ \Carbon\Carbon::parse($config['meta']['imported_at'])->format('d/m/Y H:i') }}
                    @endif
                </p>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 mb-2">Règle de scoring (méthode Excel)</h2>
                <p class="text-muted small mb-2">Par critère : score = poids × (fort / moyen / faible). Si valeur manquante, score = 0.</p>
                <ul class="small text-muted mb-0 ps-3">
                    <li>Fort : coefficient {{ $config['coefficients']['strong'] ?? 1 }}</li>
                    <li>Moyen : coefficient {{ $config['coefficients']['medium'] ?? 0.6 }}</li>
                    <li>Faible : coefficient {{ $config['coefficients']['weak'] ?? 0.2 }}</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<form method="post" action="{{ route('admin.scoring-parameters.update') }}">
    @csrf

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h6 mb-3">Coefficients de scoring</h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="coef_strong">Fort</label>
                    <input id="coef_strong" name="coefficients[strong]" type="number" step="0.01" class="form-control" value="{{ old('coefficients.strong', $config['coefficients']['strong'] ?? 1) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="coef_medium">Moyen</label>
                    <input id="coef_medium" name="coefficients[medium]" type="number" step="0.01" class="form-control" value="{{ old('coefficients.medium', $config['coefficients']['medium'] ?? 0.6) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="coef_weak">Faible</label>
                    <input id="coef_weak" name="coefficients[weak]" type="number" step="0.01" class="form-control" value="{{ old('coefficients.weak', $config['coefficients']['weak'] ?? 0.2) }}" required>
                </div>
            </div>
        </div>
    </div>

    @php
        $blocks = [
            'bank' => ['label' => 'Banque', 'criteria' => [
                'dscr' => 'DSCR',
                'interest_coverage' => 'Couverture intérêts',
                'current_ratio' => 'Current ratio',
                'debt_asset' => 'Dette / actif',
                'bfr_days' => 'BFR jours',
            ]],
            'investor' => ['label' => 'Investisseur', 'criteria' => [
                'revenue_growth' => 'Croissance CA',
                'ebitda_margin' => 'Marge EBITDA',
                'roe' => 'ROE',
                'fcf_margin' => 'Marge FCF',
                'asset_turnover' => 'Rotation actif',
            ]],
            'internal' => ['label' => 'Interne', 'criteria' => [
                'net_margin' => 'Marge nette',
                'quick_ratio' => 'Quick ratio',
                'receivable_days' => 'Délai clients (jours)',
                'inventory_days' => 'Délai stocks (jours)',
                'ebitda_growth' => 'Croissance EBITDA',
            ]],
        ];
    @endphp

    <div class="row g-3">
        @foreach($blocks as $blockKey => $block)
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h6 mb-3">Seuils &amp; poids — {{ $block['label'] }}</h2>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered bg-white mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Critère</th>
                                        <th class="text-end">Poids</th>
                                        <th class="text-end">Seuil fort</th>
                                        <th class="text-end">Seuil moyen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($block['criteria'] as $critKey => $critLabel)
                                        <tr>
                                            <td>{{ $critLabel }}</td>
                                            <td class="text-end" style="min-width: 90px;">
                                                <input name="{{ $blockKey }}[weights][{{ $critKey }}]" type="number" step="0.01" class="form-control form-control-sm text-end"
                                                       value="{{ old($blockKey.'.weights.'.$critKey, $config[$blockKey]['weights'][$critKey] ?? 0) }}" required>
                                            </td>
                                            <td class="text-end" style="min-width: 110px;">
                                                <input name="{{ $blockKey }}[thresholds][{{ $critKey }}][strong]" type="number" step="0.01" class="form-control form-control-sm text-end"
                                                       value="{{ old($blockKey.'.thresholds.'.$critKey.'.strong', $config[$blockKey]['thresholds'][$critKey]['strong'] ?? 0) }}" required>
                                            </td>
                                            <td class="text-end" style="min-width: 110px;">
                                                <input name="{{ $blockKey }}[thresholds][{{ $critKey }}][medium]" type="number" step="0.01" class="form-control form-control-sm text-end"
                                                       value="{{ old($blockKey.'.thresholds.'.$critKey.'.medium', $config[$blockKey]['thresholds'][$critKey]['medium'] ?? 0) }}" required>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="row g-2 mt-3">
                            <div class="col-6">
                                <label class="form-label small mb-1">Décision — fort (≥)</label>
                                <input name="{{ $blockKey }}[decision][strong_min]" type="number" step="0.01" class="form-control form-control-sm"
                                       value="{{ old($blockKey.'.decision.strong_min', $config[$blockKey]['decision']['strong_min'] ?? 80) }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-1">Décision — moyen (≥)</label>
                                <input name="{{ $blockKey }}[decision][medium_min]" type="number" step="0.01" class="form-control form-control-sm"
                                       value="{{ old($blockKey.'.decision.medium_min', $config[$blockKey]['decision']['medium_min'] ?? 60) }}" required>
                            </div>
                        </div>
                        <p class="text-muted small mt-2 mb-0">Somme des poids attendue : 100.</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm my-4">
        <div class="card-body">
            <h2 class="h6 mb-3">Poids Composite SITIAME 360</h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="w_bank">Banque</label>
                    <input id="w_bank" name="composite[weights][bank]" type="number" step="0.01" class="form-control" value="{{ old('composite.weights.bank', $config['composite']['weights']['bank'] ?? 40) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="w_investor">Investisseur</label>
                    <input id="w_investor" name="composite[weights][investor]" type="number" step="0.01" class="form-control" value="{{ old('composite.weights.investor', $config['composite']['weights']['investor'] ?? 35) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="w_internal">Interne</label>
                    <input id="w_internal" name="composite[weights][internal]" type="number" step="0.01" class="form-control" value="{{ old('composite.weights.internal', $config['composite']['weights']['internal'] ?? 25) }}" required>
                </div>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <label class="form-label">Décision — fort (≥)</label>
                    <input name="composite[decision][strong_min]" type="number" step="0.01" class="form-control" value="{{ old('composite.decision.strong_min', $config['composite']['decision']['strong_min'] ?? 80) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Décision — moyen (≥)</label>
                    <input name="composite[decision][medium_min]" type="number" step="0.01" class="form-control" value="{{ old('composite.decision.medium_min', $config['composite']['decision']['medium_min'] ?? 60) }}" required>
                </div>
            </div>
            <p class="text-muted small mt-2 mb-0">Somme des poids attendue : 100.</p>
        </div>
    </div>

    <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>
</form>
@endsection

