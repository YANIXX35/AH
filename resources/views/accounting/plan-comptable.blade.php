@extends('layouts.app')

@section('title', 'Plan comptable OHADA | Sitiame Capitale')
@section('page_title', 'Plan comptable OHADA')

@section('content')
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                    <div>
                        <h5 class="card-title">Plan comptable OHADA (paramétrable)</h5>
                        <p class="text-muted mb-0">Importez votre plan comptable Excel pour que tous les calculs comptables s'appuient sur votre référentiel de comptes.</p>
                        <p class="text-muted small mb-0">Source actuelle : {{ $source }}</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('accounting.plan.download.template') }}" class="btn btn-outline-info">
                            <i data-feather="download" class="me-1"></i>Télécharger le modèle
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if(isset($validation) && !empty($validation['missingClasses']))
        <div class="alert alert-warning">
            <strong>Plan incomplet :</strong> classes manquantes détectées :
            {{ implode(', ', $validation['missingClasses']) }}.
            <br>
            Assurez-vous que le fichier Excel contient au moins une ligne pour chaque classe 1 à 7.
        </div>
    @elseif(isset($validation) && $validation['isValid'])
        <div class="alert alert-success">
            Le plan comptable contient toutes les classes 1 à 7 et est prêt à être utilisé.
        </div>
    @endif

    @if(session('invalidRows') && count(session('invalidRows')) > 0)
        <div class="alert alert-warning">
            <h6 class="mb-2">Lignes invalides détectées lors de l’import</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Ligne</th>
                            <th>Compte</th>
                            <th>Libellé</th>
                            <th>Raison</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(session('invalidRows') as $invalid)
                            <tr>
                                <td>{{ $invalid['row'] }}</td>
                                <td>{{ $invalid['code'] ?: 'N/A' }}</td>
                                <td>{{ $invalid['label'] ?: 'N/A' }}</td>
                                <td>{{ $invalid['reason'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if(isset($qualityAlerts) && count($qualityAlerts) > 0)
        <div class="alert alert-warning">
            <h6 class="mb-2">Alertes qualité du plan comptable</h6>
            <p class="mb-2">Certains libellés sont trop génériques pour un pilotage financier fiable.</p>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Classe</th>
                            <th>Libellé actuel</th>
                            <th>Recommandation expert</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($qualityAlerts as $alert)
                            <tr>
                                <td>{{ $alert['prefix'] }}</td>
                                <td>{{ $alert['label'] }}</td>
                                <td>{{ $alert['reason'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-xl-12">
            <div class="card border-info">
                <div class="card-body">
                    <h6 class="card-title text-info">
                        <i data-feather="info" class="me-2"></i>Comment utiliser le plan comptable
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>1. Télécharger le modèle</h6>
                            <p class="small text-muted">Téléchargez le modèle CSV contenant tous les comptes du plan comptable français standard.</p>
                        </div>
                        <div class="col-md-6">
                            <h6>2. Personnaliser</h6>
                            <p class="small text-muted">Modifiez les libellés selon vos besoins spécifiques.</p>
                        </div>
                        <div class="col-md-6">
                            <h6>3. Importer</h6>
                            <p class="small text-muted">Importez votre fichier Excel/CSV personnalisé pour remplacer le plan par défaut.</p>
                        </div>
                        <div class="col-md-6">
                            <h6>4. Calculs automatiques</h6>
                            <p class="small text-muted">Tous les rapports financiers utiliseront automatiquement votre plan comptable personnalisé.</p>
                        </div>
                    </div>
                    <div class="alert alert-warning mt-3 mb-0">
                        <strong>Note expert comptable :</strong> les classes 1-5 sont traitées en bilan et les classes 6-7 en résultat.
                        Pour garantir la cohérence des états financiers, cette classification est verrouillée par la classe du compte.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-5">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('accounting.plan.upload') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Fichier plan comptable</label>
                            <input type="file" name="plan_comptable" class="form-control @error('plan_comptable') is-invalid @enderror" accept=".xls,.xlsx,.csv,.pdf">
                            @error('plan_comptable')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text mt-2">
                                Formats : <strong>Excel (XLS, XLSX), CSV ou PDF</strong>.
                                Tableur : deux colonnes minimum (<strong>Compte/Code</strong> et <strong>Intitulé/Libellé</strong>).
                                PDF : texte sélectionnable ou scan (OCR) ; lignes du type <code>601 … Libellé</code>.
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Importer le plan comptable</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">Comptes importés</h5>
                        <div class="d-flex gap-2">
                            <form action="{{ route('accounting.plan.reset') }}" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm">Réinitialiser</button>
                            </form>
                            <button type="submit" form="plan-edit-form" class="btn btn-primary btn-sm">Enregistrer les modifications</button>
                        </div>
                    </div>
                    <form id="plan-edit-form" action="{{ route('accounting.plan.update') }}" method="POST">
                        @csrf
                    <div class="table-responsive">
                        @php
                            $hasDetailedAccounts = collect($plan)->filter(fn($a, $k) => isset($a['numero_compte'] ?? null) !== null)->isNotEmpty();
                        @endphp
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th class="text-center" style="width: 80px;">Classe</th>
                                    @if($hasDetailedAccounts)
                                        <th class="text-center" style="width: 120px;">Numéro</th>
                                    @endif
                                    <th>Libellé du compte</th>
                                    @if($hasDetailedAccounts)
                                        <th class="text-center" style="width: 120px;">Type</th>
                                    @endif
                                    <th class="text-center" style="width: 120px;">Catégorie</th>
                                    <th class="text-center" style="width: 120px;">Sous-catégorie</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($plan as $key => $account)
                                    @php
                                        $class = $account['classe'] ?? $account['prefix'] ?? $key;
                                    @endphp
                                    <tr class="table-{{ $class === '1' ? 'primary' : ($class === '2' ? 'success' : ($class === '3' ? 'info' : ($class === '4' ? 'warning' : ($class === '5' ? 'secondary' : ($class === '6' ? 'danger' : 'light'))))) }} bg-opacity-10">
                                        <td class="text-center fw-bold">{{ $class }}</td>
                                        @if($hasDetailedAccounts)
                                            <td class="text-center">
                                                <input type="text" name="plan[{{ $key }}][numero_compte]" value="{{ $account['numero_compte'] ?? $class }}" class="form-control form-control-sm text-center" @if(isset($account['numero_compte'])) readonly @endif>
                                                <input type="hidden" name="plan[{{ $key }}][prefix]" value="{{ $class }}">
                                            </td>
                                        @else
                                            <td class="text-center">
                                                <input type="text" name="plan[{{ $key }}][prefix]" value="{{ $class }}" class="form-control form-control-sm text-center" readonly style="background-color: transparent; border: none; font-weight: bold;">
                                            </td>
                                        @endif
                                        <td>
                                            <input type="text" name="plan[{{ $key }}][label]" value="{{ $account['libelle_compte'] ?? $account['label'] }}" class="form-control form-control-sm" required>
                                        </td>
                                        @if($hasDetailedAccounts)
                                            <td class="text-center">
                                                <input type="text" name="plan[{{ $key }}][type_compte]" value="{{ $account['type_compte'] ?? '' }}" class="form-control form-control-sm text-center">
                                            </td>
                                        @endif
                                        <td class="text-center">
                                            <input type="text" class="form-control form-control-sm text-center"
                                                value="{{ ($account['category'] ?? (in_array($class, ['6', '7']) ? 'Résultat' : 'Bilan') }}"
                                                readonly>
                                            <input type="hidden" name="plan[{{ $key }}][category]" value="{{ $account['category'] ?? (in_array($class, ['6', '7']) ? 'resultat' : 'balance') }}">
                                        </td>
                                        <td class="text-center">
                                            @if(in_array($class, ['6', '7'], true))
                                                <select name="plan[{{ $key }}][subtype]" class="form-select form-select-sm">
                                                    <option value="charge" {{ ($account['subtype'] ?? ($class === '6' ? 'charge' : 'produit')) === 'charge' ? 'selected' : '' }}>Charge</option>
                                                    <option value="produit" {{ ($account['subtype'] ?? ($class === '7' ? 'produit' : 'charge')) === 'produit' ? 'selected' : '' }}>Produit</option>
                                                </select>
                                            @else
                                                <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $hasDetailedAccounts ? 6 : 5 }}" class="text-center text-muted py-4">
                                            <i data-feather="file-x" class="mb-2"></i>
                                            <br>Aucun compte chargé pour l'instant.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Historique des imports</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Fichier</th>
                                    <th>Statut</th>
                                    <th>Valides</th>
                                    <th>Invalides</th>
                                    <th>Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($importHistory as $import)
                                    <tr>
                                        <td>{{ $import->created_at->format('d/m/Y H:i') }}</td>
                                        <td>{{ $import->original_filename }}</td>
                                        <td>{{ ucfirst($import->status) }}</td>
                                        <td>{{ $import->valid_rows }}</td>
                                        <td>{{ $import->invalid_rows }}</td>
                                        <td>{{ $import->message }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">Aucun import enregistré.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-end">
        <a href="{{ route('accounting') }}" class="btn btn-outline-secondary">Retour au module comptabilité</a>
    </div>
@endsection
