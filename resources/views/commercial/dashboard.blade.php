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

                <button type="button" class="btn btn-outline-primary rounded-pill px-3 py-2 fw-bold text-sm" data-bs-toggle="modal" data-bs-target="#addProspectModal">
                    + Lead CRM
                </button>
                <button type="button" class="btn btn-primary rounded-pill px-4 py-2 fw-bold text-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#addClientModal">
                    <i data-feather="user-plus" class="me-1" style="width:14px; height:14px;"></i> + Nouveau Client
                </button>
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
                    <button class="btn btn-sm btn-light rounded-pill border px-3 text-muted fw-semibold" data-bs-toggle="modal" data-bs-target="#addClientModal">
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
                        $isTrialActive = $client->is_premium && $client->premium_ends_at && $client->premium_ends_at->isFuture();
                        $daysLeft = $isTrialActive ? now()->diffInDays($client->premium_ends_at) : 0;
                    @endphp
                    <div class="row g-2 align-items-center p-2 rounded-4 hover-bg-light border-bottom border-light">
                        <div class="col-md-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-primary text-white rounded-circle fw-bold d-flex align-items-center justify-content-center" style="width:40px; height:40px; font-size:0.85rem;">
                                    {{ strtoupper(substr($client->name, 0, 2)) }}
                                </div>
                                <div>
                                    <div class="fw-bold text-dark mb-0">{{ $client->name }}</div>
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
                                        {{ strtoupper(substr($prospect->name, 0, 2)) }}
                                    </div>
                                    <div class="fw-bold text-dark text-truncate small mb-0">{{ $prospect->name }}</div>
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
                        <h3 class="fw-bold text-dark mb-1">Bonjour, {{ explode(' ', auth()->user()->name)[0] }}</h3>
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

<!-- Modal Add Client (2-Step Wizard) -->
<div class="modal fade" id="addClientModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="{{ route('commercial.clients.store') }}" method="POST" enctype="multipart/form-data" novalidate>
                @csrf
                <div class="modal-header border-0 bg-primary text-white p-4">
                    <div>
                        <span class="badge bg-white text-primary rounded-pill px-3 py-1 mb-2 fw-semibold" id="stepBadgeLabel">Étape 1/2</span>
                        <h4 class="modal-title fw-bold text-white mb-0" id="stepTitleLabel">COMPTE CLIENT & CRÉDENTIELS</h4>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="progress rounded-0" style="height: 4px;">
                    <div class="progress-bar bg-success" role="progressbar" id="wizardProgressBar" style="width: 50%;"></div>
                </div>

                <div class="modal-body p-4" style="max-height: 65vh; overflow-y: auto;">
                    <div id="wizardStep1">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark">Nom complet du dirigeant</label>
                                <input type="text" name="name" class="form-control rounded-3">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark">Adresse Email (Identifiant)</label>
                                <input type="email" name="email" class="form-control rounded-3">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark">Téléphone</label>
                                <input type="text" name="phone" class="form-control rounded-3">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark">Mot de passe temporaire</label>
                                <input type="password" name="password" class="form-control rounded-3" minlength="8">
                            </div>
                        </div>
                    </div>

                    <div id="wizardStep2" style="display: none;">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark">Raison Sociale / Entreprise</label>
                                <input type="text" name="company_name" class="form-control rounded-3">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark">Sigle / Nom commercial</label>
                                <input type="text" name="company_sigle" class="form-control rounded-3">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark">NIF / Matricule Fiscal</label>
                                <input type="text" name="company_tax_id" class="form-control rounded-3">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark">Secteur d'activité</label>
                                <input type="text" name="sector" class="form-control rounded-3">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-3" id="wizardPrevBtn" style="display:none;" onclick="goToStep(1)">Précédent</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" id="wizardNextBtn" onclick="goToStep(2)">Suivant : Entreprise &rarr;</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" id="wizardSubmitBtn" style="display:none;">Créer le compte client &check;</button>
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
    document.addEventListener('DOMContentLoaded', function () {
        const urlParams = new URLSearchParams(window.location.search);
        const action = urlParams.get('action');
        if (action === 'add-client') {
            const modal = new bootstrap.Modal(document.getElementById('addClientModal'));
            modal.show();
        } else if (action === 'add-prospect') {
            const modal = new bootstrap.Modal(document.getElementById('addProspectModal'));
            modal.show();
        }
    });
</script>
@endpush
@endsection
