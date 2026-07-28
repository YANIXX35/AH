@extends('layouts.app')

@section('title', 'Portail Commercial | PME360')
@section('page_title', 'Dashboard Commercial')

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
</style>
@endpush

@section('content')
<div class="mondays-container pb-4">
    <!-- Header -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-2">
            <div>
                <div class="text-muted small fw-semibold">ESPACE REPRÉSENTANT COMMERCIAL</div>
                <h1 class="mondays-hero-title">
                    Bonjour, {{ explode(' ', auth()->user()->name)[0] }} 👋
                </h1>
            </div>
            <div>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold" data-bs-toggle="modal" data-bs-target="#addClientModal">
                    <i data-feather="user-plus" class="me-1" style="width:16px; height:16px;"></i> Enregistrer un client
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
        <div class="col-md-4">
            <div class="card mondays-card border-0 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Total Portefeuille Clients</span>
                    <span class="mondays-badge mondays-badge-info">Général</span>
                </div>
                <div class="mondays-metric-val text-primary mb-1">{{ number_format($totalClients) }}</div>
                <div class="text-muted small">Nombre total d'entreprises inscrites.</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mondays-card border-0 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Essais Gratuits Actifs</span>
                    <span class="mondays-badge mondays-badge-success">En cours</span>
                </div>
                <div class="mondays-metric-val text-success mb-1">{{ number_format($activeTrials) }}</div>
                <div class="text-muted small">Accès actif de 1 mois pour ces clients.</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mondays-card border-0 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Essais Expirés / Relance</span>
                    <span class="mondays-badge mondays-badge-danger">À relancer</span>
                </div>
                <div class="mondays-metric-val text-danger mb-1">{{ number_format($expiredTrials) }}</div>
                <div class="text-muted small">Clients ayant terminé leur période d'essai.</div>
            </div>
        </div>
    </div>

    <!-- Client list -->
    <div class="card mondays-card border-0 p-4 mb-4">
        <h3 class="h5 fw-bold text-dark mb-3">Vos clients parrainés</h3>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nom du client</th>
                        <th>Email</th>
                        <th>Entreprise</th>
                        <th>Date d'inscription</th>
                        <th>Statut Abonnement</th>
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
                                <span class="badge bg-light text-dark border p-2">{{ $client->company_name }}</span>
                            </td>
                            <td>
                                <div>{{ $client->created_at->format('d/m/Y') }}</div>
                                <div class="text-muted small">{{ $client->created_at->format('H:i') }}</div>
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
                                <form action="{{ route('commercial.clients.destroy', $client->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce compte client ? Cette action est irréversible.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light text-danger border">
                                        <i data-feather="trash-2" style="width:14px; height:14px;"></i> Supprimer
                                    </button>
                                </form>
                            </td>
                        </tr>

                        <!-- Edit Client Modal -->
                        <div class="modal fade" id="editClientModal{{ $client->id }}" data-bs-backdrop="static" tabindex="-1" aria-labelledby="editClientModalLabel{{ $client->id }}" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow-lg rounded-4">
                                    <div class="modal-header border-0 pb-0">
                                        <h5 class="modal-title fw-bold text-dark" id="editClientModalLabel{{ $client->id }}">Modifier le client</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form action="{{ route('commercial.clients.update', $client->id) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-body py-4">
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Nom complet du contact</label>
                                                <input type="text" name="name" class="form-control rounded-3" value="{{ $client->name }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Adresse E-mail</label>
                                                <input type="email" name="email" class="form-control rounded-3" value="{{ $client->email }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Nom de l'entreprise</label>
                                                <input type="text" name="company_name" class="form-control rounded-3" value="{{ $client->company_name }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Nouveau mot de passe (laisser vide pour ne pas changer)</label>
                                                <input type="password" name="password" class="form-control rounded-3" placeholder="Min. 8 caractères">
                                            </div>
                                        </div>
                                        <div class="modal-footer border-0 pt-0">
                                            <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Annuler</button>
                                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">Enregistrer les modifications</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i data-feather="users" class="mb-3 text-muted" style="width:48px; height:48px;"></i>
                                <p class="mb-0">Vous n'avez enregistré aucun client pour le moment.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Client Modal -->
<!-- Add Client Modal 2-STEP WIZARD -->
<div class="modal fade" id="addClientModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="addClientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="addClientModalLabel">Nouveau Client & Création d'Entreprise</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="{{ route('commercial.clients.store') }}" method="POST" enctype="multipart/form-data" id="wizardForm">
                @csrf
                
                <!-- STEP PROGRESS BAR HEADER -->
                <div class="px-4 pt-3 pb-2">
                    <div class="p-3 rounded-4 border" style="background:#fff7ed; border-color:#ffedd5 !important;">
                        <div class="d-flex justify-content-between align-items-center fw-bold small mb-2 text-uppercase" style="letter-spacing:1px; color:#c2410c;">
                            <span id="stepBadgeLabel">Étape 1/2</span>
                            <span id="stepTitleLabel">COMPTE</span>
                        </div>
                        <div class="progress" style="height: 6px; background-color: #ffedd5;">
                            <div class="progress-bar" id="wizardProgressBar" role="progressbar" style="width: 50%; background-color: #ea580c; transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                </div>

                <div class="modal-body px-4 py-3">

                    <!-- STEP 1 : COMPTE RESPONSABLE -->
                    <div id="wizardStep1">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nom du responsable <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control rounded-3 py-2" placeholder="ex: Yannick Kouamé" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Adresse E-mail pro <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control rounded-3 py-2" placeholder="ex: contact@entreprise.ci" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Téléphone</label>
                            <input type="text" name="phone" class="form-control rounded-3 py-2" placeholder="ex: +225 07000000">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Mot de passe <span class="text-danger">*</span></label>
                                <input type="password" name="password" id="wiz_pass" class="form-control rounded-3 py-2" placeholder="Min. 8 caractères" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Confirmer <span class="text-danger">*</span></label>
                                <input type="password" name="password_confirmation" id="wiz_pass_conf" class="form-control rounded-3 py-2" placeholder="Confirmer mot de passe" required>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2 : ENTREPRISE & KYC -->
                    <div id="wizardStep2" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nom de l'entreprise <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control rounded-3 py-2" placeholder="ex: Kouamé & Fils SARL" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Sigle usuel</label>
                            <input type="text" name="company_sigle" class="form-control rounded-3 py-2" placeholder="ex: KFS">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">N° d'identification fiscale (NIF)</label>
                            <input type="text" name="company_tax_id" class="form-control rounded-3 py-2" placeholder="ex: 1234567A">
                            <div class="form-text small text-muted">Si vous utilisez une clé de licence, le NIF doit être identique à celui de votre entreprise.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Attestation DFE / NIF (Fichier logo ou PDF)</label>
                            <input type="file" name="company_logo" class="form-control rounded-3 py-2" accept="image/*,.pdf">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Secteur d'activité</label>
                            <select name="sector" class="form-select rounded-3 py-2">
                                <option value="">Choisir un secteur...</option>
                                <option value="Agroalimentaire">Agroalimentaire</option>
                                <option value="Commerce & Distribution">Commerce & Distribution</option>
                                <option value="BTP & Construction">BTP & Construction</option>
                                <option value="Services aux entreprises">Services aux entreprises</option>
                                <option value="Technologies / IT">Technologies / IT</option>
                                <option value="Transport & Logistique">Transport & Logistique</option>
                                <option value="Santé">Santé</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Numéro RCCM / SIRET</label>
                            <input type="text" name="rccm" class="form-control rounded-3 py-2" placeholder="ex: CI-ABJ-2026-B-1234">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">📄 Registre de commerce (optionnel)</label>
                            <input type="file" name="trade_register" class="form-control rounded-3 py-2" accept="image/*,.pdf">
                            <div class="form-text small text-muted">PDF ou photo (max 5 Mo).</div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-8">
                                <label class="form-label small fw-semibold">Adresse complète</label>
                                <input type="text" name="address" class="form-control rounded-3 py-2" placeholder="ex: Cocody Deux Plateaux">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Ville</label>
                                <input type="text" name="city" class="form-control rounded-3 py-2" placeholder="ex: Abidjan">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Clé de licence (optionnel — 2e, 3e utilisateur...)</label>
                            <input type="text" name="license_key" class="form-control rounded-3 py-2" placeholder="Fournie par l'administrateur">
                        </div>
                        <div class="p-3 bg-light rounded-3 small text-muted">
                            💡 À la création, ce client bénéficiera automatiquement de **1 mois d'accès gratuit** à la plateforme Premium PME360.
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

    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('action') === 'add-client') {
            const modalEl = document.getElementById('addClientModal');
            if (modalEl) {
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }
        }
    });
</script>
@endpush
@endsection
