@extends('layouts.app')

@section('title', 'Solde de ' . $commercial->name . ' | Cabinet')
@section('page_title', 'Solde commercial')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <a href="{{ route('accountant.commercials-balance.index') }}" class="text-decoration-none small d-block mb-1">&larr; Suivi Solde Commerciaux</a>
        <h1 class="h3 mb-1"><strong>{{ $commercial->name }}</strong></h1>
        <p class="text-muted mb-0">{{ $commercial->email }} @if($commercial->phone) &middot; {{ $commercial->phone }} @endif</p>
    </div>
</div>

@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show rounded-3 mb-3 border-0 shadow-sm" role="alert">
        <i data-feather="check-circle" class="me-2 text-success"></i>
        {{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-3 border-0 shadow-sm" role="alert">
        <i data-feather="alert-triangle" class="me-2 text-danger"></i>
        {{ $errors->first() }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body">
                <div class="text-uppercase small fw-bold text-muted">Total gagné</div>
                <div class="h4 fw-bold mt-1 mb-0">{{ number_format($totalBalance, 0, ',', ' ') }} F</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body">
                <div class="text-uppercase small fw-bold text-muted">Bonus d'ajout</div>
                <div class="h4 fw-bold mt-1 mb-0">{{ number_format($totalSignupEarnings, 0, ',', ' ') }} F</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body">
                <div class="text-uppercase small fw-bold text-muted">Renouvellements</div>
                <div class="h4 fw-bold mt-1 mb-0">{{ number_format($totalRenewalEarnings, 0, ',', ' ') }} F</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100" style="background: #0F2747;">
            <div class="card-body">
                <div class="text-uppercase small fw-bold" style="color: #F2D89B;">Reste à payer</div>
                <div class="h4 fw-bold mt-1 mb-0 text-white">{{ number_format($remaining, 0, ',', ' ') }} F</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0">Valider un paiement</h5>
            </div>
            <div class="card-body">
                @if($remaining <= 0)
                    <p class="text-muted mb-0">Ce commercial est à jour — aucun montant restant à verser.</p>
                @else
                    <form action="{{ route('accountant.commercials-balance.payouts.store', $commercial) }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Montant versé (FCFA)</label>
                            <input type="number" name="amount" min="1" max="{{ $remaining }}" value="{{ old('amount', $remaining) }}" class="form-control @error('amount') is-invalid @enderror" required>
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Reste dû : {{ number_format($remaining, 0, ',', ' ') }} FCFA</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Note (optionnel)</label>
                            <textarea name="note" rows="2" class="form-control" placeholder="Ex. Virement Wave du 06/08/2026">{{ old('note') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" onclick="return confirm('Confirmer ce versement ? Un reçu PDF sera généré.');">
                            <i data-feather="check-circle" class="me-1" style="width:16px;height:16px;"></i> Valider le paiement
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0">Historique des versements</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="bg-light text-slate-700 text-uppercase fs-8 fw-bold border-bottom">
                            <tr>
                                <th class="py-2 px-3">Reçu</th>
                                <th class="py-2 px-3 text-center">Date</th>
                                <th class="py-2 px-3 text-end">Montant</th>
                                <th class="py-2 px-3 text-end">PDF</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($payouts as $payout)
                            <tr>
                                <td class="py-2 px-3 small font-monospace">{{ $payout->receipt_number }}</td>
                                <td class="py-2 px-3 text-center small">{{ $payout->created_at->format('d/m/Y') }}</td>
                                <td class="py-2 px-3 text-end fw-semibold">{{ number_format($payout->amount, 0, ',', ' ') }} F</td>
                                <td class="py-2 px-3 text-end">
                                    <a href="{{ route('accountant.commercials-balance.payouts.receipt', $payout) }}" class="btn btn-xs btn-outline-primary rounded-pill px-2">
                                        <i data-feather="download" style="width:12px;height:12px;"></i> Reçu
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted small">Pas encore payé — aucun versement enregistré.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white border-bottom py-3">
        <h5 class="card-title mb-0">Détail par client apporté</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="min-width: 700px;">
                <thead class="bg-light text-slate-700 text-uppercase fs-8 fw-bold border-bottom">
                    <tr>
                        <th class="py-3 px-3 text-center" style="width: 60px;">Rang paiement</th>
                        <th class="py-3 px-3">Client / Entreprise</th>
                        <th class="py-3 px-3 text-center">Ajouté le</th>
                        <th class="py-3 px-3 text-end">Bonus d'ajout</th>
                        <th class="py-3 px-3 text-center">Renouvellements</th>
                        <th class="py-3 px-3 text-end">Sous-total</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="py-2.5 px-3 text-center fw-bold text-muted small align-middle">{{ $row['has_paid'] ? $row['rank'] : '—' }}</td>
                        <td class="py-2.5 px-3 align-middle">
                            <div class="fw-semibold text-dark fs-7">{{ $row['client']->company_name ?: $row['client']->name }}</div>
                            <div class="text-muted small">{{ $row['client']->email }}</div>
                        </td>
                        <td class="py-2.5 px-3 text-center text-slate-500 small align-middle">{{ $row['client']->created_at?->format('d/m/Y') }}</td>
                        <td class="py-2.5 px-3 text-end align-middle">
                            @if($row['has_paid'])
                                {{ number_format($row['signup_bonus'], 0, ',', ' ') }} F
                                @if($row['rank'] <= $tier1Slots)
                                    <span class="badge bg-light-warning text-warning ms-1" style="font-size:9px;">Palier 1</span>
                                @endif
                            @else
                                <span class="badge bg-light-secondary text-secondary" style="font-size:9px;">Pas encore payé</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-center align-middle">
                            @if($row['renewal_count'] > 0)
                                <span class="badge bg-light-success text-success">{{ $row['renewal_count'] }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-end fw-bold align-middle">{{ number_format($row['subtotal'], 0, ',', ' ') }} F</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">Aucun client ajouté par ce commercial.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
