@extends('layouts.minimal')

@section('title', 'Inscrire un client PME | SITIAME CAPITAL')

@push('styles')
<style>
    body {
        background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 40%, #ede9fe 100%);
        min-height: 100vh;
    }

    .create-client-page {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 32px 16px;
    }

    .create-client-card {
        width: 100%;
        max-width: 700px;
        background: #ffffff;
        border-radius: 28px;
        box-shadow: 0 24px 60px rgba(99, 102, 241, 0.12), 0 4px 16px rgba(0,0,0,0.06);
        overflow: hidden;
    }

    /* Barre de progression */
    .wizard-progress {
        height: 5px;
        background: #e0e7ff;
    }
    .wizard-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #3b82f6, #6366f1);
        transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* En-tête de la carte */
    .create-client-header {
        padding: 36px 40px 28px;
        text-align: center;
        border-bottom: 1px solid #f1f5f9;
    }

    .create-client-icon {
        width: 68px;
        height: 68px;
        margin: 0 auto 16px;
        background: linear-gradient(135deg, #3b82f6, #6366f1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 24px rgba(99, 102, 241, 0.3);
    }

    .step-badge {
        display: inline-block;
        background: linear-gradient(135deg, #3b82f6, #6366f1);
        color: #fff;
        border-radius: 999px;
        padding: 5px 18px;
        font-size: 0.76rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        margin-bottom: 12px;
    }

    .create-client-title {
        font-size: 1.65rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 6px;
    }

    .create-client-subtitle {
        color: #64748b;
        font-size: 0.9rem;
        margin: 0;
    }

    /* Corps du formulaire */
    .create-client-body {
        padding: 36px 40px;
    }

    .form-floating-custom label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 6px;
        display: block;
    }

    .form-input-styled {
        width: 100%;
        padding: 14px 18px;
        border: 1.5px solid #e2e8f0;
        border-radius: 14px;
        font-size: 0.95rem;
        background: #f8fafc;
        color: #0f172a;
        transition: border-color 0.2s, box-shadow 0.2s;
        outline: none;
    }

    .form-input-styled:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
        background: #fff;
    }

    .form-input-styled::placeholder {
        color: #94a3b8;
    }

    /* Footer */
    .create-client-footer {
        padding: 20px 40px 32px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-top: 1px solid #f1f5f9;
        gap: 12px;
    }

    .btn-cancel {
        background: #f1f5f9;
        color: #64748b;
        border: none;
        border-radius: 999px;
        padding: 12px 28px;
        font-weight: 600;
        font-size: 0.9rem;
        text-decoration: none;
        transition: background 0.2s, color 0.2s;
        cursor: pointer;
    }
    .btn-cancel:hover {
        background: #e2e8f0;
        color: #374151;
    }

    .btn-primary-action {
        background: linear-gradient(135deg, #3b82f6, #6366f1);
        color: #fff;
        border: none;
        border-radius: 999px;
        padding: 12px 36px;
        font-weight: 700;
        font-size: 0.95rem;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
        transition: opacity 0.2s, transform 0.15s;
    }
    .btn-primary-action:hover {
        opacity: 0.92;
        transform: translateY(-1px);
    }

    .btn-success-action {
        background: linear-gradient(135deg, #10b981, #059669);
        color: #fff;
        border: none;
        border-radius: 999px;
        padding: 12px 36px;
        font-weight: 700;
        font-size: 0.95rem;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
        transition: opacity 0.2s, transform 0.15s;
    }
    .btn-success-action:hover {
        opacity: 0.92;
        transform: translateY(-1px);
    }

    .btn-back-action {
        background: #f1f5f9;
        color: #374151;
        border: none;
        border-radius: 999px;
        padding: 12px 24px;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: background 0.2s;
    }
    .btn-back-action:hover { background: #e2e8f0; }

    /* Info essai */
    .trial-info {
        text-align: center;
        margin-top: 20px;
        font-size: 0.82rem;
        color: #64748b;
    }
    .trial-info strong { color: #3b82f6; }

    /* Logo header */
    .create-client-topbar {
        position: fixed;
        top: 0; left: 0; right: 0;
        padding: 14px 28px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        z-index: 10;
    }

    .logo-text {
        font-weight: 800;
        font-size: 1.1rem;
        color: #3b82f6;
        letter-spacing: -0.3px;
    }

    @media (max-width: 600px) {
        .create-client-body { padding: 24px 20px; }
        .create-client-header { padding: 28px 20px 20px; }
        .create-client-footer { padding: 16px 20px 24px; flex-wrap: wrap; }
        .create-client-title { font-size: 1.3rem; }
    }
</style>
@endpush

@section('content')
<div class="create-client-page">

    {{-- Logo flottant --}}
    <div class="create-client-topbar">
        <span class="logo-text">sitiame</span>
        <a href="{{ route('commercial.dashboard') }}" class="btn-cancel" style="padding: 8px 20px; font-size: 0.82rem;">
            ← Retour au dashboard
        </a>
    </div>

    {{-- Carte principale --}}
    <div class="create-client-card">

        {{-- Barre de progression --}}
        <div class="wizard-progress">
            <div class="wizard-progress-bar" id="wizardProgressBar" style="width: 50%;"></div>
        </div>

        {{-- En-tête --}}
        <div class="create-client-header">
            <div class="create-client-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="none"
                     viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
            </div>
            <div class="step-badge" id="stepBadge">Étape 1 / 2</div>
            <h1 class="create-client-title" id="stepTitle">Compte Client &amp; Crédentiels</h1>
            <p class="create-client-subtitle" id="stepSubtitle">Créez le compte d'accès pour votre nouveau client PME</p>
        </div>

        {{-- Formulaire --}}
        <form action="{{ route('commercial.clients.store') }}" method="POST" enctype="multipart/form-data" novalidate id="clientWizardForm">
            @csrf

            {{-- STEP 1 : Compte & Crédentiels --}}
            <div class="create-client-body" id="wizardStep1">
                @if($errors->any())
                    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:14px 18px;margin-bottom:20px;">
                        <p style="color:#dc2626;font-weight:700;margin:0 0 6px;">Erreur lors de la création :</p>
                        <ul style="margin:0;padding-left:18px;color:#b91c1c;font-size:0.88rem;">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <label>👤 Nom complet du dirigeant</label>
                            <input type="text" name="name" class="form-input-styled"
                                   placeholder="Ex : Jean Kouassi" value="{{ old('name') }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <label>✉️ Adresse Email <span style="color:#ef4444;">*</span></label>
                            <input type="email" name="email" class="form-input-styled"
                                   placeholder="dirigeant@entreprise.ci" value="{{ old('email') }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <label>📱 Téléphone</label>
                            <input type="text" name="phone" class="form-input-styled"
                                   placeholder="+225 07 00 00 00 00" value="{{ old('phone') }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <label>🔑 Mot de passe temporaire</label>
                            <input type="password" name="password" class="form-input-styled"
                                   placeholder="8 caractères minimum" minlength="8">
                        </div>
                    </div>
                </div>
            </div>

            {{-- STEP 2 : Entreprise --}}
            <div class="create-client-body" id="wizardStep2" style="display:none;">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <label>🏢 Raison Sociale / Entreprise</label>
                            <input type="text" name="company_name" class="form-input-styled"
                                   placeholder="Ex : Ivoire Agro SARL" value="{{ old('company_name') }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <label>🏷️ Sigle / Nom commercial</label>
                            <input type="text" name="company_sigle" class="form-input-styled"
                                   placeholder="Ex : IAGRO" value="{{ old('company_sigle') }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <label>🔢 NIF / Matricule Fiscal</label>
                            <input type="text" name="company_tax_id" class="form-input-styled"
                                   placeholder="Numéro d'identification fiscale" value="{{ old('company_tax_id') }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <label>🌿 Secteur d'activité</label>
                            <input type="text" name="sector" class="form-input-styled"
                                   placeholder="Ex : Agriculture, BTP, Commerce..." value="{{ old('sector') }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <label>🏙️ Ville</label>
                            <input type="text" name="city" class="form-input-styled"
                                   placeholder="Ex : Abidjan" value="{{ old('city') }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <label>📍 Adresse</label>
                            <input type="text" name="address" class="form-input-styled"
                                   placeholder="Ex : Rue des Jardins, Zone 4" value="{{ old('address') }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer actions --}}
            <div class="create-client-footer">
                <a href="{{ route('commercial.dashboard') }}" class="btn-cancel">← Annuler</a>

                <div style="display:flex; gap: 10px; align-items: center;">
                    <button type="button" class="btn-back-action" id="wizardPrevBtn"
                            style="display:none;" onclick="goToStep(1)">
                        ← Précédent
                    </button>
                    <button type="button" class="btn-primary-action" id="wizardNextBtn"
                            onclick="goToStep(2)">
                        Suivant : Entreprise →
                    </button>
                    <button type="submit" class="btn-success-action" id="wizardSubmitBtn"
                            style="display:none;">
                        ✓ Créer le compte client
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Info essai gratuit --}}
    <p class="trial-info">
        🎁 Ce client bénéficiera automatiquement d'un <strong>essai gratuit de 30 jours</strong>
    </p>
</div>
@endsection

@push('scripts')
<script>
    function goToStep(step) {
        if (step === 2) {
            document.getElementById('wizardStep1').style.display = 'none';
            document.getElementById('wizardStep2').style.display = 'block';
            document.getElementById('stepBadge').textContent   = 'Étape 2 / 2';
            document.getElementById('stepTitle').textContent   = 'Entreprise & Informations';
            document.getElementById('stepSubtitle').textContent = 'Renseignez les informations de l\'entreprise du client';
            document.getElementById('wizardProgressBar').style.width = '100%';
            document.getElementById('wizardPrevBtn').style.display  = 'inline-block';
            document.getElementById('wizardNextBtn').style.display  = 'none';
            document.getElementById('wizardSubmitBtn').style.display = 'inline-block';
        } else {
            document.getElementById('wizardStep1').style.display = 'block';
            document.getElementById('wizardStep2').style.display = 'none';
            document.getElementById('stepBadge').textContent   = 'Étape 1 / 2';
            document.getElementById('stepTitle').textContent   = 'Compte Client & Crédentiels';
            document.getElementById('stepSubtitle').textContent = 'Créez le compte d\'accès pour votre nouveau client PME';
            document.getElementById('wizardProgressBar').style.width = '50%';
            document.getElementById('wizardPrevBtn').style.display  = 'none';
            document.getElementById('wizardNextBtn').style.display  = 'inline-block';
            document.getElementById('wizardSubmitBtn').style.display = 'none';
        }
    }
</script>
@endpush
