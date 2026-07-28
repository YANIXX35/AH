@extends('layouts.app')

@section('title', 'Dossiers clients | Cabinet')
@section('page_title', 'Dossiers clients')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><strong>Dossiers</strong> clients</h1>
        <p class="text-muted mb-0">Recherchez une entreprise et ouvrez sa fiche pour accéder aux outils.</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#addAccountantClientModal">
            <i data-feather="plus-circle" class="me-1" style="width:16px; height:16px;"></i> Ajouter Client / Entreprise
        </button>
        <a href="{{ route('accountant.dashboard') }}" class="btn btn-outline-secondary btn-sm">Tableau de bord cabinet</a>
    </div>
</div>

@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show rounded-3 mb-3 border-0 shadow-sm" role="alert">
        <i data-feather="check-circle" class="me-2 text-success"></i>
        {{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<form method="get" action="{{ route('accountant.clients.index') }}" class="row g-2 mb-3">
    <div class="col-md-6 col-lg-4">
        <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Nom, société, e-mail…">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary">Rechercher</button>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        @forelse($enterpriseGroups as $group)
            <div class="border-bottom p-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <div>
                        <h6 class="mb-1">{{ $group['company_name'] }}</h6>
                        <p class="small text-muted mb-0">
                            @if(!empty($group['company_tax_id'])) NIF: {{ $group['company_tax_id'] }} · @endif
                            @if(!empty($group['enterprise_license_id'])) Licence #{{ $group['enterprise_license_id'] }} · @endif
                            {{ $group['users_count'] }} utilisateur(s)
                        </p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Utilisateur</th>
                            <th>E-mail</th>
                            <th class="text-end">Inscription</th>
                            <th class="text-end">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($group['users'] as $u)
                            <tr>
                                <td>{{ $u->name }}</td>
                                <td class="small">{{ $u->email }}</td>
                                <td class="text-end small">{{ $u->created_at?->format('d/m/Y') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('accountant.clients.show', $u) }}" class="btn btn-sm btn-outline-primary me-1">Fiche dossier</a>
                                    <button type="button" class="btn btn-sm btn-light border me-1" data-bs-toggle="modal" data-bs-target="#editAccountantClientModal{{ $u->id }}">
                                        <i data-feather="edit-2" style="width:13px; height:13px;"></i> Modifier
                                    </button>
                                    <form action="{{ route('accountant.clients.destroy', $u->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce compte dossier client ? Cette action est irréversible.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light text-danger border">
                                            <i data-feather="trash-2" style="width:13px; height:13px;"></i> Supprimer
                                        </button>
                                    </form>

                                    <!-- Edit Modal for Client -->
                                    <div class="modal fade text-start" id="editAccountantClientModal{{ $u->id }}" data-bs-backdrop="static" tabindex="-1" aria-labelledby="editAccountantClientModalLabel{{ $u->id }}" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                                            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                                <div class="modal-header border-0 pb-0">
                                                    <h5 class="modal-title fw-bold text-dark" id="editAccountantClientModalLabel{{ $u->id }}">Modifier le dossier client</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form action="{{ route('accountant.clients.update', $u->id) }}" method="POST" enctype="multipart/form-data">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="modal-body px-4 py-3" style="max-height: 65vh; overflow-y: auto;">
                                                        <h6 class="fw-bold text-uppercase small text-muted mb-3" style="letter-spacing:1px;">1. Informations du responsable</h6>
                                                        <div class="row g-2 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label small fw-semibold">Nom du responsable <span class="text-danger">*</span></label>
                                                                <input type="text" name="name" class="form-control rounded-3 py-2" value="{{ $u->name }}" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label small fw-semibold">Adresse E-mail <span class="text-danger">*</span></label>
                                                                <input type="email" name="email" class="form-control rounded-3 py-2" value="{{ $u->email }}" required>
                                                            </div>
                                                        </div>
                                                        <div class="row g-2 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label small fw-semibold">Téléphone</label>
                                                                <input type="text" name="phone" class="form-control rounded-3 py-2" value="{{ $u->phone }}" placeholder="ex: +225 07000000">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label small fw-semibold">Nouveau mot de passe</label>
                                                                <input type="password" name="password" class="form-control rounded-3 py-2" placeholder="Laisser vide si inchangé">
                                                            </div>
                                                        </div>

                                                        <h6 class="fw-bold text-uppercase small text-muted mb-3 mt-4" style="letter-spacing:1px;">2. Informations de l'entreprise & KYC (Facultatif)</h6>
                                                        <div class="row g-2 mb-3">
                                                            <div class="col-md-8">
                                                                <label class="form-label small fw-semibold">Nom de l'entreprise</label>
                                                                <input type="text" name="company_name" class="form-control rounded-3 py-2" value="{{ $u->company_name }}" placeholder="ex: Société Ivoirienne SARL">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label small fw-semibold">Sigle usuel</label>
                                                                <input type="text" name="company_sigle" class="form-control rounded-3 py-2" value="{{ $u->company_sigle }}" placeholder="ex: SIS">
                                                            </div>
                                                        </div>
                                                        <div class="row g-2 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label small fw-semibold">N° d'identification fiscale (NIF)</label>
                                                                <input type="text" name="company_tax_id" class="form-control rounded-3 py-2" value="{{ $u->company_tax_id }}" placeholder="ex: 1234567A">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label small fw-semibold">Secteur d'activité</label>
                                                                <select name="sector" class="form-select rounded-3 py-2">
                                                                    <option value="">Choisir un secteur...</option>
                                                                    @foreach(['Agroalimentaire', 'Commerce & Distribution', 'BTP & Construction', 'Services aux entreprises', 'Technologies / IT', 'Transport & Logistique', 'Santé', 'Autre'] as $sec)
                                                                        <option value="{{ $sec }}" {{ $u->sector === $sec ? 'selected' : '' }}>{{ $sec }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="row g-2 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label small fw-semibold">Numéro RCCM / SIRET</label>
                                                                <input type="text" name="rccm" class="form-control rounded-3 py-2" value="{{ $u->rccm }}" placeholder="ex: CI-ABJ-2026-B-1234">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label small fw-semibold">Ville</label>
                                                                <input type="text" name="city" class="form-control rounded-3 py-2" value="{{ $u->city }}" placeholder="ex: Abidjan">
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold">Adresse complète</label>
                                                            <input type="text" name="address" class="form-control rounded-3 py-2" value="{{ $u->address }}" placeholder="ex: Plateau Rue du Commerce">
                                                        </div>
                                                        <div class="row g-2 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label small fw-semibold">Attestation DFE / NIF (Fichier)</label>
                                                                <input type="file" name="company_logo" class="form-control rounded-3 py-2" accept="image/*,.pdf">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label small fw-semibold">Registre de commerce (PDF/image)</label>
                                                                <input type="file" name="trade_register" class="form-control rounded-3 py-2" accept="image/*,.pdf">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-0 pt-0 px-4 pb-4">
                                                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Annuler</button>
                                                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">Enregistrer les modifications</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="text-center text-muted py-4">Aucun résultat.</div>
        @endforelse
    </div>
</div>

<!-- Add Accountant Client Modal 2-STEP WIZARD -->
<div class="modal fade text-start" id="addAccountantClientModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="addAccountantClientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable my-auto" style="max-width: 850px; width: 95%; max-height: 85vh;">
        <div class="modal-content border-0 shadow-2xl rounded-4 overflow-hidden d-flex flex-column" style="border-radius: 16px; max-height: 85vh;">
            <!-- 1. Header Fixe (flex: 0 0 auto) -->
            <div class="modal-header border-bottom bg-white p-3 px-4 d-flex justify-content-between align-items-center flex-shrink-0">
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1.5 fw-semibold fs-7" id="accStepBadgeLabel">Étape 1/2</span>
                    <h5 class="modal-title fw-bold text-slate-800 mb-0 fs-5" id="accStepTitleLabel">COMPTE DU RESPONSABLE</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="progress rounded-0 flex-shrink-0" style="height: 3px; background-color: #f1f5f9;">
                <div class="progress-bar bg-warning" id="accWizardProgressBar" role="progressbar" style="width: 50%; transition: width 0.3s ease;"></div>
            </div>

            <form action="{{ route('accountant.clients.store') }}" method="POST" enctype="multipart/form-data" id="accWizardForm" autocomplete="off" class="d-flex flex-column m-0 flex-grow-1 overflow-hidden" style="min-height: 0;">
                @csrf
                
                <!-- Fake inputs to trap aggressive browser autofills -->
                <input type="text" name="fake_usernameremembered" style="display:none" tabindex="-1">
                <input type="password" name="fake_passwordremembered" style="display:none" tabindex="-1">

                <!-- 2. Corps Scrollable uniquement (flex: 1 1 auto; overflow-y: auto) -->
                <div class="modal-body p-4 bg-white flex-grow-1 overflow-y-auto" id="accWizardModalBody" style="min-height: 0; scroll-behavior: smooth;">

                    <!-- STEP 1 : COMPTE RESPONSABLE -->
                    <div id="accWizardStep1">
                        <div class="alert alert-light border rounded-3 mb-4 text-muted small py-2.5 px-3">
                            <i data-feather="info" class="me-1 text-primary"></i> Coordonnées du responsable qui aura accès à cet espace client.
                        </div>

                        <div class="mb-3.5">
                            <label class="form-label small fw-semibold text-slate-700 mb-1">Nom complet du responsable <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control rounded-3 py-2.5 px-3 border-slate-200" placeholder="ex: Jean Dupont" autocomplete="off" required>
                        </div>
                        <div class="row g-3 mb-3.5">
                            <div class="col-md-7">
                                <label class="form-label small fw-semibold text-slate-700 mb-1">Adresse E-mail pro <span class="text-danger">*</span></label>
                                <input type="email" name="email" id="accInputEmail" class="form-control rounded-3 py-2.5 px-3 border-slate-200" placeholder="ex: client@entreprise.ci" autocomplete="new-email" value="" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small fw-semibold text-slate-700 mb-1">Téléphone</label>
                                <input type="text" name="phone" class="form-control rounded-3 py-2.5 px-3 border-slate-200" placeholder="ex: +225 0700000000" autocomplete="off">
                            </div>
                        </div>
                        <div class="row g-3 mb-2">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-slate-700 mb-1">Mot de passe temporaire <span class="text-danger">*</span></label>
                                <input type="password" name="password" id="accInputPassword" class="form-control rounded-3 py-2.5 px-3 border-slate-200" placeholder="Min. 8 caractères" autocomplete="new-password" value="" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-slate-700 mb-1">Confirmer mot de passe <span class="text-danger">*</span></label>
                                <input type="password" name="password_confirmation" id="accInputPasswordConfirm" class="form-control rounded-3 py-2.5 px-3 border-slate-200" placeholder="Confirmer mot de passe" autocomplete="new-password" value="" required>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2 : ENTREPRISE & KYC -->
                    <div id="accWizardStep2" style="display: none;">
                        <div class="row g-3 mb-3.5">
                            <div class="col-md-8">
                                <label class="form-label small fw-semibold text-slate-700 mb-1">Nom de l'entreprise</label>
                                <input type="text" name="company_name" class="form-control rounded-3 py-2.5 px-3 border-slate-200" placeholder="ex: Société Ivoirienne SARL" autocomplete="off">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-slate-700 mb-1">Sigle usuel</label>
                                <input type="text" name="company_sigle" class="form-control rounded-3 py-2.5 px-3 border-slate-200" placeholder="ex: SIS" autocomplete="off">
                            </div>
                        </div>

                        <div class="row g-3 mb-3.5">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-slate-700 mb-1">N° identification fiscale (NIF)</label>
                                <input type="text" name="company_tax_id" class="form-control rounded-3 py-2.5 px-3 border-slate-200" placeholder="ex: 1234567A" autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-slate-700 mb-1">Secteur d'activité</label>
                                <select name="sector" class="form-select rounded-3 py-2.5 px-3 border-slate-200">
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
                        </div>

                        <div class="row g-3 mb-3.5">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-slate-700 mb-1">Attestation DFE / NIF (Fichier)</label>
                                <input type="file" name="company_logo" class="form-control rounded-3 py-2.5 px-3 border-slate-200" accept="image/*,.pdf">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-slate-700 mb-1">Registre de commerce (Fichier)</label>
                                <input type="file" name="trade_register" class="form-control rounded-3 py-2.5 px-3 border-slate-200" accept="image/*,.pdf">
                            </div>
                        </div>

                        <div class="row g-3 mb-3.5">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-slate-700 mb-1">Numéro RCCM / SIRET</label>
                                <input type="text" name="rccm" class="form-control rounded-3 py-2.5 px-3 border-slate-200" placeholder="ex: CI-ABJ-2026-B-1234" autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-slate-700 mb-1">Ville</label>
                                <input type="text" name="city" class="form-control rounded-3 py-2.5 px-3 border-slate-200" placeholder="ex: Abidjan" autocomplete="off">
                            </div>
                        </div>

                        <div class="mb-3.5">
                            <label class="form-label small fw-semibold text-slate-700 mb-1">Adresse complète</label>
                            <input type="text" name="address" class="form-control rounded-3 py-2.5 px-3 border-slate-200" placeholder="ex: Plateau Rue du Commerce" autocomplete="off">
                        </div>

                        <div class="mb-3.5">
                            <label class="form-label small fw-semibold text-slate-700 mb-1">Clé de licence (optionnel)</label>
                            <input type="text" name="license_key" class="form-control rounded-3 py-2.5 px-3 border-slate-200" placeholder="Fournie par l'administrateur" autocomplete="off">
                        </div>

                        <div class="p-3 bg-slate-50 rounded-3 small text-slate-600 border mb-1">
                            💡 À la création, ce dossier bénéficiera automatiquement de **1 mois d'accès gratuit**.
                        </div>
                    </div>

                </div>

                <!-- 3. Footer Fixe (flex: 0 0 auto) -->
                <div class="modal-footer border-top bg-white p-3 px-4 d-flex justify-content-between align-items-center flex-shrink-0">
                    <div>
                        <button type="button" class="btn btn-light rounded-pill px-4 border text-slate-600 font-medium" data-bs-dismiss="modal">Annuler</button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary rounded-pill px-4 fw-semibold" id="accWizardPrevBtn" style="display:none;" onclick="goToAccStep(1)">&larr; Précédent</button>
                        <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" id="accWizardNextBtn" onclick="goToAccStep(2)">Suivant : Entreprise &rarr;</button>
                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" id="accWizardSubmitBtn" style="display:none;">Créer le dossier client &check;</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function goToAccStep(step) {
        const modalBody = document.getElementById('accWizardModalBody');
        if (modalBody) modalBody.scrollTop = 0;

        if (step === 2) {
            document.getElementById('accWizardStep1').style.display = 'none';
            document.getElementById('accWizardStep2').style.display = 'block';
            document.getElementById('accStepBadgeLabel').innerText = 'Étape 2/2';
            document.getElementById('accStepTitleLabel').innerText = 'ENTREPRISE & KYC';
            document.getElementById('accWizardProgressBar').style.width = '100%';
            document.getElementById('accWizardPrevBtn').style.display = 'inline-block';
            document.getElementById('accWizardNextBtn').style.display = 'none';
            document.getElementById('accWizardSubmitBtn').style.display = 'inline-block';
        } else {
            document.getElementById('accWizardStep1').style.display = 'block';
            document.getElementById('accWizardStep2').style.display = 'none';
            document.getElementById('accStepBadgeLabel').innerText = 'Étape 1/2';
            document.getElementById('accStepTitleLabel').innerText = 'COMPTE DU RESPONSABLE';
            document.getElementById('accWizardProgressBar').style.width = '50%';
            document.getElementById('accWizardPrevBtn').style.display = 'none';
            document.getElementById('accWizardNextBtn').style.display = 'inline-block';
            document.getElementById('accWizardSubmitBtn').style.display = 'none';
        }
    }

    function clearAccModalFields() {
        const form = document.getElementById('accWizardForm');
        if (form) {
            form.reset();
            const emailInput = document.getElementById('accInputEmail');
            const passInput = document.getElementById('accInputPassword');
            const confirmInput = document.getElementById('accInputPasswordConfirm');
            if (emailInput) emailInput.value = '';
            if (passInput) passInput.value = '';
            if (confirmInput) confirmInput.value = '';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const modalEl = document.getElementById('addAccountantClientModal');
        if (modalEl) {
            modalEl.addEventListener('show.bs.modal', function () {
                clearAccModalFields();
                goToAccStep(1);
            });
            modalEl.addEventListener('shown.bs.modal', function () {
                setTimeout(clearAccModalFields, 100);
                setTimeout(clearAccModalFields, 400);
            });
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('action') === 'add-client') {
            if (modalEl) {
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }
        }
    });
</script>
@endpush
@endsection
