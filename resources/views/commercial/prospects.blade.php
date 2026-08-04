@extends('layouts.app')

@section('title', 'Pipeline Leads CRM & Prospects | SITIAME CAPITAL')
@section('page_title', 'Pipeline Leads CRM')

@push('styles')
<style>
    .prospects-bg {
        background-color: #f1f5f9;
        min-height: 100vh;
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        padding: 24px;
    }
    .prospect-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.03);
    }
    /* Exact Layout & Styling Replica of the User's Mockup */
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
</style>
@endpush

@section('content')
<div class="prospects-bg animate__animated animate__fadeIn">
    <div class="container-fluid max-w-7xl mx-auto">
        
        <!-- TOP HEADER BAR (Matching Mockup Navigation Bar) -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 mockup-header-bar">
            <!-- Left: Logo & Pill Navigation Tabs -->
            <div class="d-flex align-items-center gap-2 flex-grow-1 flex-lg-grow-0 w-100 w-lg-auto">
                <div class="bg-primary text-white rounded-circle p-2 d-none d-sm-flex align-items-center justify-content-center flex-shrink-0" style="width:40px; height:40px;">
                    <i data-feather="grid" style="width:20px; height:20px;"></i>
                </div>
                <div class="d-none d-lg-flex align-items-center gap-1 bg-light rounded-pill p-1 border overflow-auto scrollbar-none flex-nowrap w-100">
                    <a href="{{ route('commercial.dashboard') }}" class="pill-tab-btn pill-tab-btn-inactive text-decoration-none text-nowrap flex-shrink-0">
                        <i data-feather="layout" class="me-1" style="width:14px; height:14px;"></i> Tableau de bord
                    </a>
                    <a href="{{ route('commercial.portefeuille') }}" class="pill-tab-btn pill-tab-btn-inactive text-decoration-none text-nowrap flex-shrink-0">
                        <i data-feather="briefcase" class="me-1" style="width:14px; height:14px;"></i> Mon Portefeuille
                    </a>
                    <a href="{{ route('commercial.prospects') }}" class="pill-tab-btn pill-tab-btn-active text-decoration-none text-nowrap flex-shrink-0">
                        <i data-feather="users" class="me-1" style="width:14px; height:14px;"></i> Leads CRM ({{ $prospects->count() }})
                    </a>
                    <a href="{{ route('commercial.showcase') }}" class="pill-tab-btn pill-tab-btn-inactive text-decoration-none text-nowrap flex-shrink-0">
                        <i data-feather="book-open" class="me-1" style="width:14px; height:14px;"></i> Kit Marketing
                    </a>
                </div>
            </div>

            <!-- Right: Action Button -->
            <div class="d-flex align-items-center justify-content-between justify-content-lg-end gap-2 flex-grow-1 w-100 w-lg-auto">
                <div class="d-flex align-items-center gap-1.5 overflow-auto scrollbar-none flex-nowrap w-100 justify-content-start justify-content-lg-end">
                    <button type="button" class="btn btn-primary rounded-pill px-3.5 py-2 fw-bold text-xs text-nowrap flex-shrink-0 shadow-sm" data-bs-toggle="modal" data-bs-target="#addProspectModal">
                        + Lead
                    </button>
                </div>
            </div>
        </div>

        <!-- Notification Status -->
        @if(session('status'))
            <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4 border-0 shadow-sm" role="alert">
                <i data-feather="check-circle" class="me-2 text-success"></i>
                {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Prospects Pipeline Table Card -->
        <div class="prospect-card p-4 mb-4">
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle border-0 mb-0">
                    <thead>
                        <tr class="text-muted text-uppercase small" style="font-size:0.75rem;">
                            <th class="border-0">Prospect / Dirigeant</th>
                            <th class="border-0">Entreprise & Poste</th>
                            <th class="border-0">Besoin Principal</th>
                            <th class="border-0">Statut CRM</th>
                            <th class="border-0">Date de création</th>
                            <th class="border-0 text-end">Mettre à jour statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($prospects as $prospect)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-primary text-white rounded-circle fw-bold d-flex align-items-center justify-content-center" style="width:38px; height:38px; font-size:0.85rem;">
                                            {{ strtoupper(substr($prospect->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark">{{ $prospect->name }}</div>
                                            <div class="text-muted small">{{ $prospect->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $prospect->company_name ?? 'PME non renseignée' }}</div>
                                    <div class="text-muted small">{{ $prospect->job_title ?? 'Dirigeant / RAF' }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-primary text-white rounded-pill px-3 py-1">
                                        {{ $prospect->need_label }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $prospect->status_badge_class }} px-3 py-2 rounded-pill fw-semibold">
                                        {{ $prospect->status_label }}
                                    </span>
                                </td>
                                <td>{{ $prospect->created_at->format('d/m/Y') }}</td>
                                <td class="text-end">
                                    <form action="{{ route('commercial.prospects.updateStatus', $prospect->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('PUT')
                                        <select name="status" class="form-select form-select-sm d-inline-block w-auto rounded-pill border me-1 fw-semibold" onchange="this.form.submit()">
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
                                        <button type="submit" class="btn btn-sm btn-light text-danger rounded-pill border ms-1 px-2">
                                            <i data-feather="trash-2" style="width:14px; height:14px;"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i data-feather="target" class="mb-2" style="width:40px; height:40px; opacity:0.3;"></i>
                                    <div>Aucun prospect enregistré pour le moment.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Mobile View: Cards Layout -->
            <div class="d-block d-md-none">
                @forelse($prospects as $prospect)
                    <div class="p-3 bg-light rounded-3 border d-flex flex-column gap-2 mb-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-primary text-white rounded-circle fw-bold d-flex align-items-center justify-content-center" style="width:32px; height:32px; font-size:0.75rem;">
                                    {{ strtoupper(substr($prospect->name, 0, 2)) }}
                                </div>
                                <div>
                                    <div class="fw-bold text-dark small">{{ $prospect->name }}</div>
                                    <div class="text-muted small" style="font-size:0.7rem;">{{ $prospect->email }}</div>
                                </div>
                            </div>
                            <span class="badge {{ $prospect->status_badge_class }} rounded-pill px-2.5 py-1" style="font-size: 0.65rem;">
                                {{ $prospect->status_label }}
                            </span>
                        </div>
                        <div class="py-2 border-top border-bottom my-2">
                            <div class="fw-semibold text-dark small">{{ $prospect->company_name ?? 'PME non renseignée' }}</div>
                            <div class="text-muted small" style="font-size: 0.75rem;">{{ $prospect->job_title ?? 'Dirigeant / RAF' }}</div>
                            <div class="mt-2">
                                <span class="badge bg-primary text-white rounded-pill px-2 py-0.5" style="font-size: 0.68rem;">
                                    {{ $prospect->need_label }}
                                </span>
                            </div>
                        </div>
                        <div class="d-flex flex-column gap-2">
                            <div class="d-flex justify-content-between align-items-center text-muted small" style="font-size: 0.7rem;">
                                <span>Créé le {{ $prospect->created_at->format('d/m/Y') }}</span>
                                <form action="{{ route('commercial.prospects.destroy', $prospect->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce prospect ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-outline-danger rounded-pill px-2">
                                        <i data-feather="trash-2" style="width:14px; height:14px;"></i>
                                    </button>
                                </form>
                            </div>
                            <div class="mt-1">
                                <form action="{{ route('commercial.prospects.updateStatus', $prospect->id) }}" method="POST" class="w-100">
                                    @csrf
                                    @method('PUT')
                                    <label class="form-label small fw-semibold text-dark mb-1" style="font-size: 0.75rem;">Changer le statut :</label>
                                    <select name="status" class="form-select form-select-sm rounded-3 border fw-semibold" style="min-height: 38px;" onchange="this.form.submit()">
                                        <option value="nouveau" {{ $prospect->status === 'nouveau' ? 'selected' : '' }}>Nouveau</option>
                                        <option value="contacte" {{ $prospect->status === 'contacte' ? 'selected' : '' }}>Contacté</option>
                                        <option value="qualifie" {{ $prospect->status === 'qualifie' ? 'selected' : '' }}>Qualifié</option>
                                        <option value="client" {{ $prospect->status === 'client' ? 'selected' : '' }}>Converti Client</option>
                                        <option value="sans_suite" {{ $prospect->status === 'sans_suite' ? 'selected' : '' }}>Sans suite</option>
                                    </select>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted small py-3 bg-light rounded-3 border">
                        Aucun prospect disponible.
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</div>

<!-- Modal Add Prospect -->
<div class="modal fade" id="addProspectModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
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
@endsection
