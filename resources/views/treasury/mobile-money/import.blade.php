@extends('layouts.app')

@section('title', 'Rapprochement Mobile Money | Sitiame Capital')
@section('page_title', 'Rapprochement Mobile Money')

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h3 mb-1"><strong>Rapprochement</strong> Mobile Money</h2>
            <p class="text-muted small mb-0">Importe un relevé Wave / Orange Money / MTN MoMo exporté depuis l'application de l'opérateur, et rapproche automatiquement avec ta trésorerie.</p>
        </div>
        <a href="{{ route('treasury.tracking') }}" class="btn btn-outline-secondary btn-sm">Retour trésorerie</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-xl-5">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-1">Importer un relevé</h5>
                    <p class="text-muted small mb-3">
                        Export au format CSV depuis l'appli Wave / Orange Money / MTN MoMo (historique des transactions).
                        Aucune donnée bancaire n'est requise : lecture seule.
                    </p>
                    <form action="{{ route('treasury.mobile-money.import') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label" for="operator">Opérateur *</label>
                            <select name="operator" id="operator" class="form-select" required>
                                <option value="wave">Wave</option>
                                <option value="orange_money">Orange Money</option>
                                <option value="mtn_momo">MTN MoMo</option>
                                <option value="moov_money">Moov Money</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="statement">Fichier CSV *</label>
                            <input type="file" name="statement" id="statement" class="form-control" accept=".csv,.txt" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="treasury_account_code">Compte de trésorerie cible (optionnel)</label>
                            <select name="treasury_account_code" id="treasury_account_code" class="form-select">
                                <option value="">— Non précisé —</option>
                                @foreach ($treasuryAccounts as $account)
                                    <option value="{{ $account->prefix }}">{{ $account->prefix }} — {{ $account->label }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Utilisé comme référence pour les écritures que tu créeras ensuite dans le module Comptabilité.</small>
                        </div>

                        <div class="alert alert-secondary small mb-3">
                            <strong>Protection des données.</strong> Ce relevé peut contenir des données à caractère personnel de tes correspondants (nom, numéro de téléphone). Elles seront utilisées uniquement pour le rapprochement avec ta trésorerie et la traçabilité comptable. Tu pourras les effacer à tout moment depuis la page de revue, sans perdre les montants ni les écritures déjà générées.
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" name="consent" id="consent" class="form-check-input" value="1" required>
                            <label class="form-check-label" for="consent">
                                Je confirme être autorisé à importer ce relevé et je consens au traitement des données personnelles qu'il contient, aux fins décrites ci-dessus. *
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary">Importer et rapprocher</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-7">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Imports précédents</h5>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Opérateur</th>
                                    <th>Fichier</th>
                                    <th class="text-end">Importées</th>
                                    <th class="text-end">Rapprochées</th>
                                    <th>Statut</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($imports as $import)
                                    <tr>
                                        <td>{{ $import->created_at->format('d/m/Y H:i') }}</td>
                                        <td>{{ ucfirst(str_replace('_', ' ', $import->operator)) }}</td>
                                        <td class="text-truncate" style="max-width: 160px;" title="{{ $import->original_filename }}">{{ $import->original_filename }}</td>
                                        <td class="text-end">{{ $import->rows_imported }}</td>
                                        <td class="text-end">{{ $import->rows_matched }}</td>
                                        <td>
                                            <span class="badge bg-{{ $import->status === 'completed' ? 'success' : ($import->status === 'failed' ? 'danger' : 'secondary') }}">
                                                {{ $import->status }}
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('treasury.mobile-money.review', $import) }}" class="btn btn-sm btn-outline-primary">Revoir</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">Aucun import pour l'instant.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $imports->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
