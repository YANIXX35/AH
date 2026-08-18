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
        border-radius: 24px;
        padding: 12px 18px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.02);
    }

    .header-search-input {
        width: 220px;
        transition: width .2s ease;
    }
    .header-search-input:focus {
        width: 260px;
    }

    .header-divider {
        width: 1px;
        align-self: stretch;
        background: #e2e8f0;
        margin: 2px 4px;
    }

    .header-action-group {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 9999px;
        padding: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .header-action-group .btn {
        white-space: nowrap;
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

    /* ============================================================ */
    /* MOBILE — surcharge locale garantie (indépendante du cache CSS externe) */
    /* ============================================================ */
    @media (max-width: 767.98px) {
        .soft-dashboard-body {
            min-height: auto;
            padding: 10px 8px;
        }
        .soft-dashboard-container {
            padding: 0;
            border-radius: 0;
            border: none;
            box-shadow: none;
            background: transparent;
        }

        /* Grille d'actions rapides : 3 colonnes icône + libellé, compacte */
        .mobile-quick-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        .mobile-quick-actions .btn {
            flex-direction: column;
            gap: 4px;
            height: 64px;
            padding: 6px 4px;
            font-size: 0.72rem;
            line-height: 1.1;
            white-space: normal;
        }
        .mobile-quick-actions .btn i,
        .mobile-quick-actions .btn svg {
            width: 18px;
            height: 18px;
        }

        .mobile-stat-card {
            padding: 12px 10px;
        }
        .mobile-stat-card .fs-3 {
            font-size: 1.5rem !important;
        }

        .mobile-section-card {
            padding: 14px;
            margin-bottom: 12px;
        }

        /* Barre d'actions du formulaire "Nouveau Client" : empilée en pleine largeur,
           bouton principal en premier, plutôt qu'une ligne qui déborde de l'écran */
        .wizard-footer-actions {
            flex-direction: column-reverse;
            align-items: stretch;
            gap: 10px;
        }
        .wizard-footer-actions > .wizard-footer-btn-group {
            flex-direction: column;
            width: 100%;
            gap: 10px;
        }
        .wizard-footer-actions .btn {
            width: 100%;
        }
    }
</style>
@endpush

@section('content')
@if(request('action') === 'add-client')
<div class="soft-dashboard-body animate__animated animate__fadeIn">
    <div class="soft-dashboard-container" style="background: linear-gradient(135deg, #f0f4ff 0%, #e8f0fe 50%, #f0f9ff 100%); border-radius: 32px; padding: 40px 20px;">
        <form id="addClientWizardForm" action="{{ route('commercial.clients.store') }}" method="POST" enctype="multipart/form-data" novalidate>
            @csrf

            {{-- Barre de progression pleine largeur --}}
            <div class="progress rounded-pill mb-4" style="height: 6px; background-color: rgba(99, 102, 241, 0.1);">
                <div class="progress-bar rounded-pill" role="progressbar" id="wizardProgressBar"
                     style="width: 50%; background: linear-gradient(90deg, #3b82f6, #6366f1); transition: width 0.4s ease;"></div>
            </div>

            <div class="d-flex align-items-center justify-content-center">
                <div class="w-100" style="max-width: 680px;">

                    {{-- En-tête centré --}}
                    <div class="text-center mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                             style="width:64px;height:64px;background:linear-gradient(135deg,#3b82f6,#6366f1); box-shadow: 0 8px 20px rgba(59, 130, 246, 0.2);">
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
                        <h2 class="fw-bold text-dark mb-1" id="stepTitleLabel" style="font-size:1.6rem; letter-spacing:-0.5px;">
                            Compte Client &amp; Crédentiels
                        </h2>
                        <p class="text-muted small mb-0">Créez le compte d'accès pour votre nouveau client PME</p>
                    </div>

                    {{-- Carte formulaire --}}
                    <div class="card border-0 shadow-lg rounded-4 overflow-hidden bg-white p-4 p-md-5">

                        {{-- Zone d'importation intelligente de fichier --}}
                        <div class="mb-4 p-3 bg-light rounded-4 border border-dashed border-primary text-center">
                            <div class="d-flex align-items-center justify-content-center gap-2 mb-1 text-primary fw-bold">
                                <i data-feather="file-text" style="width:18px; height:18px;"></i>
                                <span>Import &amp; Lecture Automatique de Fiche Entreprise</span>
                            </div>
                            <p class="text-muted small mb-2">
                                Importez un document (Word <code>.docx</code>, Excel <code>.xlsx</code>, <code>.pdf</code>, <code>.csv</code>, <code>.txt</code>). Les données reconnues pré-rempliront automatiquement les champs du formulaire ci-dessous. Les champs absents restent inchangés.
                            </p>
                            <div class="d-flex justify-content-center align-items-center gap-2 flex-wrap">
                                <input type="file" id="wizardDocInput" class="d-none" accept=".docx,.doc,.xlsx,.xls,.csv,.pdf,.txt" onchange="uploadAndParseDocument(this, 'addClientWizardForm')">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-4 fw-semibold" onclick="document.getElementById('wizardDocInput').click()">
                                    <i data-feather="upload" class="me-1" style="width:14px; height:14px;"></i> Parcourir et Importer Fichier...
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold" onclick="resetClientForm('addClientWizardForm')">
                                    <i data-feather="trash-2" class="me-1" style="width:14px; height:14px;"></i> Effacer le formulaire
                                </button>
                            </div>
                            <div id="parseStatusAlert" class="mt-2 d-none"></div>
                        </div>

                        {{-- Step 1 --}}
                        <div id="wizardStep1">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">
                                        👤 Nom complet du dirigeant
                                    </label>
                                    <input type="text" name="name" class="form-control form-control-lg rounded-3 border-0 bg-light"
                                           placeholder="Ex : Jean Kouassi">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">
                                        ✉️ Adresse Email (Identifiant)
                                    </label>
                                    <input type="email" name="email" class="form-control form-control-lg rounded-3 border-0 bg-light"
                                           placeholder="dirigeant@entreprise.ci">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">
                                        📱 Téléphone
                                    </label>
                                    <input type="text" name="phone" class="form-control form-control-lg rounded-3 border-0 bg-light"
                                           placeholder="+225 07 00 00 00 00">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">
                                        🔑 Mot de passe temporaire
                                    </label>
                                    <input type="password" name="password" class="form-control form-control-lg rounded-3 border-0 bg-light"
                                           placeholder="8 caractères minimum" minlength="8" value="Sitiame2026">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">
                                        🔑 Confirmer le mot de passe
                                    </label>
                                    <input type="password" name="password_confirmation" class="form-control form-control-lg rounded-3 border-0 bg-light"
                                           placeholder="Confirmer le mot de passe" value="Sitiame2026">
                                </div>
                            </div>
                        </div>

                        {{-- Step 2 --}}
                        <div id="wizardStep2" style="display:none;">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">
                                        🏢 Raison Sociale / Entreprise
                                    </label>
                                    <input type="text" name="company_name" class="form-control form-control-lg rounded-3 border-0 bg-light"
                                           placeholder="Ex : Ivoire Agro SARL">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">
                                        🏷️ Sigle / Nom commercial
                                    </label>
                                    <input type="text" name="company_sigle" class="form-control form-control-lg rounded-3 border-0 bg-light"
                                           placeholder="Ex : IAGRO">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">
                                        🔢 NIF / Matricule Fiscal
                                    </label>
                                    <input type="text" name="company_tax_id" class="form-control form-control-lg rounded-3 border-0 bg-light"
                                           placeholder="Numéro d'identification fiscale">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">
                                        🌿 Secteur d'activité
                                    </label>
                                    <select name="sector" class="form-select form-select-lg rounded-3 border-0 bg-light">
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
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">
                                        🔢 Numéro RCCM / SIRET
                                    </label>
                                    <input type="text" name="rccm" class="form-control form-control-lg rounded-3 border-0 bg-light"
                                           placeholder="Ex : CI-ABJ-2026-B-1234">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">
                                        📍 Ville
                                    </label>
                                    <input type="text" name="city" class="form-control form-control-lg rounded-3 border-0 bg-light"
                                           placeholder="Ex : Abidjan">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold text-dark">
                                        📍 Adresse complète
                                    </label>
                                    <input type="text" name="address" class="form-control form-control-lg rounded-3 border-0 bg-light"
                                           placeholder="Ex : Plateau Rue du Commerce">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">
                                        📎 Attestation DFE / NIF (Fichier)
                                    </label>
                                    <input type="file" name="company_logo" class="form-control form-control-lg rounded-3 border-0 bg-light" accept="image/*,.pdf,.doc,.docx">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">
                                        📎 Registre de commerce (Fichier)
                                    </label>
                                    <input type="file" name="trade_register" class="form-control form-control-lg rounded-3 border-0 bg-light" accept="image/*,.pdf,.doc,.docx">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold text-dark">
                                        🔑 Clé de licence (optionnel)
                                    </label>
                                    <input type="text" name="license_key" class="form-control form-control-lg rounded-3 border-0 bg-light"
                                           placeholder="Fournie par l'administrateur">
                                </div>
                            </div>
                        </div>

                        {{-- Footer actions --}}
                        <div class="wizard-footer-actions d-flex justify-content-between align-items-center mt-5 pt-4 border-top">
                            <a href="{{ route('commercial.dashboard') }}"
                               class="btn btn-light rounded-pill px-4 fw-semibold text-muted text-decoration-none">
                                ← Annuler
                            </a>
                            <div class="wizard-footer-btn-group d-flex gap-2">
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
                    <p class="text-center text-muted small mt-4 mb-0">
                        🎁 Le client bénéficiera automatiquement d'un <strong>essai gratuit de 30 jours</strong>
                    </p>
                </div>
            </div>
        </form>
    </div>
</div>
@else
<div class="soft-dashboard-body">
    <div class="soft-dashboard-container">
        
        {{-- ============================================================ --}}
        {{-- 💻 VERSION DESKTOP DU DASHBOARD                              --}}
        {{-- ============================================================ --}}
        <div class="d-none d-md-block">
            <!-- TOP HEADER BAR -->
            <div class="d-flex flex-column gap-3 mb-4">

                <!-- Rangée 1 : Logo & Navigation -->
                <div class="d-flex align-items-center gap-2 mockup-header-bar">
                    <div class="bg-primary text-white rounded-circle p-2 d-none d-sm-flex align-items-center justify-content-center flex-shrink-0" style="width:40px; height:40px;">
                        <i data-feather="grid" style="width:20px; height:20px;"></i>
                    </div>
                    <div class="d-flex align-items-center gap-1 bg-light rounded-pill p-1 border overflow-auto scrollbar-none flex-nowrap">
                        <a href="{{ route('commercial.dashboard') }}" class="pill-tab-btn pill-tab-btn-active text-decoration-none text-nowrap flex-shrink-0">
                            <i data-feather="layout" class="me-1" style="width:14px; height:14px;"></i> Tableau de bord
                        </a>
                        <a href="{{ route('commercial.portefeuille') }}" class="pill-tab-btn pill-tab-btn-inactive text-decoration-none text-nowrap flex-shrink-0">
                            <i data-feather="briefcase" class="me-1" style="width:14px; height:14px;"></i> Mon Portefeuille
                        </a>
                        <a href="{{ route('commercial.prospects') }}" class="pill-tab-btn pill-tab-btn-inactive text-decoration-none text-nowrap flex-shrink-0">
                            <i data-feather="users" class="me-1" style="width:14px; height:14px;"></i> Leads CRM ({{ $totalProspects }})
                        </a>
                        <a href="{{ route('commercial.showcase') }}" class="pill-tab-btn pill-tab-btn-inactive text-decoration-none text-nowrap flex-shrink-0">
                            <i data-feather="book-open" class="me-1" style="width:14px; height:14px;"></i> Kit Marketing
                        </a>
                        <a href="{{ route('commercial.prospections.index') }}" class="pill-tab-btn pill-tab-btn-inactive text-decoration-none text-nowrap flex-shrink-0">
                            <i data-feather="bar-chart-2" class="me-1" style="width:14px; height:14px;"></i> Prospection
                        </a>
                    </div>
                </div>

                <!-- Rangée 2 : Recherche, équipe, actions -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mockup-header-bar">
                    <div class="position-relative">
                        <i data-feather="search" class="position-absolute text-muted" style="left:14px; top:50%; transform:translateY(-50%); width:15px; height:15px;"></i>
                        <input type="text" class="form-control rounded-pill border-0 bg-light ps-5 pe-4 header-search-input" placeholder="Rechercher un client, un lead…" style="font-size:0.85rem;">
                    </div>

                    <div class="d-flex align-items-center gap-2 gap-lg-3 flex-wrap justify-content-end">
                        <!-- Avatar Stack (équipe) -->
                        <div class="d-none d-lg-flex align-items-center flex-shrink-0" title="Membres de l'équipe">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold border border-white" style="width:32px; height:32px; font-size:0.75rem; margin-right:-8px;">EK</div>
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center fw-bold border border-white" style="width:32px; height:32px; font-size:0.75rem; margin-right:-8px;">JK</div>
                            <div class="bg-dark text-white rounded-circle d-flex align-items-center justify-content-center fw-bold border border-white" style="width:32px; height:32px; font-size:0.75rem;">+{{ $totalClients }}</div>
                        </div>

                        <div class="header-divider d-none d-lg-block"></div>

                        <!-- Groupe d'actions -->
                        <div class="header-action-group flex-wrap">
                            <button type="button" class="btn btn-outline-success rounded-pill px-3 py-2 fw-bold text-xs" data-bs-toggle="modal" data-bs-target="#smartImportClientModal">
                                <i data-feather="upload-cloud" class="me-1" style="width:12px; height:12px;"></i> Importer
                            </button>
                            <button type="button" class="btn btn-outline-primary rounded-pill px-3 py-2 fw-bold text-xs" data-bs-toggle="modal" data-bs-target="#addProspectModal">
                                <i data-feather="user-plus" class="me-1" style="width:12px; height:12px;"></i> + Lead
                            </button>
                            <a href="{{ route('commercial.dashboard', ['action' => 'add-client']) }}" class="btn btn-primary rounded-pill px-3 py-2 fw-bold text-xs shadow-sm text-decoration-none">
                                <i data-feather="briefcase" class="me-1" style="width:12px; height:12px;"></i> + Client
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- VUE D'ENSEMBLE TABLEAU DE BORD COMMERCIAL (DESKTOP)          --}}
            {{-- ============================================================ --}}
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <a href="{{ route('commercial.portefeuille') }}" class="text-decoration-none">
                        <x-kpi-card label="Portefeuille Clients" value="{{ $totalClients }}" sub="Voir mon portefeuille →" colorClass="kpi-total" />
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('commercial.portefeuille') }}" class="text-decoration-none">
                        <x-kpi-card label="⌛ Essais en cours" value="{{ $activeTrials }}" sub="Période d'essai 30j" colorClass="kpi-trial" />
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('commercial.prospects') }}" class="text-decoration-none">
                        <x-kpi-card label="🎯 Leads CRM" value="{{ $totalProspects }}" sub="{{ $newProspects }} nouveaux leads" colorClass="kpi-converted" />
                    </a>
                </div>
                <div class="col-md-3">
                    <div class="portfolio-kpi-card kpi-total">
                        <div class="kpi-label">Taux de conversion</div>
                        <div class="kpi-number text-primary">{{ $conversionRate }}%</div>
                        <div class="kpi-sub">{{ $portfolioConverted }} convertis sur {{ $portfolioTrialExpired }} essais</div>
                    </div>
                </div>
            </div>

            {{-- SECTION ACTIVITÉ RÉCENTE COMMERCIAL --}}
            <div class="mockup-card p-4 mb-4 shadow-sm border border-light">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h5 fw-bold text-dark mb-0">⚡ Activité Récente &amp; Inscriptions</h3>
                    <a href="{{ route('commercial.portefeuille') }}" class="text-decoration-none small text-primary fw-semibold">Voir tout le portefeuille &rarr;</a>
                </div>
                <div class="row g-3">
                    @forelse($recentActivities as $activity)
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4 border d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-{{ $activity['type'] === 'client' ? 'primary' : 'success' }} text-white rounded-circle fw-bold d-flex align-items-center justify-content-center" style="width:36px; height:36px; font-size:0.8rem;">
                                        {{ strtoupper(substr($activity['name'], 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark small">{{ $activity['name'] }}</div>
                                        <div class="text-muted small" style="font-size:0.75rem;">
                                            {{ $activity['type'] === 'client' ? 'Client PME inscrit' : 'Prospect CRM ajouté' }}
                                        </div>
                                    </div>
                                </div>
                                <span class="badge bg-white text-muted border rounded-pill small">
                                    {{ $activity['date']->format('d M Y') }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="col-12 text-center text-muted py-3">
                            Aucune activité récente.
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

        {{-- ============================================================ --}}
        {{-- 📱 VERSION MOBILE-FIRST DU DASHBOARD (OPTIMISÉE SMARTPHONE) --}}
        {{-- ============================================================ --}}
        <div class="d-block d-md-none">
            <!-- HEADER MOBILE -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="h5 fw-bold text-dark mb-0">Bonjour {{ explode(' ', auth()->user()?->name ?? 'Commercial')[0] }} 👋</h2>
                    <p class="text-muted small mb-0">Portail Commercial & Marketing</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('notifications.index') }}" class="btn btn-light rounded-circle p-2 d-flex align-items-center justify-content-center" style="width:36px; height:36px; border: 1px solid #e2e8f0;">
                        <i data-feather="bell" style="width:16px; height:16px;"></i>
                    </a>
                    <a href="{{ route('profile') }}" class="d-block">
                        <img src="{{ (Auth::user()?->avatar) ? route('user.avatar', Auth::user()) : asset('images/sitiam.png') }}" class="rounded-circle border" style="width:36px; height:36px; object-fit: cover;" alt="Profil" />
                    </a>
                </div>
            </div>

            <!-- BOUTONS D'ACTIONS RAPIDES -->
            <div class="mobile-quick-actions mb-3">
                <button type="button" class="btn btn-outline-success rounded-4" data-bs-toggle="modal" data-bs-target="#smartImportClientModal">
                    <i data-feather="upload-cloud"></i>
                    <span>Importer</span>
                </button>
                <button type="button" class="btn btn-outline-primary rounded-4" data-bs-toggle="modal" data-bs-target="#addProspectModal">
                    <i data-feather="plus"></i>
                    <span>Lead CRM</span>
                </button>
                <a href="{{ route('commercial.dashboard', ['action' => 'add-client']) }}" class="btn btn-primary rounded-4 text-decoration-none">
                    <i data-feather="user-plus"></i>
                    <span>Client</span>
                </a>
            </div>

            <!-- STATISTIQUES (2 PAR LIGNE) -->
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <div class="mobile-stat-card p-3 bg-white border rounded-4 shadow-sm text-center h-100">
                        <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">Portefeuille</div>
                        <div class="fs-3 fw-extrabold text-primary my-1">{{ $totalClients }}</div>
                        <div class="text-muted" style="font-size: 0.7rem;">Clients parrainés</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="mobile-stat-card p-3 bg-white border rounded-4 shadow-sm text-center h-100">
                        <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">Essais actifs</div>
                        <div class="fs-3 fw-extrabold text-warning my-1">{{ $activeTrials }}</div>
                        <div class="text-muted" style="font-size: 0.7rem;">Essais en cours</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="mobile-stat-card p-3 bg-white border rounded-4 shadow-sm text-center h-100">
                        <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">Leads CRM</div>
                        <div class="fs-3 fw-extrabold text-success my-1">{{ $totalProspects }}</div>
                        <div class="text-muted" style="font-size: 0.7rem;">Leads CRM</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="mobile-stat-card p-3 bg-white border rounded-4 shadow-sm text-center h-100">
                        <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">Conversion</div>
                        <div class="fs-3 fw-extrabold text-info my-1">{{ $conversionRate }}%</div>
                        <div class="text-muted" style="font-size: 0.7rem;">Taux Conversion</div>
                    </div>
                </div>
            </div>

            <!-- PORTEFEUILLE CLIENTS (AFFICHAGE DIRECT CARTES) -->
            <div class="mobile-section-card card p-3 mb-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h6 fw-bold text-dark mb-0">🏢 Mon Portefeuille</h3>
                    <a href="{{ route('commercial.portefeuille') }}" class="text-decoration-none small text-primary fw-semibold">Voir tout &rarr;</a>
                </div>
                <div class="d-flex flex-column gap-2">
                    @forelse($clients->take(3) as $client)
                        @php
                            $isTrialActive = false;
                            $daysLeft = 0;
                            if ($client && ($client->is_premium ?? false) && !empty($client->premium_ends_at)) {
                                try {
                                    $endsAt = $client->premium_ends_at instanceof \Carbon\Carbon
                                        ? $client->premium_ends_at
                                        : \Carbon\Carbon::parse($client->premium_ends_at);
                                    $isTrialActive = $endsAt->isFuture();
                                    $daysLeft = $isTrialActive ? (int) floor(now()->diffInDays($endsAt)) : 0;
                                } catch (\Throwable $e) {
                                    $isTrialActive = false;
                                    $daysLeft = 0;
                                }
                            }
                        @endphp
                        <div class="p-3 bg-light rounded-3 border d-flex flex-column gap-2">
                            <div class="d-flex align-items-start gap-2">
                                <div class="bg-primary text-white rounded-circle fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px; height:36px; font-size:0.75rem;">
                                    {{ strtoupper(substr($client->name ?? 'PME', 0, 2)) }}
                                </div>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="fw-bold text-dark small text-truncate">{{ $client->name }}</div>
                                    <div class="text-muted small text-truncate" style="font-size:0.7rem;">{{ $client->company_name ?? '—' }}</div>
                                </div>
                                <div class="flex-shrink-0">
                                    @if($isTrialActive)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 text-nowrap" style="font-size: 0.65rem;">
                                            {{ $daysLeft }}j
                                        </span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 text-nowrap" style="font-size: 0.65rem;">
                                            Expiré
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-1">
                                <span class="text-muted" style="font-size:0.7rem;">Créé le {{ $client->created_at->format('d/m/Y') }}</span>
                                <div class="d-flex gap-1.5">
                                    <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-2.5 py-1 text-xs" data-bs-toggle="modal" data-bs-target="#editClientModalMobile{{ $client->id }}">
                                        Modifier
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Mobile Edit Client Modal -->
                        <div class="modal fade" id="editClientModalMobile{{ $client->id }}" data-bs-backdrop="static" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                    <form action="{{ route('commercial.clients.update', $client->id) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header border-0 bg-dark text-white p-3">
                                            <h5 class="modal-title fw-bold text-white fs-6">Modifier — {{ $client->name }}</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-3">
                                            <div class="d-flex flex-column gap-3">
                                                <div>
                                                    <label class="form-label small fw-semibold text-dark mb-1">Nom complet</label>
                                                    <input type="text" name="name" class="form-control rounded-3" value="{{ old('name', $client->name) }}">
                                                </div>
                                                <div>
                                                    <label class="form-label small fw-semibold text-dark mb-1">Adresse Email</label>
                                                    <input type="email" name="email" class="form-control rounded-3" value="{{ old('email', $client->email) }}">
                                                </div>
                                                <div>
                                                    <label class="form-label small fw-semibold text-dark mb-1">Téléphone</label>
                                                    <input type="text" name="phone" class="form-control rounded-3" value="{{ old('phone', $client->phone) }}">
                                                </div>
                                                <div>
                                                    <label class="form-label small fw-semibold text-dark mb-1">Raison Sociale</label>
                                                    <input type="text" name="company_name" class="form-control rounded-3" value="{{ old('company_name', $client->company_name) }}">
                                                </div>
                                                <div>
                                                    <label class="form-label small fw-semibold text-dark mb-1">Secteur</label>
                                                    <input type="text" name="sector" class="form-control rounded-3" value="{{ old('sector', $client->sector) }}">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-0 p-3 pt-0">
                                            <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Annuler</button>
                                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Enregistrer</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted small py-3 bg-light rounded-3 border">
                            Aucun client parrainé.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- DERNIERS LEADS CRM -->
            <div class="mobile-section-card card p-3 mb-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h6 fw-bold text-dark mb-0">🎯 Derniers Leads CRM</h3>
                    <a href="{{ route('commercial.prospects') }}" class="text-decoration-none small text-primary fw-semibold">Voir tout &rarr;</a>
                </div>
                <div class="d-flex flex-column gap-2">
                    @forelse($prospects->take(3) as $prospect)
                        <div class="p-3 bg-light rounded-3 border d-flex flex-column gap-2">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="fw-bold text-dark small">{{ $prospect->name }}</div>
                                    <div class="text-muted small" style="font-size:0.7rem;">{{ $prospect->company_name ?? '—' }}</div>
                                </div>
                                <span class="badge {{ $prospect->status_badge_class }} rounded-pill px-2 py-1" style="font-size: 0.65rem;">
                                    {{ $prospect->status_label }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-1">
                                <span class="badge bg-primary text-white rounded-pill px-2 py-0.5" style="font-size: 0.65rem;">{{ $prospect->need_label }}</span>
                                <span class="text-muted" style="font-size:0.7rem;">{{ $prospect->created_at->format('d/m/Y') }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted small py-3 bg-light rounded-3 border">
                            Aucun lead CRM disponible.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- ASSISTANT IA MOBILE -->
            <div class="mobile-section-card card p-3 mb-3">
                <h3 class="h6 fw-bold text-dark mb-1">🤖 Assistant IA</h3>
                <p class="text-muted small mb-3">Bonjour {{ explode(' ', auth()->user()?->name ?? 'Commercial')[0] }}. Que puis-je analyser pour vous ?</p>
                <form class="m-0" onsubmit="event.preventDefault(); window.openAdminGlobalChat && window.openAdminGlobalChat(document.getElementById('mobileAiMiniInput').value); document.getElementById('mobileAiMiniInput').value='';">
                    <div class="bg-light p-2 rounded-3 border">
                        <input id="mobileAiMiniInput" type="text" class="form-control border-0 bg-transparent p-0 text-sm mb-2" placeholder="Posez votre question à l'IA..." style="box-shadow: none; font-size: 0.85rem;">
                        <div class="d-flex justify-content-end align-items-center pt-1">
                            <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-bold text-xs">
                                Envoyer &rarr;
                            </button>
                        </div>
                    </div>
                </form>
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
@endif


<!-- Modal Smart Import & Inscription Client -->
<div class="modal fade" id="smartImportClientModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form id="smartClientModalForm" action="{{ route('commercial.clients.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header border-0 bg-dark text-white p-4">
                    <div>
                        <span class="badge bg-success text-white rounded-pill px-3 py-1 mb-2 fw-semibold">LECTURE ET EXTRACTION IA</span>
                        <h4 class="modal-title fw-bold text-white mb-0">📂 Importer &amp; Inscrire un Client PME</h4>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4" style="max-height: 70vh; overflow-y: auto;">
                    {{-- Zone Dropzone d'importation --}}
                    <div class="p-4 bg-light rounded-4 border border-dashed border-success text-center mb-4">
                        <div class="d-flex align-items-center justify-content-center gap-2 mb-2 text-success fw-bold">
                            <i data-feather="file-text" style="width:24px; height:24px;"></i>
                            <span class="fs-6">Sélectionnez un document d'entreprise (Word, Excel, PDF, Text, CSV)</span>
                        </div>
                        <p class="text-muted small mb-3">
                            L'outil lit le document et remplit automatiquement les champs ci-dessous (Nom, Email, Téléphone, NIF, RCCM, Secteur...). Les champs non trouvés restent vierges.
                        </p>
                        <div class="d-flex justify-content-center align-items-center gap-2 flex-wrap">
                            <input type="file" id="modalDocInput" class="d-none" accept=".docx,.doc,.xlsx,.xls,.csv,.pdf,.txt" onchange="uploadAndParseDocument(this, 'smartClientModalForm')">
                            <button type="button" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" onclick="document.getElementById('modalDocInput').click()">
                                <i data-feather="upload-cloud" class="me-1" style="width:16px; height:16px;"></i> Choisir et analyser un fichier
                            </button>
                            <button type="button" class="btn btn-outline-danger rounded-pill px-3 fw-semibold" onclick="resetClientForm('smartClientModalForm')">
                                <i data-feather="trash-2" class="me-1" style="width:14px; height:14px;"></i> Supprimer / Effacer tout
                            </button>
                        </div>
                        <div id="parseStatusAlertModal" class="mt-3 d-none"></div>
                    </div>

                    <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">👤 Informations du Dirigeant</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Nom complet du dirigeant <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control rounded-3" placeholder="Ex: Jean Kouassi" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Adresse Email (Identifiant) <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control rounded-3" placeholder="dirigeant@entreprise.ci" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Numéro de Téléphone</label>
                            <input type="text" name="phone" class="form-control rounded-3" placeholder="+225 07 00 00 00 00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Mot de passe temporaire <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control rounded-3" placeholder="8 caractères minimum" minlength="8" value="Sitiame2026" required>
                        </div>
                    </div>

                    <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">🏢 Informations de l'Entreprise</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Raison Sociale / Entreprise <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control rounded-3" placeholder="Ex: Ivoire Agro SARL" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Sigle / Nom commercial</label>
                            <input type="text" name="company_sigle" class="form-control rounded-3" placeholder="Ex: IAGRO">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">NIF / Matricule Fiscal</label>
                            <input type="text" name="company_tax_id" class="form-control rounded-3" placeholder="Numéro d'identification fiscale">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Numéro RCCM / SIRET</label>
                            <input type="text" name="rccm" class="form-control rounded-3" placeholder="Ex : CI-ABJ-2026-B-1234">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Secteur d'activité</label>
                            <select name="sector" class="form-select rounded-3">
                                <option value="">Choisir un secteur...</option>
                                <option value="Agroalimentaire">Agroalimentaire</option>
                                <option value="Commerce & Distribution">Commerce &amp; Distribution</option>
                                <option value="BTP & Construction">BTP &amp; Construction</option>
                                <option value="Services aux entreprises">Services aux entreprises</option>
                                <option value="Technologies / IT">Technologies / IT</option>
                                <option value="Transport & Logistique">Transport &amp; Logistique</option>
                                <option value="Santé">Santé</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Ville</label>
                            <input type="text" name="city" class="form-control rounded-3" placeholder="Ex: Abidjan">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold text-dark">Adresse complète</label>
                            <input type="text" name="address" class="form-control rounded-3" placeholder="Ex: Plateau Rue du Commerce">
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-outline-danger rounded-pill px-4" onclick="resetClientForm('smartClientModalForm')">
                        <i data-feather="trash-2" class="me-1" style="width:14px; height:14px;"></i> Supprimer la saisie
                    </button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold">✓ Enregistrer le Client</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    async function uploadAndParseDocument(inputElement, targetFormId = 'smartClientModalForm') {
        const file = inputElement.files[0];
        if (!file) return;

        const alertBox = targetFormId === 'addClientWizardForm'
            ? document.getElementById('parseStatusAlert')
            : document.getElementById('parseStatusAlertModal');

        if (alertBox) {
            alertBox.className = 'alert alert-info py-2 px-3 rounded-3 small mb-3 align-items-center d-flex justify-content-center gap-2';
            alertBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Analyse et lecture automatique du document en cours...';
            alertBox.classList.remove('d-none');
        }

        const formData = new FormData();
        formData.append('document', file);

        try {
            const response = await fetch('{{ route("commercial.parseCompanyDocument") }}', {
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

            const targetForm = document.getElementById(targetFormId) || document.forms[0];

            const fieldMapping = {
                'name': 'name',
                'email': 'email',
                'phone': 'phone',
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
                    alertBox.innerHTML = `Aucun champ d'entreprise n'a été automatiquement reconnu dans <em>${json.filename}</em>. Vous pouvez remplir les champs manuellement.`;
                }
            }
        } catch (error) {
            if (alertBox) {
                alertBox.className = 'alert alert-danger py-2 px-3 rounded-3 small mb-3';
                alertBox.innerHTML = `<strong>⚠️ Erreur :</strong> ${error.message}`;
            }
        }
    }

    function resetClientForm(targetFormId = 'smartClientModalForm') {
        const form = document.getElementById(targetFormId);
        if (form) {
            form.reset();
            form.querySelectorAll('.is-valid').forEach(el => el.classList.remove('is-valid'));
        }
        const alertBox = targetFormId === 'addClientWizardForm'
            ? document.getElementById('parseStatusAlert')
            : document.getElementById('parseStatusAlertModal');
        if (alertBox) {
            alertBox.classList.add('d-none');
        }
    }

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
        if (action === 'add-prospect') {
            const modal = new bootstrap.Modal(document.getElementById('addProspectModal'));
            modal.show();
        } else if (action === 'import-client') {
            const modal = new bootstrap.Modal(document.getElementById('smartImportClientModal'));
            modal.show();
        }

        const growthCanvas = document.getElementById('clientGrowthChart');
        if (growthCanvas && window.Chart) {
            new Chart(growthCanvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: @json($growthLabels ?? []),
                    datasets: [{
                        label: 'Clients cumulés',
                        data: @json($growthData ?? []),
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.08)',
                        tension: 0.35,
                        fill: true,
                        pointRadius: 3,
                        pointBackgroundColor: '#3b82f6',
                    }]
                },
                options: {
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }
    });
</script>


@endpush
@endsection
