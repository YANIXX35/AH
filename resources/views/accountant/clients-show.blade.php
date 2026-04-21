@extends('layouts.app')

@section('title', 'Dossier — '.$client->company_name)
@section('page_title', 'Fiche dossier')

@section('content')
<div class="mb-3">
    <a href="{{ route('accountant.clients.index') }}" class="small text-decoration-none">← Retour à la liste</a>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">{{ $client->company_name ?: $client->name }}</h1>
        <p class="text-muted mb-0">{{ $client->name }} — {{ $client->email }}</p>
        @if($client->phone)
            <p class="small mb-0">{{ $client->phone }}</p>
        @endif
    </div>
    <div class="d-flex flex-column gap-2 align-items-stretch" style="min-width: 220px;">
        <form method="post" action="{{ route('accountant.workspace.select', $client) }}">
            @csrf
            <input type="hidden" name="redirect" value="accounting">
            <button type="submit" class="btn btn-primary w-100">Ouvrir — Comptabilité</button>
        </form>
        <form method="post" action="{{ route('accountant.workspace.select', $client) }}">
            @csrf
            <input type="hidden" name="redirect" value="treasury">
            <button type="submit" class="btn btn-outline-primary w-100">Ouvrir — Trésorerie</button>
        </form>
        <form method="post" action="{{ route('accountant.workspace.select', $client) }}">
            @csrf
            <input type="hidden" name="redirect" value="fird">
            <button type="submit" class="btn btn-outline-secondary w-100">Ouvrir — Fiche entreprise (FIRD)</button>
        </form>
        <form method="post" action="{{ route('accountant.workspace.select', $client) }}">
            @csrf
            <input type="hidden" name="redirect" value="investor">
            <button type="submit" class="btn btn-outline-secondary w-100">Ouvrir — Investisseurs</button>
        </form>
    </div>
</div>

@php
    $cl = $analysis['classement'] ?? [];
    $scores = $analysis['scores'] ?? [];
    $ratios = $analysis['ratios'] ?? [];
@endphp

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-uppercase small text-muted">Classement auto</h6>
                <p class="fw-semibold mb-1">{{ $cl['libelle'] ?? '—' }}</p>
                <p class="small text-muted mb-0">Code : {{ $cl['code'] ?? '—' }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-uppercase small text-muted">Écritures</h6>
                <p class="display-6 fw-bold mb-0">{{ (int) ($analysis['entries_count'] ?? 0) }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-uppercase small text-muted">Synthèse fiabilisée</h6>
                <p class="display-6 fw-bold mb-0">{{ isset($scores['global']['valeur_fiabilisee']) ? number_format((float) $scores['global']['valeur_fiabilisee'], 1, ',', ' ') : '—' }}</p>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="card-title mb-0">Ratios (aperçu)</h5>
    </div>
    <div class="card-body">
        <div class="row small">
            <div class="col-md-3 mb-2"><strong>ROA</strong><br>{{ isset($ratios['roa_pct']) ? number_format((float) $ratios['roa_pct'], 2, ',', ' ').' %' : '—' }}</div>
            <div class="col-md-3 mb-2"><strong>ROE</strong><br>{{ isset($ratios['roe_pct']) ? number_format((float) $ratios['roe_pct'], 2, ',', ' ').' %' : '—' }}</div>
            <div class="col-md-3 mb-2"><strong>Marge nette</strong><br>{{ isset($ratios['marge_nette_pct']) ? number_format((float) $ratios['marge_nette_pct'], 2, ',', ' ').' %' : '—' }}</div>
            <div class="col-md-3 mb-2"><strong>Liquidité générale</strong><br>{{ isset($ratios['liquidite_generale']) ? number_format((float) $ratios['liquidite_generale'], 3, ',', ' ') : '—' }}</div>
        </div>
        <p class="text-muted small mb-0 mt-2">Indicateurs indicatifs issus du grand livre ; ouvrez le dossier pour saisie, rapprochements et contrôles détaillés.</p>
    </div>
</div>
@endsection
