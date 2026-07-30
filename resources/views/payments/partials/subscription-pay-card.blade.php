<div class="card sandbox-pay-card mb-4">
    <div class="row g-0">
        <div class="col-lg-4 pay-accent p-4 d-flex flex-column justify-content-center">
            <p class="small text-white-50 text-uppercase mb-1 fw-semibold">Abonnement mensuel</p>
            <p class="display-6 fw-bold mb-2">{{ number_format((float) $paymentAmount, 0, ',', ' ') }} <span class="fs-5">FCFA</span></p>
            <ul class="small mb-0 ps-3 text-white-50">
                <li>Comptabilité et pièces justificatives</li>
                <li>Premium 30 jours après paiement validé</li>
                @if($useCinetPay ?? false)
                    <li>Paiement sécurisé via <strong>CinetPay</strong></li>
                @else
                    <li>Mobile Money, WAVE, carte (selon pays)</li>
                @endif
            </ul>
        </div>
        <div class="col-lg-8 p-4 bg-white">
            @if($isPremiumActive ?? false)
                <div class="alert alert-warning py-2 small mb-3 mb-lg-4">
                    Votre Premium est déjà actif. Vous pouvez renouveler avant l’échéance pour prolonger la période.
                </div>
            @else
                <p class="text-muted small mb-3">
                    Bienvenue ! Cliquez sur le bouton ci-dessous pour régler votre abonnement et débloquer toutes les fonctionnalités.
                </p>
            @endif

            @if($useCinetPay ?? false)
                <p class="small text-muted mb-3">
                    Vous serez redirigé vers le guichet sécurisé <strong>CinetPay</strong> (Mobile Money, carte, portefeuille).
                    Documentation : <a href="https://panel.cinetpay.net/sitiame-capital/developer/documentation?doc=overview" target="_blank" rel="noopener">panneau développeur</a>.
                </p>
                @if(! ($isCinetPayConfigured ?? false))
                    <div class="alert alert-info py-2 small">
                        Mode test : les identifiants CinetPay ne sont pas encore configurés dans <code>.env</code> — un paiement simulé sera proposé.
                    </div>
                @endif
                <form method="POST" action="{{ route('payments.cinetpay.checkout') }}" class="row g-3">
                    @csrf
                    <input type="hidden" name="amount" value="{{ (int) $paymentAmount }}">
                    <div class="col-md-6">
                        <label for="cinetpay_channels" class="form-label">Canaux de paiement</label>
                        <select name="channels" id="cinetpay_channels" class="form-select">
                            <option value="ALL">Tous les moyens (recommandé)</option>
                            <option value="MOBILE_MONEY">Mobile Money uniquement</option>
                            <option value="CREDIT_CARD">Carte bancaire</option>
                            <option value="WALLET">Portefeuille</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="cinetpay_phone" class="form-label">Téléphone (optionnel)</label>
                        <input type="tel" name="customer_phone" id="cinetpay_phone" class="form-control @error('customer_phone') is-invalid @enderror"
                            value="{{ old('customer_phone', $defaultPhone ?? '') }}" placeholder="Ex. 07 00 00 00 00" maxlength="20">
                        @error('customer_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @forelse($cinetPayMobileMethods ?? [] as $method)
                                <span class="sandbox-chip">{{ $method }}</span>
                            @empty
                                <span class="sandbox-chip">MOBILE_MONEY</span>
                                <span class="sandbox-chip">WAVE</span>
                                <span class="sandbox-chip">ORANGE_MONEY</span>
                            @endforelse
                        </div>
                        <div class="d-grid d-sm-flex gap-2">
                            <button type="submit" class="btn btn-warning text-dark sandbox-pay-btn">
                                Payer {{ number_format((float) $paymentAmount, 0, ',', ' ') }} FCFA avec CinetPay
                            </button>
                            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary align-self-center">Plus tard</a>
                        </div>
                    </div>
                </form>
            @elseif($isHostedPaymentMode ?? false)
                <p class="small text-muted mb-3">
                    Vous serez redirigé vers la page sécurisée FedaPay pour choisir votre moyen de paiement.
                </p>
                <div class="d-grid d-sm-flex gap-2 align-items-center">
                    <a href="{{ route('payments.redirect') }}" class="btn btn-warning text-dark sandbox-pay-btn">
                        Payer {{ number_format((float) $paymentAmount, 0, ',', ' ') }} FCFA maintenant
                    </a>
                    <a href="{{ route('pricing') }}" class="btn btn-outline-secondary">Voir les tarifs</a>
                </div>
            @else
                <form method="POST" action="{{ route('payments.sandbox.store') }}" class="row g-3" id="sandboxPayForm">
                    @csrf
                    <input type="hidden" name="amount" value="{{ (int) $paymentAmount }}">
                    <div class="col-md-6">
                        <label for="country" class="form-label">Pays</label>
                        <select name="country" id="country" class="form-select @error('country') is-invalid @enderror" required>
                            @foreach($gatewayConfig ?? [] as $countryCode => $countryCfg)
                                <option value="{{ $countryCode }}" @selected(old('country', $defaultCountry ?? 'CIV') === $countryCode)>
                                    {{ $countryCode }} — {{ $countryCfg['currency'] ?? 'XOF' }}
                                </option>
                            @endforeach
                        </select>
                        @error('country')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="correspondent" class="form-label">Moyen de paiement</label>
                        <select name="correspondent" id="correspondent" class="form-select @error('correspondent') is-invalid @enderror" required>
                            @php
                                $selectedCountry = old('country', $defaultCountry ?? 'CIV');
                                $correspondents = ($gatewayConfig[$selectedCountry]['correspondents'] ?? []) ?: ['wave' => 'WAVE'];
                            @endphp
                            @foreach($correspondents as $key => $label)
                                <option value="{{ $key }}" @selected(old('correspondent') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('correspondent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="payer_msisdn" class="form-label">Numéro Mobile Money / téléphone</label>
                        <input type="tel" name="payer_msisdn" id="payer_msisdn" class="form-control @error('payer_msisdn') is-invalid @enderror"
                            value="{{ old('payer_msisdn', $defaultPhone ?? '') }}" placeholder="Ex. 07 00 00 00 00" minlength="8" maxlength="20">
                        @error('payer_msisdn')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <div class="d-grid d-sm-flex gap-2">
                            <a href="https://pay.wave.com/m/M_ci_uR8pX7JWZNax/c/ci/?amount=15000" target="_blank" rel="noopener" class="btn btn-warning text-dark sandbox-pay-btn d-inline-flex align-items-center justify-content-center">
                                Payer {{ number_format((float) $paymentAmount, 0, ',', ' ') }} FCFA
                            </a>
                            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary align-self-center">Plus tard</a>
                        </div>
                    </div>
                </form>


                @if(! ($isFedaPaySandboxEnabled ?? false))
                    <p class="small text-muted mt-3 mb-0">
                        Mode simulation FedaPay. Activez CinetPay (<code>CINETPAY_ENABLED=true</code>) pour le paiement en production.
                    </p>
                @endif

            @endif

            @error('cinetpay')
                <div class="alert alert-danger py-2 small mt-3 mb-0">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

@push('scripts')
@if(! ($useCinetPay ?? false) && ! ($isHostedPaymentMode ?? false))
<script>
    (function () {
        const gateway = @json($gatewayConfig ?? []);
        const countryEl = document.getElementById('country');
        const correspondentEl = document.getElementById('correspondent');
        if (!countryEl || !correspondentEl) return;

        countryEl.addEventListener('change', function () {
            const code = countryEl.value;
            const correspondents = (gateway[code] && gateway[code].correspondents) ? gateway[code].correspondents : { wave: 'WAVE' };
            const previous = correspondentEl.value;
            correspondentEl.innerHTML = '';
            Object.entries(correspondents).forEach(function (entry) {
                const opt = document.createElement('option');
                opt.value = entry[0];
                opt.textContent = entry[1];
                correspondentEl.appendChild(opt);
            });
            if (correspondents[previous]) {
                correspondentEl.value = previous;
            }
        });
    })();
</script>
@endif
@endpush
