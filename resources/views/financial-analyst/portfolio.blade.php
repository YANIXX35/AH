@extends('layouts.app')

@section('title', 'Portefeuille de PME | Sitiame Capital')
@section('page_title', 'Portefeuille de PME')

@section('content')
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-1">Portefeuille de PME</h5>
                    <p class="text-muted mb-0">Toutes les entreprises clientes de la plateforme — cliquez sur une ligne pour ouvrir sa fiche d'analyse.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Vue consolidée --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">PME suivies</div>
                    <div class="fs-4 fw-bold">{{ $summary['total'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100 border-secondary-subtle">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Jamais évaluées</div>
                    <div class="fs-4 fw-bold">{{ $summary['never_evaluated'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100 border-danger-subtle">
                <div class="card-body">
                    <div class="text-danger small text-uppercase">PME à risque</div>
                    <div class="fs-4 fw-bold">{{ $summary['at_risk'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100 border-warning-subtle">
                <div class="card-body">
                    <div class="text-warning small text-uppercase">Dossiers de financement en attente</div>
                    <div class="fs-5 fw-bold">{{ $summary['pending_requests_count'] }} · {{ number_format((float) $summary['pending_requests_amount'], 0, ',', ' ') }} FCFA</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('analyst.portfolio') }}" method="GET" class="row g-2 align-items-end mb-4">
                <div class="col-auto">
                    <label class="small text-muted d-block">Rechercher</label>
                    <input type="search" name="q" class="form-control form-control-sm" placeholder="Nom, entreprise, e-mail..." value="{{ $search }}">
                </div>
                <div class="col-auto">
                    <label class="small text-muted d-block">Secteur</label>
                    <select name="sector" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Tous</option>
                        @foreach($sectors as $s)
                            <option value="{{ $s }}" {{ $sector === $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="small text-muted d-block">Niveau de risque</label>
                    <select name="risk" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Tous</option>
                        <option value="bon" {{ $risk === 'bon' ? 'selected' : '' }}>Bon (≥ 70)</option>
                        <option value="moyen" {{ $risk === 'moyen' ? 'selected' : '' }}>Moyen (40-69)</option>
                        <option value="eleve" {{ $risk === 'eleve' ? 'selected' : '' }}>Élevé (&lt; 40)</option>
                        <option value="jamais_evalue" {{ $risk === 'jamais_evalue' ? 'selected' : '' }}>Jamais évalué</option>
                    </select>
                </div>
                <div class="col-auto form-check pb-2">
                    <input type="checkbox" name="pending_financing" value="1" id="pendingFinancing" class="form-check-input" onchange="this.form.submit()" {{ $pendingFinancingOnly ? 'checked' : '' }}>
                    <label class="form-check-label small" for="pendingFinancing">Dossier de financement en attente</label>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Filtrer</button>
                    <a href="{{ route('analyst.portfolio') }}" class="btn btn-sm btn-outline-secondary">Effacer</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Entreprise</th>
                            <th>Contact</th>
                            <th>Secteur</th>
                            <th>Santé financière</th>
                            <th>Fiabilité des données</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($companies as $company)
                            @php($profile = $company->investorProfile)
                            <tr>
                                <td>
                                    <a href="{{ route('analyst.pme.show', $company) }}" class="fw-semibold text-decoration-none">
                                        {{ $company->company_name ?: $company->name }}
                                    </a>
                                </td>
                                <td class="small text-muted">{{ $company->name }}<br>{{ $company->email }}</td>
                                <td class="small text-muted">{{ $company->sector ?: '—' }}</td>
                                <td>
                                    @if($profile)
                                        @php($niveau = ($profile->risk_score ?? 0) >= 70 ? 'success' : (($profile->risk_score ?? 0) >= 40 ? 'warning' : 'danger'))
                                        <span class="badge bg-{{ $niveau }}-subtle text-{{ $niveau }}-emphasis">
                                            {{ $profile->classement_libelle ?: 'Score '.number_format((float) $profile->risk_score, 0) }}
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis">Jamais évalué</span>
                                    @endif
                                </td>
                                <td>
                                    @if($company->accounting_quality_reviewed_at)
                                        <span class="badge bg-success-subtle text-success-emphasis">Vérifiées le {{ $company->accounting_quality_reviewed_at->format('d/m/Y') }}</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning-emphasis">Non vérifiées</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('analyst.pme.show', $company) }}" class="btn btn-sm btn-outline-primary">Ouvrir la fiche</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Aucune entreprise ne correspond à ce filtre.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $companies->links() }}
        </div>
    </div>
@endsection
