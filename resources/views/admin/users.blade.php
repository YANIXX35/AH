@extends('layouts.app')

@section('title', 'Utilisateurs | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@push('styles')
<style>
    .admin-users-hero {
        background: linear-gradient(135deg, #1f3c88 0%, #2f5fb3 55%, #4b7bd4 100%);
        border-radius: 1rem;
        color: #fff;
        padding: 1.15rem 1.25rem;
        box-shadow: 0 10px 28px rgba(30, 57, 109, 0.22);
    }
    .admin-users-hero .hero-sub {
        color: rgba(255, 255, 255, 0.9);
        margin-bottom: 0;
        font-size: 0.9rem;
    }
    .admin-stat-card {
        border: 0;
        border-radius: 0.95rem;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
        height: 100%;
    }
    .admin-stat-icon {
        width: 2.1rem;
        height: 2.1rem;
        border-radius: 0.65rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .admin-users-shell {
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        box-shadow: 0 10px 26px rgba(15, 23, 42, 0.05);
        overflow: hidden;
        background: #ffffff;
    }
    .admin-users-shell .card-header {
        border-bottom: 1px solid #e9edf5;
        background: #fff;
    }
    .admin-enterprise-accordion {
        padding: 12px;
    }
    .admin-enterprise-accordion .accordion-item {
        border: 1px solid #e2e8f0 !important;
        border-radius: 12px !important;
        overflow: hidden;
        margin-bottom: 12px;
        background: #ffffff;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
        outline: none !important;
    }
    .admin-enterprise-accordion .accordion-button {
        box-shadow: none !important;
        background: #ffffff;
        padding: 1rem 1.25rem;
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
        margin-left: 1rem;
        flex-shrink: 0;
    }
    .enterprise-meta {
        font-size: 0.8rem;
        color: #64748b;
    }
    .enterprise-chip {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
        border-radius: 999px;
        padding: 0.2rem 0.65rem;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }
    .admin-user-table thead th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #475569;
        background: #f8fafc;
        padding-top: 0.75rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #e2e8f0;
    }
</style>
@endpush

@section('content')
<div class="mb-3">
    <nav aria-label="Fil d’Ariane admin" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Administration</a></li>
            <li class="breadcrumb-item active" aria-current="page">Utilisateurs</li>
        </ol>
    </nav>
    <div class="admin-users-hero d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3">
        <div class="pe-lg-3">
            <h1 class="h3 mb-1 text-white"><strong>Entreprises</strong> inscrites</h1>
            <p class="hero-sub">Gestion des comptes, abonnements Premium et suspensions dans un espace consolidé.</p>
        </div>
        <a href="{{ route('register') }}" class="btn btn-light text-primary fw-semibold rounded-pill px-4">Nouvel utilisateur</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card admin-stat-card">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted small mb-0">Total</p>
                        <p class="h4 mb-0 text-primary fw-bold">{{ $totalUsers }}</p>
                    </div>
                    <span class="admin-stat-icon bg-primary-subtle text-primary"><i data-feather="users" style="width:16px;height:16px;"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card admin-stat-card">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted small mb-0">Suspendus</p>
                        <p class="h4 mb-0 text-danger fw-bold">{{ $suspendedCount }}</p>
                    </div>
                    <span class="admin-stat-icon bg-danger-subtle text-danger"><i data-feather="slash" style="width:16px;height:16px;"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card admin-stat-card">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted small mb-0">Premium actifs</p>
                        <p class="h4 mb-0 text-warning fw-bold">{{ $premiumActiveCount }}</p>
                        <p class="text-muted small mb-0 mt-1">Échéance &lt; 7 j. : <strong>{{ $premiumExpiringSoon }}</strong></p>
                    </div>
                    <span class="admin-stat-icon bg-warning-subtle text-warning"><i data-feather="star" style="width:16px;height:16px;"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card admin-stat-card">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted small mb-0">Avec registre</p>
                        <p class="h4 mb-0 text-success fw-bold">{{ $withFiles }}</p>
                    </div>
                    <span class="admin-stat-icon bg-success-subtle text-success"><i data-feather="file-text" style="width:16px;height:16px;"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card admin-stat-card">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted small mb-0">Sans registre</p>
                        <p class="h4 mb-0 text-secondary fw-bold">{{ $withoutFiles }}</p>
                    </div>
                    <span class="admin-stat-icon bg-secondary-subtle text-secondary"><i data-feather="folder-minus" style="width:16px;height:16px;"></i></span>
                </div>
            </div>
        </div>
    </div>
</div>

@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show mb-3">{{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show mb-3">
        <ul class="mb-0 small">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
    </div>
@endif

<div class="card admin-users-shell mb-4">
    <div class="card-header py-3 bg-white">
        <h5 class="card-title mb-0 fw-bold">Créer un utilisateur et l’attribuer à une entreprise</h5>
    </div>
    <div class="card-body">
        <form method="post" action="{{ route('admin.users.store') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-lg-4">
                <label class="form-label">Entreprise cible</label>
                <select name="template_user_id" class="form-select" required>
                    <option value="">— Choisir —</option>
                    @foreach($enterpriseOptions as $opt)
                        <option value="{{ $opt['template_user_id'] }}" @selected((string) old('template_user_id') === (string) $opt['template_user_id'])>
                            {{ $opt['label'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">Le nouveau compte reprendra la même entreprise (NIF/licence/profil) que le modèle sélectionné.</div>
            </div>
            <div class="col-lg-3">
                <label class="form-label">Nom complet</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="form-control">
            </div>
            <div class="col-lg-3">
                <label class="form-label">E-mail</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="form-control">
            </div>
            <div class="col-lg-2">
                <label class="form-label">Mot de passe</label>
                <input type="password" name="password" required class="form-control">
            </div>
            <div class="col-lg-2">
                <label class="form-label">Confirmation</label>
                <input type="password" name="password_confirmation" required class="form-control">
            </div>
            <div class="col-lg-2">
                <button type="submit" class="btn btn-primary w-100 rounded-pill">Créer</button>
            </div>
        </form>
    </div>
</div>

<div class="card admin-users-shell">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3 py-3 bg-white">
        <div class="d-flex align-items-center gap-2">
            <h5 class="card-title mb-0 fw-bold">Entreprises regroupées</h5>
            <span class="badge bg-primary rounded-pill">{{ $enterpriseGroups->count() }} groupe(s)</span>
        </div>
        <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 380px;">
            <input type="text" id="enterpriseSearchInput" class="form-control form-control-sm rounded-pill px-3" placeholder="🔍 Rechercher entreprise, NIF, e-mail..." onkeyup="filterEnterprises(this.value)">
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-collapse="all-open">Tout ouvrir</button>
            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-collapse="all-close">Tout fermer</button>
        </div>
    </div>
    <div class="card-body p-0">
        @if($enterpriseGroups->isEmpty())
            <div class="text-center text-muted py-5">Aucun utilisateur.</div>
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
                                            <span class="fw-bold text-dark fs-5 search-company-title">{{ $group['company_name'] }}</span>
                                            <span class="enterprise-chip"><i data-feather="users" style="width:12px;height:12px;"></i> {{ $group['users_count'] }} utilisateur(s)</span>
                                        </div>
                                        <div class="enterprise-meta mt-1 d-flex flex-wrap gap-2 align-items-center">
                                            @if(!empty($group['company_tax_id']))
                                                <span class="badge bg-info-subtle text-info border">NIF: {{ $group['company_tax_id'] }}</span>
                                            @endif
                                            @if(!empty($group['enterprise_license_id']))
                                                <span class="badge bg-primary-subtle text-primary border">Licence #{{ $group['enterprise_license_id'] }}</span>
                                            @endif
                                            <span class="search-members-text ms-1">
                                                <i class="align-middle me-1" data-feather="user" style="width:12px;height:12px;"></i>
                                                <strong>Membres :</strong> {{ collect($group['users'])->pluck('name')->join(', ') }} 
                                                <span class="text-muted">({{ collect($group['users'])->pluck('email')->join(', ') }})</span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="d-none d-md-flex align-items-center gap-2">
                                        <span class="badge bg-success-subtle text-success border rounded-pill px-3 py-1.5 fw-semibold">
                                            🟢 Premium {{ $activePremium }}
                                        </span>
                                        <span class="badge bg-danger-subtle text-danger border rounded-pill px-3 py-1.5 fw-semibold">
                                            🔴 Suspendus {{ $suspended }}
                                        </span>
                                    </div>
                                </div>
                            </button>
                        </h2>
                        <div id="{{ $collapseId }}" class="accordion-collapse collapse {{ $show ? 'show' : '' }}" aria-labelledby="{{ $headingId }}" data-bs-parent="#enterpriseUsersAccordion">
                            <div class="accordion-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0 align-middle admin-user-table">
                                        <thead>
                                            <tr>
                                                <th class="ps-4">Utilisateur</th>
                                                <th class="d-none d-lg-table-cell">E-mail</th>
                                                <th>Abonnement</th>
                                                <th class="d-none d-md-table-cell">Échéance</th>
                                                <th>Alerte</th>
                                                <th class="text-center pe-4">Actions</th>
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
                                                <td class="ps-4">
                                                    <div class="fw-bold text-dark">{{ $user->name }}</div>
                                                    <small class="text-muted d-block" style="font-size:11px;">ID #{{ $user->id }}</small>
                                                    <small class="text-muted d-lg-none">{{ $user->email }}</small>
                                                </td>
                                                <td class="small d-none d-lg-table-cell">{{ $user->email }}</td>
                                                <td>
                                                    @if($user->is_platform_admin)
                                                        @if($user->hasActivePremiumPeriod())
                                                            <span class="badge bg-warning text-dark rounded-pill px-2.5">Premium Admin</span>
                                                        @else
                                                            <span class="badge bg-primary rounded-pill px-2.5">Admin</span>
                                                        @endif
                                                    @elseif($user->is_accountant ?? false)
                                                        <span class="badge bg-info text-dark rounded-pill px-2.5">Cabinet</span>
                                                    @elseif($user->role_key === 'commercial')
                                                        <span class="badge bg-primary text-white rounded-pill px-2.5">Commercial</span>
                                                    @elseif($premiumOk)
                                                        <span class="badge bg-warning text-dark rounded-pill px-2.5">Premium</span>
                                                    @elseif($user->is_premium)
                                                        <span class="badge bg-secondary rounded-pill px-2.5">Premium Exp.</span>
                                                    @else
                                                        <span class="badge bg-light text-dark border rounded-pill px-2.5">Gratuit</span>
                                                    @endif
                                                </td>
                                                <td class="d-none d-md-table-cell">
                                                    @if($user->premium_ends_at)
                                                        <small class="fw-semibold">{{ $user->premium_ends_at->format('d/m/Y H:i') }}</small>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($user->account_suspended)
                                                        <span class="badge bg-danger rounded-pill">Bloqué</span>
                                                        @if($user->auto_suspended_for_payment)
                                                            <span class="badge bg-dark rounded-pill ms-1">Auto</span>
                                                        @endif
                                                    @elseif($past)
                                                        <span class="badge bg-danger rounded-pill">Échéance dépassée</span>
                                                    @elseif($soon && $premiumOk)
                                                        <span class="badge bg-warning text-dark rounded-pill">&lt; 7 j.</span>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td class="text-center pe-4">
                                                    <div class="d-inline-flex flex-wrap gap-1 justify-content-center align-items-center">
                                                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-primary rounded-pill px-3">Gérer</a>
                                                        @if(! $user->is_platform_admin && ! ($user->is_accountant ?? false))
                                                            @if($premiumOk)
                                                                <form method="POST" action="{{ route('admin.users.premium.deactivate', $user) }}" class="d-inline" onsubmit="return confirm('Repasser ce compte en Gratuit ?');">
                                                                    @csrf
                                                                    <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5" title="Fin d’essai Premium">Gratuit</button>
                                                                </form>
                                                            @else
                                                                <form method="POST" action="{{ route('admin.users.premium.activate', $user) }}" class="d-inline">
                                                                    @csrf
                                                                    <input type="hidden" name="days" value="30">
                                                                    <button type="submit" class="btn btn-sm btn-warning text-dark rounded-pill px-2.5 fw-semibold" title="Essai Premium 30 jours">Premium 30j</button>
                                                                </form>
                                                                <form method="POST" action="{{ route('admin.users.premium.activate', $user) }}" class="d-inline">
                                                                    @csrf
                                                                    <input type="hidden" name="days" value="90">
                                                                    <button type="submit" class="btn btn-sm btn-outline-warning rounded-pill px-2.5" title="Essai Premium 90 jours">90j</button>
                                                                </form>
                                                            @endif
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

<p class="text-muted small mt-3 mb-0">
    <strong>Automatique :</strong> chaque jour à 01:00, les abonnements Premium expirés sont passés en gratuit ;
    les comptes clients peuvent être <strong>suspendus</strong> tant qu’ils n’ont pas régularisé (commande <code>app:premium-expire</code>).
    Les <strong>administrateurs plateforme</strong> disposent du Premium sans échéance (non expiré par le planificateur). Utilisez <strong>Premium 30j</strong> / <strong>90j</strong> pour les comptes clients en essai.
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
