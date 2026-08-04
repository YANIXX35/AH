@extends('layouts.app')

@section('title', 'Dossiers clients | Cabinet')
@section('page_title', 'Dossiers clients')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3" @if(request('action') === 'add-client') style="display: none !important;" @endif>
    <div>
        <h1 class="h3 mb-1"><strong>Dossiers</strong> clients</h1>
        <p class="text-muted mb-0">Recherchez une entreprise et ouvrez sa fiche pour accéder aux outils.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-primary btn-sm fw-semibold flex-fill flex-md-grow-0" data-bs-toggle="modal" data-bs-target="#addAccountantClientModal">
            <i data-feather="plus-circle" class="me-1" style="width:16px; height:16px;"></i> Ajouter Client / Entreprise
        </button>
        <a href="{{ route('accountant.dashboard') }}" class="btn btn-outline-secondary btn-sm flex-fill flex-md-grow-0 text-center">Tableau de bord cabinet</a>
    </div>
</div>

@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show rounded-3 mb-3 border-0 shadow-sm" role="alert">
        <i data-feather="check-circle" class="me-2 text-success"></i>
        {{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<form method="get" action="{{ route('accountant.clients.index') }}" class="row g-2 mb-3" @if(request('action') === 'add-client') style="display: none !important;" @endif>
    <div class="col-md-6 col-lg-4">
        <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Nom, société, e-mail…">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary">Rechercher</button>
    </div>
</form>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4" @if(request('action') === 'add-client') style="display: none !important;" @endif>
    <div class="card-body p-0">
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle mb-0" style="min-width: 750px;">
                <thead class="bg-light text-slate-700 text-uppercase fs-8 fw-bold border-bottom">
                    <tr>
                        <th class="py-3 px-3 text-center" style="width: 50px;">#</th>
                        <th class="py-3 px-3">Entreprise / Raison sociale</th>
                        <th class="py-3 px-3">Responsable Client</th>
                        <th class="py-3 px-3">E-mail pro</th>
                        <th class="py-3 px-3 text-center">Inscription</th>
                        <th class="py-3 px-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @php $index = 1; @endphp
                @forelse($enterpriseGroups as $group)
                    @foreach($group['users'] as $u)
                        <tr style="height: 60px;">
                            <td class="py-2.5 px-3 text-center fw-bold text-muted small align-middle">{{ $index++ }}</td>
                            <td class="py-2.5 px-3 align-middle">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-circle bg-primary-subtle text-primary rounded-2 d-flex align-items-center justify-content-center fw-bold small" style="width: 32px; height: 32px; flex-shrink: 0;">
                                        {{ strtoupper(substr($group['company_name'] ?? 'E', 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark fs-7 mb-0">{{ $group['company_name'] }}</div>
                                        @if(!empty($group['company_tax_id']))
                                            <span class="badge bg-slate-100 text-slate-600 border py-0 px-1.5" style="font-size:10px;">NIF: {{ $group['company_tax_id'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="py-2.5 px-3 align-middle">
                                <div class="fw-semibold text-dark fs-7">{{ $u->name }}</div>
                                <span class="badge bg-primary-subtle text-primary py-0 px-1.5" style="font-size: 10px;">Responsable Client</span>
                            </td>
                            <td class="py-2.5 px-3 text-slate-600 font-mono small align-middle">{{ $u->email }}</td>
                            <td class="py-2.5 px-3 text-center text-slate-500 small align-middle">{{ $u->created_at?->format('d/m/Y') }}</td>
                            <td class="py-2.5 px-3 text-end align-middle">
                                <div class="d-inline-flex gap-1 align-items-center">
                                    <a href="{{ route('accountant.clients.show', $u) }}" class="btn btn-xs btn-primary rounded-pill px-2.5 fw-semibold">
                                        <i data-feather="folder" class="me-1" style="width:12px; height:12px;"></i> Accéder au dossier
                                    </a>
                                    <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-2" data-bs-toggle="modal" data-bs-target="#editAccountantClientModal{{ $u->id }}" title="Modifier">
                                        <i data-feather="edit-2" style="width:12px; height:12px;"></i>
                                    </button>
                                    <form action="{{ route('accountant.clients.destroy', $u->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce compte dossier client ? Cette action me sera irréversible.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger rounded-pill px-2" title="Supprimer">
                                            <i data-feather="trash-2" style="width:12px; height:12px;"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="6" class="p-5 text-center text-muted">
                            <i data-feather="inbox" class="mb-2 text-slate-300" style="width: 40px; height: 40px;"></i>
                            <div class="fw-semibold fs-6">Aucun dossier client trouvé.</div>
                            <p class="small text-muted mb-0">Cliquez sur le bouton "Ajouter Client / Entreprise" pour créer un nouveau dossier.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Vue mobile : cartes empilées plutôt qu'un tableau de 750px forcé en scroll --}}
        <div class="d-block d-md-none p-3">
            @php $mobileIndex = 1; @endphp
            @forelse($enterpriseGroups as $group)
                @foreach($group['users'] as $u)
                    <div class="p-3 bg-light rounded-3 border d-flex flex-column gap-2 mb-3">
                        <div class="d-flex align-items-start gap-2">
                            <div class="bg-primary-subtle text-primary rounded-2 d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width:36px; height:36px; font-size:0.75rem;">
                                {{ strtoupper(substr($group['company_name'] ?? 'E', 0, 2)) }}
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="fw-bold text-dark small text-truncate">{{ $group['company_name'] }}</div>
                                @if(!empty($group['company_tax_id']))
                                    <span class="badge bg-secondary-subtle text-secondary border" style="font-size:10px;">NIF: {{ $group['company_tax_id'] }}</span>
                                @endif
                            </div>
                            <span class="text-muted flex-shrink-0" style="font-size:0.7rem;">#{{ $mobileIndex++ }}</span>
                        </div>

                        <div class="border-top pt-2">
                            <div class="fw-semibold text-dark small">{{ $u->name }}</div>
                            <span class="badge bg-primary-subtle text-primary" style="font-size: 10px;">Responsable Client</span>
                            <div class="text-muted small text-truncate mt-1">{{ $u->email }}</div>
                            <div class="text-muted small">Inscrit le {{ $u->created_at?->format('d/m/Y') }}</div>
                        </div>

                        <div class="d-flex gap-2 pt-1">
                            <a href="{{ route('accountant.clients.show', $u) }}" class="btn btn-xs btn-primary rounded-pill flex-fill">
                                <i data-feather="folder" class="me-1" style="width:12px; height:12px;"></i> Accéder au dossier
                            </a>
                            <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#editAccountantClientModal{{ $u->id }}" title="Modifier">
                                <i data-feather="edit-2" style="width:12px; height:12px;"></i>
                            </button>
                            <form action="{{ route('accountant.clients.destroy', $u->id) }}" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce compte dossier client ? Cette action sera irréversible.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-outline-danger rounded-pill px-3" title="Supprimer">
                                    <i data-feather="trash-2" style="width:12px; height:12px;"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            @empty
                <div class="text-center text-muted py-4">
                    <i data-feather="inbox" class="mb-2 text-slate-300" style="width: 36px; height: 36px;"></i>
                    <div class="fw-semibold small">Aucun dossier client trouvé.</div>
                    <p class="small text-muted mb-0">Cliquez sur "Ajouter Client / Entreprise" pour créer un nouveau dossier.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

{{-- Modals de modification sorties du tableau HTML --}}
@foreach($enterpriseGroups as $group)
    @foreach($group['users'] as $u)
        <div class="modal fade text-start" id="editAccountantClientModal{{ $u->id }}" data-bs-backdrop="static" tabindex="-1" aria-labelledby="editAccountantClientModalLabel{{ $u->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header border-0 bg-primary text-white p-3 px-4">
                        <h5 class="modal-title fw-bold text-white mb-0" id="editAccountantClientModalLabel{{ $u->id }}">Modifier le dossier client</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
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
                                    <input type="file" name="company_logo" class="form-control rounded-3 py-2" accept="image/*,.pdf,.doc,.docx">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Registre de commerce (PDF/image)</label>
                                    <input type="file" name="trade_register" class="form-control rounded-3 py-2" accept="image/*,.pdf,.doc,.docx">
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
    @endforeach
@endforeach

<!-- Add Accountant Client Modal 2-STEP WIZARD -->
<div class="modal fade text-start" id="addAccountantClientModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="addAccountantClientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered my-2" style="max-width: 850px; width: 95%;">
        <div class="modal-content border-0 shadow-2xl rounded-4 overflow-hidden d-flex flex-column" style="border-radius: 16px; max-height: 80vh; height: 80vh;">
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

            <form action="{{ route('accountant.clients.store') }}" method="POST" enctype="multipart/form-data" id="accWizardForm" autocomplete="off" novalidate class="d-flex flex-column m-0 flex-grow-1 overflow-hidden" style="min-height: 0; height: 100%;">
                @csrf
                
                <!-- Fake inputs to trap aggressive browser autofills -->
                <input type="text" name="fake_usernameremembered" style="display:none" tabindex="-1">
                <input type="password" name="fake_passwordremembered" style="display:none" tabindex="-1">

                <!-- 2. Corps Scrollable uniquement (flex: 1 1 auto; overflow-y: auto) -->
                <div class="modal-body p-4 bg-white flex-grow-1" id="accWizardModalBody" style="min-height: 0; overflow-y: auto !important; scroll-behavior: smooth;">

                    @if($errors->any())
                        <div class="alert alert-danger rounded-3 mb-4">
                            <div class="fw-bold mb-1"><i data-feather="alert-triangle" class="me-1" style="width:16px; height:16px;"></i> Le dossier n'a pas pu être enregistré :</div>
                            <ul class="mb-0 ps-3 small">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Zone d'importation intelligente de fichier --}}
                    <div class="mb-4 p-3 bg-light rounded-4 border border-dashed border-success text-center">
                        <div class="d-flex align-items-center justify-content-center gap-2 mb-1 text-success fw-bold">
                            <i data-feather="file-text" style="width:18px; height:18px;"></i>
                            <span>Import &amp; Lecture Automatique de Fiche Entreprise (Word, Excel, PDF, Text)</span>
                        </div>
                        <p class="text-muted small mb-2">
                            Importez la fiche PME. L'IA extrait automatiquement les informations et complète le dossier. Les champs absents restent vierges.
                        </p>
                        <div class="d-flex justify-content-center align-items-center gap-2 flex-wrap">
                            <input type="file" id="accWizardDocInput" class="d-none" accept=".docx,.doc,.xlsx,.xls,.csv,.pdf,.txt" onchange="uploadAndParseAccountantIndexDocument(this)">
                            <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-4 fw-semibold" onclick="document.getElementById('accWizardDocInput').click()">
                                <i data-feather="upload" class="me-1" style="width:14px; height:14px;"></i> Parcourir et Importer Fichier...
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold" onclick="clearAccModalFields()">
                                <i data-feather="trash-2" class="me-1" style="width:14px; height:14px;"></i> Effacer le formulaire
                            </button>
                        </div>
                        <div id="parseStatusAlertAccIndex" class="mt-2 d-none"></div>
                    </div>

                    <!-- STEP 1 : COMPTE RESPONSABLE -->
                    <div id="accWizardStep1">
                        <div class="alert alert-light border rounded-3 mb-4 text-muted small py-2.5 px-3">
                            <i data-feather="info" class="me-1 text-primary"></i> Coordonnées du responsable qui aura accès à cet espace client.
                        </div>

                        <div class="mb-3.5">
                            <label class="form-label small fw-semibold text-slate-700 mb-1">Nom complet du responsable</label>
                            <input type="text" name="name" class="form-control rounded-3 py-2.5 px-3 border-slate-200" placeholder="ex: Jean Dupont" autocomplete="off">
                        </div>
                        <div class="row g-3 mb-3.5">
                            <div class="col-md-7">
                                <label class="form-label small fw-semibold text-slate-700 mb-1">Adresse E-mail pro</label>
                                <input type="email" name="email" id="accInputEmail" class="form-control rounded-3 py-2.5 px-3 border-slate-200" placeholder="ex: client@entreprise.ci" autocomplete="new-email" value="">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small fw-semibold text-slate-700 mb-1">Téléphone</label>
                                <input type="text" name="phone" class="form-control rounded-3 py-2.5 px-3 border-slate-200" placeholder="ex: +225 0700000000" autocomplete="off">
                            </div>
                        </div>
                        <div class="row g-3 mb-2">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-slate-700 mb-1">Mot de passe temporaire</label>
                                <input type="password" name="password" id="accInputPassword" class="form-control rounded-3 py-2.5 px-3 border-slate-200" placeholder="Min. 8 caractères" autocomplete="new-password" value="">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-slate-700 mb-1">Confirmer mot de passe</label>
                                <input type="password" name="password_confirmation" id="accInputPasswordConfirm" class="form-control rounded-3 py-2.5 px-3 border-slate-200" placeholder="Confirmer mot de passe" autocomplete="new-password" value="">
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
                                <input type="file" name="company_logo" class="form-control rounded-3 py-2.5 px-3 border-slate-200" accept="image/*,.pdf,.doc,.docx">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-slate-700 mb-1">Registre de commerce (Fichier)</label>
                                <input type="file" name="trade_register" class="form-control rounded-3 py-2.5 px-3 border-slate-200" accept="image/*,.pdf,.doc,.docx">
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
        const alertBox = document.getElementById('parseStatusAlertAccIndex');
        if (alertBox) {
            alertBox.classList.add('d-none');
        }
    }

    async function uploadAndParseAccountantIndexDocument(inputElement) {
        const file = inputElement.files[0];
        if (!file) return;

        const alertBox = document.getElementById('parseStatusAlertAccIndex');

        if (alertBox) {
            alertBox.className = 'alert alert-info py-2 px-3 rounded-3 small mb-3 align-items-center d-flex justify-content-center gap-2';
            alertBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Extraction et lecture automatique du document en cours...';
            alertBox.classList.remove('d-none');
        }

        const formData = new FormData();
        formData.append('document', file);

        try {
            const response = await fetch('{{ route("accountant.parseCompanyDocument") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: formData
            });

            const json = await response.json();

            if (!response.ok || !json.ok) {
                throw new Error(json.message || 'Erreur lors de l’analyse du fichier.');
            }

            const data = json.extracted || {};
            let filledCount = 0;

            const targetForm = document.getElementById('accWizardForm');

            const fieldMapping = {
                'name': 'name',
                'email': 'email',
                'phone': 'phone',
                'password': 'password',
                'company_name': 'company_name',
                'company_sigle': 'company_sigle',
                'company_tax_id': 'company_tax_id',
                'rccm': 'rccm',
                'sector': 'sector',
                'city': 'city',
                'address': 'address'
            };

            for (const [key, fieldName] of Object.entries(fieldMapping)) {
                if (data[key] !== undefined && data[key] !== null && data[key] !== '') {
                    const el = targetForm ? targetForm.querySelector(`[name="${fieldName}"]`) : null;
                    if (el) {
                        el.value = data[key];
                        el.classList.add('is-valid');
                        filledCount++;
                    }
                }
            }

            if (alertBox) {
                if (filledCount > 0) {
                    alertBox.className = 'alert alert-success py-2 px-3 rounded-3 small mb-3';
                    alertBox.innerHTML = `<strong>✓ ${filledCount} champ(s) identifié(s) et pré-rempli(s) !</strong> (${json.filename})`;
                } else {
                    alertBox.className = 'alert alert-warning py-2 px-3 rounded-3 small mb-3';
                    alertBox.innerHTML = `Aucun champ d'entreprise n'a été reconnu dans <em>${json.filename}</em>. Vous pouvez remplir les champs manuellement.`;
                }
            }
        } catch (error) {
            if (alertBox) {
                alertBox.className = 'alert alert-danger py-2 px-3 rounded-3 small mb-3';
                alertBox.innerHTML = `<strong>⚠️ Erreur :</strong> ${error.message}`;
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const modalEl = document.getElementById('addAccountantClientModal');
        const hasValidationErrors = @json($errors->any());

        // Après un échec de validation, on rouvre le modal tel quel (pas de reset,
        // pas de retour forcé à l'étape 1) pour que l'utilisateur voie le message
        // d'erreur affiché en haut du formulaire au lieu d'un échec silencieux.
        let skipResetOnNextShow = hasValidationErrors;

        if (modalEl) {
            modalEl.addEventListener('show.bs.modal', function () {
                if (skipResetOnNextShow) {
                    skipResetOnNextShow = false;
                    return;
                }
                clearAccModalFields();
                goToAccStep(1);
            });
            modalEl.addEventListener('shown.bs.modal', function () {
                if (hasValidationErrors) return;
                setTimeout(clearAccModalFields, 100);
                setTimeout(clearAccModalFields, 400);
            });
        }

        const urlParams = new URLSearchParams(window.location.search);
        if ((urlParams.get('action') === 'add-client' || hasValidationErrors) && modalEl) {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
            // La plupart des champs (dont les fichiers) sont à l'étape 2 : on l'ouvre
            // directement dessus quand une erreur de validation vient d'avoir lieu.
            if (hasValidationErrors) {
                goToAccStep(2);
            }
        }
    });
</script>
@endpush
@endsection
