@extends('layouts.app')

@section('title', 'Profil | Sitiame Capitale')
@section('page_title', 'Profil & paramètres')

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
                    <h6 class="card-title mb-2">Fiche entreprise (FIRD)</h6>
                    <p class="small text-muted mb-3">Après inscription, complétez l’identification légale, les exercices, les registres, les bancaires — comme sur une fiche administrative.</p>
                    <a href="{{ route('profile.company.fird') }}" class="btn btn-primary btn-sm w-100">Ouvrir la fiche entreprise</a>
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
                            <p class="small text-muted mb-1">Version : <strong>Gratuite</strong></p>
                            <p class="small mb-0 text-muted">
                                Passez en Premium après configuration du moyen de paiement définitif.
                            </p>
                        @endif

                        <hr>
                        <div class="d-grid mb-3">
                            <a href="{{ route('subscriptions.history') }}" class="btn btn-sm btn-outline-secondary">Voir l'historique des abonnements</a>
                        </div>
                        <h6 class="mb-2">Simulation abonnement (temporaire)</h6>
                        <form method="POST" action="{{ route('profile.subscription.simulate') }}" class="row g-2">
                            @csrf
                            <div class="col-12">
                                <select name="plan_type" class="form-select form-select-sm">
                                    <option value="free">Version gratuite</option>
                                    <option value="trial">Essai gratuit</option>
                                    <option value="premium">Premium actif</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <input type="number" name="duration_days" min="1" max="365" value="30" class="form-control form-control-sm" placeholder="Durée en jours">
                                <small class="text-muted">30 jours = cycle mensuel.</small>
                            </div>
                            <div class="col-12 d-grid">
                                <button type="submit" class="btn btn-sm btn-outline-primary">Appliquer la simulation</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
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
                                @include('partials.file-input-camera', [
                                    'name' => 'company_logo',
                                    'id' => 'profile_company_logo',
                                    'label' => 'Logo de l’entreprise',
                                    'accept' => 'image/jpeg,image/png,image/webp,image/gif',
                                    'capture' => 'environment',
                                    'help' => 'Logo ou photo du document : appareil arrière utile pour scanner une carte de visite ou un logo imprimé. Max. 5 Mo.',
                                ])
                            </div>
                            @if($user->company_logo)
                                <div class="col-md-6">
                                    <label class="form-label">Logo actuel</label>
                                    <div class="border rounded p-2">
                                        <img src="{{ asset('storage/' . $user->company_logo) }}" alt="Logo entreprise" style="max-width: 180px; max-height: 100px; object-fit: contain;">
                                    </div>
                                </div>
                            @endif
                        </div>

                        <hr class="my-4">
                        <h5 class="card-title mb-3">Préférences régionales</h5>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Langue</label>
                                <select name="locale" class="form-select @error('locale') is-invalid @enderror">
                                    <option value="fr" {{ old('locale', $user->locale ?? 'fr') === 'fr' ? 'selected' : '' }}>Français</option>
                                    <option value="en" {{ old('locale', $user->locale ?? 'fr') === 'en' ? 'selected' : '' }}>Anglais</option>
                                </select>
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
