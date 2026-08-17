@extends('layouts.app')

@section('title', 'Profil | Sitiame Capital')
@section('page_title', 'Profil & paramètres')

@push('styles')
<style>
    .profile-locale-field {
        min-height: calc(1.5em + .75rem + 2px);
        padding: .375rem .75rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
    }
    .profile-locale-field .gtranslate_wrapper {
        width: 100%;
        display: inline-flex;
        align-items: center;
    }
    .profile-locale-field .gt_selector,
    .profile-locale-field .gtranslate_wrapper select {
        width: 100%;
        margin: 0 !important;
        border: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
        padding: 0 !important;
        min-height: 30px;
        font-size: .95rem;
        color: #495057;
    }
    .gt_float_switcher {
        display: none !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid p-0">
    <div class="row g-3">
        <div class="col-xl-4 col-md-5">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-4">
                        @if($user->avatar)
                            <img src="{{ asset('storage/' . $user->avatar) }}" class="img-fluid rounded-circle" alt="Avatar de {{ $user->name }}" style="width: 140px; height: 140px; object-fit: cover;">
                        @else
                            <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width: 140px; height: 140px; font-size: 2.5rem;">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                        @endif
                    </div>
                    <h5 class="card-title mb-1">{{ $user->name }}</h5>
                    <p class="text-muted mb-1">{{ $user->email }}</p>
                    <p class="text-muted mb-0">{{ $user->company_name ?: 'Entreprise non renseignée' }}</p>
                </div>
            </div>
            <div class="card border-primary border-opacity-25">
                <div class="card-body">
                    <h6 class="card-title mb-2">Référentiel entreprise complet</h6>
                    <p class="small text-muted mb-3">Tous les champs liés à l’entreprise sont disponibles dans cette page Paramètres. La fiche FIRD avancée reste accessible pour les données expertes.</p>
                    <a href="{{ route('profile.company.fird') }}" class="btn btn-outline-primary btn-sm w-100">Ouvrir aussi la fiche FIRD avancée</a>
                </div>
            </div>
            @if($user->canManageEnterpriseTeam())
                <div class="card border-success border-opacity-25">
                    <div class="card-body">
                        <h6 class="card-title mb-2">Équipe &amp; licence</h6>
                        <p class="small text-muted mb-3">Créez les comptes collègues rattachés à votre licence (même entreprise), dans la limite des sièges.</p>
                        <a href="{{ route('profile.team') }}" class="btn btn-success btn-sm w-100">Gérer l’équipe</a>
                    </div>
                </div>
            @endif
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Aide configuration</h5>
                </div>
                <div class="card-body small text-muted">
                    <p class="mb-2">1) Mettez à jour vos coordonnées et celles de l’entreprise.</p>
                    <p class="mb-2">2) Activez vos préférences de notifications.</p>
                    <p class="mb-2">3) Définissez vos paramètres régionaux (langue, devise, fuseau).</p>
                    <p class="mb-0">4) Activez la sécurité renforcée et mettez un mot de passe robuste.</p>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Abonnement</h5>
                </div>
                <div class="card-body">
                    @if($user->is_platform_admin)
                        <p class="mb-2">
                            <span class="badge bg-primary">🛡️ Administrateur plateforme</span>
                        </p>
                        <p class="small text-muted mb-2">
                            Votre compte n’est pas classé <strong>Gratuit</strong> ni <strong>Premium</strong> : vous disposez d’un accès opérationnel complet pour gérer la plateforme.
                        </p>
                        <p class="small mb-0 text-muted">
                            La simulation Gratuit / Premium et l’expiration automatique d’abonnement ne s’appliquent pas aux administrateurs.
                        </p>
                        <hr class="my-3">
                        <div class="d-grid">
                            <a href="{{ route('subscriptions.history') }}" class="btn btn-sm btn-outline-secondary">Historique des abonnements (consultation)</a>
                        </div>
                    @else
                        @php
                            $status = $user->premium_status ?? 'free';
                            $isPremiumActive = (bool) ($user->is_premium ?? false)
                                && (empty($user->premium_ends_at) || $user->premium_ends_at->isFuture());
                            $badge = $isPremiumActive ? 'warning text-dark' : 'success';
                            $badgeLabel = $isPremiumActive ? '⭐ PREMIUM' : '🟢 GRATUIT';
                        @endphp
                        <p class="mb-2">
                            Statut actuel :
                            <span class="badge bg-{{ $badge }}">{{ $badgeLabel }}</span>
                        </p>
                        @if($user->premium_trial_ends_at)
                            <p class="small text-muted mb-2">Essai jusqu'au {{ $user->premium_trial_ends_at->format('d/m/Y H:i') }}</p>
                        @endif
                        @if($isPremiumActive)
                            <p class="small text-muted mb-2">
                                Version : <strong>Premium</strong>
                            </p>
                            <p class="small text-muted mb-2">
                                Validité :
                                <strong>
                                    {{ $user->premium_ends_at ? $user->premium_ends_at->format('d/m/Y H:i') : 'À définir' }}
                                </strong>
                            </p>
                            <p class="small mb-0 text-muted">
                                Rappel : l'abonnement Premium est mensuel, un paiement est attendu à chaque échéance.
                            </p>
                        @else
                            <p class="small text-muted mb-1">Version : <strong>Gratuit (période d'essai)</strong></p>
                            <p class="small mb-0 text-muted">
                                Passez en Enterprise Premium après configuration du moyen de paiement définitif.
                            </p>
                        @endif

                        <hr>
                        <div class="d-grid gap-2">
                            @unless($isPremiumActive)
                                <a href="{{ route('payments.sandbox') }}" class="btn btn-warning text-dark">
                                    Payer l’abonnement Premium (15 000 FCFA)
                                </a>
                            @endunless
                            <a href="{{ route('subscriptions.history') }}" class="btn btn-sm btn-outline-secondary">Voir l'historique des abonnements</a>
                        </div>
                    @endif
                </div>
            </div>
            @if(! $user->is_platform_admin && ! ($user->is_accountant ?? false))
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Conformité KYC/KYB</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">
                            Statut :
                            <span class="badge bg-{{ ($user->kyc_status ?? 'pending') === 'approved' ? 'success' : (($user->kyc_status ?? 'pending') === 'rejected' ? 'danger' : 'warning text-dark') }}">
                                {{ strtoupper((string) ($user->kyc_status ?? 'pending')) }}
                            </span>
                        </p>
                        @if($user->kyc_rejection_reason)
                            <div class="alert alert-danger small">{{ $user->kyc_rejection_reason }}</div>
                        @endif
                        <form method="POST" action="{{ route('compliance.kyc.submit') }}" enctype="multipart/form-data">
                            @csrf
                            <label class="form-label">Pièces KYC/KYB (PDF/JPG/PNG)</label>
                            <input type="file" name="documents[]" class="form-control mb-2" accept=".pdf,.jpg,.jpeg,.png" multiple required>
                            <button class="btn btn-sm btn-outline-primary">Soumettre à l’administrateur</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-xl-8 col-md-7">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <h5 class="card-title mb-3">Compte utilisateur</h5>
                        @include('partials.camera-upload-hint')
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nom complet</label>
                                <input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror" required>
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Téléphone</label>
                                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control @error('phone') is-invalid @enderror">
                                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                @include('partials.file-input-camera', [
                                    'name' => 'avatar',
                                    'id' => 'profile_avatar',
                                    'label' => 'Avatar',
                                    'accept' => 'image/jpeg,image/png,image/webp,image/gif',
                                    'capture' => 'user',
                                    'help' => 'Portrait : vous pouvez choisir une image ou ouvrir l’appareil frontal (selfie). Formats image — max. 5 Mo.',
                                ])
                            </div>
                        </div>

                        <hr class="my-4">
                        <h5 class="card-title mb-3">Entreprise</h5>
                        @include('partials.camera-upload-hint')
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nom de l’entreprise</label>
                                <input type="text" name="company_name" value="{{ old('company_name', $user->company_name) }}" class="form-control @error('company_name') is-invalid @enderror">
                                @error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sigle usuel</label>
                                <input type="text" name="company_sigle" value="{{ old('company_sigle', $user->company_sigle) }}" class="form-control @error('company_sigle') is-invalid @enderror">
                                @error('company_sigle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">N° d’identification fiscale</label>
                                <input type="text" name="company_tax_id" value="{{ old('company_tax_id', $user->company_tax_id) }}" class="form-control @error('company_tax_id') is-invalid @enderror">
                                @error('company_tax_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Numéro RCCM / Registre</label>
                                <input type="text" name="rccm" value="{{ old('rccm', $user->rccm) }}" class="form-control @error('rccm') is-invalid @enderror">
                                @error('rccm')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">Désignation légale de l’entreprise</label>
                                <input type="text" name="company_designation" value="{{ old('company_designation', $user->company_designation) }}" class="form-control @error('company_designation') is-invalid @enderror" placeholder="Ex: Société à responsabilité limitée au capital de ...">
                                @error('company_designation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Secteur</label>
                                <input type="text" name="sector" value="{{ old('sector', $user->sector) }}" class="form-control @error('sector') is-invalid @enderror">
                                @error('sector')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Adresse</label>
                                <input type="text" name="address" value="{{ old('address', $user->address) }}" class="form-control @error('address') is-invalid @enderror">
                                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ville</label>
                                <input type="text" name="city" value="{{ old('city', $user->city) }}" class="form-control @error('city') is-invalid @enderror">
                                @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Code postal</label>
                                <input type="text" name="postal_code" value="{{ old('postal_code', $user->postal_code) }}" class="form-control @error('postal_code') is-invalid @enderror">
                                @error('postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Boîte postale</label>
                                <input type="text" name="po_box" value="{{ old('po_box', $user->po_box) }}" class="form-control @error('po_box') is-invalid @enderror">
                                @error('po_box')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">Adresse géographique complète</label>
                                <textarea name="full_geographic_address" rows="2" class="form-control @error('full_geographic_address') is-invalid @enderror" placeholder="Commune, quartier, rue, repère...">{{ old('full_geographic_address', $user->full_geographic_address) }}</textarea>
                                @error('full_geographic_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">Activité principale</label>
                                <textarea name="main_activity_description" rows="2" class="form-control @error('main_activity_description') is-invalid @enderror" placeholder="Décrivez brièvement votre activité">{{ old('main_activity_description', $user->main_activity_description) }}</textarea>
                                @error('main_activity_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                @include('partials.file-input-camera', [
                                    'name' => 'company_logo',
                                    'id' => 'profile_company_logo',
                                    'label' => 'Logo de l’entreprise',
                                    'accept' => 'image/jpeg,image/png,image/webp,image/gif',
                                    'capture' => 'environment',
                                    'help' => 'Logo ou photo du document : appareil arrière utile pour scanner une carte de visite ou un logo imprimé. Max. 5 Mo.',
                                ])
                            </div>
                            <div class="col-md-6">
                                @include('partials.file-input-camera', [
                                    'name' => 'trade_register',
                                    'id' => 'profile_trade_register',
                                    'label' => 'Registre de commerce',
                                    'accept' => 'application/pdf,image/jpeg,image/png,image/webp',
                                    'capture' => 'environment',
                                    'help' => 'Fichier légal entreprise (PDF/JPG/PNG/WEBP) — max. 5 Mo.',
                                ])
                            </div>
                            @if($user->company_logo)
                                <div class="col-md-6">
                                    <label class="form-label">Logo / attestation actuel</label>
                                    @php $companyLogoExt = strtolower(pathinfo($user->company_logo, PATHINFO_EXTENSION)); @endphp
                                    @if(in_array($companyLogoExt, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true))
                                        <div class="border rounded p-2">
                                            <img src="{{ asset('storage/' . $user->company_logo) }}" alt="Logo entreprise" style="max-width: 180px; max-height: 100px; object-fit: contain;">
                                        </div>
                                    @else
                                        <div class="border rounded p-2 d-flex align-items-center justify-content-between gap-2">
                                            <span class="small text-muted text-truncate">{{ basename((string) $user->company_logo) }}</span>
                                            <a href="{{ route('company-documents.view', ['user' => $user, 'type' => 'company_logo']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Voir</a>
                                        </div>
                                    @endif
                                </div>
                            @endif
                            @if($user->trade_register_file)
                                <div class="col-md-6">
                                    <label class="form-label">Registre actuel</label>
                                    <div class="border rounded p-2 d-flex align-items-center justify-content-between gap-2">
                                        <span class="small text-muted text-truncate">{{ basename((string) $user->trade_register_file) }}</span>
                                        <a href="{{ route('company-documents.view', ['user' => $user, 'type' => 'trade_register']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Voir</a>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <hr class="my-4">
                        <h5 class="card-title mb-3">Préférences régionales</h5>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Langue</label>
                                <div class="form-control profile-locale-field">
                                    <div class="gtranslate_wrapper w-100"></div>
                                </div>
                                <small class="text-muted">Choisissez la langue directement dans le menu déroulant.</small>
                                <input type="hidden" id="profile-locale-hidden" name="locale" value="{{ old('locale', $user->locale ?? app()->getLocale() ?? 'fr') }}">
                                @error('locale')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Devise</label>
                                <select name="currency" class="form-select @error('currency') is-invalid @enderror">
                                    <option value="XOF" {{ old('currency', $user->currency ?? 'XOF') === 'XOF' ? 'selected' : '' }}>XOF (FCFA)</option>
                                    <option value="EUR" {{ old('currency', $user->currency ?? 'XOF') === 'EUR' ? 'selected' : '' }}>EUR</option>
                                    <option value="USD" {{ old('currency', $user->currency ?? 'XOF') === 'USD' ? 'selected' : '' }}>USD</option>
                                </select>
                                @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Fuseau horaire</label>
                                <select name="timezone" class="form-select @error('timezone') is-invalid @enderror">
                                    @foreach(['Africa/Abidjan', 'Africa/Dakar', 'Europe/Paris', 'UTC'] as $tz)
                                        <option value="{{ $tz }}" {{ old('timezone', $user->timezone ?? 'Africa/Abidjan') === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                                    @endforeach
                                </select>
                                @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <hr class="my-4">
                        <h5 class="card-title mb-3">Notifications & sécurité</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="email_notifications" id="email_notifications" value="1" {{ old('email_notifications', (int) ($user->email_notifications ?? 1)) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="email_notifications">Notifications email opérationnelles</label>
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="weekly_digest" id="weekly_digest" value="1" {{ old('weekly_digest', (int) ($user->weekly_digest ?? 0)) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="weekly_digest">Recevoir un résumé hebdomadaire</label>
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="marketing_emails" id="marketing_emails" value="1" {{ old('marketing_emails', (int) ($user->marketing_emails ?? 0)) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="marketing_emails">Recevoir les nouveautés produit</label>
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="two_factor_enabled" id="two_factor_enabled" value="1" {{ old('two_factor_enabled', (int) ($user->two_factor_enabled ?? 0)) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="two_factor_enabled">Activer la vérification en 2 étapes (mode préparatoire)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nouveau mot de passe</label>
                                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <label class="form-label mt-3">Confirmation mot de passe</label>
                                <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
                            </div>
                        </div>

                        <div class="mt-4 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Enregistrer tous les paramètres</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const profileLocaleHidden = document.getElementById('profile-locale-hidden');
    const profileUpdateForm = document.querySelector('form[action="{{ route('profile.update') }}"]');

    function syncLocaleFromGTranslateCombo() {
        const combo = document.querySelector('.profile-locale-field .gt_selector, .profile-locale-field .gtranslate_wrapper select');
        if (!combo || !profileLocaleHidden) {
            return false;
        }

        const applyValue = function () {
            if (!combo.value) {
                return;
            }
            profileLocaleHidden.value = combo.value;
            localStorage.setItem('preferred_locale', combo.value);
            document.cookie = "googtrans=/fr/" + combo.value + ";path=/";
            document.cookie = "googtrans=/auto/" + combo.value + ";path=/";
        };

        combo.addEventListener('change', function () {
            applyValue();
        });

        // Préselection selon la langue stockée dans le profil/session.
        if (profileLocaleHidden.value) {
            combo.value = profileLocaleHidden.value;
            combo.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
            applyValue();
        }

        return true;
    }

    let attempts = 0;
    const maxAttempts = 40;
    const interval = window.setInterval(function () {
        attempts += 1;
        if (syncLocaleFromGTranslateCombo() || attempts >= maxAttempts) {
            window.clearInterval(interval);
        }
    }, 200);

    if (profileUpdateForm) {
        profileUpdateForm.addEventListener('submit', function () {
            const combo = document.querySelector('.profile-locale-field .gt_selector, .profile-locale-field .gtranslate_wrapper select');
            if (combo && combo.value && profileLocaleHidden) {
                profileLocaleHidden.value = combo.value;
            }
        });
    }
</script>
@endpush
