@extends('layouts.app')

@section('title', 'Ajouter Transaction | Sitiame Capitale')
@section('page_title', 'Nouvelle transaction de trésorerie')

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
        font-size: 1.25rem;
        font-weight: 800;
        margin-top: .2rem;
        color: #0f172a;
    }
    .crypto-sub {
        font-size: .8rem;
        color: #6b7280;
    }
    .crypto-form-label {
        font-size: .74rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #475467;
        font-weight: 600;
        margin-bottom: .35rem;
    }
    .crypto-form .form-control,
    .crypto-form .form-select {
        border-radius: .6rem;
        border-color: #d0d5dd;
    }
    .crypto-form .form-control:focus,
    .crypto-form .form-select:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 .2rem rgba(59, 130, 246, .15);
    }
</style>
@endpush

@section('content')
<div class="container-fluid p-0 tracking-crypto">
    <div class="crypto-headline">
        <div>
            <h2 class="h3 mb-1"><strong>Tableau de bord</strong> Trésorerie</h2>
            <p class="text-muted mb-0">Treasury tracking styled like AdminKit crypto dashboard.</p>
        </div>
        <div class="crypto-actions d-flex gap-2">
            <a href="{{ route('treasury.tracking') }}" class="btn btn-primary btn-sm">Retour suivi</a>
            <a href="{{ route('treasury.balance') }}" class="btn btn-outline-secondary btn-sm">Voir le solde</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="crypto-card">
                <div class="card-body">
                    <h5 class="mb-3">Formulaire de transaction</h5>
                    <form action="{{ route('treasury.store') }}" method="POST" class="crypto-form">
                        @csrf

                        <div class="row g-3 mb-1">
                            <div class="col-12 col-md-6">
                                <label class="crypto-form-label" for="type">Type de transaction *</label>
                                <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                                    <option value="">Sélectionner...</option>
                                    <option value="encaissement" {{ old('type') === 'encaissement' ? 'selected' : '' }}>Encaissement</option>
                                    <option value="decaissement" {{ old('type') === 'decaissement' ? 'selected' : '' }}>Décaissement</option>
                                </select>
                                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="crypto-form-label" for="transaction_type">Catégorie *</label>
                                <select id="transaction_type" name="transaction_type" class="form-select @error('transaction_type') is-invalid @enderror" required>
                                    <option value="">Sélectionner...</option>
                                    <option value="Paiement client" {{ old('transaction_type') === 'Paiement client' ? 'selected' : '' }}>Paiement client</option>
                                    <option value="Paiement fournisseur" {{ old('transaction_type') === 'Paiement fournisseur' ? 'selected' : '' }}>Paiement fournisseur</option>
                                    <option value="Frais bancaires" {{ old('transaction_type') === 'Frais bancaires' ? 'selected' : '' }}>Frais bancaires</option>
                                    <option value="Versement" {{ old('transaction_type') === 'Versement' ? 'selected' : '' }}>Versement</option>
                                    <option value="Retrait" {{ old('transaction_type') === 'Retrait' ? 'selected' : '' }}>Retrait</option>
                                    <option value="Intérêts" {{ old('transaction_type') === 'Intérêts' ? 'selected' : '' }}>Intérêts</option>
                                    <option value="Autre" {{ old('transaction_type') === 'Autre' ? 'selected' : '' }}>Autre</option>
                                </select>
                                @error('transaction_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="row g-3 mb-1">
                            <div class="col-12 col-md-6">
                                <label class="crypto-form-label" for="payment_module">Passerelle</label>
                                <select id="payment_module" name="payment_module" class="form-select @error('payment_module') is-invalid @enderror" required>
                                    <option value="stripe" selected>Stripe</option>
                                </select>
                                @error('payment_module')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="crypto-form-label" for="stripe_payment_channel">Type d'opération Stripe *</label>
                                <select id="stripe_payment_channel" name="stripe_payment_channel" class="form-select @error('stripe_payment_channel') is-invalid @enderror" required>
                                    <option value="card" {{ old('stripe_payment_channel', 'card') === 'card' ? 'selected' : '' }}>Cartes bancaires (Visa, Mastercard, Amex)</option>
                                    <option value="bank_debit" {{ old('stripe_payment_channel') === 'bank_debit' ? 'selected' : '' }}>Virements bancaires (ACH / SEPA)</option>
                                </select>
                                @error('stripe_payment_channel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="row g-3 mb-1" id="stripe-bank-scheme-fields" style="display:none;">
                            <div class="col-12 col-md-6">
                                <label class="crypto-form-label" for="stripe_bank_scheme">Réseau bancaire *</label>
                                <select id="stripe_bank_scheme" name="stripe_bank_scheme" class="form-select @error('stripe_bank_scheme') is-invalid @enderror">
                                    <option value="ach" {{ old('stripe_bank_scheme') === 'ach' ? 'selected' : '' }}>ACH (US)</option>
                                    <option value="sepa" {{ old('stripe_bank_scheme', 'sepa') === 'sepa' ? 'selected' : '' }}>SEPA (Europe)</option>
                                </select>
                                @error('stripe_bank_scheme')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="crypto-form-label" for="payment_provider">Fournisseur</label>
                                <input type="text" id="payment_provider" name="payment_provider" class="form-control @error('payment_provider') is-invalid @enderror"
                                       value="{{ old('payment_provider', 'Stripe') }}" readonly>
                                @error('payment_provider')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="row g-3 mb-1">
                            <div class="col-12 col-md-6">
                                <label class="crypto-form-label" for="amount">Montant (FCFA) *</label>
                                <input type="number" id="amount" name="amount" class="form-control @error('amount') is-invalid @enderror"
                                       value="{{ old('amount') }}" placeholder="0.00" step="0.01" min="0" required>
                                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="crypto-form-label" for="transaction_date">Date de transaction *</label>
                                <input type="date" id="transaction_date" name="transaction_date" class="form-control @error('transaction_date') is-invalid @enderror"
                                       value="{{ old('transaction_date', now()->format('Y-m-d')) }}" required>
                                @error('transaction_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="crypto-form-label" for="description">Description *</label>
                            <input type="text" id="description" name="description" class="form-control @error('description') is-invalid @enderror"
                                   value="{{ old('description') }}" placeholder="Ex: Paiement facture #123" maxlength="255" required>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="row g-3 mb-1">
                            <div class="col-12 col-md-6">
                                <label class="crypto-form-label" for="reference">Référence / N° chèque</label>
                                <input type="text" id="reference" name="reference" class="form-control @error('reference') is-invalid @enderror"
                                       value="{{ old('reference') }}" placeholder="Ex: REF-001 ou CHEQUE-456" maxlength="100">
                                @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="crypto-form-label" for="bank_account">Compte bancaire</label>
                                <input type="text" id="bank_account" name="bank_account" class="form-control @error('bank_account') is-invalid @enderror"
                                       value="{{ old('bank_account') }}" placeholder="Ex: Compte courant" maxlength="100">
                                @error('bank_account')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="crypto-form-label" for="status">Statut *</label>
                            <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                <option value="planifie" {{ old('status', 'planifie') === 'planifie' ? 'selected' : '' }}>Planifiée</option>
                                
                                <option value="effectue" {{ old('status') === 'effectue' ? 'selected' : '' }}>Effectuée</option>
                                <option value="annule" {{ old('status') === 'annule' ? 'selected' : '' }}>Annulée</option>
                            </select>
                            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="crypto-form-label" for="notes">Notes / Observations</label>
                            <textarea id="notes" name="notes" class="form-control @error('notes') is-invalid @enderror"
                                      rows="3" placeholder="Détails supplémentaires...">{{ old('notes') }}</textarea>
                            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-4">
                            <a href="{{ route('treasury.tracking') }}" class="btn btn-outline-secondary">Annuler</a>
                            <button type="submit" class="btn btn-primary">Enregistrer la transaction</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="crypto-card">
                <div class="card-body">
                    <div class="crypto-label">Guide rapide</div>
                    <div class="crypto-value">Saisie sécurisée</div>
                    <p class="crypto-sub mb-3">Renseignez le type, le montant et le statut réel pour une traçabilité fidèle.</p>
                    <div class="alert alert-info small mb-3">
                        Stripe gère ici deux opérations :<br>
                        - Cartes bancaires (Visa, Mastercard, Amex)<br>
                        - Virements bancaires ACH / SEPA
                    </div>
                    @php($stripePaymentLink = trim((string) config('services.stripe.payment_link_url', '')))
                    @if($stripePaymentLink !== '')
                        <a href="{{ $stripePaymentLink }}" class="btn btn-outline-dark btn-sm w-100 mb-3" target="_blank" rel="noopener">
                            Ouvrir le lien de paiement Stripe
                        </a>
                    @endif
                    <ul class="mb-0 ps-3 small text-muted">
                        <li>Le montant reste positif.</li>
                        <li>Le type détermine l'impact net.</li>
                        <li>Le statut pilote les calculs de solde.</li>
                        <li>La référence facilite le rapprochement.</li>
                        <li>Encaissement effectué : redirection Checkout Stripe.</li>
                        <li>Décaissement effectué : lancement d'un Payout Stripe.</li>
                        <li>Configurez les clés API dans .env (STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET).</li>
                    </ul>
                </div>
            </div>
            <div class="crypto-card mt-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Traçabilité des paiements</h6>
                        <a href="{{ route('treasury.tracking') }}" class="btn btn-outline-primary btn-sm">Voir tout</a>
                    </div>
                    <p class="text-muted small mb-3">Derniers paiements enregistrés avec référence et statut.</p>

                    @php($recentPayments = $recentPayments ?? collect())
                    @if($recentPayments->isEmpty())
                        <div class="text-muted small">Aucun paiement récent à tracer pour le moment.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                <tr>
                                    <th class="small text-muted">Date</th>
                                    <th class="small text-muted">Référence</th>
                                    <th class="small text-muted">Montant</th>
                                    <th class="small text-muted">Statut</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($recentPayments as $payment)
                                    @php
                                        $statusClass = $payment->status === 'effectue'
                                            ? 'ok'
                                            : ($payment->status === 'annule' ? 'bad' : 'pending');
                                    @endphp
                                    <tr>
                                        <td class="small text-nowrap">{{ $payment->transaction_date->format('d/m/Y') }}</td>
                                        <td class="small text-break">
                                            {{ $payment->reference ?? '---' }}
                                            @if($payment->payment_module === 'stripe' && $payment->stripe_status)
                                                <div class="text-muted">Stripe: {{ $payment->stripe_status }}</div>
                                            @endif
                                            @if($payment->payment_module === 'stripe' && $payment->stripe_payout_id)
                                                <div class="text-muted">Payout: {{ $payment->stripe_payout_id }}</div>
                                            @endif
                                        </td>
                                        <td class="small {{ $payment->type === 'encaissement' ? 'text-success' : 'text-danger' }}">
                                            {{ $payment->type === 'encaissement' ? '+' : '-' }}{{ number_format($payment->amount, 0, ',', ' ') }}
                                        </td>
                                        <td class="small">
                                            <span class="crypto-status {{ $statusClass }}">{{ strtoupper($payment->status) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const channelSelect = document.getElementById('stripe_payment_channel');
        const schemeFields = document.getElementById('stripe-bank-scheme-fields');

        function applyVisibility() {
            const channel = channelSelect ? channelSelect.value : 'card';
            if (schemeFields) schemeFields.style.display = channel === 'bank_debit' ? '' : 'none';
        }

        if (channelSelect) {
            channelSelect.addEventListener('change', applyVisibility);
            applyVisibility();
        }
    })();
</script>
@endpush
