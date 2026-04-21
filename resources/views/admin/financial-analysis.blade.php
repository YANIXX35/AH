@extends('layouts.app')

@section('title', 'Analyse financière PME | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="mb-4">
    <nav aria-label="Fil d’Ariane admin" class="mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Administration</a></li>
            <li class="breadcrumb-item active" aria-current="page">Analyse financière PME</li>
        </ol>
    </nav>
    <h1 class="h3 mb-1"><strong>Analyse financière</strong> automatique</h1>
    <p class="text-muted mb-0">Ratios de rentabilité, solvabilité, liquidité et rotation — à partir des écritures comptables et de la trésorerie saisies par l’entreprise (cadre OHADA simplifié).</p>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="{{ route('admin.financial-analysis') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="user_id">Entreprise (compte)</label>
                <select name="user_id" id="user_id" class="form-select" required>
                    <option value="">— Choisir —</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected((string) $selectedUserId === (string) $u->id)>
                            {{ $u->company_name ?? $u->name }} ({{ $u->email }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="date_from">Période du</label>
                <input type="date" name="date_from" id="date_from" class="form-control" value="{{ old('date_from', $dateFrom) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="date_to">au</label>
                <input type="date" name="date_to" id="date_to" class="form-control" value="{{ old('date_to', $dateTo) }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Analyser</button>
            </div>
        </form>
        <p class="text-muted small mt-2 mb-0">Sans dates : toutes les écritures et la trésorerie cumulée sur les mouvements « effectués ».</p>
    </div>
</div>

@if($selectedUser && $analysis)
    @php $b = $analysis['base']; $r = $analysis['ratios']; @endphp

    <div class="alert alert-info border-0 shadow-sm">
        <strong>Périmètre :</strong> {{ $selectedUser->company_name ?? $selectedUser->name }}
        — {{ $analysis['entries_count'] }} écriture(s) sur la période sélectionnée.
    </div>

    @if(!empty($analysis['qualite_donnees'] ?? []))
        @foreach($analysis['qualite_donnees'] as $alert)
            <div class="alert alert-{{ ($alert['niveau'] ?? 'warning') === 'info' ? 'info' : (($alert['niveau'] ?? '') === 'danger' ? 'danger' : 'warning') }} border-0 shadow-sm mb-3">
                <strong>{{ $alert['titre'] ?? '' }}</strong>
                <p class="mb-0 small mt-1">{{ $alert['texte'] ?? '' }}</p>
            </div>
        @endforeach
    @endif

    @if(!empty($analysis['scores'] ?? []))
        @php $sc = $analysis['scores']; @endphp
        @if(($sc['rentabilite'] ?? null) !== null)
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted small text-uppercase mb-1">Score rentabilité</p>
                            <div class="d-flex align-items-baseline gap-2 mb-2">
                                <span class="display-6 fw-bold text-{{ ($sc['rentabilite']['niveau'] ?? '') === 'success' ? 'success' : (($sc['rentabilite']['niveau'] ?? '') === 'danger' ? 'danger' : 'warning') }}">{{ number_format((float) $sc['rentabilite']['valeur'], 1, ',', ' ') }}</span>
                                <span class="text-muted">/ 100</span>
                            </div>
                            <p class="small mb-2"><span class="badge bg-{{ ($sc['rentabilite']['niveau'] ?? '') === 'success' ? 'success' : (($sc['rentabilite']['niveau'] ?? '') === 'danger' ? 'danger' : 'warning') }}">{{ $sc['rentabilite']['libelle'] ?? '' }}</span></p>
                            <div class="progress mb-0" style="height: 10px;">
                                <div class="progress-bar bg-{{ ($sc['rentabilite']['niveau'] ?? '') === 'success' ? 'success' : (($sc['rentabilite']['niveau'] ?? '') === 'danger' ? 'danger' : 'warning') }}" role="progressbar" style="width: {{ min(100, max(0, (float) $sc['rentabilite']['valeur'])) }}%" aria-valuenow="{{ (float) $sc['rentabilite']['valeur'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <p class="text-muted small mt-2 mb-0">{{ $sc['rentabilite']['detail'] ?? '' }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted small text-uppercase mb-1">Score solvabilité</p>
                            <div class="d-flex align-items-baseline gap-2 mb-2">
                                <span class="display-6 fw-bold text-{{ ($sc['solvabilite']['niveau'] ?? '') === 'success' ? 'success' : (($sc['solvabilite']['niveau'] ?? '') === 'danger' ? 'danger' : 'warning') }}">{{ number_format((float) $sc['solvabilite']['valeur'], 1, ',', ' ') }}</span>
                                <span class="text-muted">/ 100</span>
                            </div>
                            <p class="small mb-2"><span class="badge bg-{{ ($sc['solvabilite']['niveau'] ?? '') === 'success' ? 'success' : (($sc['solvabilite']['niveau'] ?? '') === 'danger' ? 'danger' : 'warning') }}">{{ $sc['solvabilite']['libelle'] ?? '' }}</span></p>
                            <div class="progress mb-0" style="height: 10px;">
                                <div class="progress-bar bg-{{ ($sc['solvabilite']['niveau'] ?? '') === 'success' ? 'success' : (($sc['solvabilite']['niveau'] ?? '') === 'danger' ? 'danger' : 'warning') }}" role="progressbar" style="width: {{ min(100, max(0, (float) $sc['solvabilite']['valeur'])) }}%" aria-valuenow="{{ (float) $sc['solvabilite']['valeur'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <p class="text-muted small mt-2 mb-0">{{ $sc['solvabilite']['detail'] ?? '' }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 border-primary border-2">
                        <div class="card-body">
                            <p class="text-muted small text-uppercase mb-1">Synthèse (fiabilisée)</p>
                            <div class="d-flex align-items-baseline gap-2 mb-2">
                                <span class="display-6 fw-bold text-primary">{{ number_format((float) ($sc['global']['valeur_fiabilisee'] ?? 0), 1, ',', ' ') }}</span>
                                <span class="text-muted">/ 100</span>
                            </div>
                            <p class="small mb-1 text-muted">Moyenne brute : {{ number_format((float) ($sc['global']['valeur'] ?? 0), 1, ',', ' ') }} — <span class="badge bg-{{ ($sc['global']['niveau'] ?? '') === 'success' ? 'success' : (($sc['global']['niveau'] ?? '') === 'danger' ? 'danger' : 'warning') }}">{{ $sc['global']['libelle'] ?? '' }}</span></p>
                            <p class="small mb-2"><strong>Fiabilité des données :</strong> {{ number_format((float) ($sc['fiabilite_donnees_pct'] ?? 0), 1, ',', ' ') }} %</p>
                            <div class="progress mb-0" style="height: 10px;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: {{ min(100, max(0, (float) ($sc['global']['valeur_fiabilisee'] ?? 0))) }}%" aria-valuenow="{{ (float) ($sc['global']['valeur_fiabilisee'] ?? 0) }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <p class="text-muted small mt-2 mb-0">{{ $sc['global']['detail'] ?? '' }} {{ $sc['fiabilite_detail'] ?? '' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="alert alert-secondary border-0 shadow-sm mb-4">
                <strong>Scores :</strong> {{ $sc['legende'] ?? 'Non disponibles.' }}
            </div>
        @endif
    @endif

    @if(!empty($analysis['classement'] ?? []))
        @php
            $cl = $analysis['classement'];
            $cb = $cl['code'] ?? '';
            $badgeCl = $cb === 'financable' ? 'success' : ($cb === 'solvable_seulement' ? 'warning' : ($cb === 'insuffisant' ? 'secondary' : 'light text-dark'));
        @endphp
        <div class="alert alert-light border shadow-sm mb-4">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <strong>Classement plateforme (automatique) :</strong>
                <span class="badge bg-{{ $badgeCl }}">{{ $cl['libelle'] ?? '—' }}</span>
                <span class="small text-muted">Solvable : {{ !empty($cl['solvable']) ? 'oui' : 'non' }} — Finançable : {{ !empty($cl['financable']) ? 'oui' : 'non' }}</span>
            </div>
            @if(!empty($cl['motifs']))
                <ul class="small text-muted mb-0 ps-3">
                    @foreach($cl['motifs'] as $m)
                        <li>{{ $m }}</li>
                    @endforeach
                </ul>
            @endif
            <p class="small mb-0 mt-2"><a href="{{ route('admin.financial-ranking') }}">Voir le classement de toutes les entreprises</a></p>
        </div>
    @endif

    @if(!empty($analysis['modele_financier'] ?? []))
        @php $mf = $analysis['modele_financier']; @endphp
        <div class="accordion mb-4" id="accordionModeleFinancier">
            <div class="accordion-item border-0 shadow-sm">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseModeleFinancier" aria-expanded="false">
                        {{ $mf['titre'] ?? 'Modèle financier de référence' }}
                    </button>
                </h2>
                <div id="collapseModeleFinancier" class="accordion-collapse collapse" data-bs-parent="#accordionModeleFinancier">
                    <div class="accordion-body small">
                        <p class="text-muted">{{ $mf['intro'] ?? '' }}</p>
                        <h3 class="h6 mt-3">Postulats du modèle</h3>
                        <ul class="text-muted">
                            @foreach($mf['postulats'] ?? [] as $p)
                                <li>{{ $p }}</li>
                            @endforeach
                        </ul>
                        <h3 class="h6 mt-3">Formules des indicateurs</h3>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered bg-white mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Indicateur</th>
                                        <th>Définition retenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($mf['formules'] ?? [] as $row)
                                        <tr>
                                            <td>{{ $row['indicateur'] ?? '' }}</td>
                                            <td class="font-monospace text-muted">{{ $row['expression'] ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if(!empty($mf['seuils_synthese']))
                            <p class="text-muted mt-3 mb-2"><strong>Synthèse automatique :</strong> {{ $mf['seuils_synthese'] }}</p>
                        @endif
                        <h3 class="h6 mt-3">Limites d’usage</h3>
                        <ul class="text-muted mb-0">
                            @foreach($mf['limites'] ?? [] as $lim)
                                <li>{{ $lim }}</li>
                            @endforeach
                        </ul>
                        @if(!empty($mf['scores']))
                            <h3 class="h6 mt-3">Scores (0–100)</h3>
                            <p class="text-muted small mb-0">{{ $mf['scores'] }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if(!empty($analysis['verdicts']))
        @php $v = $analysis['verdicts']; @endphp
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow h-100 border-start border-4
                    @if(($v['rentabilite']['niveau'] ?? '') === 'success') border-success
                    @elseif(($v['rentabilite']['niveau'] ?? '') === 'warning') border-warning
                    @elseif(($v['rentabilite']['niveau'] ?? '') === 'danger') border-danger
                    @else border-secondary
                    @endif">
                    <div class="card-body">
                        <p class="text-muted small text-uppercase mb-1">Rentabilité</p>
                        <h2 class="h5 mb-2">{{ $v['rentabilite']['label'] ?? '—' }}</h2>
                        <p class="mb-2 small">{{ $v['rentabilite']['resume'] ?? '' }}</p>
                        <dl class="row small mb-2 gy-1 border-top pt-2">
                            <dt class="col-7 text-muted mb-0">Résultat net</dt>
                            <dd class="col-5 text-end mb-0 fw-medium {{ ($b['resultat_net'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format((float) ($b['resultat_net'] ?? 0), 0, ',', ' ') }} FCFA</dd>
                            <dt class="col-7 text-muted mb-0">Chiffre d’affaires</dt>
                            <dd class="col-5 text-end mb-0">{{ number_format((float) ($b['chiffre_affaires_ht'] ?? 0), 0, ',', ' ') }} FCFA</dd>
                            <dt class="col-7 text-muted mb-0">Marge nette</dt>
                            <dd class="col-5 text-end mb-0">{{ $r['marge_nette_pct'] !== null ? number_format((float) $r['marge_nette_pct'], 2, ',', ' ').' %' : '—' }}</dd>
                            <dt class="col-7 text-muted mb-0">ROA</dt>
                            <dd class="col-5 text-end mb-0">{{ $r['roa_pct'] !== null ? number_format((float) $r['roa_pct'], 2, ',', ' ').' %' : '—' }}</dd>
                            <dt class="col-7 text-muted mb-0">ROE</dt>
                            <dd class="col-5 text-end mb-0">{{ $r['roe_pct'] !== null ? number_format((float) $r['roe_pct'], 2, ',', ' ').' %' : '—' }}</dd>
                        </dl>
                        <p class="mb-0 small text-muted"><strong>Critères retenus :</strong> {{ $v['rentabilite']['criteres'] ?? '' }}</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow h-100 border-start border-4
                    @if(($v['solvabilite']['niveau'] ?? '') === 'success') border-success
                    @elseif(($v['solvabilite']['niveau'] ?? '') === 'warning') border-warning
                    @elseif(($v['solvabilite']['niveau'] ?? '') === 'danger') border-danger
                    @else border-secondary
                    @endif">
                    <div class="card-body">
                        <p class="text-muted small text-uppercase mb-1">Solvabilité</p>
                        <h2 class="h5 mb-2">{{ $v['solvabilite']['label'] ?? '—' }}</h2>
                        <p class="mb-2 small">{{ $v['solvabilite']['resume'] ?? '' }}</p>
                        <dl class="row small mb-2 gy-1 border-top pt-2">
                            <dt class="col-7 text-muted mb-0">Capitaux propres (estim.)</dt>
                            <dd class="col-5 text-end mb-0">{{ number_format((float) ($b['capitaux_propres_estimes'] ?? 0), 0, ',', ' ') }} FCFA</dd>
                            <dt class="col-7 text-muted mb-0">Total actif</dt>
                            <dd class="col-5 text-end mb-0">{{ number_format((float) ($b['total_actif'] ?? 0), 0, ',', ' ') }} FCFA</dd>
                            <dt class="col-7 text-muted mb-0">Total passif (dettes)</dt>
                            <dd class="col-5 text-end mb-0">{{ number_format((float) ($b['total_passif'] ?? 0), 0, ',', ' ') }} FCFA</dd>
                            <dt class="col-7 text-muted mb-0">Dettes / actif</dt>
                            <dd class="col-5 text-end mb-0">{{ $r['endettement_sur_actif_pct'] !== null ? number_format((float) $r['endettement_sur_actif_pct'], 2, ',', ' ').' %' : '—' }}</dd>
                            <dt class="col-7 text-muted mb-0">Levier (dettes / CP)</dt>
                            <dd class="col-5 text-end mb-0">{{ $r['dettes_sur_capitaux_propres'] !== null ? number_format((float) $r['dettes_sur_capitaux_propres'], 3, ',', ' ') : '—' }}</dd>
                            <dt class="col-7 text-muted mb-0">Liquidité générale</dt>
                            <dd class="col-5 text-end mb-0">{{ $r['liquidite_generale'] !== null ? number_format((float) $r['liquidite_generale'], 3, ',', ' ') : '—' }}</dd>
                        </dl>
                        <p class="mb-0 small text-muted"><strong>Critères retenus :</strong> {{ $v['solvabilite']['criteres'] ?? '' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="accordion mb-4" id="faqVerdicts">
            <div class="accordion-item border-0 shadow-sm">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseVerdicts" aria-expanded="false">
                        Comment savoir si une PME est rentable ou solvable ?
                    </button>
                </h2>
                <div id="collapseVerdicts" class="accordion-collapse collapse" data-bs-parent="#faqVerdicts">
                    <div class="accordion-body small text-muted">
                        <p class="mb-2"><strong>Rentabilité</strong> : on regarde si l’activité <em>génère un surplus</em> après charges — en pratique le <strong>résultat net</strong> sur la période, puis la <strong>marge nette</strong> (résultat ÷ chiffre d’affaires) et le <strong>ROA</strong> (résultat ÷ actif) pour voir si les ressources sont bien utilisées.</p>
                        <p class="mb-2"><strong>Solvabilité</strong> : il s’agit de la capacité à <em>honorer les dettes sur le long terme</em> — ici des <strong>capitaux propres estimés</strong> (actif − dettes sur bilan simplifié), le <strong>poids des dettes</strong> par rapport à l’actif, le <strong>levier</strong> (dettes ÷ capitaux propres) et la <strong>liquidité générale</strong> (actif ÷ passif, approximation).</p>
                        <p class="mb-0"><strong>Important :</strong> les seuils (ex. marge ≥ 3 %, dettes/actif ≤ 70 %) sont des repères pour PME ; le secteur (commerce, services, industrie) change beaucoup la lecture. Ce module ne remplace pas un expert-comptable ni un audit.</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Chiffre d’affaires (cl. 7)</p>
                    <p class="h5 mb-0">{{ number_format($b['chiffre_affaires_ht'], 0, ',', ' ') }} FCFA</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Résultat net (estimé)</p>
                    <p class="h5 mb-0 {{ $b['resultat_net'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($b['resultat_net'], 0, ',', ' ') }} FCFA</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Total actif (simpl.)</p>
                    <p class="h5 mb-0">{{ number_format($b['total_actif'], 0, ',', ' ') }} FCFA</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Capitaux propres (est.)</p>
                    <p class="h5 mb-0">{{ number_format($b['capitaux_propres_estimes'], 0, ',', ' ') }} FCFA</p>
                </div>
            </div>
        </div>
    </div>

    <h2 class="h5 mb-3">📊 Rentabilité</h2>
    <div class="table-responsive mb-4">
        <table class="table table-bordered bg-white shadow-sm">
            <thead class="table-light">
                <tr>
                    <th>Indicateur</th>
                    <th class="text-end">Valeur</th>
                    <th>Lecture experte (synthèse)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>ROA — Return on Assets</td>
                    <td class="text-end fw-semibold">{{ $r['roa_pct'] !== null ? $r['roa_pct'].' %' : '—' }}</td>
                    <td class="small text-muted">Résultat net / actif : efficacité globale des ressources investies.</td>
                </tr>
                <tr>
                    <td>ROE — Return on Equity</td>
                    <td class="text-end fw-semibold">{{ $r['roe_pct'] !== null ? $r['roe_pct'].' %' : '—' }}</td>
                    <td class="small text-muted">Résultat / capitaux propres estimés (actif − dettes).</td>
                </tr>
                <tr>
                    <td>Marge nette</td>
                    <td class="text-end fw-semibold">{{ $r['marge_nette_pct'] !== null ? $r['marge_nette_pct'].' %' : '—' }}</td>
                    <td class="small text-muted">Part du CA revenant au résultat après charges.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 class="h5 mb-3">⚖️ Solvabilité</h2>
    <div class="table-responsive mb-4">
        <table class="table table-bordered bg-white shadow-sm">
            <thead class="table-light">
                <tr>
                    <th>Indicateur</th>
                    <th class="text-end">Valeur</th>
                    <th>Lecture experte (synthèse)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Endettement / actif</td>
                    <td class="text-end fw-semibold">{{ $r['endettement_sur_actif_pct'] !== null ? $r['endettement_sur_actif_pct'].' %' : '—' }}</td>
                    <td class="small text-muted">Poids du passif dans le financement de l’actif.</td>
                </tr>
                <tr>
                    <td>Dettes / capitaux propres</td>
                    <td class="text-end fw-semibold">{{ $r['dettes_sur_capitaux_propres'] !== null ? $r['dettes_sur_capitaux_propres'] : '—' }}</td>
                    <td class="small text-muted">Levier : dettes rapportées aux fonds propres estimés.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 class="h5 mb-3">💧 Liquidité</h2>
    <div class="table-responsive mb-4">
        <table class="table table-bordered bg-white shadow-sm">
            <thead class="table-light">
                <tr>
                    <th>Indicateur</th>
                    <th class="text-end">Valeur</th>
                    <th>Lecture experte (synthèse)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Liquidité générale (approx.)</td>
                    <td class="text-end fw-semibold">{{ $r['liquidite_generale'] !== null ? $r['liquidite_generale'] : '—' }}</td>
                    <td class="small text-muted">Actif / passif sur bilan simplifié ; à croiser avec le cycle d’exploitation.</td>
                </tr>
                <tr>
                    <td>Trésorerie effectuée / passif</td>
                    <td class="text-end fw-semibold">{{ $r['couverture_tresorerie_passif'] !== null ? $r['couverture_tresorerie_passif'] : '—' }}</td>
                    <td class="small text-muted">Flux trésorerie sur la période vs passif comptable (indicateur de tension).</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 class="h5 mb-3">🔄 Rotation</h2>
    <div class="table-responsive mb-4">
        <table class="table table-bordered bg-white shadow-sm">
            <thead class="table-light">
                <tr>
                    <th>Indicateur</th>
                    <th class="text-end">Valeur</th>
                    <th>Lecture experte (synthèse)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Rotation de l’actif</td>
                    <td class="text-end fw-semibold">{{ $r['rotation_actif'] !== null ? $r['rotation_actif'] : '—' }}</td>
                    <td class="small text-muted">Nombre de fois que le CA « utilise » l’actif sur la période.</td>
                </tr>
                <tr>
                    <td>Délai créances (ordre de grandeur)</td>
                    <td class="text-end fw-semibold">{{ $r['delai_creances_jours'] !== null ? $r['delai_creances_jours'].' j.' : '—' }}</td>
                    <td class="small text-muted">Basé sur soldes classe 4 ; sensible à la qualité du plan comptable.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 class="h5 mb-3">💡 Synthèse &amp; interprétation</h2>
    <div class="row g-3">
        @foreach($analysis['interpretation'] as $block)
            <div class="col-12 col-lg-6">
                <div class="card border-0 shadow-sm h-100 border-start border-4
                    @if($block['niveau'] === 'success') border-success
                    @elseif($block['niveau'] === 'warning') border-warning
                    @elseif($block['niveau'] === 'danger') border-danger
                    @else border-info
                    @endif">
                    <div class="card-body">
                        <h3 class="h6 card-title">{{ $block['titre'] }}</h3>
                        <p class="card-text small mb-0">{{ $block['texte'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card border-0 bg-light mt-4">
        <div class="card-body small text-muted">
            <strong>Rappel :</strong> voir le panneau « Modèle financier de référence » ci-dessus pour les postulats, formules et limites.
            Ces indicateurs ne se substituent pas à un audit ni à un arrêté de comptes certifié.
        </div>
    </div>
@elseif($selectedUserId && !$selectedUser)
    <div class="alert alert-warning">Compte introuvable.</div>
@else
    <div class="alert alert-secondary border-0">
        Sélectionnez une entreprise et lancez l’analyse pour afficher les ratios et les commentaires.
    </div>
@endif
@endsection
