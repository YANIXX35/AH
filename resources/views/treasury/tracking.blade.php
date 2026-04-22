@extends('layouts.app')

@section('title', 'Suivi Trésorerie | Sitiame Capitale')
@section('page_title', 'Tableau de bord Trésorerie')

@push('styles')
    <style>
        .tracking-crypto {
            background: linear-gradient(180deg, #f5f7fb 0%, #eef3f9 100%);
            border-radius: 1rem;
            padding: 1rem;
        }
        .crypto-headline {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            gap: .75rem;
        }
        .crypto-actions .btn { border-radius: .65rem; }
        .crypto-card {
            border: 1px solid #e5e7eb;
            border-radius: .95rem;
            background: #fff;
            box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
            height: 100%;
        }
        .crypto-card .card-body { padding: 1rem; }
        .crypto-label {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #64748b;
            font-weight: 700;
        }
        .crypto-value {
            font-size: 1.35rem;
            font-weight: 800;
            margin-top: .2rem;
            color: #0f172a;
        }
        .crypto-sub {
            font-size: .8rem;
            color: #6b7280;
        }
        .crypto-table thead th {
            background: #f8fafc;
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #64748b;
            white-space: nowrap;
        }
        .crypto-status {
            border-radius: 999px;
            font-size: .7rem;
            font-weight: 700;
            padding: .22rem .55rem;
            display: inline-flex;
            align-items: center;
        }
        .crypto-status.ok { background: #dcfce7; color: #166534; }
        .crypto-status.bad { background: #fee2e2; color: #991b1b; }
        .crypto-status.pending { background: #e2e8f0; color: #334155; }
    </style>
@endpush

@section('content')
<div class="container-fluid p-0 crypto-page">
    <div class="crypto-headline">
        <div>
            <h2 class="h3 mb-1"><strong>Tableau de bord</strong> Trésorerie</h2>
        </div>
        <div class="crypto-actions d-flex gap-2">
            <a href="{{ route('treasury.create') }}" class="btn btn-primary btn-sm">Nouvelle transaction</a>
            <a href="{{ route('treasury.forecast') }}" class="btn btn-outline-secondary btn-sm">Prévisions</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-4">
            <div class="crypto-card">
                <div class="card-body">
                    <div class="crypto-label">Solde global</div>
                    <div class="crypto-value">{{ number_format($soldeNetEffectue, 0, ',', ' ') }} FCFA</div>
                    <div class="crypto-sub {{ $soldeNetFiltre >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $soldeNetFiltre >= 0 ? '+' : '' }}{{ number_format($soldeNetFiltre, 0, ',', ' ') }} sur la période filtrée
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="crypto-card">
                <div class="card-body">
                    <div class="crypto-label">Encaissements</div>
                    <div class="crypto-value">{{ number_format($encaissementsTotal, 0, ',', ' ') }}</div>
                    <div class="crypto-sub">Encaissements</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="crypto-card">
                <div class="card-body">
                    <div class="crypto-label">Décaissements</div>
                    <div class="crypto-value">{{ number_format($decaissementsTotal, 0, ',', ' ') }}</div>
                    <div class="crypto-sub">Décaissements</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="crypto-card">
                <div class="card-body">
                    <div class="crypto-label">Planifié net</div>
                    <div class="crypto-value">{{ number_format($engagementsPlanifies, 0, ',', ' ') }}</div>
                    <div class="crypto-sub">Planifié net</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="crypto-card">
                <div class="card-body">
                    <div class="crypto-label">Clôture période</div>
                    <div class="crypto-value">{{ number_format($soldeCloturePeriode, 0, ',', ' ') }}</div>
                    <div class="crypto-sub">Clôture période</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-8">
            <div class="crypto-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Transactions</h5>
                        <small class="text-muted">View all</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover crypto-table mb-0">
                            <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Module</th>
                                <th>Description</th>
                                <th>Montant</th>
                                <th>Référence</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($transactions as $tx)
                                @php
                                    $statusClass = $tx->status === 'effectue'
                                        ? 'ok'
                                        : ($tx->status === 'annule' ? 'bad' : 'pending');
                                @endphp
                                <tr>
                                    <td class="small text-nowrap">{{ $tx->transaction_date->format('d/m/Y') }}</td>
                                    <td class="small">{{ ucfirst($tx->type) }}</td>
                                    <td class="small">
                                        @if($tx->payment_module === 'stripe')
                                            @if($tx->stripe_payment_channel === 'bank_debit')
                                                <span class="badge bg-info-subtle text-info">Stripe {{ strtoupper((string) ($tx->stripe_bank_scheme ?? 'SEPA')) }}</span>
                                            @else
                                                <span class="badge bg-dark-subtle text-dark">Stripe Carte</span>
                                            @endif
                                        @elseif($tx->payment_module === 'fedapay_mobile')
                                            <span class="badge bg-warning-subtle text-warning">FedaPay Mobile</span>
                                        @else
                                            <span class="badge bg-light text-muted">Non défini</span>
                                        @endif
                                    </td>
                                    <td class="small">{{ \Illuminate\Support\Str::limit($tx->description, 45) }}</td>
                                    <td class="small fw-semibold {{ $tx->type === 'encaissement' ? 'text-success' : 'text-danger' }}">
                                        {{ $tx->type === 'encaissement' ? '+' : '-' }}{{ number_format($tx->amount, 0, ',', ' ') }} FCFA
                                    </td>
                                    <td class="small text-break">
                                        {{ $tx->reference ?? '-' }}
                                        @if($tx->payment_module === 'stripe' && $tx->stripe_status)
                                            <div class="text-muted">Stripe: {{ $tx->stripe_status }}</div>
                                        @endif
                                        @if($tx->payment_module === 'stripe' && $tx->stripe_payout_id)
                                            <div class="text-muted">Payout: {{ $tx->stripe_payout_id }}</div>
                                        @endif
                                        @if($tx->payment_module === 'fedapay_mobile' && $tx->bank_reference)
                                            <div class="text-muted">FedaPay: {{ $tx->bank_reference }}</div>
                                        @endif
                                    </td>
                                    <td class="small"><span class="crypto-status {{ $statusClass }}">{{ strtoupper($tx->status) }}</span></td>
                                    <td class="small text-nowrap">
                                        @if($tx->payment_module === 'fedapay_mobile' && $tx->type === 'encaissement' && $tx->status !== 'annule')
                                            <a href="{{ route('treasury.fedapay.checkout.form', ['transaction' => $tx->id, 'country' => 'CIV']) }}" class="btn btn-sm btn-outline-success">Payer mobile</a>
                                        @endif
                                        <a href="{{ route('treasury.edit', $tx) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form action="{{ route('treasury.destroy', $tx) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Confirmer la suppression de cette transaction ?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Aucune transaction ne correspond aux critères.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($transactions->hasPages())
                        <div class="mt-3">{{ $transactions->links('pagination::simple-bootstrap-5') }}</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="crypto-card h-100">
                <div class="card-body">
                    <h5 class="mb-3">Filtres</h5>
                    <form method="GET" class="row g-2">
                        <div class="col-12">
                            <label class="form-label small">Type</label>
                            <select name="type" class="form-select form-select-sm">
                                <option value="all" {{ $type === 'all' ? 'selected' : '' }}>All</option>
                                <option value="encaissement" {{ $type === 'encaissement' ? 'selected' : '' }}>Encaissement</option>
                                <option value="decaissement" {{ $type === 'decaissement' ? 'selected' : '' }}>Décaissement</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All</option>
                                <option value="planifie" {{ $status === 'planifie' ? 'selected' : '' }}>Planifiée</option>
                                <option value="effectue" {{ $status === 'effectue' ? 'selected' : '' }}>Effectuée</option>
                                <option value="annule" {{ $status === 'annule' ? 'selected' : '' }}>Annulée</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Module paiement</label>
                            <select name="payment_module" class="form-select form-select-sm">
                                <option value="all" {{ ($paymentModule ?? 'all') === 'all' ? 'selected' : '' }}>Tous</option>
                                <option value="stripe" {{ ($paymentModule ?? 'all') === 'stripe' ? 'selected' : '' }}>Stripe</option>
                                <option value="fedapay_mobile" {{ ($paymentModule ?? 'all') === 'fedapay_mobile' ? 'selected' : '' }}>FedaPay Mobile</option>
                                <option value="none" {{ ($paymentModule ?? 'all') === 'none' ? 'selected' : '' }}>Non défini</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">From</label>
                            <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">To</label>
                            <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 d-grid mt-2">
                            <button class="btn btn-primary btn-sm" type="submit">Appliquer les filtres</button>
                        </div>
                    </form>
                    <hr>
                    <div class="small text-muted">
                        Solde d'ouverture: <strong>{{ number_format($soldeOuverturePeriode, 0, ',', ' ') }} FCFA</strong><br>
                        Solde de clôture: <strong>{{ number_format($soldeCloturePeriode, 0, ',', ' ') }} FCFA</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
