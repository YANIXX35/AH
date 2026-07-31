@extends('layouts.app')

@section('title', 'Portefeuille Clients | SITIAME CAPITAL')
@section('page_title', 'Portefeuille Clients Soft-UI')

@push('styles')
<style>
    .soft-dashboard-body {
        background-color: #eef2f6;
        min-height: 100vh;
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        color: #1e293b;
        padding: 24px;
    }
    
    .soft-dashboard-container {
        background: #f8fafc;
        border-radius: 32px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.06);
        padding: 20px;
    }

    /* Soft White Cards */
    .mockup-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.03), 0 4px 12px rgba(15, 23, 42, 0.02);
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .mockup-card:hover {
        box-shadow: 0 16px 36px -4px rgba(15, 23, 42, 0.06);
        border-color: #cbd5e1;
    }

    /* Top Navigation Header */
    .mockup-header-bar {
        background: #ffffff;
        border-radius: 9999px;
        padding: 8px 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.02);
    }

    .pill-tab-btn {
        border-radius: 9999px;
        font-weight: 600;
        font-size: 0.85rem;
        padding: 8px 22px;
        border: none;
        transition: all 0.2s ease;
    }
    .pill-tab-btn-active {
        background: #ffffff;
        color: #0f172a !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
    }
    .pill-tab-btn-inactive {
        background: transparent;
        color: #64748b;
    }
    .pill-tab-btn-inactive:hover {
        color: #0f172a;
    }

    /* Portfolio & Retention Stats */
    .portfolio-kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 20px 22px;
        transition: all 0.22s ease;
        position: relative;
        overflow: hidden;
    }
    .portfolio-kpi-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        border-radius: 20px 20px 0 0;
    }
    .portfolio-kpi-card.kpi-total::before    { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
    .portfolio-kpi-card.kpi-trial::before    { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
    .portfolio-kpi-card.kpi-converted::before { background: linear-gradient(90deg, #10b981, #34d399); }
    .portfolio-kpi-card.kpi-churned::before  { background: linear-gradient(90deg, #ef4444, #f87171); }
    .portfolio-kpi-card:hover {
        box-shadow: 0 12px 28px -4px rgba(15,23,42,0.08);
        transform: translateY(-2px);
        border-color: #cbd5e1;
    }
    .kpi-number {
        font-size: 2.2rem;
        font-weight: 800;
        line-height: 1;
        letter-spacing: -0.5px;
    }
    .kpi-label {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #94a3b8;
        margin-bottom: 4px;
    }
    .kpi-sub {
        font-size: 0.78rem;
        color: #64748b;
        margin-top: 4px;
    }
    .retention-bar-outer {
        background: #f1f5f9;
        border-radius: 99px;
        height: 12px;
        overflow: hidden;
        position: relative;
        display: flex;
    }
    .retention-bar-converted {
        background: linear-gradient(90deg, #10b981, #34d399);
        height: 100%;
        border-radius: 99px 0 0 99px;
        transition: width 1s cubic-bezier(0.4,0,0.2,1);
    }
    .retention-bar-churned {
        background: linear-gradient(90deg, #ef4444, #f87171);
        height: 100%;
        border-radius: 0 99px 99px 0;
        transition: width 1s cubic-bezier(0.4,0,0.2,1);
    }
    .retention-section-header {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #94a3b8;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 8px;
        margin-bottom: 16px;
    }
</style>
@endpush

@section('content')
<div class="soft-dashboard-body animate__animated animate__fadeIn">
    <div class="soft-dashboard-container">
        
        <!-- TOP HEADER BAR (Matching Mockup Navigation Bar) -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 mockup-header-bar">
            <!-- Left: Logo & Pill Navigation Tabs -->
            <div class="d-flex align-items-center gap-2 flex-grow-1 flex-lg-grow-0 w-100 w-lg-auto">
                <div class="bg-primary text-white rounded-circle p-2 d-none d-sm-flex align-items-center justify-content-center flex-shrink-0" style="width:40px; height:40px;">
                    <i data-feather="grid" style="width:20px; height:20px;"></i>
                </div>
                <div class="d-flex align-items-center gap-1 bg-light rounded-pill p-1 border overflow-auto scrollbar-none flex-nowrap w-100">
                    <a href="{{ route('commercial.dashboard') }}" class="pill-tab-btn pill-tab-btn-inactive text-decoration-none text-nowrap flex-shrink-0">
                        <i data-feather="layout" class="me-1" style="width:14px; height:14px;"></i> Tableau de bord
                    </a>
                    <a href="{{ route('commercial.portefeuille') }}" class="pill-tab-btn pill-tab-btn-active text-decoration-none text-nowrap flex-shrink-0">
                        <i data-feather="briefcase" class="me-1" style="width:14px; height:14px;"></i> Mon Portefeuille
                    </a>
                    <a href="{{ route('commercial.prospects') }}" class="pill-tab-btn pill-tab-btn-inactive text-decoration-none text-nowrap flex-shrink-0">
                        <i data-feather="users" class="me-1" style="width:14px; height:14px;"></i> Leads CRM ({{ $totalProspects }})
                    </a>
                    <a href="{{ route('commercial.showcase') }}" class="pill-tab-btn pill-tab-btn-inactive text-decoration-none text-nowrap flex-shrink-0">
                        <i data-feather="book-open" class="me-1" style="width:14px; height:14px;"></i> Kit Marketing
                    </a>
                </div>
            </div>

            <!-- Right: Action Buttons -->
            <div class="d-flex align-items-center justify-content-between justify-content-lg-end gap-2 flex-grow-1 w-100 w-lg-auto">
                <div class="d-flex align-items-center gap-1.5 overflow-auto scrollbar-none flex-nowrap w-100 justify-content-start justify-content-lg-end">
                    <button type="button" class="btn btn-outline-primary rounded-pill px-3 py-2 fw-bold text-xs text-nowrap flex-shrink-0" data-bs-toggle="modal" data-bs-target="#addProspectModal">
                        + Lead
                    </button>
                    <a href="{{ route('commercial.dashboard', ['action' => 'add-client']) }}" class="btn btn-primary rounded-pill px-3.5 py-2 fw-bold text-xs text-nowrap flex-shrink-0 shadow-sm text-decoration-none">
                        <i data-feather="user-plus" class="me-1" style="width:12px; height:12px;"></i> + Client
                    </a>
                </div>
            </div>
        </div>

        <!-- Alert Status -->
        @if(session('status'))
            <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4 border-0 shadow-sm" role="alert">
                <i data-feather="check-circle" class="me-2 text-success"></i>
                {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- ============================================================ --}}
        {{-- SECTION 1 : STATISTIQUES & EVOLUTION DU PORTEFEUILLE         --}}
        {{-- ============================================================ --}}
        <div class="mockup-card p-4 mb-5 shadow-sm border border-light">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <div>
                    <div class="retention-section-header">📊 Portefeuille &amp; Rétention Clients</div>
                    <h2 class="h4 fw-bold text-dark mb-0">Statistiques d'acquisition &amp; de conversion</h2>
                    <p class="text-muted small mb-0">Indicateurs clés du portefeuille de clients que vous avez personnellement ajoutés.</p>
                </div>
                @if($portfolioTrialExpired > 0)
                    <span class="badge rounded-pill px-3 py-2 fw-bold"
                          style="background: {{ $conversionRate >= 60 ? 'linear-gradient(135deg,#10b981,#059669)' : ($conversionRate >= 30 ? 'linear-gradient(135deg,#f59e0b,#d97706)' : 'linear-gradient(135deg,#ef4444,#dc2626)') }}; color:#fff; font-size:.85rem;">
                        {{ $conversionRate }}% de conversion
                    </span>
                @endif
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="portfolio-kpi-card kpi-total">
                        <div class="kpi-label">Portefeuille</div>
                        <div class="kpi-number text-primary">{{ $totalClients }}</div>
                        <div class="kpi-sub">clients ajoutés au total</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="portfolio-kpi-card kpi-trial">
                        <div class="kpi-label">⌛ En Essai</div>
                        <div class="kpi-number" style="color:#f59e0b;">{{ $activeTrials }}</div>
                        <div class="kpi-sub">période d'essai en cours</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="portfolio-kpi-card kpi-converted">
                        <div class="kpi-label">✓ Abonnés</div>
                        <div class="kpi-number text-success">{{ $portfolioConverted }}</div>
                        <div class="kpi-sub">ont souscrit après l'essai</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="portfolio-kpi-card kpi-churned">
                        <div class="kpi-label">✗ Partis</div>
                        <div class="kpi-number text-danger">{{ $portfolioChurned }}</div>
                        <div class="kpi-sub">non convertis après essai</div>
                    </div>
                </div>
            </div>

            @if($portfolioTrialExpired > 0)
                @php
                    $convertedPct = $portfolioTrialExpired > 0 ? round(($portfolioConverted / $portfolioTrialExpired) * 100) : 0;
                    $churnedPct   = 100 - $convertedPct;
                @endphp
                <div class="mb-1">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-semibold text-muted">Rétention après essai ({{ $portfolioTrialExpired }} essais terminés)</span>
                        <span class="small fw-bold text-success">{{ $portfolioConverted }} convertis</span>
                    </div>
                    <div class="retention-bar-outer">
                        <div class="retention-bar-converted" style="width:{{ $convertedPct }}%;"></div>
                        <div class="retention-bar-churned" style="width:{{ $churnedPct }}%;"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <span class="small text-muted">
                            <span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:#10b981;"></span>
                            Convertis {{ $convertedPct }}%
                        </span>
                        <span class="small text-muted">
                            <span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:#ef4444;"></span>
                            Partis {{ $churnedPct }}%
                        </span>
                    </div>
                </div>
            @else
                <div class="text-center py-3 text-muted small bg-light rounded-4 border">
                    <i data-feather="clock" class="me-1" style="width:14px;height:14px;"></i>
                    Aucune période d'essai encore terminée &mdash; les statistiques détaillées apparaîtront après 1 mois.
                </div>
            @endif
        </div>

        {{-- ============================================================ --}}
        {{-- SECTION 2 : RECHERCHE ET LISTE DES CLIENTS / ENTREPRISES     --}}
        {{-- ============================================================ --}}
        <div class="mockup-card p-4 mb-5 shadow-sm border border-light">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div>
                    <h2 class="h4 fw-bold text-dark mb-1">🏢 Liste des Clients &amp; Entreprises parrainés</h2>
                    <p class="text-muted small mb-0">Répertoire complet et recherche dynamique de vos clients parrainés.</p>
                </div>
                
                <!-- Search Form -->
                <form action="{{ route('commercial.portefeuille') }}" method="GET" class="d-flex align-items-center gap-2">
                    <div class="position-relative">
                        <i data-feather="search" class="position-absolute text-muted" style="left:14px; top:12px; width:15px; height:15px;"></i>
                        <input type="text" name="search" class="form-control rounded-pill border bg-light ps-5 pe-4" 
                               placeholder="Rechercher..." value="{{ $search ?? '' }}" style="width:240px; font-size:0.85rem;">
                    </div>
                    <button type="submit" class="btn btn-primary rounded-pill px-3 py-2 fw-bold text-sm shadow-sm">
                        Filtrer
                    </button>
                    @if(!empty($search))
                        <a href="{{ route('commercial.portefeuille') }}" class="btn btn-light rounded-pill px-3 py-2 text-sm border">
                            Réinitialiser
                        </a>
                    @endif
                </form>
            </div>

            <div class="table-responsive d-none d-md-block">
                <table class="table align-middle">
                    <thead>
                        <tr class="text-muted small text-uppercase" style="font-size:0.75rem;">
                            <th class="border-0">Client</th>
                            <th class="border-0">Entreprise</th>
                            <th class="border-0">Contact</th>
                            <th class="border-0">Statut</th>
                            <th class="border-0 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                @forelse($clients as $client)
                    @php
                        $isTrialActive = false;
                        $daysLeft = 0;
                        if ($client && ($client->is_premium ?? false) && !empty($client->premium_ends_at)) {
                            try {
                                $endsAt = $client->premium_ends_at instanceof \Carbon\Carbon
                                    ? $client->premium_ends_at
                                    : \Carbon\Carbon::parse($client->premium_ends_at);
                                $isTrialActive = $endsAt->isFuture();
                                $daysLeft = $isTrialActive ? now()->diffInDays($endsAt) : 0;
                            } catch (\Throwable $e) {
                                $isTrialActive = false;
                                $daysLeft = 0;
                            }
                        }
                    @endphp
                        <tr class="border-bottom border-light">
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-primary text-white rounded-circle fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px; height:40px; font-size:0.85rem;">
                                        {{ strtoupper(substr($client->name ?? 'PME', 0, 2)) }}
                                    </div>
                                    <div class="fw-bold text-dark mb-0">{{ $client->name ?? 'Client PME' }}</div>
                                </div>
                            </td>
                            <td class="text-dark">
                                <div class="fw-semibold">{{ $client->company_name ?? '—' }}</div>
                                <div class="text-muted small" style="font-size:0.75rem;">{{ $client->sector ?? 'Secteur non spécifié' }}</div>
                            </td>
                            <td>
                                <div class="text-dark small"><i data-feather="mail" class="text-muted me-1" style="width:12px; height:12px;"></i>{{ $client->email ?? '—' }}</div>
                                <div class="text-muted small" style="font-size:0.78rem;"><i data-feather="phone" class="text-muted me-1" style="width:12px; height:12px;"></i>{{ $client->phone ?? '—' }}</div>
                            </td>
                            <td>
                                @if($isTrialActive)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 fw-semibold">
                                        <i data-feather="check-circle" class="me-1" style="width:12px; height:12px;"></i> Essai Actif ({{ $daysLeft }}j)
                                    </span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-1 fw-semibold">
                                        <i data-feather="alert-circle" class="me-1" style="width:12px; height:12px;"></i> Essai Expiré
                                    </span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#editClientModal{{ $client->id }}">
                                        Modifier
                                    </button>
                                    <form action="{{ route('commercial.clients.destroy', $client->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce client ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2">
                                            <i data-feather="trash-2" style="width:14px; height:14px;"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <!-- Edit Client Modal -->
                        <div class="modal fade" id="editClientModal{{ $client->id }}" data-bs-backdrop="static" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                    <form action="{{ route('commercial.clients.update', $client->id) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header border-0 bg-dark text-white p-4">
                                            <h5 class="modal-title fw-bold">Modifier la Fiche Client — {{ $client->name }}</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold">Nom complet</label>
                                                    <input type="text" name="name" class="form-control rounded-3" value="{{ old('name', $client->name) }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold">Adresse Email</label>
                                                    <input type="email" name="email" class="form-control rounded-3" value="{{ old('email', $client->email) }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold">Téléphone</label>
                                                    <input type="text" name="phone" class="form-control rounded-3" value="{{ old('phone', $client->phone) }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold">Raison Sociale / Entreprise</label>
                                                    <input type="text" name="company_name" class="form-control rounded-3" value="{{ old('company_name', $client->company_name) }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold">Sigle / Nom commercial</label>
                                                    <input type="text" name="company_sigle" class="form-control rounded-3" value="{{ old('company_sigle', $client->company_sigle) }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold">Matricule Fiscal / NIF</label>
                                                    <input type="text" name="company_tax_id" class="form-control rounded-3" value="{{ old('company_tax_id', $client->company_tax_id) }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold">Secteur d'activité</label>
                                                    <input type="text" name="sector" class="form-control rounded-3" value="{{ old('sector', $client->sector) }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold">Numéro RCCM / SIRET</label>
                                                    <input type="text" name="rccm" class="form-control rounded-3" value="{{ old('rccm', $client->rccm) }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold">Ville</label>
                                                    <input type="text" name="city" class="form-control rounded-3" value="{{ old('city', $client->city) }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold">Adresse Complète</label>
                                                    <input type="text" name="address" class="form-control rounded-3" value="{{ old('address', $client->address) }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold">Nouveau Mot de Passe (laisser vide si inchangé)</label>
                                                    <input type="password" name="password" class="form-control rounded-3" placeholder="Min. 8 caractères">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-0 p-4 pt-0">
                                            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Annuler</button>
                                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Enregistrer</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <i data-feather="users" class="mb-2" style="width:36px; height:36px; opacity:0.3;"></i>
                                <div>Aucun client trouvé dans votre portefeuille.</div>
                            </td>
                        </tr>
                @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Mobile View: Cards Layout -->
            <div class="d-block d-md-none">
                @forelse($clients as $client)
                    @php
                        $isTrialActive = false;
                        $daysLeft = 0;
                        if ($client && ($client->is_premium ?? false) && !empty($client->premium_ends_at)) {
                            try {
                                $endsAt = $client->premium_ends_at instanceof \Carbon\Carbon
                                    ? $client->premium_ends_at
                                    : \Carbon\Carbon::parse($client->premium_ends_at);
                                $isTrialActive = $endsAt->isFuture();
                                $daysLeft = $isTrialActive ? now()->diffInDays($endsAt) : 0;
                            } catch (\Throwable $e) {
                                $isTrialActive = false;
                                $daysLeft = 0;
                            }
                        }
                    @endphp
                    <div class="p-3 bg-light rounded-3 border d-flex flex-column gap-2 mb-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-primary text-white rounded-circle fw-bold d-flex align-items-center justify-content-center" style="width:32px; height:32px; font-size:0.75rem;">
                                    {{ strtoupper(substr($client->name ?? 'PME', 0, 2)) }}
                                </div>
                                <div>
                                    <div class="fw-bold text-dark small">{{ $client->name }}</div>
                                    <div class="text-muted small" style="font-size:0.7rem;">{{ $client->company_name ?? '—' }}</div>
                                </div>
                            </div>
                            <div>
                                @if($isTrialActive)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1" style="font-size: 0.65rem;">
                                        Essai ({{ $daysLeft }}j)
                                    </span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1" style="font-size: 0.65rem;">
                                        Expiré
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-1">
                            <span class="text-muted" style="font-size:0.7rem;">Créé le {{ $client->created_at->format('d/m/Y') }}</span>
                            <div class="d-flex gap-1.5">
                                <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-2.5 py-1 text-xs" data-bs-toggle="modal" data-bs-target="#editClientModal{{ $client->id }}">
                                    Modifier
                                </button>
                                <form action="{{ route('commercial.clients.destroy', $client->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce client ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-outline-danger rounded-pill px-2.5 py-1 text-xs ms-1">
                                        Supprimer
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted small py-3 bg-light rounded-3 border">
                        Aucun client trouvé dans votre portefeuille.
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</div>

<!-- Modal Add Prospect -->
<div class="modal fade" id="addProspectModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="{{ route('commercial.prospects.store') }}" method="POST">
                @csrf
                <div class="modal-header border-0 bg-dark text-white p-4">
                    <div>
                        <span class="badge bg-primary text-white rounded-pill px-3 py-1 mb-2 fw-semibold">LEAD GENERATION CRM</span>
                        <h4 class="modal-title fw-bold text-white mb-0">Nouveau Prospect / Lead Qualifié</h4>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4" style="max-height: 65vh; overflow-y: auto;">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Nom complet du prospect <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control rounded-3" placeholder="Ex: Jean Kouassi" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Adresse Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control rounded-3" placeholder="prospect@entreprise.ci" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Numéro de Téléphone / WhatsApp</label>
                            <input type="text" name="phone" class="form-control rounded-3" placeholder="+225 07 00 00 00 00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Raison Sociale / Entreprise</label>
                            <input type="text" name="company_name" class="form-control rounded-3" placeholder="Nom de la PME">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Poste occupé</label>
                            <input type="text" name="job_title" class="form-control rounded-3" placeholder="Ex: RAF / Gérant">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Besoin Principal constaté <span class="text-danger">*</span></label>
                            <select name="need_type" class="form-select rounded-3" required>
                                <option value="syscohada">Comptabilité Générale (SYSCOHADA)</option>
                                <option value="tresorerie">Suivi de Trésorerie & MM</option>
                                <option value="diagnostic">Diagnostic & Scoring Financier</option>
                                <option value="levee_fonds">Préparation Levée de Fonds</option>
                                <option value="ma">Conseils IA & FIRD</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold text-dark">Notes de prospection</label>
                            <textarea name="notes" class="form-control rounded-3" rows="3" placeholder="Historique d'appel, observations..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Qualifier Lead CRM &rarr;</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
