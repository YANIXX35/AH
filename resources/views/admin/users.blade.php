@extends('layouts.app')

@section('title', 'Utilisateurs | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@push('styles')
<style>
    .admin-users-hero {
        background: linear-gradient(135deg, #1f3c88 0%, #2f5fb3 55%, #4b7bd4 100%);
        border-radius: 0.75rem;
        color: #fff;
        padding: 0.85rem 1.15rem;
        box-shadow: 0 6px 18px rgba(30, 57, 109, 0.18);
    }
    .admin-users-hero .hero-sub {
        color: rgba(255, 255, 255, 0.9);
        margin-bottom: 0;
        font-size: 0.85rem;
    }
    .admin-stat-card {
        border: 0;
        border-radius: 0.75rem;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
        height: 100%;
    }
    .admin-stat-icon {
        width: 1.9rem;
        height: 1.9rem;
        border-radius: 0.5rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .admin-users-shell {
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
        overflow: hidden;
        background: #ffffff;
    }
    .admin-users-shell .card-header {
        border-bottom: 1px solid #e9edf5;
        background: #fff;
        padding: 0.65rem 1rem;
    }
    .admin-enterprise-accordion {
        padding: 6px;
    }
    .admin-enterprise-accordion .accordion-item {
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
        overflow: hidden;
        margin-bottom: 6px;
        background: #ffffff;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.02);
        outline: none !important;
    }
    .admin-enterprise-accordion .accordion-button {
        box-shadow: none !important;
        background: #ffffff;
        padding: 0.5rem 0.85rem !important;
        border: none !important;
        outline: none !important;
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100% !important;
    }
    .admin-enterprise-accordion .accordion-button:not(.collapsed) {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0 !important;
    }
    .admin-enterprise-accordion .accordion-button::after {
        margin-left: 0.75rem;
        flex-shrink: 0;
    }
    .enterprise-meta {
        font-size: 0.78rem;
        color: #64748b;
    }
    .enterprise-chip {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
        border-radius: 999px;
        padding: 0.1rem 0.5rem;
        font-size: 0.72rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.2rem;
    }
    .admin-user-table td, .admin-user-table th {
        padding: 0.4rem 0.75rem !important;
        vertical-align: middle;
    }
    .admin-user-table thead th {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        color: #475569;
        background: #f8fafc;
        padding-top: 0.4rem !important;
        padding-bottom: 0.4rem !important;
        border-bottom: 1px solid #e2e8f0;
    }
</style>
@endpush

@section('content')
<div class="mb-3">
    <nav aria-label="Fil d’Ariane admin" class="mb-2">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Administration</a></li>
            <li class="breadcrumb-item active" aria-current="page">Utilisateurs</li>
        </ol>
    </nav>
    <div class="admin-users-hero d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-2">
        <div class="pe-lg-3">
            <h1 class="h4 mb-0 text-white"><strong>Entreprises</strong> inscrites</h1>
            <p class="hero-sub">Gestion des comptes, abonnements Premium et suspensions dans un espace consolidé.</p>
        </div>
        <a href="{{ route('register') }}" class="btn btn-sm btn-light text-primary fw-semibold rounded-pill px-3">Nouvel utilisateur</a>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card admin-stat-card">
            <div class="card-body py-2 px-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted small mb-0" style="font-size:11px;">Total</p>
                        <p class="fs-5 mb-0 text-primary fw-bold">{{ $totalUsers }}</p>
                    </div>
                    <span class="admin-stat-icon bg-primary-subtle text-primary"><i data-feather="users" style="width:14px;height:14px;"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card admin-stat-card">
            <div class="card-body py-2 px-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted small mb-0" style="font-size:11px;">Suspendus</p>
                        <p class="fs-5 mb-0 text-danger fw-bold">{{ $suspendedCount }}</p>
                    </div>
                    <span class="admin-stat-icon bg-danger-subtle text-danger"><i data-feather="slash" style="width:14px;height:14px;"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card admin-stat-card">
            <div class="card-body py-2 px-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted small mb-0" style="font-size:11px;">Premium actifs</p>
                        <p class="fs-5 mb-0 text-warning fw-bold">{{ $premiumActiveCount }}</p>
                    </div>
                    <span class="admin-stat-icon bg-warning-subtle text-warning"><i data-feather="star" style="width:14px;height:14px;"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card admin-stat-card">
            <div class="card-body py-2 px-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted small mb-0" style="font-size:11px;">Avec registre</p>
                        <p class="fs-5 mb-0 text-success fw-bold">{{ $withFiles }}</p>
                    </div>
                    <span class="admin-stat-icon bg-success-subtle text-success"><i data-feather="file-text" style="width:14px;height:14px;"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card admin-stat-card">
            <div class="card-body py-2 px-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted small mb-0" style="font-size:11px;">Sans registre</p>
                        <p class="fs-5 mb-0 text-secondary fw-bold">{{ $withoutFiles }}</p>
                    </div>
                    <span class="admin-stat-icon bg-secondary-subtle text-secondary"><i data-feather="folder-minus" style="width:14px;height:14px;"></i></span>
                </div>
            </div>
        </div>
    </div>
</div>

@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show py-2 mb-3 small">{{ session('status') }}
        <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Fermer"></button>
    </div>
@endif

@if(session('passwordResetLink'))
    <div class="alert alert-info py-2 mb-3 small">
        <div class="fw-semibold mb-1">🔗 Lien de réinitialisation généré (valable 5 minutes, usage unique) :</div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <code id="generatedResetLink" class="flex-grow-1 text-break">{{ session('passwordResetLink') }}</code>
            <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-2 py-0.5" style="font-size:11px;"
                    onclick="navigator.clipboard.writeText(document.getElementById('generatedResetLink').textContent); this.textContent='Copié !';">
                Copier
            </button>
        </div>
        <div class="text-muted mt-1">Transmettez ce lien à l'utilisateur par le canal de votre choix (WhatsApp, SMS, en main propre).</div>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show py-2 mb-3 small">
        <ul class="mb-0 small">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
        <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Fermer"></button>
    </div>
@endif

<div class="card admin-users-shell mb-3">
    <div class="card-header py-2 bg-white">
        <h6 class="card-title mb-0 fw-bold">Créer un utilisateur et l’attribuer à une entreprise</h6>
    </div>
    <div class="card-body py-2.5">
        <form method="post" action="{{ route('admin.users.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-lg-3">
                <label class="form-label small mb-1">Entreprise cible</label>
                <select name="template_user_id" class="form-select form-select-sm" required>
                    <option value="">— Choisir —</option>
                    @foreach($enterpriseOptions as $opt)
                        <option value="{{ $opt['template_user_id'] }}" @selected((string) old('template_user_id') === (string) $opt['template_user_id'])>
                            {{ $opt['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label small mb-1">Nom complet</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="form-control form-control-sm">
            </div>
            <div class="col-lg-3">
                <label class="form-label small mb-1">E-mail</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="form-control form-control-sm">
            </div>
            <div class="col-lg-2">
                <label class="form-label small mb-1">Mot de passe</label>
                <input type="password" name="password" required class="form-control form-control-sm">
            </div>
            <div class="col-lg-2">
                <input type="hidden" name="password_confirmation" id="pwdConfirmTmp">
                <button type="submit" class="btn btn-sm btn-primary w-100 rounded-pill" onclick="document.getElementById('pwdConfirmTmp').value=this.form.password.value;">Créer</button>
            </div>
        </form>
    </div>
</div>

<div class="card admin-users-shell">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 bg-white">
        <div class="d-flex align-items-center gap-2">
            <h6 class="card-title mb-0 fw-bold">Entreprises regroupées</h6>
            <span class="badge bg-primary rounded-pill" style="font-size:11px;">{{ $enterpriseGroups->count() }} groupe(s)</span>
        </div>
        <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 340px;">
            <input type="text" id="enterpriseSearchInput" class="form-control form-control-sm rounded-pill px-3" placeholder="🔍 Rechercher entreprise, NIF, e-mail..." onkeyup="filterEnterprises(this.value)">
        </div>
        <div class="d-flex align-items-center gap-1">
            <button type="button" class="btn btn-outline-secondary btn-xs rounded-pill px-2.5" style="font-size:11px;" data-collapse="all-open">Tout ouvrir</button>
            <button type="button" class="btn btn-outline-secondary btn-xs rounded-pill px-2.5" style="font-size:11px;" data-collapse="all-close">Tout fermer</button>
        </div>
    </div>
    <div class="card-body p-0">
        @if($enterpriseGroups->isEmpty())
            <div class="text-center text-muted py-4 small">Aucun utilisateur.</div>
        @else
            <div class="accordion accordion-flush admin-enterprise-accordion" id="enterpriseUsersAccordion">
                @foreach($enterpriseGroups as $groupIndex => $group)
                    @php
                        $collapseId = 'enterprise-users-'.$groupIndex;
                        $headingId = 'enterprise-users-heading-'.$groupIndex;
                        $show = $groupIndex < 3;
                        $activePremium = collect($group['users'])->filter(fn ($u) => $u->hasActivePremiumPeriod())->count();
                        $suspended = collect($group['users'])->filter(fn ($u) => (bool) $u->account_suspended)->count();
                    @endphp
                    <div class="accordion-item search-target-item">
                        <h2 class="accordion-header" id="{{ $headingId }}">
                            <button class="accordion-button {{ $show ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="{{ $show ? 'true' : 'false' }}" aria-controls="{{ $collapseId }}">
                                <div class="d-flex flex-wrap align-items-center justify-content-between w-100 me-2 gap-2">
                                    <div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <span class="fw-bold text-dark fs-6 search-company-title">{{ $group['company_name'] }}</span>
                                            <span class="enterprise-chip"><i data-feather="users" style="width:11px;height:11px;"></i> {{ $group['users_count'] }} utilisateur(s)</span>
                                        </div>
                                        <div class="enterprise-meta mt-0.5 d-flex flex-wrap gap-1.5 align-items-center">
                                            @if(!empty($group['company_tax_id']))
                                                <span class="badge bg-info-subtle text-info border px-1.5 py-0.5" style="font-size:10px;">NIF: {{ $group['company_tax_id'] }}</span>
                                            @endif
                                            @if(!empty($group['enterprise_license_id']))
                                                <span class="badge bg-primary-subtle text-primary border px-1.5 py-0.5" style="font-size:10px;">Licence #{{ $group['enterprise_license_id'] }}</span>
                                            @endif
                                            <span class="search-members-text ms-1" style="font-size:11px;">
                                                <i class="align-middle me-0.5" data-feather="user" style="width:10px;height:10px;"></i>
                                                <strong>Membres :</strong> {{ collect($group['users'])->pluck('name')->join(', ') }} 
                                                <span class="text-muted">({{ collect($group['users'])->pluck('email')->join(', ') }})</span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="d-none d-md-flex align-items-center gap-1.5">
                                        <span class="badge bg-success-subtle text-success border rounded-pill px-2.5 py-1 fw-semibold" style="font-size:11px;">
                                            🟢 Premium {{ $activePremium }}
                                        </span>
                                        <span class="badge bg-danger-subtle text-danger border rounded-pill px-2.5 py-1 fw-semibold" style="font-size:11px;">
                                            🔴 Suspendus {{ $suspended }}
                                        </span>
                                    </div>
                                </div>
                            </button>
                        </h2>
                        <div id="{{ $collapseId }}" class="accordion-collapse collapse {{ $show ? 'show' : '' }}" aria-labelledby="{{ $headingId }}" data-bs-parent="#enterpriseUsersAccordion">
                            <div class="accordion-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0 align-middle admin-user-table">
                                        <thead>
                                            <tr>
                                                <th class="ps-3">Utilisateur</th>
                                                <th class="d-none d-lg-table-cell">E-mail</th>
                                                <th class="d-none d-md-table-cell">Abonnement</th>
                                                <th class="d-none d-md-table-cell">Échéance</th>
                                                <th class="d-none d-md-table-cell">Alerte</th>
                                                <th class="text-center pe-3">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($group['users'] as $user)
                                            @php
                                                $premiumOk = $user->hasActivePremiumPeriod();
                                                $soon = $user->premiumEndsWithinDays(7);
                                                $past = $user->is_premium && $user->premium_ends_at && $user->premium_ends_at->isPast();
                                            @endphp
                                            <tr class="{{ $user->account_suspended ? 'table-danger' : '' }}">
                                                <td class="ps-3">
                                                    <div class="fw-bold text-dark small">{{ $user->name }}</div>
                                                    <small class="text-muted d-block" style="font-size:10px;">ID #{{ $user->id }}</small>
                                                    {{-- Résumé abonnement + alerte affiché uniquement sur mobile, colonnes dédiées masquées en dessous de md --}}
                                                    <div class="d-md-none d-flex flex-wrap gap-1 mt-1">
                                                        @if($user->is_platform_admin)
                                                            @if($user->hasActivePremiumPeriod())
                                                                <span class="badge bg-warning text-dark rounded-pill px-2 py-0.5" style="font-size:10px;">Premium Admin</span>
                                                            @else
                                                                <span class="badge bg-primary rounded-pill px-2 py-0.5" style="font-size:10px;">Admin</span>
                                                            @endif
                                                        @elseif($user->is_accountant ?? false)
                                                            <span class="badge bg-info text-dark rounded-pill px-2 py-0.5" style="font-size:10px;">Cabinet</span>
                                                        @elseif($user->role_key === 'commercial')
                                                            <span class="badge bg-primary text-white rounded-pill px-2 py-0.5" style="font-size:10px;">Commercial</span>
                                                        @elseif($premiumOk)
                                                            <span class="badge bg-warning text-dark rounded-pill px-2 py-0.5" style="font-size:10px;">Premium</span>
                                                        @elseif($user->is_premium)
                                                            <span class="badge bg-secondary rounded-pill px-2 py-0.5" style="font-size:10px;">Premium Exp.</span>
                                                        @else
                                                            <span class="badge bg-light text-dark border rounded-pill px-2 py-0.5" style="font-size:10px;">Gratuit</span>
                                                        @endif
                                                        @if($user->account_suspended)
                                                            <span class="badge bg-danger rounded-pill px-2 py-0.5" style="font-size:10px;">Bloqué</span>
                                                        @elseif($past)
                                                            <span class="badge bg-danger rounded-pill px-2 py-0.5" style="font-size:10px;">Échéance dépassée</span>
                                                        @elseif($soon && $premiumOk)
                                                            <span class="badge bg-warning text-dark rounded-pill px-2 py-0.5" style="font-size:10px;">&lt; 7 j.</span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="small d-none d-lg-table-cell">{{ $user->email }}</td>
                                                <td class="d-none d-md-table-cell">
                                                    @if($user->is_platform_admin)
                                                        @if($user->hasActivePremiumPeriod())
                                                            <span class="badge bg-warning text-dark rounded-pill px-2 py-0.5" style="font-size:10px;">Premium Admin</span>
                                                        @else
                                                            <span class="badge bg-primary rounded-pill px-2 py-0.5" style="font-size:10px;">Admin</span>
                                                        @endif
                                                    @elseif($user->is_accountant ?? false)
                                                        <span class="badge bg-info text-dark rounded-pill px-2 py-0.5" style="font-size:10px;">Cabinet</span>
                                                    @elseif($user->role_key === 'commercial')
                                                        <span class="badge bg-primary text-white rounded-pill px-2 py-0.5" style="font-size:10px;">Commercial</span>
                                                    @elseif($premiumOk)
                                                        <span class="badge bg-warning text-dark rounded-pill px-2 py-0.5" style="font-size:10px;">Premium</span>
                                                    @elseif($user->is_premium)
                                                        <span class="badge bg-secondary rounded-pill px-2 py-0.5" style="font-size:10px;">Premium Exp.</span>
                                                    @else
                                                        <span class="badge bg-light text-dark border rounded-pill px-2 py-0.5" style="font-size:10px;">Gratuit</span>
                                                    @endif
                                                </td>
                                                <td class="d-none d-md-table-cell">
                                                    @if($user->premium_ends_at)
                                                        <small class="fw-semibold text-dark" style="font-size:11px;">{{ $user->premium_ends_at->format('d/m/Y H:i') }}</small>
                                                    @else
                                                        <span class="text-muted small">—</span>
                                                    @endif
                                                </td>
                                                <td class="d-none d-md-table-cell">
                                                    @if($user->account_suspended)
                                                        <span class="badge bg-danger rounded-pill px-2 py-0.5" style="font-size:10px;">Bloqué</span>
                                                    @elseif($past)
                                                        <span class="badge bg-danger rounded-pill px-2 py-0.5" style="font-size:10px;">Échéance dépassée</span>
                                                    @elseif($soon && $premiumOk)
                                                        <span class="badge bg-warning text-dark rounded-pill px-2 py-0.5" style="font-size:10px;">&lt; 7 j.</span>
                                                    @else
                                                        <span class="text-muted small">—</span>
                                                    @endif
                                                </td>
                                                <td class="text-center pe-3">
                                                    <div class="d-flex flex-wrap gap-1 justify-content-center align-items-center">
                                                        <!-- Modifier -->
                                                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-xs btn-outline-primary rounded-pill px-2 py-0.5" style="font-size:11px;" title="Modifier cet utilisateur">
                                                            ✏️ Éditer
                                                        </a>

                                                        <!-- Réinitialiser Mot de Passe -->
                                                        <button type="button" class="btn btn-xs btn-outline-info rounded-pill px-2 py-0.5" style="font-size:11px;" data-bs-toggle="modal" data-bs-target="#resetPasswordModal{{ $user->id }}" title="Réinitialiser le mot de passe">
                                                            🔑 Mdp
                                                        </button>

                                                        <!-- Lien de réinitialisation à envoyer à l'utilisateur -->
                                                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-2 py-0.5 js-generate-reset-link" style="font-size:11px;" data-url="{{ route('admin.users.password-reset-link', $user) }}" title="Générer un lien pour que l'utilisateur définisse lui-même son mot de passe">
                                                            🔗 Lien
                                                        </button>

                                                        <!-- Activer / Désactiver Premium -->
                                                        @if(! $user->is_platform_admin && ! ($user->is_accountant ?? false))
                                                            @if($premiumOk)
                                                                <form method="POST" action="{{ route('admin.users.premium.deactivate', $user) }}" class="d-inline" onsubmit="return confirm('Repasser ce compte en Gratuit ?');">
                                                                    @csrf
                                                                    <button type="submit" class="btn btn-xs btn-outline-secondary rounded-pill px-2 py-0.5" style="font-size:11px;" title="Fin d’essai Premium">Gratuit</button>
                                                                </form>
                                                            @else
                                                                <form method="POST" action="{{ route('admin.users.premium.activate', $user) }}" class="d-inline">
                                                                    @csrf
                                                                    <input type="hidden" name="days" value="30">
                                                                    <button type="submit" class="btn btn-xs btn-warning text-dark rounded-pill px-2 py-0.5 fw-semibold" style="font-size:11px;" title="Essai Premium 30 jours">30j</button>
                                                                </form>
                                                                <form method="POST" action="{{ route('admin.users.premium.activate', $user) }}" class="d-inline">
                                                                    @csrf
                                                                    <input type="hidden" name="days" value="90">
                                                                    <button type="submit" class="btn btn-xs btn-outline-warning rounded-pill px-2 py-0.5" style="font-size:11px;" title="Essai Premium 90 jours">90j</button>
                                                                </form>
                                                            @endif
                                                        @endif

                                                        <!-- Supprimer Utilisateur -->
                                                        @if($user->id !== auth()->id())
                                                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer définitivement {{ addslashes($user->name) }} ({{ $user->email }}) ?');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-xs btn-outline-danger rounded-pill px-1.5 py-0.5" style="font-size:11px;" title="Supprimer cet utilisateur">
                                                                    🗑️
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<!-- MODALS HORS TABLEAU -->
@foreach($enterpriseGroups as $group)
    @foreach($group['users'] as $user)
        <div class="modal fade" id="resetPasswordModal{{ $user->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <form method="POST" action="{{ route('admin.users.reset-password', $user) }}">
                        @csrf
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-bold fs-6">🔑 Réinitialiser le mot de passe</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body py-3">
                            <p class="text-muted small mb-3">
                                Utilisateur : <strong>{{ $user->name }}</strong> (<code>{{ $user->email }}</code>)
                            </p>
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Nouveau mot de passe</label>
                                <input type="text" name="password" id="pwdInput{{ $user->id }}" class="form-control form-control-sm rounded-3" placeholder="Saisissez ou générez un mot de passe" required minlength="8" value="12345678">
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-xs btn-light border rounded-pill px-3" onclick="document.getElementById('pwdInput{{ $user->id }}').value='12345678';">
                                    Mot de passe par défaut (12345678)
                                </button>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endforeach

<p class="text-muted small mt-3 mb-0" style="font-size:11px;">
    <strong>Automatique :</strong> chaque jour à 01:00, les abonnements Premium expirés sont passés en gratuit.
</p>
@endsection

@push('scripts')
<script>
    (function () {
        const root = document.getElementById('enterpriseUsersAccordion');
        if (!root) return;

        const toggles = document.querySelectorAll('[data-collapse]');
        toggles.forEach(btn => {
            btn.addEventListener('click', () => {
                const openAll = btn.getAttribute('data-collapse') === 'all-open';
                root.querySelectorAll('.accordion-collapse').forEach(el => {
                    const instance = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
                    openAll ? instance.show() : instance.hide();
                });
            });
        });
    })();

    document.querySelectorAll('.js-generate-reset-link').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const url = btn.getAttribute('data-url');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = '...';

            fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(function (data) {
                    if (data.ok && data.url) {
                        window.prompt(
                            'Lien de réinitialisation pour ' + data.user_name + ' (' + data.user_email + ') — copiez-le (Ctrl+C) puis Annuler :',
                            data.url
                        );
                    } else {
                        alert('Réponse inattendue du serveur : ' + JSON.stringify(data));
                    }
                })
                .catch(function (err) {
                    alert('Échec de la génération du lien : ' + err.message + '\n\nOuvrez la console (F12) pour plus de détails.');
                    console.error('Erreur génération lien reset password:', err);
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.textContent = originalText;
                });
        });
    });

    function filterEnterprises(query) {
        const q = query.toLowerCase().trim();
        const items = document.querySelectorAll('.search-target-item');
        items.forEach(item => {
            const title = item.querySelector('.search-company-title')?.textContent.toLowerCase() || '';
            const members = item.querySelector('.search-members-text')?.textContent.toLowerCase() || '';
            if (title.includes(q) || members.includes(q)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }
</script>
@endpush
