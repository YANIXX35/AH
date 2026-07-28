@extends('layouts.app')

@section('title', 'Portail Commercial & Marketing | SITIAME CAPITAL')
@section('page_title', 'Dashboard Commercial Soft-UI')

@push('styles')
<style>
    /* Soft-UI & Glassmorphic Design System (Matching Mockup) */
    .soft-bg {
        background-color: #f1f5f9;
        min-height: 100vh;
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        color: #1e293b;
    }
    
    .soft-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.03), 0 4px 12px rgba(15, 23, 42, 0.02);
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .soft-card:hover {
        box-shadow: 0 16px 36px -4px rgba(15, 23, 42, 0.06);
        border-color: #cbd5e1;
    }

    .pill-header-bar {
        background: #ffffff;
        border-radius: 9999px;
        padding: 8px 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
    }

    .soft-pill-btn {
        border-radius: 9999px;
        font-weight: 600;
        font-size: 0.85rem;
        padding: 8px 20px;
        border: none;
        transition: all 0.2s ease;
    }
    .soft-pill-btn-active {
        background: #0f172a;
        color: #ffffff !important;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.25);
    }
    .soft-pill-btn-inactive {
        background: transparent;
        color: #64748b;
    }
    .soft-pill-btn-inactive:hover {
        background: #f8fafc;
        color: #0f172a;
    }

    .pill-badge {
        display: inline-flex;
        align-items: center;
        padding: 5px 14px;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.3px;
    }
    .pill-badge-emerald { background: #d1fae5; color: #047857; }
    .pill-badge-purple { background: #f3e8ff; color: #6b21a8; }
    .pill-badge-blue { background: #dbeafe; color: #1d4ed8; }
    .pill-badge-amber { background: #fef3c7; color: #b45309; }
    .pill-badge-rose { background: #ffe4e6; color: #be123c; }

    /* Copilot 3D Orb Card */
    .ai-copilot-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        position: relative;
        overflow: hidden;
    }
    .copilot-orb {
        width: 90px;
        height: 90px;
        background: radial-gradient(circle at 30% 30%, #60a5fa, #2563eb 60%, #1e40af);
        border-radius: 50%;
        box-shadow: 0 12px 32px rgba(37, 99, 235, 0.35), inset -6px -6px 12px rgba(0, 0, 0, 0.2), inset 6px 6px 12px rgba(255, 255, 255, 0.6);
        animation: floatOrb 4s ease-in-out infinite alternate;
    }
    @keyframes floatOrb {
        0% { transform: translateY(0px) scale(1); }
        100% { transform: translateY(-8px) scale(1.04); }
    }

    .schedule-grid-header {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        border-radius: 20px 20px 0 0;
    }
</style>
@endpush

@section('content')
<div class="soft-bg pb-5">
    <div class="container-fluid px-4 py-3">
        
        <!-- Top Floating Header Bar (Mockup Navigation Style) -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 pill-header-bar">
            <div class="d-flex align-items-center gap-2">
                <div class="bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width:38px; height:38px;">
                    <i data-feather="grid" style="width:18px; height:18px;"></i>
                </div>
                <div class="nav nav-pills border-0" role="tablist">
                    <button class="soft-pill-btn soft-pill-btn-active me-1" id="tab-btn-dashboard" data-bs-toggle="pill" data-bs-target="#panel-dashboard">
                        <i data-feather="layout" class="me-1" style="width:14px; height:14px;"></i> Vue Générale
                    </button>
                    <button class="soft-pill-btn soft-pill-btn-inactive me-1" id="tab-btn-prospects" data-bs-toggle="pill" data-bs-target="#panel-prospects">
                        <i data-feather="users" class="me-1" style="width:14px; height:14px;"></i> Leads CRM ({{ $totalProspects }})
                    </button>
                    <button class="soft-pill-btn soft-pill-btn-inactive me-1" id="tab-btn-marketing" data-bs-toggle="pill" data-bs-target="#panel-marketing">
                        <i data-feather="target" class="me-1" style="width:14px; height:14px;"></i> Kit Marketing & Inbound
                    </button>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="position-relative d-none d-md-block">
                    <i data-feather="search" class="position-absolute text-muted" style="left:14px; top:10px; width:16px; height:16px;"></i>
                    <input type="text" class="form-control rounded-pill border-0 bg-light ps-5 pe-4 text-sm" placeholder="Rechercher prospect, PME, client..." style="width:240px; font-size:0.85rem;">
                </div>
                <button type="button" class="btn btn-outline-primary rounded-pill px-3 py-2 fw-bold text-sm" data-bs-toggle="modal" data-bs-target="#addProspectModal">
                    + Lead CRM
                </button>
                <button type="button" class="btn btn-primary rounded-pill px-4 py-2 fw-bold text-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#addClientModal">
                    <i data-feather="user-plus" class="me-1" style="width:14px; height:14px;"></i> + Client PME (2 Étapes)
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

        <!-- Tab Panels -->
        <div class="tab-content">
            
            <!-- PANEL 1: MAIN DASHBOARD -->
            <div class="tab-pane fade show active" id="panel-dashboard" role="tabpanel">
                
                <!-- Metrics Row (Soft White Floating Cards) -->
                <div class="row g-4 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="soft-card p-4 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing:0.5px;">PORTEFEUILLE CLIENTS</span>
                                <span class="pill-badge pill-badge-blue">PME Inscrites</span>
                            </div>
                            <div class="display-6 fw-bold text-dark mb-1">{{ number_format($totalClients) }}</div>
                            <div class="text-muted small">Entreprises gérées dans votre espace.</div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="soft-card p-4 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing:0.5px;">PIPELINE PROSPECTS</span>
                                <span class="pill-badge pill-badge-amber">Inbound Leads</span>
                            </div>
                            <div class="display-6 fw-bold text-dark mb-1">{{ number_format($totalProspects) }}</div>
                            <div class="text-muted small"><strong class="text-warning">{{ $newProspects }}</strong> nouveaux leads à contacter.</div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="soft-card p-4 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing:0.5px;">ESSAIS 1 MOIS ACTIFS</span>
                                <span class="pill-badge pill-badge-emerald">Free Trial</span>
                            </div>
                            <div class="display-6 fw-bold text-emerald mb-1 text-success">{{ number_format($activeTrials) }}</div>
                            <div class="text-muted small">Clients en période d'essai 30 jours.</div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="soft-card p-4 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing:0.5px;">CONVERSIONS PAYANTES</span>
                                <span class="pill-badge pill-badge-purple">Abonnés</span>
                            </div>
                            <div class="display-6 fw-bold text-dark mb-1">{{ number_format($convertedProspects) }}</div>
                            <div class="text-muted small">Leads devenus abonnés payants.</div>
                        </div>
                    </div>
                </div>

                <!-- Central Content Grid (2 Columns: Main Schedule & AI Copilot Widget) -->
                <div class="row g-4 mb-4">
                    
                    <!-- Left Column: Main Clients Grid & Schedule -->
                    <div class="col-lg-8">
                        <div class="soft-card p-4 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div>
                                    <h3 class="h5 fw-bold text-dark mb-1">Portefeuille Clients PME</h3>
                                    <p class="text-muted small mb-0">Entreprises parrainées et suivi d'essai gratuit 1 mois.</p>
                                </div>
                                <button type="button" class="btn btn-sm btn-light rounded-pill px-3 fw-semibold border" data-bs-toggle="modal" data-bs-target="#addClientModal">
                                    + Nouveau Client
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 border-0">
                                    <thead>
                                        <tr class="text-muted text-uppercase small" style="font-size:0.75rem;">
                                            <th class="border-0">Client & Dirigeant</th>
                                            <th class="border-0">Entreprise</th>
                                            <th class="border-0">Inscription</th>
                                            <th class="border-0">Statut d'Essai</th>
                                            <th class="border-0 text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($clients as $client)
                                            @php
                                                $isTrialActive = $client->is_premium && $client->premium_ends_at && $client->premium_ends_at->isFuture();
                                                $daysLeft = $isTrialActive ? now()->diffInDays($client->premium_ends_at) : 0;
                                            @endphp
                                            <tr>
                                                <td class="py-3">
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div class="bg-light rounded-circle text-primary fw-bold d-flex align-items-center justify-content-center border" style="width:38px; height:38px; font-size:0.85rem;">
                                                            {{ strtoupper(substr($client->name, 0, 2)) }}
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold text-dark">{{ $client->name }}</div>
                                                            <div class="text-muted small">{{ $client->email }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="pill-badge pill-badge-blue">{{ $client->company_name ?? 'PME Non Renseignée' }}</span>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold text-dark">{{ $client->created_at->format('d/m/Y') }}</div>
                                                </td>
                                                <td>
                                                    @if($isTrialActive)
                                                        <span class="pill-badge pill-badge-emerald">
                                                            Essai Actif ({{ $daysLeft }} j)
                                                        </span>
                                                    @else
                                                        <span class="pill-badge pill-badge-rose">
                                                            Essai Expiré
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-sm btn-light rounded-pill border px-3" data-bs-toggle="modal" data-bs-target="#editClientModal{{ $client->id }}">
                                                        Modifier
                                                    </button>
                                                    <form action="{{ route('commercial.clients.destroy', $client->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce compte client ?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-link text-danger border-0 p-0 ms-2">
                                                            <i data-feather="trash-2" style="width:15px; height:15px;"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>

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
                                                                    <div class="col-md-6">
                                                                        <label class="form-label small fw-semibold">Secteur d'activité</label>
                                                                        <input type="text" name="sector" class="form-control rounded-3" value="{{ old('sector', $client->sector) }}">
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label class="form-label small fw-semibold">Ville</label>
                                                                        <input type="text" name="city" class="form-control rounded-3" value="{{ old('city', $client->city) }}">
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
                                                <td colspan="5" class="text-center text-muted py-5">
                                                    <i data-feather="users" class="mb-2" style="width:36px; height:36px; opacity:0.3;"></i>
                                                    <div>Aucun client parrainé enregistré.</div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: AI Assistant / Copilot Orb Widget (Mockup Style) -->
                    <div class="col-lg-4">
                        <div class="ai-copilot-card p-4 h-100 d-flex flex-column justify-content-between shadow-sm">
                            <div>
                                <div class="d-flex justify-content-center mb-4 pt-2">
                                    <div class="copilot-orb d-flex align-items-center justify-content-center text-white">
                                        <i data-feather="cpu" style="width:36px; height:36px;"></i>
                                    </div>
                                </div>
                                <div class="text-center mb-4">
                                    <h4 class="fw-bold text-dark mb-1">Assistant IA Commercial 🚀</h4>
                                    <p class="text-muted small">Que souhaitez-vous analyser ou accomplir aujourd'hui ?</p>
                                </div>

                                <div class="d-flex flex-wrap gap-2 justify-content-center mb-4">
                                    <button type="button" class="btn btn-sm btn-light border rounded-pill text-sm px-3" data-bs-toggle="modal" data-bs-target="#addProspectModal">
                                        <i data-feather="user-plus" class="me-1" style="width:12px; height:12px;"></i> + Lead CRM
                                    </button>
                                    <a href="{{ route('commercial.showcase') }}" class="btn btn-sm btn-light border rounded-pill text-sm px-3">
                                        <i data-feather="book" class="me-1" style="width:12px; height:12px;"></i> Guides Inbound
                                    </a>
                                    <button type="button" class="btn btn-sm btn-light border rounded-pill text-sm px-3" data-bs-toggle="modal" data-bs-target="#addClientModal">
                                        <i data-feather="check-circle" class="me-1" style="width:12px; height:12px;"></i> Inscrire PME
                                    </button>
                                </div>
                            </div>

                            <div class="position-relative mt-auto">
                                <input type="text" class="form-control rounded-pill border bg-white ps-4 pe-5 py-2 text-sm" placeholder="Posez une question à l'IA...">
                                <button type="button" class="btn btn-primary rounded-circle position-absolute p-0 d-flex align-items-center justify-content-center" style="right:6px; top:4px; width:32px; height:32px;">
                                    <i data-feather="arrow-right" style="width:14px; height:14px;"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PANEL 2: PROSPECTS CRM -->
            <div class="tab-pane fade" id="panel-prospects" role="tabpanel">
                <div class="soft-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h3 class="h5 fw-bold text-dark mb-1">Pipeline des Prospects Inbound CRM</h3>
                            <p class="text-muted small mb-0">Gestion et qualification des opportunités d'affaires Sitiame Capital.</p>
                        </div>
                        <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#addProspectModal">
                            + Ajouter un Prospect
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle border-0">
                            <thead>
                                <tr class="text-muted text-uppercase small" style="font-size:0.75rem;">
                                    <th class="border-0">Prospect</th>
                                    <th class="border-0">Entreprise & Poste</th>
                                    <th class="border-0">Besoin Financier</th>
                                    <th class="border-0">Statut CRM</th>
                                    <th class="border-0">Date</th>
                                    <th class="border-0 text-end">Changer Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($prospects as $prospect)
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $prospect->name }}</div>
                                            <div class="text-muted small">{{ $prospect->email }}</div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark">{{ $prospect->company_name ?? 'PME non renseignée' }}</div>
                                            <div class="text-muted small">{{ $prospect->job_title ?? 'Dirigeant / RAF' }}</div>
                                        </td>
                                        <td>
                                            <span class="pill-badge pill-badge-blue">{{ $prospect->need_label }}</span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $prospect->status_badge_class }} p-2 rounded-pill">
                                                {{ $prospect->status_label }}
                                            </span>
                                        </td>
                                        <td>{{ $prospect->created_at->format('d/m/Y') }}</td>
                                        <td class="text-end">
                                            <form action="{{ route('commercial.prospects.updateStatus', $prospect->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('PUT')
                                                <select name="status" class="form-select form-select-sm d-inline-block w-auto rounded-pill me-1" onchange="this.form.submit()">
                                                    <option value="nouveau" {{ $prospect->status === 'nouveau' ? 'selected' : '' }}>Nouveau</option>
                                                    <option value="contacte" {{ $prospect->status === 'contacte' ? 'selected' : '' }}>Contacté</option>
                                                    <option value="qualifie" {{ $prospect->status === 'qualifie' ? 'selected' : '' }}>Qualifié</option>
                                                    <option value="client" {{ $prospect->status === 'client' ? 'selected' : '' }}>Converti Client</option>
                                                    <option value="sans_suite" {{ $prospect->status === 'sans_suite' ? 'selected' : '' }}>Sans suite</option>
                                                </select>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-5">
                                            <i data-feather="target" class="mb-2" style="width:36px; height:36px; opacity:0.3;"></i>
                                            <div>Aucun prospect enregistré.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- PANEL 3: MARKETING KIT -->
            <div class="tab-pane fade" id="panel-marketing" role="tabpanel">
                <div class="row g-4 mb-4">
                    <div class="col-md-7">
                        <div class="soft-card p-4 h-100">
                            <h4 class="h5 fw-bold text-dark mb-3">📚 Guides d'Expertise & Lead Magnets</h4>
                            <p class="text-muted small mb-4">Partagez ces supports de référence SYSCOHADA et Trésorerie pour capturer des leads qualifiés.</p>
                            
                            <div class="p-3 mb-3 border rounded-4 bg-light d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="fw-bold text-dark mb-1">Guide 1 : Réussir son Bilan SYSCOHADA</h6>
                                    <div class="text-muted small">Manuel pratique de gestion comptable PME.</div>
                                </div>
                                <a href="{{ route('commercial.showcase') }}" class="btn btn-sm btn-outline-primary rounded-pill">Télécharger</a>
                            </div>

                            <div class="p-3 mb-3 border rounded-4 bg-light d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="fw-bold text-dark mb-1">Guide 2 : Trésorerie & Rapprochement Mobile Money</h6>
                                    <div class="text-muted small">Intégration Wave, Orange Money et MTN.</div>
                                </div>
                                <a href="{{ route('commercial.showcase') }}" class="btn btn-sm btn-outline-primary rounded-pill">Télécharger</a>
                            </div>

                            <div class="p-3 border rounded-4 bg-light d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="fw-bold text-dark mb-1">Guide 3 : Préparer sa PME à une Levée de Fonds (FIRD)</h6>
                                    <div class="text-muted small">Diagnostic de maturité & Investor Readiness.</div>
                                </div>
                                <a href="{{ route('commercial.showcase') }}" class="btn btn-sm btn-outline-primary rounded-pill">Télécharger</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="soft-card p-4 h-100 bg-dark text-white d-flex flex-column justify-content-between">
                            <div>
                                <span class="pill-badge pill-badge-purple mb-3">COMMUNAUTÉ SITIAME</span>
                                <h4 class="fw-bold text-white mb-2">Sitiame Finance Club 🌐</h4>
                                <p class="text-white-50 small mb-4">Invitez vos dirigeants de PME à rejoindre le réseau exclusif d'entrepreneurs pour accéder aux webinaires mensuels et aux opportunités de financement.</p>
                            </div>
                            <a href="{{ route('commercial.showcase') }}" class="btn btn-light text-dark rounded-pill fw-bold w-100 py-2">
                                Présenter le Sitiame Finance Club &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Modal Add Prospect -->
<div class="modal fade" id="addProspectModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="{{ route('commercial.prospects.store') }}" method="POST">
                @csrf
                <div class="modal-header border-0 bg-dark text-white p-4">
                    <div>
                        <span class="pill-badge pill-badge-blue mb-2">LEAD GENERATION CRM</span>
                        <h4 class="modal-title fw-bold text-white mb-0">Nouveau Prospect / Lead Qualifié</h4>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
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
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="{{ route('commercial.clients.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header border-0 bg-primary text-white p-4">
                    <div>
                        <span class="pill-badge pill-badge-emerald mb-2" id="stepBadgeLabel">Étape 1/2</span>
                        <h4 class="modal-title fw-bold text-white mb-0" id="stepTitleLabel">COMPTE CLIENT & CRÉDENTIELS</h4>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="progress rounded-0" style="height: 4px;">
                    <div class="progress-bar bg-success" role="progressbar" id="wizardProgressBar" style="width: 50%;"></div>
                </div>

                <div class="modal-body p-4">
                    <div id="wizardStep1">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark">Nom complet du dirigeant <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control rounded-3" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark">Adresse Email (Identifiant) <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control rounded-3" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark">Téléphone</label>
                                <input type="text" name="phone" class="form-control rounded-3">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark">Mot de passe temporaire <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control rounded-3" required minlength="8">
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
        }
    }
</script>
@endpush
@endsection
