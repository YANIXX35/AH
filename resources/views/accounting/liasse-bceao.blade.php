@extends('layouts.app')

@section('title', 'Liasse Fiscale BCEAO | Sitiame Capital')
@section('page_title', 'Liasse Fiscale Officielle BCEAO / SYSCOHADA')

@section('content')
<div class="container-fluid p-0">

    {{-- En-tête --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <h4 class="card-title text-primary fw-bold mb-1">
                            <i data-feather="file-text" class="me-2"></i>Liasse Fiscale BCEAO (Système Normal)
                        </h4>
                        <p class="text-muted small mb-0">
                            États financiers annuels conformes aux directives BCEAO / SYSCOHADA Révisé — Bilan · Résultat · TAFIRE · 13 Annexes
                        </p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('accounting.liasse-bceao.pdf.view', request()->query()) }}" target="_blank" class="btn btn-outline-primary">
                            <i data-feather="eye" class="me-1"></i>Visualiser PDF
                        </a>
                        <a href="{{ route('accounting.liasse-bceao.pdf.download', request()->query()) }}" class="btn btn-primary">
                            <i data-feather="download" class="me-1"></i>Télécharger Liasse PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtres --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="GET" action="{{ route('accounting.liasse-bceao') }}" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Date de Début</label>
                            <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Date de Fin (Clôture)</label>
                            <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control">
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-secondary w-100">
                                <i data-feather="filter" class="me-1"></i>Filtrer
                            </button>
                            <a href="{{ route('accounting.liasse-bceao') }}" class="btn btn-outline-secondary">Réinitialiser</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Identification entreprise --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body">
                    <div class="row text-center text-md-start">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <h6 class="fw-bold text-dark mb-1">{{ $companyName }} {{ $companySigle ? '('.$companySigle.')' : '' }}</h6>
                            <span class="text-muted small">NIF / N° Impôt : <strong>{{ $companyTaxId ?: 'Non renseigné' }}</strong></span>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <span class="badge bg-success mb-1">Exercice {{ $exerciseYear }}</span>
                            <div class="text-muted small">Exercice clos le : <strong>{{ $exerciseEnd ?: date('31/12/'.$exerciseYear) }}</strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Onglets Liasse --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom-0 pt-3">
            <ul class="nav nav-tabs card-header-tabs fw-semibold flex-nowrap overflow-auto" id="liasseTab" role="tablist" style="white-space:nowrap;">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="actif-tab" data-bs-toggle="tab" data-bs-target="#actif" type="button" role="tab">
                        <i data-feather="grid" class="me-1"></i>Bilan Actif
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="passif-tab" data-bs-toggle="tab" data-bs-target="#passif" type="button" role="tab">
                        <i data-feather="layers" class="me-1"></i>Bilan Passif
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="resultat-tab" data-bs-toggle="tab" data-bs-target="#resultat" type="button" role="tab">
                        <i data-feather="trending-up" class="me-1"></i>Compte de Résultat
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tafire-tab" data-bs-toggle="tab" data-bs-target="#tafire" type="button" role="tab">
                        <i data-feather="activity" class="me-1"></i>TAFIRE
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="amort-tab" data-bs-toggle="tab" data-bs-target="#amort" type="button" role="tab">
                        <i data-feather="bar-chart-2" class="me-1"></i>Amortissements
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="prov-tab" data-bs-toggle="tab" data-bs-target="#prov" type="button" role="tab">
                        <i data-feather="shield" class="me-1"></i>Provisions
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="immo-tab" data-bs-toggle="tab" data-bs-target="#immo" type="button" role="tab">
                        <i data-feather="box" class="me-1"></i>Immobilisations
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="annexes-tab" data-bs-toggle="tab" data-bs-target="#annexes" type="button" role="tab">
                        <i data-feather="book-open" class="me-1"></i>États Annexes
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body tab-content pt-4">

            {{-- TAB 1: BILAN ACTIF --}}
            <div class="tab-pane fade show active" id="actif" role="tabpanel">
                <h5 class="fw-bold mb-3 text-secondary">BILAN ACTIF (Système Normal)</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-dark text-center">
                            <tr>
                                <th style="width:8%">Réf.</th>
                                <th>LIBELLÉ ACTIF</th>
                                <th style="width:15%">Brut N</th>
                                <th style="width:15%">Amort. / Prov.</th>
                                <th style="width:15%">Net N</th>
                                <th style="width:15%">Net N-1</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($liasse['actif']['rows'] as $ref => $row)
                                <tr class="{{ !empty($row['is_total']) ? 'table-warning fw-bold' : '' }}">
                                    <td class="text-center fw-bold">{{ $ref }}</td>
                                    <td>{{ $row['libelle'] }}</td>
                                    <td class="text-end">{{ number_format($row['brut'], 0, ',', ' ') }}</td>
                                    <td class="text-end">{{ number_format($row['prov'], 0, ',', ' ') }}</td>
                                    <td class="text-end fw-bold">{{ number_format($row['net_n'], 0, ',', ' ') }}</td>
                                    <td class="text-end text-muted">{{ number_format($row['net_n1'], 0, ',', ' ') }}</td>
                                </tr>
                            @endforeach
                            <tr class="table-dark fw-bold text-uppercase">
                                <td class="text-center">TOTAL</td>
                                <td>{{ $liasse['actif']['total']['libelle'] }}</td>
                                <td class="text-end">{{ number_format($liasse['actif']['total']['brut'], 0, ',', ' ') }}</td>
                                <td class="text-end">{{ number_format($liasse['actif']['total']['prov'], 0, ',', ' ') }}</td>
                                <td class="text-end text-warning">{{ number_format($liasse['actif']['total']['net_n'], 0, ',', ' ') }}</td>
                                <td class="text-end">{{ number_format($liasse['actif']['total']['net_n1'], 0, ',', ' ') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- TAB 2: BILAN PASSIF --}}
            <div class="tab-pane fade" id="passif" role="tabpanel">
                <h5 class="fw-bold mb-3 text-secondary">BILAN PASSIF (Système Normal)</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-dark text-center">
                            <tr>
                                <th style="width:8%">Réf.</th>
                                <th>LIBELLÉ PASSIF</th>
                                <th style="width:20%">Exercice N</th>
                                <th style="width:20%">Exercice N-1</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($liasse['passif']['rows'] as $ref => $row)
                                <tr class="{{ !empty($row['is_total']) ? 'table-warning fw-bold' : '' }}">
                                    <td class="text-center fw-bold">{{ $ref }}</td>
                                    <td>{{ $row['libelle'] }}</td>
                                    <td class="text-end fw-bold {{ $row['net_n'] < 0 ? 'text-danger' : '' }}">{{ number_format($row['net_n'], 0, ',', ' ') }}</td>
                                    <td class="text-end text-muted">{{ number_format($row['net_n1'], 0, ',', ' ') }}</td>
                                </tr>
                            @endforeach
                            <tr class="table-dark fw-bold text-uppercase">
                                <td class="text-center">TOTAL</td>
                                <td>{{ $liasse['passif']['total']['libelle'] }}</td>
                                <td class="text-end text-warning">{{ number_format($liasse['passif']['total']['net_n'], 0, ',', ' ') }}</td>
                                <td class="text-end">{{ number_format($liasse['passif']['total']['net_n1'], 0, ',', ' ') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- TAB 3: COMPTE DE RÉSULTAT --}}
            <div class="tab-pane fade" id="resultat" role="tabpanel">
                <h5 class="fw-bold mb-3 text-secondary">COMPTE DE RÉSULTAT (Système Normal)</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-dark text-center">
                            <tr>
                                <th style="width:8%">Réf.</th>
                                <th>LIBELLÉ</th>
                                <th style="width:20%">Exercice N</th>
                                <th style="width:20%">Exercice N-1</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($liasse['resultat']['rows'] as $ref => $row)
                                <tr class="{{ !empty($row['is_total']) ? 'table-warning fw-bold' : '' }}">
                                    <td class="text-center fw-bold">{{ $ref }}</td>
                                    <td>{{ $row['libelle'] }}</td>
                                    <td class="text-end fw-bold">{{ number_format($row['net_n'], 0, ',', ' ') }}</td>
                                    <td class="text-end text-muted">{{ number_format($row['net_n1'], 0, ',', ' ') }}</td>
                                </tr>
                            @endforeach
                            <tr class="table-secondary fw-bold">
                                <td colspan="4" class="text-center text-uppercase">── SOLDES INTERMÉDIAIRES DE GESTION (SIG) ──</td>
                            </tr>
                            @foreach($liasse['resultat']['totals'] as $ref => $row)
                                <tr class="{{ $ref === 'XZ' ? 'table-success fw-bold fs-6' : 'table-light fw-bold' }}">
                                    <td class="text-center fw-bold">{{ $ref }}</td>
                                    <td>{{ $row['libelle'] }}</td>
                                    <td class="text-end fw-bold {{ $row['net_n'] >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ number_format($row['net_n'], 0, ',', ' ') }}
                                    </td>
                                    <td class="text-end text-muted">{{ number_format($row['net_n1'], 0, ',', ' ') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- TAB 4: TAFIRE --}}
            <div class="tab-pane fade" id="tafire" role="tabpanel">
                <h5 class="fw-bold mb-3 text-secondary">TAFIRE — Tableau de Financement des Ressources et Emplois</h5>
                @php $t = $liasse['tafire']; @endphp
                <div class="row g-4">
                    {{-- CAFG --}}
                    <div class="col-lg-6">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white fw-bold">
                                <i data-feather="zap" class="me-2"></i>Capacité d'Autofinancement Globale (CAFG)
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <tbody>
                                        <tr><td>Résultat net</td><td class="text-end fw-bold {{ $t['resultat_net'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($t['resultat_net'], 0, ',', ' ') }}</td></tr>
                                        <tr><td>(+) Dotations amortissements & provisions</td><td class="text-end">{{ number_format($t['dot_amort'], 0, ',', ' ') }}</td></tr>
                                        <tr><td>(−) Reprises provisions</td><td class="text-end text-danger">{{ number_format($t['reprises_amort'], 0, ',', ' ') }}</td></tr>
                                        <tr><td>(−) Produits cessions HAO</td><td class="text-end text-danger">{{ number_format($t['prod_cessions_hao'], 0, ',', ' ') }}</td></tr>
                                        <tr><td>(+) Valeurs comptables cessions HAO</td><td class="text-end">{{ number_format($t['val_compt_cessions'], 0, ',', ' ') }}</td></tr>
                                        <tr class="table-primary fw-bold"><td>= CAFG</td><td class="text-end text-primary">{{ number_format($t['cafg'], 0, ',', ' ') }}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    {{-- Ressources / Emplois --}}
                    <div class="col-lg-6">
                        <div class="card border-success">
                            <div class="card-header bg-success text-white fw-bold">
                                <i data-feather="arrow-up-circle" class="me-2"></i>Ressources Stables
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <tbody>
                                        <tr><td>CAFG</td><td class="text-end">{{ number_format($t['cafg'], 0, ',', ' ') }}</td></tr>
                                        <tr><td>Cessions d'immobilisations</td><td class="text-end">{{ number_format($t['cessions_immob'], 0, ',', ' ') }}</td></tr>
                                        <tr><td>Augmentation de capital</td><td class="text-end">{{ number_format($t['augment_capital'], 0, ',', ' ') }}</td></tr>
                                        <tr><td>Nouveaux emprunts contractés</td><td class="text-end">{{ number_format($t['emprunts_nouveaux'], 0, ',', ' ') }}</td></tr>
                                        <tr><td>Subventions d'investissement reçues</td><td class="text-end">{{ number_format($t['subventions_inv'], 0, ',', ' ') }}</td></tr>
                                        <tr class="table-success fw-bold"><td>= TOTAL RESSOURCES</td><td class="text-end text-success">{{ number_format($t['total_ressources'], 0, ',', ' ') }}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white fw-bold">
                                <i data-feather="arrow-down-circle" class="me-2"></i>Emplois Stables
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <tbody>
                                        <tr><td>Acquisitions immo. corporelles</td><td class="text-end">{{ number_format($t['acq_immo_corpo'], 0, ',', ' ') }}</td></tr>
                                        <tr><td>Acquisitions immo. incorporelles</td><td class="text-end">{{ number_format($t['acq_immo_incorpo'], 0, ',', ' ') }}</td></tr>
                                        <tr><td>Acquisitions immo. financières</td><td class="text-end">{{ number_format($t['acq_immo_financ'], 0, ',', ' ') }}</td></tr>
                                        <tr><td>Remboursements d'emprunts</td><td class="text-end">{{ number_format($t['remb_emprunts'], 0, ',', ' ') }}</td></tr>
                                        <tr><td>Dividendes distribués</td><td class="text-end">{{ number_format($t['dividendes'], 0, ',', ' ') }}</td></tr>
                                        <tr class="table-danger fw-bold"><td>= TOTAL EMPLOIS</td><td class="text-end text-danger">{{ number_format($t['total_emplois'], 0, ',', ' ') }}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card border-warning">
                            <div class="card-header bg-warning fw-bold">
                                <i data-feather="check-circle" class="me-2"></i>Synthèse TAFIRE
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <tbody>
                                        <tr class="fw-bold"><td>Variation FRNG</td><td class="text-end {{ $t['variation_frng'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($t['variation_frng'], 0, ',', ' ') }}</td></tr>
                                        <tr><td>BFR N</td><td class="text-end">{{ number_format($t['bfr_n'], 0, ',', ' ') }}</td></tr>
                                        <tr><td>BFR N-1</td><td class="text-end text-muted">{{ number_format($t['bfr_n1'], 0, ',', ' ') }}</td></tr>
                                        <tr class="fw-bold"><td>Variation BFR</td><td class="text-end">{{ number_format($t['variation_bfr'], 0, ',', ' ') }}</td></tr>
                                        <tr><td>Trésorerie nette N</td><td class="text-end">{{ number_format($t['treso_nette_n'], 0, ',', ' ') }}</td></tr>
                                        <tr><td>Trésorerie nette N-1</td><td class="text-end text-muted">{{ number_format($t['treso_nette_n1'], 0, ',', ' ') }}</td></tr>
                                        <tr class="fw-bold"><td>Variation Trésorerie</td><td class="text-end">{{ number_format($t['variation_treso'], 0, ',', ' ') }}</td></tr>
                                        <tr class="{{ $t['verification'] ? 'table-success' : 'table-danger' }} fw-bold">
                                            <td colspan="2" class="text-center">
                                                @if($t['verification'])
                                                    ✅ Équilibre TAFIRE vérifié (FRNG = ΔBFR + ΔTrésorerie)
                                                @else
                                                    ⚠️ Vérifier les données — Déséquilibre détecté
                                                @endif
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TAB 5: AMORTISSEMENTS --}}
            <div class="tab-pane fade" id="amort" role="tabpanel">
                <h5 class="fw-bold mb-3 text-secondary">Tableau des Amortissements Cumulés</h5>
                @php $am = $liasse['amortissements']; @endphp
                @if(!empty($am['rows']))
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-dark text-center">
                            <tr>
                                <th>Catégorie</th>
                                <th>Base amortissable</th>
                                <th>Amortissements cumulés</th>
                                <th>VNC (Valeur Nette Comptable)</th>
                                <th>Taux moyen (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($am['rows'] as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row['categorie'] }}</td>
                                <td class="text-end">{{ number_format($row['base'], 0, ',', ' ') }}</td>
                                <td class="text-end text-danger">{{ number_format($row['amort_cumule'], 0, ',', ' ') }}</td>
                                <td class="text-end fw-bold text-success">{{ number_format($row['vnc'], 0, ',', ' ') }}</td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $row['taux_moyen'] > 80 ? 'danger' : ($row['taux_moyen'] > 50 ? 'warning' : 'success') }}">
                                        {{ $row['taux_moyen'] }}%
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                            <tr class="table-dark fw-bold">
                                <td>TOTAL</td>
                                <td class="text-end">{{ number_format($am['totaux']['base'], 0, ',', ' ') }}</td>
                                <td class="text-end text-warning">{{ number_format($am['totaux']['amort_cumule'], 0, ',', ' ') }}</td>
                                <td class="text-end text-warning">{{ number_format($am['totaux']['vnc'], 0, ',', ' ') }}</td>
                                <td class="text-center"><span class="badge bg-info">{{ $am['totaux']['taux_moyen'] }}%</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                @else
                <div class="alert alert-info"><i data-feather="info" class="me-2"></i>Aucun amortissement enregistré sur la période.</div>
                @endif
            </div>

            {{-- TAB 6: PROVISIONS --}}
            <div class="tab-pane fade" id="prov" role="tabpanel">
                <h5 class="fw-bold mb-3 text-secondary">Tableau des Provisions Constituées</h5>
                @php $pr = $liasse['provisions']; @endphp
                @if(!empty($pr['rows']))
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-dark text-center">
                            <tr>
                                <th>Nature des Provisions</th>
                                <th style="width:30%">Montant (FCFA)</th>
                                <th style="width:20%">% du Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pr['rows'] as $row)
                            <tr>
                                <td>{{ $row['libelle'] }}</td>
                                <td class="text-end fw-bold">{{ number_format($row['montant'], 0, ',', ' ') }}</td>
                                <td class="text-center">
                                    @if($pr['totaux']['total'] > 0)
                                        {{ number_format(($row['montant'] / $pr['totaux']['total']) * 100, 1) }}%
                                    @else 0%
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                            <tr class="table-dark fw-bold">
                                <td>TOTAL PROVISIONS</td>
                                <td class="text-end text-warning">{{ number_format($pr['totaux']['total'], 0, ',', ' ') }}</td>
                                <td class="text-center">100%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                @else
                <div class="alert alert-info"><i data-feather="info" class="me-2"></i>Aucune provision constituée sur la période.</div>
                @endif
            </div>

            {{-- TAB 7: IMMOBILISATIONS --}}
            <div class="tab-pane fade" id="immo" role="tabpanel">
                <h5 class="fw-bold mb-3 text-secondary">Notes sur les Immobilisations — Mouvements de l'exercice</h5>
                @php $im = $liasse['immobilisations']; @endphp
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle small">
                        <thead class="table-dark text-center">
                            <tr>
                                <th>Catégorie</th>
                                <th>Valeur brute N-1</th>
                                <th>Acquisitions</th>
                                <th>Cessions</th>
                                <th>Valeur brute N</th>
                                <th>Amort. N-1</th>
                                <th>Dotations N</th>
                                <th>Amort. N</th>
                                <th>VNC N</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($im['rows'] as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row['libelle'] }}</td>
                                <td class="text-end">{{ number_format($row['brutN1'], 0, ',', ' ') }}</td>
                                <td class="text-end text-success">{{ number_format($row['acq'], 0, ',', ' ') }}</td>
                                <td class="text-end text-danger">{{ number_format($row['cess'], 0, ',', ' ') }}</td>
                                <td class="text-end fw-bold">{{ number_format($row['brutN'], 0, ',', ' ') }}</td>
                                <td class="text-end text-muted">{{ number_format($row['depN1'], 0, ',', ' ') }}</td>
                                <td class="text-end">{{ number_format($row['dotN'], 0, ',', ' ') }}</td>
                                <td class="text-end">{{ number_format($row['depN'], 0, ',', ' ') }}</td>
                                <td class="text-end fw-bold text-primary">{{ number_format($row['netN'], 0, ',', ' ') }}</td>
                            </tr>
                            @endforeach
                            <tr class="table-dark fw-bold">
                                <td>TOTAL</td>
                                <td class="text-end">{{ number_format($im['totaux']['brutN1'], 0, ',', ' ') }}</td>
                                <td class="text-end text-success">{{ number_format($im['totaux']['acq'], 0, ',', ' ') }}</td>
                                <td class="text-end text-danger">{{ number_format($im['totaux']['cess'], 0, ',', ' ') }}</td>
                                <td class="text-end text-warning">{{ number_format($im['totaux']['brutN'], 0, ',', ' ') }}</td>
                                <td class="text-end">{{ number_format($im['totaux']['depN1'], 0, ',', ' ') }}</td>
                                <td class="text-end">{{ number_format($im['totaux']['dotN'], 0, ',', ' ') }}</td>
                                <td class="text-end">{{ number_format($im['totaux']['depN'], 0, ',', ' ') }}</td>
                                <td class="text-end text-warning">{{ number_format($im['totaux']['netN'], 0, ',', ' ') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- TAB 8: ÉTATS ANNEXES --}}
            <div class="tab-pane fade" id="annexes" role="tabpanel">
                <h5 class="fw-bold mb-3 text-secondary">États Annexes BCEAO (EA 1 à EA 13)</h5>
                <div class="accordion" id="accordionAnnexes">
                    @php
                        $annexeIcons = ['EA1'=>'info','EA2'=>'book','EA3'=>'box','EA4'=>'bar-chart-2','EA5'=>'shield','EA6'=>'package','EA7'=>'users','EA8'=>'dollar-sign','EA9'=>'credit-card','EA10'=>'link','EA11'=>'user','EA12'=>'pie-chart','EA13'=>'divide-circle'];
                    @endphp
                    @foreach($liasse['annexes'] as $key => $annexe)
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button"
                                data-bs-toggle="collapse" data-bs-target="#collapse{{ $key }}"
                                aria-expanded="{{ $loop->first ? 'true' : 'false' }}">
                                <i data-feather="{{ $annexeIcons[$key] ?? 'file' }}" class="me-2" style="width:16px;height:16px;"></i>
                                <strong class="me-2">{{ $key }}</strong> — {{ $annexe['titre'] }}
                            </button>
                        </h2>
                        <div id="collapse{{ $key }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}">
                            <div class="accordion-body">
                                <p class="text-muted mb-3"><em>{{ $annexe['description'] ?? '' }}</em></p>

                                {{-- Affichage dynamique selon la structure de l'annexe --}}
                                @if(isset($annexe['items']) && is_array($annexe['items']))
                                    <table class="table table-sm table-bordered">
                                        @foreach($annexe['items'] as $label => $value)
                                        <tr>
                                            <td class="fw-semibold" style="width:50%">{{ $label }}</td>
                                            <td class="text-end">
                                                @if(is_numeric($value) && !is_string($value))
                                                    {{ number_format((float)$value, 0, ',', ' ') }} FCFA
                                                @else
                                                    {{ $value }}
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </table>
                                @endif

                                @if(isset($annexe['rows']) && is_array($annexe['rows']))
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered table-striped">
                                            <thead class="table-secondary">
                                                <tr>
                                                    @foreach(array_keys($annexe['rows'][0] ?? []) as $col)
                                                        <th>{{ ucfirst(str_replace('_', ' ', $col)) }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($annexe['rows'] as $row)
                                                <tr>
                                                    @foreach($row as $col => $val)
                                                    <td class="{{ is_numeric($val) ? 'text-end' : '' }}">
                                                        @if(is_numeric($val) && !is_string($val))
                                                            {{ number_format((float)$val, 0, ',', ' ') }}
                                                        @else
                                                            {{ $val }}
                                                        @endif
                                                    </td>
                                                    @endforeach
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif

                                @if(isset($annexe['engagements_donnes']))
                                    <h6 class="fw-bold mt-2">Engagements donnés</h6>
                                    <table class="table table-sm table-bordered">
                                        @foreach($annexe['engagements_donnes'] as $eng)
                                        <tr><td>{{ $eng['libelle'] }}</td><td class="text-end">{{ number_format($eng['montant'], 0, ',', ' ') }} FCFA</td></tr>
                                        @endforeach
                                    </table>
                                    <h6 class="fw-bold mt-2">Engagements reçus</h6>
                                    <table class="table table-sm table-bordered">
                                        @foreach($annexe['engagements_recus'] as $eng)
                                        <tr><td>{{ $eng['libelle'] }}</td><td class="text-end">{{ number_format($eng['montant'], 0, ',', ' ') }} FCFA</td></tr>
                                        @endforeach
                                    </table>
                                @endif

                                @if(isset($annexe['resultat_net']))
                                    <table class="table table-sm table-bordered">
                                        <tr><td class="fw-semibold">Résultat net de l'exercice</td><td class="text-end fw-bold {{ $annexe['resultat_net'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($annexe['resultat_net'], 0, ',', ' ') }} FCFA</td></tr>
                                        <tr><td class="fw-semibold">Report à nouveau</td><td class="text-end">{{ number_format($annexe['report_precedent'], 0, ',', ' ') }} FCFA</td></tr>
                                        <tr class="table-secondary fw-bold"><td>Total à distribuer</td><td class="text-end">{{ number_format($annexe['total_a_distribuer'], 0, ',', ' ') }} FCFA</td></tr>
                                        <tr><td>Réserve légale (10%)</td><td class="text-end">{{ number_format($annexe['reserve_legale'], 0, ',', ' ') }} FCFA</td></tr>
                                        <tr><td>Dividendes proposés</td><td class="text-end">{{ number_format($annexe['dividendes'], 0, ',', ' ') }} FCFA</td></tr>
                                        <tr class="table-warning fw-bold"><td>Report à nouveau (N+1)</td><td class="text-end">{{ number_format($annexe['report_suivant'], 0, ',', ' ') }} FCFA</td></tr>
                                    </table>
                                @endif

                                @if(isset($annexe['note']))
                                    <div class="alert alert-light border-start border-primary border-3 mt-2 small">
                                        <i data-feather="alert-circle" class="me-1"></i> {{ $annexe['note'] }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

        </div>{{-- end tab-content --}}
    </div>
</div>
@endsection
