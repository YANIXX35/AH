@extends('layouts.app')

@section('title', 'Paiement Mobile | Sitiame Capital')
@section('page_title', 'Paiement Mobile FedaPay')

@section('content')
<div class="container-fluid p-0">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-6">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h4 class="mb-2">Confirmer le paiement mobile</h4>
                    <p class="text-muted mb-4">Vérifiez les informations puis lancez le paiement FedaPay.</p>

                    <div class="alert alert-info">
                        Le paiement est lancé via l'API FedaPay pour permettre le préremplissage automatique
                        des données de l'utilisateur connecté (nom, prénom, email, téléphone).
                    </div>

                    @if($errors->has('fedapay'))
                        <div class="alert alert-danger">{{ $errors->first('fedapay') }}</div>
                    @endif

                    <div class="mb-3">
                        <div class="small text-muted">Nom</div>
                        <div class="fw-semibold">{{ $payer['last_name'] ?: 'N/A' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="small text-muted">Prénom(s)</div>
                        <div class="fw-semibold">{{ $payer['first_name'] ?: 'N/A' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="small text-muted">Email</div>
                        <div class="fw-semibold">{{ $payer['email'] ?: 'N/A' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="small text-muted">Pays</div>
                        <div class="fw-semibold">{{ $payer['country'] }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="small text-muted">Téléphone</div>
                        <div class="fw-semibold">{{ $payer['phone'] ?: 'N/A' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="small text-muted">Transaction</div>
                        <div class="fw-semibold">#{{ $transaction->id }} - {{ $transaction->description }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="small text-muted">Montant</div>
                        <div class="fw-semibold">{{ number_format($transaction->amount, 0, ',', ' ') }} FCFA</div>
                    </div>
                    <div class="mb-3">
                        <div class="small text-muted">Opérateur</div>
                        <div class="fw-semibold">{{ strtoupper(str_replace('_', ' ', (string) $transaction->mobile_method)) }}</div>
                    </div>
                    <div class="mb-4">
                        <div class="small text-muted">Numéro mobile</div>
                        <div class="fw-semibold">{{ $transaction->mobile_number ?: 'N/A' }}</div>
                    </div>

                    <form action="{{ route('treasury.fedapay.checkout.start', $transaction) }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-12">
                            <label class="form-label" for="fedapay_country">Pays de paiement</label>
                            <select id="fedapay_country" name="fedapay_country" class="form-select @error('fedapay_country') is-invalid @enderror" required>
                                <option value="CIV" {{ old('fedapay_country', $country) === 'CIV' ? 'selected' : '' }}>Côte d'Ivoire</option>
                                <option value="SEN" {{ old('fedapay_country', $country) === 'SEN' ? 'selected' : '' }}>Sénégal</option>
                                <option value="BEN" {{ old('fedapay_country', $country) === 'BEN' ? 'selected' : '' }}>Bénin</option>
                                <option value="TGO" {{ old('fedapay_country', $country) === 'TGO' ? 'selected' : '' }}>Togo</option>
                                <option value="CMR" {{ old('fedapay_country', $country) === 'CMR' ? 'selected' : '' }}>Cameroun</option>
                            </select>
                            @error('fedapay_country')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <a href="{{ route('treasury.tracking') }}" class="btn btn-outline-secondary">Annuler</a>
                            <button type="submit" class="btn btn-primary">Lancer le paiement mobile</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
