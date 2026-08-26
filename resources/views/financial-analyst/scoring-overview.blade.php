@extends('layouts.app')

@section('title', 'Scoring | Sitiame Capital')
@section('page_title', 'Scoring')

@section('content')
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-1">Scoring</h5>
                    <p class="text-muted mb-0">Score investisseur de toutes les PME du portefeuille — cliquez sur une ligne pour ouvrir le détail Scoring 360.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('analyst.scoring') }}" method="GET" class="row g-2 align-items-end mb-4">
                <div class="col-auto">
                    <label class="small text-muted d-block">Rechercher</label>
                    <input type="search" name="q" class="form-control form-control-sm" placeholder="Nom, entreprise..." value="{{ $search }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Rechercher</button>
                    <a href="{{ route('analyst.scoring') }}" class="btn btn-sm btn-outline-secondary">Effacer</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Entreprise</th>
                            <th>Score de risque</th>
                            <th>Score de performance</th>
                            <th>Classement</th>
                            <th>Dernier calcul</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($companies as $company)
                            @php($profile = $company->investorProfile)
                            <tr>
                                <td>
                                    <a href="{{ route('analyst.pme.scoring', $company) }}" class="fw-semibold text-decoration-none">
                                        {{ $company->company_name ?: $company->name }}
                                    </a>
                                </td>
                                <td>
                                    @if($profile && $profile->risk_score !== null)
                                        @php($niveau = $profile->risk_score >= 70 ? 'success' : ($profile->risk_score >= 40 ? 'warning' : 'danger'))
                                        <span class="badge bg-{{ $niveau }}-subtle text-{{ $niveau }}-emphasis">{{ number_format((float) $profile->risk_score, 0) }} / 100</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis">—</span>
                                    @endif
                                </td>
                                <td>{{ $profile && $profile->performance_score !== null ? number_format((float) $profile->performance_score, 0).' / 100' : '—' }}</td>
                                <td>{{ $profile?->classement_libelle ?: 'Jamais évalué' }}</td>
                                <td class="small text-muted">{{ $profile?->computed_at?->format('d/m/Y H:i') ?: '—' }}</td>
                                <td>
                                    <a href="{{ route('analyst.pme.scoring', $company) }}" class="btn btn-sm btn-outline-primary">Voir le détail</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Aucune entreprise ne correspond à cette recherche.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $companies->links() }}
        </div>
    </div>
@endsection
