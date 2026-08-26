@extends('layouts.app')

@section('title', 'Scoring — '.($company->company_name ?: $company->name))
@section('page_title', 'Scoring')

@section('content')
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h5 class="card-title mb-1">Scoring 360 — {{ $company->company_name ?: $company->name }}</h5>
                        <p class="text-muted mb-0">{{ $company->name }} · {{ $company->email }}</p>
                    </div>
                    <a href="{{ route('analyst.pme.show', $company) }}" class="btn btn-outline-secondary btn-sm">← Retour à la fiche PME</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    @if($scoring360)
                        <div class="row g-4">
                            <div class="col-md-3">
                                <div class="text-muted small text-uppercase">Score composite</div>
                                <div class="fs-2 fw-bold">{{ $scoring360['composite']['total'] ?? '—' }} / 100</div>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $scoring360['composite']['decision']['label'] ?? '' }}</span>
                                <p class="small text-muted mt-2 mb-0">{{ $scoring360['composite']['decision']['lecture'] ?? '' }}</p>
                            </div>
                            @foreach(['bank' => 'Vision bancaire', 'investor' => 'Vision investisseur', 'internal' => 'Vision interne'] as $key => $label)
                                <div class="col-md-3">
                                    <div class="text-muted small text-uppercase">{{ $label }}</div>
                                    <div class="fs-4 fw-semibold">{{ $scoring360['blocks'][$key]['total'] ?? '—' }} / 100</div>
                                    <span class="badge bg-light text-dark border">{{ $scoring360['blocks'][$key]['decision']['label'] ?? '' }}</span>
                                    <p class="small text-muted mt-2 mb-0">{{ $scoring360['blocks'][$key]['decision']['lecture'] ?? '' }}</p>
                                </div>
                            @endforeach
                        </div>

                        <hr class="my-4">

                        <h6>Détail des critères par vision</h6>
                        @foreach(['bank' => 'Vision bancaire', 'investor' => 'Vision investisseur', 'internal' => 'Vision interne'] as $key => $label)
                            <div class="mb-3">
                                <div class="fw-semibold small text-uppercase text-muted">{{ $label }}</div>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead><tr><th>Critère</th><th class="text-end">Valeur</th><th class="text-end">Score</th><th>Niveau</th></tr></thead>
                                        <tbody>
                                            @foreach($scoring360['blocks'][$key]['criteria'] ?? [] as $critere => $detail)
                                                <tr>
                                                    <td>{{ $critere }}</td>
                                                    <td class="text-end">{{ $detail['value'] ?? '—' }}</td>
                                                    <td class="text-end">{{ $detail['score'] ?? '—' }}</td>
                                                    <td>
                                                        @php($niveau = $detail['level'] ?? 'missing')
                                                        <span class="badge bg-{{ $niveau === 'strong' ? 'success' : ($niveau === 'medium' ? 'warning' : 'danger') }}-subtle text-{{ $niveau === 'strong' ? 'success' : ($niveau === 'medium' ? 'warning' : 'danger') }}-emphasis">{{ $niveau }}</span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <p class="text-muted mb-0">Score non calculable — pas assez de données comptables pour cette entreprise.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
