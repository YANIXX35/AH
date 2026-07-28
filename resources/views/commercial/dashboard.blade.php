@extends('layouts.app')

@section('title', 'Portail Commercial & Marketing | SITIAME CAPITAL')
@section('page_title', 'Dashboard Commercial & Inbound Marketing')

@push('styles')
<style>
    /* Mondays Design System - Light & Premium */
    .mondays-container {
        background-color: #f8fafc;
        min-height: 100vh;
        font-family: inherit;
    }
    .mondays-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03), 0 4px 12px rgba(0, 0, 0, 0.02);
        transition: all 0.2s ease-in-out;
    }
    .mondays-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
    }
    .mondays-hero-title {
        font-size: 1.85rem;
        font-weight: 700;
        color: #0f172a;
        margin-top: 2px;
        margin-bottom: 12px;
    }
    .mondays-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.2px;
    }
    .mondays-badge-success { background: #dcfce7; color: #15803d; }
    .mondays-badge-warning { background: #ffedd5; color: #c2410c; }
    .mondays-badge-danger { background: #fee2e2; color: #991b1b; }
    .mondays-badge-info { background: #dbeafe; color: #1d4ed8; }
    
    .mondays-metric-val {
        font-size: 1.85rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.2;
    }
    .nav-pills .nav-link {
        color: #64748b;
        font-weight: 600;
        border-radius: 12px;
        padding: 10px 20px;
        transition: all 0.2s;
    }
    .nav-pills .nav-link.active {
        background-color: #0d6efd;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25);
    }
</style>
@endpush

@section('content')
<div class="mondays-container pb-5">
    <!-- Header -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-2">
            <div>
                <div class="text-muted small fw-semibold text-uppercase">SITIAME CAPITAL — REPRÉSENTATION COMMERCIAL & MARKETING</div>
                <h1 class="mondays-hero-title">
                    Bonjour, {{ explode(' ', auth()->user()->name)[0] }} 👋
                </h1>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary rounded-pill px-4 fw-semibold" data-bs-toggle="modal" data-bs-target="#addProspectModal">
                    <i data-feather="user-check" class="me-1" style="width:16px; height:16px;"></i> Nouveau Prospect / Lead
                </button>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold" data-bs-toggle="modal" data-bs-target="#addClientModal">
                    <i data-feather="user-plus" class="me-1" style="width:16px; height:16px;"></i> Enregistrer un client (Wizard)
                </button>
            </div>
        </div>
    </div>

    <!-- Alert Status -->
    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4 border-0 shadow-sm" role="alert">
            <i data-feather="check-circle" class="me-2 text-success"></i>
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Metrics row -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card mondays-card border-0 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Portefeuille Clients</span>
                    <span class="mondays-badge mondays-badge-info">PME</span>
                </div>
                <div class="mondays-metric-val text-primary mb-1">{{ number_format($totalClients) }}</div>
                <div class="text-muted small">Entreprises inscrites parrainées.</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mondays-card border-0 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Leads Qualifiés</span>
                    <span class="mondays-badge mondays-badge-warning">Inbound CRM</span>
                </div>
                <div class="mondays-metric-val text-warning mb-1">{{ number_format($totalProspects) }}</div>
                <div class="text-muted small">{{ $newProspects }} nouveaux leads à contacter.</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mondays-card border-0 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Essais Gratuits Actifs</span>
                    <span class="mondays-badge mondays-badge-success">1 Mois Free</span>
                </div>
                <div class="mondays-metric-val text-success mb-1">{{ number_format($activeTrials) }}</div>
                <div class="text-muted small">Accès premium actif accordé.</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mondays-card border-0 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Conversions Payantes</span>
                    <span class="mondays-badge mondays-badge-success">Abonnés</span>
                </div>
                <div class="mondays-metric-val text-dark mb-1">{{ number_format($convertedProspects) }}</div>
                <div class="text-muted small">Prospects devenus clients payants.</div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-pills mb-4 bg-white p-2 rounded-4 border shadow-sm" id="commercialTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="clients-tab" data-bs-toggle="pill" data-bs-target="#tab-clients" type="button" role="tab">
                <i data-feather="briefcase" class="me-1" style="width:16px; height:16px;"></i> Portefeuille Clients ({{ $totalClients }})
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="prospects-tab" data-bs-toggle="pill" data-bs-target="#tab-prospects" type="button" role="tab">
                <i data-feather="target" class="me-1" style="width:16px; height:16px;"></i> Pipeline Prospects CRM ({{ $totalProspects }})
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="marketing-tab" data-bs-toggle="pill" data-bs-target="#tab-marketing" type="button" role="tab">
                <i data-feather="book-open" class="me-1" style="width:16px; height:16px;"></i> Kit Marketing & Inbound
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="trials-tab" data-bs-toggle="pill" data-bs-target="#tab-trials" type="button" role="tab">
                <i data-feather="clock" class="me-1" style="width:16px; height:16px;"></i> Suivi Relances Essai Gratuit
            </button>
        </li>
    </ul>

    <!-- Tab Contents -->
    <div class="tab-content" id="commercialTabsContent">
        
        <!-- TAB 1: Clients List -->
        <div class="tab-pane fade show active" id="tab-clients" role="tabpanel">
            <div class="card mondays-card border-0 p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h5 fw-bold text-dark mb-0">Vos clients parrainés</h3>
                    <button type="button" class="btn btn-sm btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#addClientModal">
                        + Ajouter un client (2 Étapes)
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nom du client</th>
                                <th>Email</th>
                                <th>Entreprise</th>
                                <th>Date d'inscription</th>
                                <th>Statut Essai Gratuit</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($clients as $client)
                                @php
                                    $isTrialActive = $client->is_premium && $client->premium_ends_at && $client->premium_ends_at->isFuture();
                                    $daysLeft = $isTrialActive ? now()->diffInDays($client->premium_ends_at) : 0;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $client->name }}</div>
                                    </td>
                                    <td>{{ $client->email }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark border p-2">{{ $client->company_name ?? 'N/A' }}</span>
                                    </td>
                                    <td>
                                        <div>{{ $client->created_at->format('d/m/Y') }}</div>
                                    </td>
                                    <td>
                                        @if($isTrialActive)
                                            <span class="mondays-badge mondays-badge-success">
                                                Essai Actif ({{ $daysLeft }} j restants)
                                            </span>
                                        @else
                                            <span class="mondays-badge mondays-badge-danger">
                                                Essai Expiré
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-light border" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editClientModal{{ $client->id }}">
                                            <i data-feather="edit-2" style="width:14px; height:14px;"></i> Modifier
                                        </button>
                                        <form action="{{ route('commercial.clients.destroy', $client->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce compte client ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger border">
                                                <i data-feather="trash-2" style="width:14px; height:14px;"></i> Supprimer
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Edit Client Modal -->
                                <div class="modal fade" id="editClientModal{{ $client->id }}" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content border-0 shadow-lg rounded-4">
                                            <form action="{{ route('commercial.clients.update', $client->id) }}" method="POST" enctype="multipart/form-data">
                                                @csrf
                                                @method('PUT')
                                                <div class="modal-header border-0 bg-light p-4">
                                                    <h5 class="modal-title fw-bold text-dark">Modifier la Fiche Client — {{ $client->name }}</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                                                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">Enregistrer les modifications</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i data-feather="users" class="mb-2" style="width:36px; height:36px; opacity:0.3;"></i>
                                        <div>Aucun client parrainé enregistré pour le moment.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: Prospects CRM Pipeline -->
        <div class="tab-pane fade" id="tab-prospects" role="tabpanel">
            <div class="card mondays-card border-0 p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h3 class="h5 fw-bold text-dark mb-1">Pipeline des Prospects (Lead Generation CRM)</h3>
                        <p class="text-muted small mb-0">Suivez les leads capturés via les formulaires de contact, guides et webinaires.</p>
                    </div>
                    <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold" data-bs-toggle="modal" data-bs-target="#addProspectModal">
                        + Ajouter un Prospect
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Prospect</th>
                                <th>Entreprise & Fonction</th>
                                <th>Besoin Identifié</th>
                                <th>Statut Lead</th>
                                <th>Date d'ajout</th>
                                <th class="text-end">Changer Statut / Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($prospects as $prospect)
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $prospect->name }}</div>
                                        <div class="text-muted small">{{ $prospect->email }} | {{ $prospect->phone ?? 'Pas de tél' }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-medium text-dark">{{ $prospect->company_name ?? 'PME non précisée' }}</div>
                                        <div class="text-muted small">{{ $prospect->job_title ?? 'Dirigeant / RAF' }}</div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-primary border p-2">{{ $prospect->need_label }}</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $prospect->status_badge_class }} p-2">
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
                                        <form action="{{ route('commercial.prospects.destroy', $prospect->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce prospect ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-link text-danger p-0 border-0 ms-1">
                                                <i data-feather="x-circle" style="width:16px; height:16px;"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i data-feather="target" class="mb-2" style="width:36px; height:36px; opacity:0.3;"></i>
                                        <div>Aucun prospect enregistré dans votre pipeline CRM.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 3: Kit Marketing & Inbound -->
        <div class="tab-pane fade" id="tab-marketing" role="tabpanel">
            <div class="row g-4 mb-4">
                <div class="col-md-7">
                    <div class="card mondays-card border-0 p-4 h-100">
                        <h4 class="h5 fw-bold text-dark mb-3">📚 Guides Téléchargeables & Lead Magnets</h4>
                        <p class="text-muted small mb-4">Offrez ces guides d'expertise financière à vos prospects pour capturer leur adresse e-mail et établir votre crédibilité.</p>
                        
                        <div class="d-flex align-items-center p-3 mb-3 border rounded-3 bg-light">
                            <div class="bg-primary text-white p-3 rounded-3 me-3">
                                <i data-feather="file-text" style="width:24px; height:24px;"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="fw-bold mb-1">Guide 1 : Réussir son Bilan SYSCOHADA</h6>
                                <div class="text-muted small">Manuel pratique pour PME & RAF en Afrique de l'Ouest.</div>
                            </div>
                            <a href="{{ route('commercial.showcase') }}" class="btn btn-sm btn-outline-primary rounded-pill fw-semibold">Voir Showcase</a>
                        </div>

                        <div class="d-flex align-items-center p-3 mb-3 border rounded-3 bg-light">
                            <div class="bg-success text-white p-3 rounded-3 me-3">
                                <i data-feather="dollar-sign" style="width:24px; height:24px;"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="fw-bold mb-1">Guide 2 : Optimiser sa Trésorerie avec Mobile Money</h6>
                                <div class="text-muted small">Rapprochement automatique Wave, Orange & MTN.</div>
                            </div>
                            <a href="{{ route('commercial.showcase') }}" class="btn btn-sm btn-outline-primary rounded-pill fw-semibold">Voir Showcase</a>
                        </div>

                        <div class="d-flex align-items-center p-3 border rounded-3 bg-light">
                            <div class="bg-warning text-dark p-3 rounded-3 me-3">
                                <i data-feather="trending-up" style="width:24px; height:24px;"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="fw-bold mb-1">Guide 3 : Préparer sa PME à une Levée de Fonds (FIRD)</h6>
                                <div class="text-muted small">Diagnostic financier & Investor Readiness.</div>
                            </div>
                            <a href="{{ route('commercial.showcase') }}" class="btn btn-sm btn-outline-primary rounded-pill fw-semibold">Voir Showcase</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-5">
                    <div class="card mondays-card border-0 p-4 h-100 bg-primary text-white">
                        <span class="badge bg-white text-primary rounded-pill mb-3 w-auto align-self-start fw-bold">COMMUNAUTÉ EXCLUSIVE</span>
                        <h4 class="fw-bold mb-2">Sitiame Finance Club 🚀</h4>
                        <p class="small text-white-50 mb-4">Proposez à vos clients et prospects de rejoindre la communauté exclusive d'entrepreneurs pour bénéficier de mentorat, de webinaires mensuels et d'échanges réseau.</p>
                        
                        <div class="p-3 bg-white bg-opacity-10 rounded-3 mb-4">
                            <div class="d-flex align-items-center mb-2">
                                <i data-feather="video" class="me-2" style="width:18px; height:18px;"></i>
                                <span class="fw-semibold small">Webinaires Mensuels</span>
                            </div>
                            <div class="small text-white-50">Co-animés avec des experts-comptables et investisseurs.</div>
                        </div>

                        <a href="{{ route('commercial.showcase') }}" class="btn btn-light text-primary rounded-pill fw-bold w-100 py-2">
                            Présenter le Sitiame Finance Club &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 4: Free Trial Management -->
        <div class="tab-pane fade" id="tab-trials" role="tabpanel">
            <div class="card mondays-card border-0 p-4 mb-4">
                <h3 class="h5 fw-bold text-dark mb-3">Gestion de l'Essai Gratuit 1 Mois</h3>
                <p class="text-muted small mb-4">Toutes les PME enregistrées via votre parrainage bénéficient automatiquement d'un mois d'essai complet gratuit. Suivez leur échéance pour déclencher les relances de conversion.</p>
                
                <div class="row g-3">
                    @foreach($clients as $client)
                        @php
                            $isTrialActive = $client->is_premium && $client->premium_ends_at && $client->premium_ends_at->isFuture();
                            $daysLeft = $isTrialActive ? now()->diffInDays($client->premium_ends_at) : 0;
                        @endphp
                        <div class="col-md-4">
                            <div class="border rounded-4 p-3 bg-white h-100">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="fw-bold mb-0 text-dark">{{ $client->name }}</h6>
                                    @if($isTrialActive)
                                        <span class="badge bg-success">Actif</span>
                                    @else
                                        <span class="badge bg-danger">Expiré</span>
                                    @endif
                                </div>
                                <div class="text-muted small mb-2">{{ $client->company_name ?? 'Entreprise' }}</div>
                                <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                    <span class="small text-muted">Jours restants :</span>
                                    <span class="fw-bold text-primary">{{ $daysLeft }} jours</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal Add Prospect -->
<div class="modal fade" id="addProspectModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="addProspectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="{{ route('commercial.prospects.store') }}" method="POST">
                @csrf
                <div class="modal-header border-0 bg-primary text-white p-4">
                    <div>
                        <span class="badge bg-white text-primary rounded-pill px-3 py-1 mb-2 fw-semibold">LEAD GENERATION CRM</span>
                        <h4 class="modal-title fw-bold text-white mb-0" id="addProspectModalLabel">Ajouter un Prospect / Lead Qualifié</h4>
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
                            <input type="text" name="job_title" class="form-control rounded-3" placeholder="Ex: CEO / Directeur Financier">
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
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-dark">Notes & Remarques de prospection</label>
                            <textarea name="notes" class="form-control rounded-3" rows="3" placeholder="Précisez la situation du prospect ou la date de relance convenue..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">Enregistrer le Prospect &check;</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Add Client (2-Step Wizard) -->
<div class="modal fade" id="addClientModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="addClientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="{{ route('commercial.clients.store') }}" method="POST" enctype="multipart/form-data" id="wizardClientForm">
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

                <div class="modal-body p-4">
                    <!-- Step 1: Compte -->
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

                    <!-- Step 2: Entreprise & KYC -->
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
                                <label class="form-label small fw-semibold text-dark">NIF / Numéro d'immatriculation fiscale</label>
                                <input type="text" name="company_tax_id" class="form-control rounded-3">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark">Secteur d'activité</label>
                                <input type="text" name="sector" class="form-control rounded-3">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark">Numéro RCCM</label>
                                <input type="text" name="rccm" class="form-control rounded-3">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark">Ville</label>
                                <input type="text" name="city" class="form-control rounded-3">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-3" id="wizardPrevBtn" style="display:none;" onclick="goToStep(1)">Précédent</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold" id="wizardNextBtn" onclick="goToStep(2)">Suivant : Entreprise &rarr;</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-semibold" id="wizardSubmitBtn" style="display:none;">Créer le compte client &check;</button>
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
