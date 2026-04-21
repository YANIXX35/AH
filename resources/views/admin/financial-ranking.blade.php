@extends('layouts.app')

@section('title', 'Classement solvabilité | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="mb-4">
    <nav aria-label="Fil d’Ariane admin" class="mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Administration</a></li>
            <li class="breadcrumb-item active" aria-current="page">Classement solvabilité</li>
        </ol>
    </nav>
    <h1 class="h3 mb-1"><strong>Classement</strong> solvable / finançable</h1>
    <p class="text-muted mb-0">
        Attribution automatique à partir des verdicts, scores et fiabilité des données (mêmes règles que l’analyse financière PME).
        Les comptes administrateurs plateforme sont exclus ; les comptes suspendus aussi.
    </p>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="{{ route('admin.financial-ranking') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="date_from">Période du</label>
                <input type="date" name="date_from" id="date_from" class="form-control" value="{{ old('date_from', $dateFrom) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="date_to">au</label>
                <input type="date" name="date_to" id="date_to" class="form-control" value="{{ old('date_to', $dateTo) }}">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">Actualiser le classement</button>
            </div>
        </form>
        <p class="text-muted small mt-2 mb-0">Sans dates : toutes les écritures disponibles par entreprise (comme l’analyse détaillée).</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm border-success border-start border-4 h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Finançables</p>
                <p class="h3 mb-0 text-success">{{ $compteurs['financable'] }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm border-warning border-start border-4 h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Solvables seulement</p>
                <p class="h3 mb-0 text-warning">{{ $compteurs['solvable_seulement'] }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Non retenus</p>
                <p class="h3 mb-0">{{ $compteurs['non_retenu'] }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Données insuffisantes</p>
                <p class="h3 mb-0 text-muted">{{ $compteurs['insuffisant'] }}</p>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-light border shadow-sm mb-3">
    <strong>Règles (indicatif) :</strong>
    <span class="small"><strong>Solvable</strong> — au moins 5 écritures et verdict de solvabilité favorable ou score de solvabilité ≥ 54/100 (hors verdict « danger »).</span>
    <span class="small d-block mt-1"><strong>Finançable</strong> — profil « solvable » + au moins 15 écritures + rentabilité favorable (verdict ou score ≥ 56) + fiabilité des données ≥ 52 % + synthèse fiabilisée ≥ 48/100.</span>
</div>

<div class="table-responsive">
    <table class="table table-bordered bg-white shadow-sm align-middle">
        <thead class="table-light">
            <tr>
                <th>Entreprise</th>
                <th>Email</th>
                <th class="text-end">Écritures</th>
                <th>Catégorie</th>
                <th class="text-center">Solvable</th>
                <th class="text-center">Finançable</th>
                <th class="text-end">Synthèse fiabilisée</th>
                <th>Motifs (automatique)</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($lignes as $row)
                @php
                    $u = $row['user'];
                    $c = $row['classement'];
                    $code = $c['code'] ?? 'insuffisant';
                    $badgeClass = match ($code) {
                        'financable' => 'success',
                        'solvable_seulement' => 'warning',
                        'non_retenu' => 'secondary',
                        default => 'light text-dark',
                    };
                    $motifs = $c['motifs'] ?? [];
                @endphp
                <tr>
                    <td class="fw-medium">{{ $u->company_name ?? $u->name }}</td>
                    <td class="small">{{ $u->email }}</td>
                    <td class="text-end">{{ $row['entries_count'] }}</td>
                    <td><span class="badge bg-{{ $badgeClass }}">{{ $c['libelle'] ?? '—' }}</span></td>
                    <td class="text-center">@if(!empty($c['solvable']))<span class="text-success">Oui</span>@else<span class="text-muted">Non</span>@endif</td>
                    <td class="text-center">@if(!empty($c['financable']))<span class="text-success fw-semibold">Oui</span>@else<span class="text-muted">Non</span>@endif</td>
                    <td class="text-end">
                        @if($row['synthese_fiabilisee'] !== null)
                            {{ number_format((float) $row['synthese_fiabilisee'], 1, ',', ' ') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="small text-muted" style="max-width: 280px;">
                        @foreach(array_slice($motifs, 0, 3) as $m)
                            <div>{{ $m }}</div>
                        @endforeach
                        @if(count($motifs) > 3)
                            <span class="text-muted">+{{ count($motifs) - 3 }} autre(s)</span>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        <a href="{{ route('admin.financial-analysis', array_filter(['user_id' => $u->id, 'date_from' => $dateFrom, 'date_to' => $dateTo])) }}" class="btn btn-sm btn-outline-primary">Analyse</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">Aucune entreprise à classer.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
