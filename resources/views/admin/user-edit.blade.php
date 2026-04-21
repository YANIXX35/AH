@extends('layouts.app')

@section('title', 'Modifier le compte | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="mb-4">
    <nav aria-label="Fil d’Ariane admin" class="mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Administration</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.users') }}">Utilisateurs</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $user->company_name ?? $user->name }}</li>
        </ol>
    </nav>
    <h1 class="h3 mb-1">Gestion du compte</h1>
    <p class="text-muted mb-0">ID {{ $user->id }} — {{ $user->email }}</p>
    @if($user->enterpriseLicense)
        <p class="small text-muted mb-0 mt-1">
            <strong>Licence entreprise :</strong>
            <code>{{ $user->enterpriseLicense->license_key }}</code>
            ({{ $user->enterpriseLicense->seatsUsed() }} / {{ $user->enterpriseLicense->max_seats }} sièges utilisés)
        </p>
    @endif
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    $expiringSoon = $user->premiumEndsWithinDays(7);
    $overduePremium = $user->is_premium && $user->premium_ends_at && $user->premium_ends_at->isPast();
@endphp

@if($user->is_platform_admin)
    <div class="alert alert-primary mb-4" role="status">
        <strong>Administrateur plateforme</strong> — hors offres Gratuit / Premium : pas d’expiration automatique d’abonnement sur ce compte.
    </div>
@elseif($user->is_premium && $user->premium_ends_at)
    <div class="alert {{ $overduePremium ? 'alert-danger' : ($expiringSoon ? 'alert-warning' : 'alert-info') }} mb-4" role="status">
        <strong>Abonnement Premium</strong>
        @if($overduePremium)
            — <span class="text-danger">échéance dépassée ({{ $user->premium_ends_at->format('d/m/Y H:i') }})</span>. Le planificateur peut suspendre le compte après expiration.
        @elseif($expiringSoon)
            — échéance dans moins de 7 jours : {{ $user->premium_ends_at->format('d/m/Y H:i') }}.
        @else
            — valide jusqu’au {{ $user->premium_ends_at->format('d/m/Y H:i') }}.
        @endif
    </div>
@endif

@if($user->account_suspended)
    <div class="alert alert-danger mb-4" role="status">
        <strong>Compte suspendu</strong>
        @if($user->auto_suspended_for_payment)
            (automatique — échéance / non-paiement)
        @endif
        @if($user->suspended_at)
            <span class="d-block small">Depuis le {{ $user->suspended_at->format('d/m/Y H:i') }}</span>
        @endif
    </div>
@endif

<form method="post" action="{{ route('admin.users.update', $user) }}" class="pb-5">
    @csrf
    @method('PUT')

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">Identité &amp; contact</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="name">Nom</label>
                        <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">E-mail</label>
                        <input type="email" name="email" id="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="phone">Téléphone</label>
                        <input type="text" name="phone" id="phone" class="form-control" value="{{ old('phone', $user->phone) }}">
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">Entreprise</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="company_name">Raison sociale</label>
                        <input type="text" name="company_name" id="company_name" class="form-control" value="{{ old('company_name', $user->company_name) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="sector">Secteur</label>
                        <input type="text" name="sector" id="sector" class="form-control" value="{{ old('sector', $user->sector) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="rccm">RCCM</label>
                        <input type="text" name="rccm" id="rccm" class="form-control" value="{{ old('rccm', $user->rccm) }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">Abonnement &amp; accès</h5>
                </div>
                <div class="card-body">
                    @if($user->is_platform_admin)
                        <p class="text-muted small mb-3">
                            Tant que « Administrateur plateforme » est coché, l’enregistrement <strong>ne modifie pas</strong> Gratuit / Premium (hors périmètre). Décochez le rôle admin pour ajuster l’abonnement client.
                        </p>
                    @elseif($user->is_accountant ?? false)
                        <p class="text-muted small mb-3">Compte <strong>comptable cabinet</strong> : pas d’abonnement Gratuit / Premium entreprise (les comptes entreprise sont gérés dans les dossiers clients).</p>
                    @endif
                    <div class="row g-3">
                        @if(! $user->is_platform_admin && ! ($user->is_accountant ?? false))
                        <div class="col-md-6">
                            <input type="hidden" name="is_premium" value="0">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_premium" id="is_premium" value="1" @checked(old('is_premium', $user->is_premium))>
                                <label class="form-check-label" for="is_premium">Abonnement Premium actif</label>
                            </div>
                            <label class="form-label" for="premium_ends_at">Fin de période Premium</label>
                            <input type="datetime-local" name="premium_ends_at" id="premium_ends_at" class="form-control"
                                value="{{ old('premium_ends_at', $user->premium_ends_at?->format('Y-m-d\TH:i')) }}">
                            <small class="text-muted">Laisser vide pour conserver la date actuelle si vous ne changez pas la date.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="premium_status">Statut technique</label>
                            <select name="premium_status" id="premium_status" class="form-select">
                                @foreach(['free' => 'Gratuit', 'active' => 'Actif', 'trialing' => 'Essai'] as $val => $label)
                                    <option value="{{ $val }}" @selected(old('premium_status', $user->premium_status ?? 'free') === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div class="col-12">
                            <hr>
                            <input type="hidden" name="is_platform_admin" value="0">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_platform_admin" id="is_platform_admin" value="1" @checked(old('is_platform_admin', $user->is_platform_admin))>
                                <label class="form-check-label" for="is_platform_admin">Administrateur plateforme (accès /admin)</label>
                            </div>
                            <input type="hidden" name="is_accountant" value="0">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_accountant" id="is_accountant" value="1" @checked(old('is_accountant', $user->is_accountant ?? false))>
                                <label class="form-check-label" for="is_accountant">Comptable cabinet (accès /accountant, dossiers clients)</label>
                            </div>
                            <p class="small text-muted">Un compte est soit <strong>administrateur plateforme</strong>, soit <strong>comptable cabinet</strong>, soit <strong>entreprise</strong> — un seul rôle à la fois.</p>
                            @error('is_accountant')
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror
                            <input type="hidden" name="account_suspended" value="0">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="account_suspended" id="account_suspended" value="1" @checked(old('account_suspended', $user->account_suspended))>
                                <label class="form-check-label" for="account_suspended">Compte suspendu (connexion bloquée)</label>
                            </div>
                            <div class="mb-0">
                                <label class="form-label" for="suspended_reason">Motif de suspension</label>
                                <textarea name="suspended_reason" id="suspended_reason" class="form-control" rows="2" placeholder="Optionnel — visible en interne">{{ old('suspended_reason', $user->suspended_reason) }}</textarea>
                                @if($user->auto_suspended_for_payment)
                                    <small class="text-muted">Dernière suspension automatique liée à l’échéance.</small>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary">Enregistrer</button>
        <a href="{{ route('admin.users') }}" class="btn btn-outline-secondary">Retour à la liste</a>
    </div>
</form>
@endsection
