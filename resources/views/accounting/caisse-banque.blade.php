@extends('layouts.app')

@section('title', 'Caisse Banque | Sitiame Capital')
@section('page_title', 'Caisse Banque')

@section('content')
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-1">Caisse Banque</h5>
                    <p class="text-muted mb-0">Suivi des règlements de vos écritures comptables — qui a payé, qui reste à payer.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-danger-subtle h-100">
                <div class="card-body">
                    <div class="text-danger small fw-semibold text-uppercase">Total impayé</div>
                    <div class="fs-4 fw-bold">{{ number_format($totalUnpaid, 0, ',', ' ') }} FCFA</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-warning-subtle h-100">
                <div class="card-body">
                    <div class="text-warning small fw-semibold text-uppercase">Total partiellement payé</div>
                    <div class="fs-4 fw-bold">{{ number_format($totalPartial, 0, ',', ' ') }} FCFA</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-success-subtle h-100">
                <div class="card-body">
                    <div class="text-success small fw-semibold text-uppercase">Total payé</div>
                    <div class="fs-4 fw-bold">{{ number_format($totalPaid, 0, ',', ' ') }} FCFA</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('accounting.caisse-banque') }}" method="GET" class="row g-2 align-items-end mb-4">
                <div class="col-auto">
                    <label class="small text-muted d-block">Statut</label>
                    <select name="payment_status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Tous</option>
                        <option value="unpaid" {{ $statusFilter === 'unpaid' ? 'selected' : '' }}>Impayé</option>
                        <option value="partial" {{ $statusFilter === 'partial' ? 'selected' : '' }}>Partiel</option>
                        <option value="paid" {{ $statusFilter === 'paid' ? 'selected' : '' }}>Payé</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="small text-muted d-block">Type</label>
                    <select name="document_type" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Tous</option>
                        <option value="Vente" {{ $documentType === 'Vente' ? 'selected' : '' }}>Vente</option>
                        <option value="Achat" {{ $documentType === 'Achat' ? 'selected' : '' }}>Achat</option>
                        <option value="Reçu" {{ $documentType === 'Reçu' ? 'selected' : '' }}>Reçu</option>
                        <option value="Justificatif" {{ $documentType === 'Justificatif' ? 'selected' : '' }}>Justificatif</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="small text-muted d-block">Compte</label>
                    <input type="text" name="account" class="form-control form-control-sm" placeholder="Débit / Crédit" value="{{ $account }}">
                </div>
                <div class="col-auto">
                    <label class="small text-muted d-block">Du</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
                </div>
                <div class="col-auto">
                    <label class="small text-muted d-block">Au</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Filtrer</button>
                    <a href="{{ route('accounting.caisse-banque') }}" class="btn btn-sm btn-outline-secondary">Effacer</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Tiers</th>
                            <th class="text-end">Montant</th>
                            <th class="text-end">Réglé</th>
                            <th class="text-end">Solde dû</th>
                            <th>Statut</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $entry)
                            @php($badge = $entry->paymentStatusBadge())
                            @php($due = (float) $entry->amount - (float) $entry->amount_paid)
                            <tr>
                                <td>{{ $entry->date?->format('d/m/Y') }}</td>
                                <td>{{ $entry->description }}</td>
                                <td class="small text-muted">{{ $entry->debit_account }} / {{ $entry->credit_account }}</td>
                                <td class="text-end">{{ number_format((float) $entry->amount, 0, ',', ' ') }}</td>
                                <td class="text-end">{{ number_format((float) $entry->amount_paid, 0, ',', ' ') }}</td>
                                <td class="text-end fw-semibold">{{ number_format($due, 0, ',', ' ') }}</td>
                                <td><span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span></td>
                                <td>
                                    @if($entry->payment_status !== 'paid')
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#payForm{{ $entry->id }}">
                                            Enregistrer un paiement
                                        </button>
                                    @endif
                                    @if($entry->payments->isNotEmpty())
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#payHistory{{ $entry->id }}">
                                            Voir les paiements ({{ $entry->payments->count() }})
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @if($entry->payment_status !== 'paid')
                                <tr class="collapse" id="payForm{{ $entry->id }}">
                                    <td colspan="8" class="bg-light">
                                        <form action="{{ route('accounting.entries.payments.store', $entry) }}" method="POST" class="row g-2 align-items-end py-2">
                                            @csrf
                                            <div class="col-auto">
                                                <label class="small text-muted d-block">Montant (solde dû : {{ number_format($due, 0, ',', ' ') }})</label>
                                                <input type="number" step="0.01" name="amount" max="{{ $due }}" class="form-control form-control-sm" required>
                                            </div>
                                            <div class="col-auto">
                                                <label class="small text-muted d-block">Date</label>
                                                <input type="date" name="payment_date" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required>
                                            </div>
                                            <div class="col-auto">
                                                <label class="small text-muted d-block">Méthode</label>
                                                <select name="method" class="form-select form-select-sm" required>
                                                    <option value="mobile_money">Mobile Money</option>
                                                    <option value="banque">Banque</option>
                                                    <option value="especes">Espèces</option>
                                                    <option value="autre">Autre</option>
                                                </select>
                                            </div>
                                            <div class="col-auto">
                                                <label class="small text-muted d-block">Référence</label>
                                                <input type="text" name="reference" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-auto">
                                                <label class="small text-muted d-block">Compte de trésorerie</label>
                                                <input type="text" name="bank_account" class="form-control form-control-sm" value="512 Banque">
                                            </div>
                                            <div class="col-auto">
                                                <button type="submit" class="btn btn-sm btn-success">Valider le paiement</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                            @if($entry->payments->isNotEmpty())
                                <tr class="collapse" id="payHistory{{ $entry->id }}">
                                    <td colspan="8" class="bg-light">
                                        <table class="table table-sm mb-0">
                                            <thead><tr><th>Date</th><th>Montant</th><th>Méthode</th><th>Référence</th></tr></thead>
                                            <tbody>
                                                @foreach($entry->payments as $payment)
                                                    <tr>
                                                        <td>{{ $payment->payment_date?->format('d/m/Y') }}</td>
                                                        <td>{{ number_format((float) $payment->amount, 0, ',', ' ') }} FCFA</td>
                                                        <td>{{ $payment->method }}</td>
                                                        <td>{{ $payment->reference ?: '—' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">Aucune écriture ne correspond à ce filtre.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
