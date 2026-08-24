@extends('layouts.app')

@section('title', ($company->company_name ?: $company->name).' | Analyse financière')
@section('page_title', 'Fiche PME')

@section('content')
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h5 class="card-title mb-1">{{ $company->company_name ?: $company->name }}</h5>
                        <p class="text-muted mb-0">{{ $company->name }} · {{ $company->email }}</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('analyst.pme.open', $company) }}" class="btn btn-outline-primary btn-sm">Ouvrir le dossier comptable (pièces justificatives)</a>
                        <a href="{{ route('analyst.portfolio') }}" class="btn btn-outline-secondary btn-sm">← Retour au portefeuille</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 1. Fiabilité des données --}}
    <div class="row mb-4">
        <div class="col-12">
            @if($company->accounting_quality_reviewed_at)
                <div class="alert alert-success mb-0">
                    <strong>Données vérifiées</strong> — dernier contrôle qualité le {{ $company->accounting_quality_reviewed_at->format('d/m/Y') }}.
                </div>
            @else
                <div class="alert alert-warning mb-0">
                    <strong>Données non vérifiées</strong> — cette entreprise n'a pas encore passé de contrôle qualité périodique. Les scores et ratios ci-dessous doivent être interprétés avec prudence.
                </div>
            @endif
        </div>
    </div>

    {{-- 2. Alertes & anomalies --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-title">Alertes &amp; anomalies</h6>
                    @if($missingAttachmentsCount > 0)
                        <div class="alert alert-warning py-2 small mb-2">
                            {{ $missingAttachmentsCount }} écriture(s) sans pièce justificative.
                        </div>
                    @else
                        <div class="alert alert-success py-2 small mb-2">Aucune écriture sans pièce justificative.</div>
                    @endif
                    @foreach($analysis['qualite_donnees'] ?? [] as $alerte)
                        <div class="alert alert-{{ $alerte['niveau'] === 'danger' ? 'danger' : ($alerte['niveau'] === 'warning' ? 'warning' : 'info') }} py-2 small mb-2">
                            <strong>{{ $alerte['titre'] }}</strong><br>{{ $alerte['texte'] }}
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-title">Classement financier automatique</h6>
                    @if(($analysis['entries_count'] ?? 0) > 0)
                        <span class="badge bg-{{ $analysis['classement']['niveau'] ?? 'secondary' }}-subtle text-{{ $analysis['classement']['niveau'] ?? 'secondary' }}-emphasis mb-2">
                            {{ $analysis['classement']['label'] ?? 'Non classé' }}
                        </span>
                        <p class="small text-muted mb-0">{{ $analysis['classement']['resume'] ?? '' }}</p>
                    @else
                        <p class="text-muted small mb-0">Pas d'écriture comptable sur la période : analyse non calculable.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- 3. Scoring --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Scoring 360</h6>
                    @if($scoring360)
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="text-muted small text-uppercase">Score composite</div>
                                <div class="fs-4 fw-bold">{{ $scoring360['composite']['total'] ?? '—' }} / 100</div>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $scoring360['composite']['decision']['label'] ?? '' }}</span>
                            </div>
                            @foreach(['bank' => 'Vision bancaire', 'investor' => 'Vision investisseur', 'internal' => 'Vision interne'] as $key => $label)
                                <div class="col-md-3">
                                    <div class="text-muted small text-uppercase">{{ $label }}</div>
                                    <div class="fs-5 fw-semibold">{{ $scoring360['blocks'][$key]['total'] ?? '—' }} / 100</div>
                                    <span class="badge bg-light text-dark border">{{ $scoring360['blocks'][$key]['decision']['label'] ?? '' }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted small mb-0">Score non calculable (pas assez de données comptables).</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- 4. Ratios financiers --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Ratios financiers</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <tbody>
                                <tr><td>ROA (rentabilité économique)</td><td class="text-end">{{ $analysis['ratios']['roa_pct'] ?? '—' }}{{ isset($analysis['ratios']['roa_pct']) ? ' %' : '' }}</td></tr>
                                <tr><td>ROE (rentabilité des capitaux propres)</td><td class="text-end">{{ $analysis['ratios']['roe_pct'] ?? '—' }}{{ isset($analysis['ratios']['roe_pct']) ? ' %' : '' }}</td></tr>
                                <tr><td>Marge nette</td><td class="text-end">{{ $analysis['ratios']['marge_nette_pct'] ?? '—' }}{{ isset($analysis['ratios']['marge_nette_pct']) ? ' %' : '' }}</td></tr>
                                <tr><td>Endettement / actif</td><td class="text-end">{{ $analysis['ratios']['endettement_sur_actif_pct'] ?? '—' }}{{ isset($analysis['ratios']['endettement_sur_actif_pct']) ? ' %' : '' }}</td></tr>
                                <tr><td>Levier financier (dettes / capitaux propres)</td><td class="text-end">{{ $analysis['ratios']['dettes_sur_capitaux_propres'] ?? '—' }}</td></tr>
                                <tr><td>Liquidité générale</td><td class="text-end">{{ $analysis['ratios']['liquidite_generale'] ?? '—' }}</td></tr>
                                <tr><td>Trésorerie / passif</td><td class="text-end">{{ $analysis['ratios']['couverture_tresorerie_passif'] ?? '—' }}</td></tr>
                                <tr><td>Rotation de l'actif</td><td class="text-end">{{ $analysis['ratios']['rotation_actif'] ?? '—' }}</td></tr>
                                <tr><td>Délai créances (jours)</td><td class="text-end">{{ $analysis['ratios']['delai_creances_jours'] ?? '—' }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 5. Dossier de financement --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Dossier(s) de financement</h6>
                    @forelse($investmentRequests as $ir)
                        <div class="border-bottom py-2 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold">{{ number_format((float) $ir->amount_requested, 0, ',', ' ') }} {{ $ir->currency }} — {{ $ir->purpose }}</div>
                                <div class="small text-muted">Horizon : {{ $ir->horizon }} · Déposé le {{ $ir->created_at?->format('d/m/Y') }}</div>
                            </div>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $ir->status }}</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Aucun dossier de financement déposé par cette entreprise.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- 6. Notes de l'analyste --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Notes et avis</h6>
                    <form action="{{ route('analyst.pme.notes.store', $company) }}" method="POST" class="mb-3">
                        @csrf
                        <textarea name="note" class="form-control mb-2" rows="3" placeholder="Votre observation ou décision motivée sur ce dossier..." required></textarea>
                        <button type="submit" class="btn btn-sm btn-primary">Enregistrer la note</button>
                    </form>
                    @forelse($notes as $note)
                        <div class="border-bottom py-2">
                            <div class="small text-muted">{{ $note->analyst?->name }} · {{ $note->created_at?->format('d/m/Y H:i') }}</div>
                            <div>{{ $note->note }}</div>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Aucune note enregistrée pour l'instant.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
