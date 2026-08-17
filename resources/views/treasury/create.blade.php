@extends('layouts.app')

@section('title', 'Ajouter Transaction | Sitiame Capital')
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
                    <h5 class="mb-1">Formulaire de transaction</h5>
                    <p class="text-muted small mb-3">Saisissez un mouvement de trésorerie avec son statut réel pour un suivi comptable fiable.</p>
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
                                    <option value="Paiement client" data-types="encaissement" {{ old('transaction_type') === 'Paiement client' ? 'selected' : '' }}>Paiement client</option>
                                    <option value="Versement" data-types="encaissement" {{ old('transaction_type') === 'Versement' ? 'selected' : '' }}>Versement</option>
                                    <option value="Intérêts" data-types="encaissement" {{ old('transaction_type') === 'Intérêts' ? 'selected' : '' }}>Intérêts</option>
                                    <option value="Paiement fournisseur" data-types="decaissement" {{ old('transaction_type') === 'Paiement fournisseur' ? 'selected' : '' }}>Paiement fournisseur</option>
                                    <option value="Frais bancaires" data-types="decaissement" {{ old('transaction_type') === 'Frais bancaires' ? 'selected' : '' }}>Frais bancaires</option>
                                    <option value="Retrait" data-types="decaissement" {{ old('transaction_type') === 'Retrait' ? 'selected' : '' }}>Retrait</option>
                                    <option value="Autre" data-types="encaissement,decaissement" {{ old('transaction_type') === 'Autre' ? 'selected' : '' }}>Autre</option>
                                </select>
                                <div class="form-text">Les catégories sont filtrées selon le type (encaissement/décaissement).</div>
                                @error('transaction_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="row g-3 mb-1">
                            <div class="col-12 col-md-6">
                                <label class="crypto-form-label" for="payment_module">Passerelle</label>
                                <select id="payment_module" name="payment_module" class="form-select @error('payment_module') is-invalid @enderror" required>
                                    <option value="stripe" {{ old('payment_module', 'stripe') === 'stripe' ? 'selected' : '' }}>Paiement bancaire</option>
                                    <option value="fedapay_mobile" {{ old('payment_module') === 'fedapay_mobile' ? 'selected' : '' }}>Mobile Money</option>
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

                        <div class="row g-3 mb-1" id="fedapay-mobile-fields" style="display:none;">
                            <div class="col-12 col-md-4">
                                <label class="crypto-form-label" for="fedapay_country">Pays *</label>
                                <select id="fedapay_country" name="fedapay_country" class="form-select @error('fedapay_country') is-invalid @enderror">
                                    <option value="CIV" {{ old('fedapay_country', 'CIV') === 'CIV' ? 'selected' : '' }}>Côte d'Ivoire</option>
                                    <option value="SEN" {{ old('fedapay_country') === 'SEN' ? 'selected' : '' }}>Sénégal</option>
                                    <option value="BEN" {{ old('fedapay_country') === 'BEN' ? 'selected' : '' }}>Bénin</option>
                                    <option value="TGO" {{ old('fedapay_country') === 'TGO' ? 'selected' : '' }}>Togo</option>
                                    <option value="CMR" {{ old('fedapay_country') === 'CMR' ? 'selected' : '' }}>Cameroun</option>
                                </select>
                                @error('fedapay_country')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="crypto-form-label" for="mobile_method">Opérateur mobile *</label>
                                <select id="mobile_method" name="mobile_method" class="form-select @error('mobile_method') is-invalid @enderror">
                                    <option value="orange_money" {{ old('mobile_method') === 'orange_money' ? 'selected' : '' }}>Orange Money</option>
                                    <option value="mtn_money" {{ old('mobile_method') === 'mtn_money' ? 'selected' : '' }}>MTN Money</option>
                                    <option value="moov_money" {{ old('mobile_method') === 'moov_money' ? 'selected' : '' }}>Moov Money</option>
                                    <option value="wave" {{ old('mobile_method', 'wave') === 'wave' ? 'selected' : '' }}>Wave</option>
                                </select>
                                @error('mobile_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="crypto-form-label" for="mobile_number">Numéro Mobile Money *</label>
                                <input type="text" id="mobile_number" name="mobile_number" class="form-control @error('mobile_number') is-invalid @enderror"
                                       value="{{ old('mobile_number') }}" placeholder="Ex: 0700000000">
                                @error('mobile_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
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

                        <div class="row g-3 mb-1">
                            <div class="col-12 col-md-6">
                                <label class="crypto-form-label" for="value_date">Date de valeur (fonds disponibles en banque)</label>
                                <input type="date" id="value_date" name="value_date" class="form-control @error('value_date') is-invalid @enderror"
                                       value="{{ old('value_date') }}">
                                <div class="form-text">Pour un chèque ou un virement : date à laquelle la banque crédite réellement le compte, si différente de la date de transaction. Laissez vide pour Mobile Money — toujours immédiat.</div>
                                @error('value_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                            <div class="form-text">Planifiée = engagement non exécuté, Effectuée = impact réel sur la trésorerie.</div>
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
                    <div class="alert alert-warning small mb-3">
                        FedaPay Mobile Money gère ici : Orange Money, MTN Money, Moov Money, Wave.
                    </div>
                    @php
                        $stripePaymentLink = trim((string) config('services.stripe.payment_link_url', ''));
                    @endphp
                    @if($stripePaymentLink !== '')
                        <a href="{{ $stripePaymentLink }}" class="btn btn-outline-dark btn-sm w-100 mb-3" target="_blank" rel="noopener">
                            Ouvrir le lien de paiement Stripe
                        </a>
                    @endif
                    <ul class="mb-0 ps-3 small text-muted">
                        <li>Le montant reste positif.</li>
                        <li>Le type détermine l'impact net.</li>
                        <li>Le statut pilote les calculs de solde (seules les opérations effectuées impactent le réalisé).</li>
                        <li>La référence facilite le rapprochement.</li>
                        <li>Encaissement effectué : redirection Checkout Stripe.</li>
                        <li>Décaissement effectué : lancement d'un Payout Stripe.</li>
                        <li>Documentez les opérations sensibles dans les notes (justificatif, décision, contrepartie).</li>
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

                    @php
                        $recentPayments = $recentPayments ?? collect();
                    @endphp
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
                                            @if($payment->payment_module === 'fedapay_mobile' && $payment->bank_reference)
                                                <div class="text-muted">FedaPay ref: {{ $payment->bank_reference }}</div>
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
        const moduleSelect = document.getElementById('payment_module');
        const typeSelect = document.getElementById('type');
        const categorySelect = document.getElementById('transaction_type');
        const channelSelect = document.getElementById('stripe_payment_channel');
        const schemeFields = document.getElementById('stripe-bank-scheme-fields');
        const fedapayFields = document.getElementById('fedapay-mobile-fields');

        function applyCategoryRules() {
            if (!typeSelect || !categorySelect) return;
            const selectedType = typeSelect.value;
            const selectedCategory = categorySelect.value;
            let selectedStillAllowed = selectedCategory === '';

            Array.from(categorySelect.options).forEach((option, index) => {
                if (index === 0) return;
                const allowed = (option.getAttribute('data-types') || '').split(',').map(v => v.trim());
                const isAllowed = selectedType === '' || allowed.includes(selectedType);
                option.hidden = !isAllowed;
                option.disabled = !isAllowed;
                if (isAllowed && option.value === selectedCategory) {
                    selectedStillAllowed = true;
                }
            });

            if (!selectedStillAllowed) {
                categorySelect.value = '';
            }
        }

        function applyVisibility() {
            const module = moduleSelect ? moduleSelect.value : 'stripe';
            const channel = channelSelect ? channelSelect.value : 'card';
            const stripeMode = module === 'stripe';

            if (schemeFields) schemeFields.style.display = stripeMode && channel === 'bank_debit' ? '' : 'none';
            if (fedapayFields) fedapayFields.style.display = module === 'fedapay_mobile' ? '' : 'none';
        }

        if (moduleSelect) {
            moduleSelect.addEventListener('change', applyVisibility);
        }
        if (typeSelect) {
            typeSelect.addEventListener('change', applyCategoryRules);
        }
        if (channelSelect) {
            channelSelect.addEventListener('change', applyVisibility);
        }
        applyCategoryRules();
        applyVisibility();
    })();
</script>
@endpush
