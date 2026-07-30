@extends('layouts.app')

@section('title', 'Portail Commercial & Marketing | SITIAME CAPITAL')
@section('page_title', 'Dashboard Commercial Soft-UI')

@push('styles')
<style>
    /* Exact Layout & Styling Replica of the User's Mockup */
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

    /* Top Navigation Header (Matching Mockup) */
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

    /* Mockup Status Badges */
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 16px;
        font-size: 0.78rem;
        font-weight: 700;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    .status-pill-green {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #ffffff;
    }
    .status-pill-purple {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        color: #ffffff;
    }
    .status-pill-blue {
        background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
        color: #ffffff;
    }

    /* Amber Event Card (Left Bottom - Matching Mockup) */
    .amber-event-card {
        background: #facc15;
        border-radius: 20px;
        padding: 20px;
        color: #1e293b;
    }

    /* Mini Grid Cards 2x2 (Middle Bottom - Matching Mockup) */
    .mini-user-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 16px;
        text-align: center;
        transition: all 0.2s ease;
    }
    .mini-user-card:hover {
        background: #ffffff;
        border-color: #cbd5e1;
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.04);
    }

    /* 3D Orb AI Copilot Card (Right Bottom - Matching Mockup) */
    .copilot-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        padding: 24px;
        text-align: center;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.03);
    }
    .copilot-orb-3d {
        width: 84px;
        height: 84px;
        margin: 0 auto 16px auto;
        background: radial-gradient(circle at 35% 35%, #93c5fd 0%, #3b82f6 50%, #1d4ed8 100%);
        border-radius: 50%;
        box-shadow: 0 14px 35px rgba(59, 130, 246, 0.4), inset -8px -8px 16px rgba(0, 0, 0, 0.25), inset 8px 8px 16px rgba(255, 255, 255, 0.7);
        animation: floatOrb 4s ease-in-out infinite alternate;
    }
    @keyframes floatOrb {
        0% { transform: translateY(0px) scale(1); }
        100% { transform: translateY(-8px) scale(1.04); }
    }

    .calendar-grid-cell {
        background: #f8fafc;
        border-radius: 12px;
        min-height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .calendar-grid-cell-active {
        background: #3b82f6;
        color: #ffffff;
        font-weight: 700;
        border-radius: 12px;
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
<div class="soft-dashboard-body">
    <div class="soft-dashboard-container">
        
        <!-- TOP HEADER BAR (Matching Mockup Navigation Bar) -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 mockup-header-bar">
            <!-- Left: Logo & Pill Navigation Tabs -->
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                    <i data-feather="grid" style="width:20px; height:20px;"></i>
                </div>
                <div class="d-flex align-items-center gap-1 bg-light rounded-pill p-1 border">
                    <button class="pill-tab-btn pill-tab-btn-active">
                        <i data-feather="layout" class="me-1" style="width:14px; height:14px;"></i> Tableau de bord
                    </button>
                    <button class="pill-tab-btn pill-tab-btn-inactive" data-bs-toggle="modal" data-bs-target="#addProspectModal">
                        <i data-feather="users" class="me-1" style="width:14px; height:14px;"></i> Leads CRM ({{ $totalProspects }})
                    </button>
                    <a href="{{ route('commercial.showcase') }}" class="pill-tab-btn pill-tab-btn-inactive text-decoration-none">
                        <i data-feather="book-open" class="me-1" style="width:14px; height:14px;"></i> Kit Marketing
                    </a>
                </div>
            </div>

            <!-- Right: Search, Avatar Group, and CTA Action Button -->
            <div class="d-flex align-items-center gap-3">
                <div class="position-relative d-none d-md-block">
                    <i data-feather="search" class="position-absolute text-muted" style="left:14px; top:11px; width:15px; height:15px;"></i>
                    <input type="text" class="form-control rounded-pill border-0 bg-light ps-5 pe-4" placeholder="Rechercher client, PME..." style="width:200px; font-size:0.85rem;">
                </div>
                
                <!-- Avatar Stack (Mockup Style) -->
                <div class="d-none d-lg-flex align-items-center me-1">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold border border-white" style="width:32px; height:32px; font-size:0.75rem; margin-right:-8px;">EK</div>
                    <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center fw-bold border border-white" style="width:32px; height:32px; font-size:0.75rem; margin-right:-8px;">JK</div>
                    <div class="bg-dark text-white rounded-circle d-flex align-items-center justify-content-center fw-bold border border-white" style="width:32px; height:32px; font-size:0.75rem;">+{{ $totalClients }}</div>
                </div>

                <a href="{{ route('commercial.import') }}" class="btn btn-outline-success rounded-pill px-3 py-2 fw-bold text-sm text-decoration-none">
                    <i data-feather="upload-cloud" class="me-1" style="width:14px; height:14px;"></i> Importer & Lire Fichier
                </a>
                <button type="button" class="btn btn-outline-primary rounded-pill px-3 py-2 fw-bold text-sm" data-bs-toggle="modal" data-bs-target="#addProspectModal">
                    + Lead CRM
                </button>
                <a href="{{ route('commercial.clients.create') }}" class="btn btn-primary rounded-pill px-4 py-2 fw-bold text-sm shadow-sm text-decoration-none">
                    <i data-feather="user-plus" class="me-1" style="width:14px; height:14px;"></i> + Nouveau Client
                </a>
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

        <!-- TOP MAIN SECTION: SCHEDULE & CLIENTS TIMELINE GRID (Exact Mockup Layout) -->
        <div class="mockup-card p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <div>
                    <h2 class="h4 fw-bold text-dark mb-1">Portefeuille Clients & Planning d'Essai 1 Mois</h2>
                    <p class="text-muted small mb-0">Suivi des activations d'essai gratuit SYSCOHADA et des conversions PME.</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="btn btn-sm btn-light rounded-pill border px-3 text-muted fw-semibold">
                        <i data-feather="calendar" class="me-1" style="width:14px; height:14px;"></i> {{ now()->format('d F Y') }}
                    </span>
                    <button class="btn btn-sm btn-light rounded-pill border px-3 text-muted fw-semibold">
                        <i data-feather="filter" class="me-1" style="width:14px; height:14px;"></i> Filtrer
                    </button>
                </div>
            </div>

            <!-- Calendar Days Header Row (Mockup Style) -->
            <div class="row g-2 text-center text-muted small fw-semibold mb-3">
                <div class="col-md-3 text-start ps-3">Client PME / Représentant</div>
                <div class="col"><div class="calendar-grid-cell">Lun 1</div></div>
                <div class="col"><div class="calendar-grid-cell">Mar 2</div></div>
                <div class="col"><div class="calendar-grid-cell">Mer 3</div></div>
                <div class="col"><div class="calendar-grid-cell">Jeu 4</div></div>
                <div class="col"><div class="calendar-grid-cell">Ven 5</div></div>
                <div class="col"><div class="calendar-grid-cell calendar-grid-cell-active">Sam 6</div></div>
                <div class="col"><div class="calendar-grid-cell">Dim 7</div></div>
                <div class="col"><div class="calendar-grid-cell">Lun 8</div></div>
            </div>

            <!-- Client Rows with Status Floating Pills (Mockup Style) -->
            <div class="d-flex flex-column gap-3">
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
                    <div class="row g-2 align-items-center p-2 rounded-4 hover-bg-light border-bottom border-light">
                        <div class="col-md-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-primary text-white rounded-circle fw-bold d-flex align-items-center justify-content-center" style="width:40px; height:40px; font-size:0.85rem;">
                                    {{ strtoupper(substr($client->name ?? 'PME', 0, 2)) }}
                                </div>
                                <div>
                                    <div class="fw-bold text-dark mb-0">{{ $client->name ?? $client->email ?? 'Client PME' }}</div>
                                    <div class="text-muted small" style="font-size:0.78rem;">{{ $client->company_name ?? 'PME Client' }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="d-flex align-items-center justify-content-between">
                                @if($isTrialActive)
                                    <span class="status-pill status-pill-green">
                                        <i data-feather="check-circle" style="width:14px; height:14px;"></i> Essai Gratuit Actif ({{ $daysLeft }} jours restants)
                                    </span>
                                @else
                                    <span class="status-pill status-pill-purple">
                                        <i data-feather="alert-circle" style="width:14px; height:14px;"></i> Essai Expiré / À Relancer
                                    </span>
                                @endif

                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-light rounded-pill border px-3" data-bs-toggle="modal" data-bs-target="#editClientModal{{ $client->id }}">
                                        Modifier
                                    </button>
                                    <form action="{{ route('commercial.clients.destroy', $client->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce client ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light text-danger rounded-pill border px-2">
                                            <i data-feather="trash-2" style="width:14px; height:14px;"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Client Modal -->
                    <div class="modal fade" id="editClientModal{{ $client->id }}" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
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
                                                <input type="text" name="name" class="form-control rounded-3" value="{{ old('name', $client->name) }}" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold">Adresse Email</label>
                                                <input type="email" name="email" class="form-control rounded-3" value="{{ old('email', $client->email) }}" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold">Téléphone</label>
                                                <input type="text" name="phone" class="form-control rounded-3" value="{{ old('phone', $client->phone) }}">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold">Raison Sociale / Entreprise</label>
                                                <input type="text" name="company_name" class="form-control rounded-3" value="{{ old('company_name', $client->company_name) }}">
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
                    <div class="text-center text-muted py-4">
                        <i data-feather="users" class="mb-2" style="width:36px; height:36px; opacity:0.3;"></i>
                        <div>Aucun client parrainé enregistré dans votre portefeuille.</div>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ============================================================ --}}
        {{-- SECTION : PORTEFEUILLE KPIs + STOCKE DE RÉTENTION             --}}
        {{-- ============================================================ --}}
        <div class="mockup-card p-4 mb-4">

            {{-- En-tête --}}
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <div>
                    <div class="retention-section-header">📊 Portefeuille &amp; Rétention Clients</div>
                    <h2 class="h5 fw-bold text-dark mb-0">Statistiques d'acquisition &amp; de conversion</h2>
                    <p class="text-muted small mb-0">Clients ajoutés depuis votre inscription &mdash; suivi de la période d'essai 1 mois</p>
                </div>
                @if($portfolioTrialExpired > 0)
                    @php
                        $badgeBg = $conversionRate >= 60
                            ? 'linear-gradient(135deg,#10b981,#059669)'
                            : ($conversionRate >= 30 ? 'linear-gradient(135deg,#f59e0b,#d97706)' : 'linear-gradient(135deg,#ef4444,#dc2626)');
                    @endphp
                    <span class="badge rounded-pill px-3 py-2 fw-bold"
                          style="background:{{ $badgeBg }};color:#fff;font-size:.85rem;">
                        {{ $conversionRate }}% de conversion
                    </span>
                @endif
            </div>

            {{-- 4 KPI Cards --}}
            <div class="row g-3 mb-4">
                {{-- Total portefeuille --}}
                <div class="col-6 col-md-3">
                    <div class="portfolio-kpi-card kpi-total">
                        <div class="kpi-label">Portefeuille</div>
                        <div class="kpi-number text-primary">{{ $totalClients }}</div>
                        <div class="kpi-sub">clients ajoutés au total</div>
                    </div>
                </div>
                {{-- En essai --}}
                <div class="col-6 col-md-3">
                    <div class="portfolio-kpi-card kpi-trial">
                        <div class="kpi-label">&#9203; En Essai</div>
                        <div class="kpi-number" style="color:#f59e0b;">{{ $activeTrials }}</div>
                        <div class="kpi-sub">période d'essai en cours</div>
                    </div>
                </div>
                {{-- Convertis --}}
                <div class="col-6 col-md-3">
                    <div class="portfolio-kpi-card kpi-converted">
                        <div class="kpi-label">&#10003; Abonnés</div>
                        <div class="kpi-number text-success">{{ $portfolioConverted }}</div>
                        <div class="kpi-sub">ont souscrit après l'essai</div>
                    </div>
                </div>
                {{-- Partis --}}
                <div class="col-6 col-md-3">
                    <div class="portfolio-kpi-card kpi-churned">
                        <div class="kpi-label">&#10007; Partis</div>
                        <div class="kpi-number text-danger">{{ $portfolioChurned }}</div>
                        <div class="kpi-sub">non convertis après essai</div>
                    </div>
                </div>
            </div>

            {{-- Barre de rétention visuelle --}}
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
                <div class="text-center py-3 text-muted small">
                    <i data-feather="clock" class="me-1" style="width:14px;height:14px;"></i>
                    Aucune période d'essai encore terminée &mdash; les stats apparaîtront après 1 mois.
                </div>
            @endif

        </div>

        <!-- BOTTOM ROW (EXACT MOCKUP 3 COLUMNS: FUTURE EVENTS | ONBOARDING LEADS | WELCOME AI COPILOT) -->
        <div class="row g-4">

            
            <!-- COLUMN 1 (LEFT ~33%): FUTURE EVENTS / ÉVÉNEMENTS (Matching Mockup Left Card) -->
            <div class="col-lg-4">
                <div class="mockup-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h5 fw-bold text-dark mb-0">Événements & Relances</h3>
                        <a href="{{ route('commercial.showcase') }}" class="text-decoration-none small text-muted fw-semibold">Voir tout &rarr;</a>
                    </div>

                    <!-- Highlighted Yellow/Amber Top Event Card (Mockup Style) -->
                    <div class="amber-event-card mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold fs-6">Webinaire Sitiame Capital</span>
                            <span class="badge bg-dark text-white rounded-pill px-2 py-1 small">Dans 15 min</span>
                        </div>
                        <p class="small text-dark mb-3">Présentation du logiciel comptable SYSCOHADA & Trésorerie Mobile Money.</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="badge bg-white text-dark rounded-pill px-3 py-2 fw-semibold">
                                <i data-feather="clock" class="me-1" style="width:12px; height:12px;"></i> 14:00 - 15:30
                            </span>
                            <span class="badge bg-white text-dark rounded-pill px-3 py-2 fw-semibold">
                                <i data-feather="calendar" class="me-1" style="width:12px; height:12px;"></i> 15 Déc.
                            </span>
                        </div>
                    </div>

                    <!-- Second Event Item -->
                    <div class="p-3 bg-light rounded-4 border mb-2 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold text-dark small">Relances Essais Expirés</div>
                            <div class="text-muted small" style="font-size:0.75rem;">Session de conversion téléphonique</div>
                        </div>
                        <span class="badge bg-white text-dark border rounded-pill">09:00 - 12:00</span>
                    </div>

                    <!-- Third Event Item -->
                    <div class="p-3 bg-light rounded-4 border d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold text-dark small">Atelier Sitiame Finance Club</div>
                            <div class="text-muted small" style="font-size:0.75rem;">Rencontre Dirigeants PME</div>
                        </div>
                        <span class="badge bg-white text-dark border rounded-pill">Jeudi</span>
                    </div>
                </div>
            </div>

            <!-- COLUMN 2 (MIDDLE ~33%): ONBOARDING / LEADS CRM 2x2 MINI GRID (Matching Mockup Middle Card) -->
            <div class="col-lg-4">
                <div class="mockup-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h5 fw-bold text-dark mb-0">Leads CRM Inbound</h3>
                        <button type="button" class="btn btn-link text-decoration-none p-0 border-0 small text-muted fw-semibold" data-bs-toggle="modal" data-bs-target="#addProspectModal">
                            + Lead
                        </button>
                    </div>

                    <!-- 2x2 Grid Layout (Mockup Style) -->
                    <div class="row g-3">
                        @forelse($prospects->take(4) as $prospect)
                            <div class="col-6">
                                <div class="mini-user-card">
                                    <div class="bg-primary text-white rounded-circle fw-bold d-flex align-items-center justify-content-center mx-auto mb-2" style="width:44px; height:44px; font-size:0.9rem;">
                                        {{ strtoupper(substr($prospect->name ?? 'PR', 0, 2)) }}
                                    </div>
                                    <div class="fw-bold text-dark text-truncate small mb-0">{{ $prospect->name ?? 'Prospect' }}</div>
                                    <div class="text-muted small text-truncate mb-2" style="font-size:0.75rem;">{{ $prospect->company_name ?? 'PME' }}</div>
                                    <span class="badge {{ $prospect->status_badge_class }} rounded-pill px-2 py-1 small">
                                        {{ $prospect->status_label }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-center text-muted py-4">
                                <i data-feather="target" class="mb-2" style="width:32px; height:32px; opacity:0.3;"></i>
                                <div class="small">Aucun prospect disponible.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- COLUMN 3 (RIGHT ~33%): WELCOME AI ASSISTANT CARD (Exact Mockup Right Card with Glowing 3D Orb) -->
            <div class="col-lg-4">
                <div class="copilot-card h-100 d-flex flex-column justify-content-between">
                    <div>
                        <!-- Glowing 3D Orb (Mockup Sphere Style) -->
                        <div class="copilot-orb-3d"></div>

                        <!-- Centered Greeting Text (Mockup Style) -->
                        <h3 class="fw-bold text-dark mb-1">Bonjour, {{ explode(' ', auth()->user()?->name ?? 'Utilisateur')[0] }}</h3>
                        <p class="text-muted small mb-4">Que puis-je analyser ou automatiser pour vous aujourd'hui ?</p>

                        <!-- Quick Action Buttons Row (Mockup Style) -->
                        <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
                            <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 text-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#addProspectModal">
                                <i data-feather="user-plus" class="me-1" style="width:12px; height:12px;"></i> + Lead CRM
                            </button>
                            <a href="{{ route('commercial.showcase') }}" class="btn btn-sm btn-light border rounded-pill px-3 text-sm fw-semibold">
                                <i data-feather="file-text" class="me-1" style="width:12px; height:12px;"></i> Guides Inbound
                            </a>
                        </div>
                    </div>

                    <!-- Bottom Search Input Box ("Ask me anything" - Mockup Style) -->
                    <div class="bg-light p-2 rounded-4 border">
                        <input type="text" class="form-control border-0 bg-transparent text-sm text-center mb-2" placeholder="Posez votre question à l'IA...">
                        <div class="d-flex justify-content-between align-items-center px-2">
                            <button type="button" class="btn btn-sm btn-white bg-white border rounded-pill px-2 py-1 text-muted small">
                                <i data-feather="paperclip" style="width:12px; height:12px;"></i> Joindre
                            </button>
                            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-bold">
                                Créer &rarr;
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add Prospect -->
<div class="modal fade" id="addProspectModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
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
                            <label class="form-label small fw-semibold text-dark">Raison Sociale / PME</label>
                            <input type="text" name="company_name" class="form-control rounded-3" placeholder="Ex: Ivoire Agro SARL">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Fonction / Poste</label>
                            <input type="text" name="job_title" class="form-control rounded-3" placeholder="Ex: CEO / RAF">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Besoin Principal Identifié <span class="text-danger">*</span></label>
                            <select name="need_type" class="form-select rounded-3" required>
                                <option value="syscohada">Comptabilité SYSCOHADA</option>
                                <option value="tresorerie">Gestion Trésorerie & Mobile Money</option>
                                <option value="diagnostic">Diagnostic Financier (FIRD)</option>
                                <option value="levee_fonds">Préparation Levée de Fonds</option>
                                <option value="ma">Restructuration & M&A</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Enregistrer le Prospect &check;</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Add Client (2-Step Wizard) - FULLSCREEN -->
<div class="modal fade" id="addClientModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content border-0" style="background: linear-gradient(135deg, #f0f4ff 0%, #e8f0fe 50%, #f0f9ff 100%);">
            <form action="{{ route('commercial.clients.store') }}" method="POST" enctype="multipart/form-data" novalidate>
                @csrf

                {{-- Barre de progression pleine largeur --}}
                <div class="progress rounded-0" style="height: 5px;">
                    <div class="progress-bar" role="progressbar" id="wizardProgressBar"
                         style="width: 50%; background: linear-gradient(90deg, #3b82f6, #6366f1);"></div>
                </div>

                <div class="modal-body d-flex align-items-center justify-content-center p-4" style="min-height: calc(100vh - 5px);">
                    <div class="w-100" style="max-width: 680px;">

                        {{-- En-tête centré --}}
                        <div class="text-center mb-4">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                                 style="width:64px;height:64px;background:linear-gradient(135deg,#3b82f6,#6366f1);">
                                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none"
                                     viewBox="0 0 24 24" stroke="white" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                                </svg>
                            </div>
                            <span class="badge rounded-pill px-3 py-2 mb-2 fw-semibold d-block mx-auto" id="stepBadgeLabel"
                                  style="background:linear-gradient(135deg,#3b82f6,#6366f1);color:#fff;width:fit-content;font-size:.8rem;">
                                Étape 1 / 2
                            </span>
                            <h2 class="fw-bold text-dark mb-1" id="stepTitleLabel" style="font-size:1.6rem;">
                                Compte Client &amp; Crédentiels
                            </h2>
                            <p class="text-muted small mb-0">Créez le compte d'accès pour votre nouveau client PME</p>
                        </div>

                        {{-- Carte formulaire --}}
                        <div class="card border-0 shadow-lg rounded-4 overflow-hidden">

                            {{-- Step 1 --}}
                            <div id="wizardStep1" class="card-body p-4 p-md-5">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-dark">
                                            <i class="me-1" style="font-style:normal;">👤</i> Nom complet du dirigeant
                                        </label>
                                        <input type="text" name="name" class="form-control form-control-lg rounded-3 border-0 bg-light"
                                               placeholder="Ex : Jean Kouassi">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-dark">
                                            <i class="me-1" style="font-style:normal;">✉️</i> Adresse Email (Identifiant)
                                        </label>
                                        <input type="email" name="email" class="form-control form-control-lg rounded-3 border-0 bg-light"
                                               placeholder="dirigeant@entreprise.ci">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-dark">
                                            <i class="me-1" style="font-style:normal;">📱</i> Téléphone
                                        </label>
                                        <input type="text" name="phone" class="form-control form-control-lg rounded-3 border-0 bg-light"
                                               placeholder="+225 07 00 00 00 00">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-dark">
                                            <i class="me-1" style="font-style:normal;">🔑</i> Mot de passe temporaire
                                        </label>
                                        <input type="password" name="password" class="form-control form-control-lg rounded-3 border-0 bg-light"
                                               placeholder="8 caractères minimum" minlength="8">
                                    </div>
                                </div>
                            </div>

                            {{-- Step 2 --}}
                            <div id="wizardStep2" class="card-body p-4 p-md-5" style="display:none;">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-dark">
                                            <i class="me-1" style="font-style:normal;">🏢</i> Raison Sociale / Entreprise
                                        </label>
                                        <input type="text" name="company_name" class="form-control form-control-lg rounded-3 border-0 bg-light"
                                               placeholder="Ex : Ivoire Agro SARL">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-dark">
                                            <i class="me-1" style="font-style:normal;">🏷️</i> Sigle / Nom commercial
                                        </label>
                                        <input type="text" name="company_sigle" class="form-control form-control-lg rounded-3 border-0 bg-light"
                                               placeholder="Ex : IAGRO">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-dark">
                                            <i class="me-1" style="font-style:normal;">🔢</i> NIF / Matricule Fiscal
                                        </label>
                                        <input type="text" name="company_tax_id" class="form-control form-control-lg rounded-3 border-0 bg-light"
                                               placeholder="Numéro d'identification fiscale">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-dark">
                                            <i class="me-1" style="font-style:normal;">🌿</i> Secteur d'activité
                                        </label>
                                        <input type="text" name="sector" class="form-control form-control-lg rounded-3 border-0 bg-light"
                                               placeholder="Ex : Agriculture, BTP, Commerce...">
                                    </div>
                                </div>
                            </div>

                            {{-- Footer actions --}}
                            <div class="card-footer border-0 bg-white px-4 px-md-5 py-4 d-flex justify-content-between align-items-center">
                                <a href="{{ route('commercial.dashboard') }}"
                                   class="btn btn-light rounded-pill px-4 fw-semibold text-muted text-decoration-none">
                                    ← Annuler
                                </a>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-light rounded-pill px-4 fw-semibold"
                                            id="wizardPrevBtn" style="display:none;" onclick="goToStep(1)">
                                        ← Précédent
                                    </button>
                                    <button type="button" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm"
                                            id="wizardNextBtn" onclick="goToStep(2)">
                                        Suivant : Entreprise →
                                    </button>
                                    <button type="submit" class="btn btn-success rounded-pill px-5 fw-bold shadow-sm"
                                            id="wizardSubmitBtn" style="display:none;">
                                        ✓ Créer le compte client
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Info essai --}}
                        <p class="text-center text-muted small mt-3">
                            🎁 Le client bénéficiera automatiquement d'un <strong>essai gratuit de 30 jours</strong>
                        </p>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>


@push('scripts')
<script>
    function goToStep(step) {
        if (step === 2) {
            document.getElementById('wizardStep1').style.display = 'none';
            document.getElementById('wizardStep2').style.display = 'block';
            document.getElementById('stepBadgeLabel').innerText = 'Étape 2/2';
            document.getElementById('stepTitleLabel').innerText = 'ENTREPRISE & KYC';
            document.getElementById('wizardProgressBar').style.width = '100%';
            document.getElementById('wizardPrevBtn').style.display = 'inline-block';
            document.getElementById('wizardNextBtn').style.display = 'none';
            document.getElementById('wizardSubmitBtn').style.display = 'inline-block';
        } else {
            document.getElementById('wizardStep1').style.display = 'block';
            document.getElementById('wizardStep2').style.display = 'none';
            document.getElementById('stepBadgeLabel').innerText = 'Étape 1/2';
            document.getElementById('stepTitleLabel').innerText = 'COMPTE';
            document.getElementById('wizardProgressBar').style.width = '50%';
            document.getElementById('wizardPrevBtn').style.display = 'none';
            document.getElementById('wizardNextBtn').style.display = 'inline-block';
            document.getElementById('wizardSubmitBtn').style.display = 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const urlParams = new URLSearchParams(window.location.search);
        const action = urlParams.get('action');
        // action=add-client redirige maintenant vers la page dédiée
        if (action === 'add-client') {
            window.location.replace('{{ route("commercial.clients.create") }}');
        } else if (action === 'add-prospect') {
            const modal = new bootstrap.Modal(document.getElementById('addProspectModal'));
            modal.show();
        }
    });
</script>


@endpush
@endsection
