@extends('layouts.app')

@section('title', 'Dossier d’investissement | Sitiame Capital')
@section('page_title', 'Dossier investissement')

@push('styles')
<style>
    /* Luxury Gold & Obsidian Theme */
    .investor-gold-container { background: #0f172a; min-height: 100vh; color: #f8fafc; }
    .investor-hero {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        border: 1px solid #d97706;
        color: #fff;
        border-radius: 1rem;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 10px 28px rgba(217, 119, 6, 0.15);
    }
    .investor-score-card {
        border: 1px solid #334155;
        background: #1e293b;
        color: #f8fafc;
        border-radius: 1rem;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        height: 100%;
    }
    .score-ring-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.04em; color: #94a3b8; }
    .score-ring-value { font-size: 2rem; font-weight: 700; line-height: 1.1; color: #f59e0b; }
    .checklist-row { border-bottom: 1px solid #334155; }
    .checklist-row:last-child { border-bottom: 0; }
    .badge-status-ok { background: #dcfce7; color: #166534; }
    .badge-status-warning { background: #fef3c7; color: #92400e; }
    .badge-status-fail { background: #fee2e2; color: #991b1b; }
    .investor-hero-title { color: #fbbf24; font-weight: 800; }
</style>
@endpush

@section('content')
<div class="investor-gold-container pb-4 px-3 pt-3 rounded-4">
    <!-- HERO LUXURY GOLD HEADER -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2">
            <div>
                <div class="text-warning small fw-semibold">
                    <i data-feather="award" class="me-1" style="width:14px; height:14px;"></i>
                    Investisseurs & Readiness · {{ \Carbon\Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </div>
                <h1 class="investor-hero-title h2 mt-1 mb-2">
                    Scorecard Investisseur — {{ explode(' ', auth()->user()?->name ?? 'Utilisateur')[0] }} 👑
                </h1>
            </div>
        </div>
    </div>
    <div class="investor-hero">
        <h1 class="h4 mb-2">Dépôt de dossier d’investissement — lecture expert-comptable</h1>
        <p class="mb-2 small opacity-95">
            En cabinet, un dossier présenté aux financeurs repose sur la <strong>cohérence</strong> entre pièces, comptabilité et prévisions,
            et sur une <strong>note d’investissement</strong> qui explique usage des fonds, risques et gouvernance. Les indicateurs ci-dessous
            automatisent une <em>revue de forme</em> : ils ne remplacent ni une diligence investisseur, ni une certification des comptes.
        </p>
        <p class="mb-0 small opacity-85">
            <strong>À prévoir en annexe :</strong> bilan / compte de résultat, liasses fiscales, prévisionnel 12–36 mois, statuts, PV d’assemblées,
            contrats majeurs, titre de propriété des actifs clés, et tout élément permettant de réconcilier les montants déclarés ci-dessous avec vos livres.
        </p>
    </div>

    {{-- PRD 4.2 — contrôle qualité périodique (méthode provisoire, non bloquante) --}}
    @if(!$qualityChecked)
        <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                ⚠ <strong>Données non vérifiées pour la période {{ $qualityPeriod['start']->format('d/m/Y') }} – {{ $qualityPeriod['end']->format('d/m/Y') }}.</strong>
                Le score ci-dessous reste calculé et affiché, mais n'a pas encore été confirmé par un contrôle qualité périodique — à interpréter avec prudence.
                @if($qualityReview)
                    <span class="d-block small mt-1">Dernier statut enregistré : <strong>{{ $qualityReview->status }}</strong> ({{ optional($qualityReview->reviewed_at)->format('d/m/Y H:i') }}).</span>
                @endif
                @if(($nonCompliantEntriesCount ?? 0) > 0)
                    <span class="d-block small mt-1">Détail automatique : {{ $nonCompliantEntriesCount }} écriture(s) signalée(s) par le contrôle de complétude (référence, montant ou identification du tiers manquants) — à consulter avant de valider la période.</span>
                @endif
            </div>
            @if($canReviewQuality)
                <form action="{{ route('investor.quality-review.store') }}" method="POST" class="d-flex gap-2">
                    @csrf
                    <button type="submit" name="status" value="validated" class="btn btn-sm btn-success">Valider la période</button>
                    <button type="submit" name="status" value="flagged" class="btn btn-sm btn-outline-danger">Signaler un problème</button>
                </form>
            @endif
        </div>
    @endif

    @error('dossier')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror
    @error('workflow')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    <div class="alert alert-light border mb-4">
        <strong>Synthèse checklist :</strong> {{ $checklistSummary }}
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-danger h-100">
                <div class="card-body">
                    <h6 class="text-danger mb-2">Recevabilité immédiate</h6>
                    <p class="small mb-1">
                        Points critiques détectés : <strong>{{ $criticalChecklistCount ?? 0 }}</strong>
                    </p>
                    <p class="small text-muted mb-0">
                        Le dépôt est automatiquement bloqué tant qu'un point « Critique » subsiste dans la checklist.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-warning h-100">
                <div class="card-body">
                    <h6 class="text-warning mb-2">Qualité du dossier</h6>
                    <p class="small mb-1">
                        Points à renforcer : <strong>{{ $warningChecklistCount ?? 0 }}</strong>
                    </p>
                    <p class="small text-muted mb-0">
                        Ces éléments n'empêchent pas le dépôt, mais influencent fortement l'analyse de crédibilité financière.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card investor-score-card border-start border-danger border-4">
                <div class="card-body">
                    <div class="score-ring-label">Score de risque</div>
                    <div class="score-ring-value text-danger">{{ number_format($metrics['risk_score'], 1, ',', ' ') }} / 100</div>
                    <p class="text-muted small mb-0 mt-2">Indicateur de risques perçus (liquidité, qualité des données, dérives de prévision).</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card investor-score-card border-start border-success border-4">
                <div class="card-body">
                    <div class="score-ring-label">Score de performance</div>
                    <div class="score-ring-value text-success">{{ number_format($metrics['performance_score'], 1, ',', ' ') }} / 100</div>
                    <p class="text-muted small mb-0 mt-2">Combine fiabilité des prévisions, qualité OCR, trésorerie et régularité de saisie.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card investor-score-card border-start border-primary border-4">
                <div class="card-body">
                    <div class="score-ring-label">Profil investisseur</div>
                    <div class="h5 mb-1">{{ $metrics['investor_profile'] }}
                        @if(!empty($metrics['profile_code']))
                            <span class="badge bg-light text-dark ms-1">{{ $metrics['profile_code'] }}</span>
                        @endif
                    </div>
                    <p class="text-muted small mb-0">{{ $metrics['investor_profile_detail'] }}</p>
                </div>
            </div>
        </div>
    </div>

    @php
        $fin = $metrics['breakdown']['financial'] ?? [];
        $finScores = $fin['scores'] ?? [];
        $finClass = $fin['classement'] ?? [];
        $finBase = $fin['base'] ?? [];
    @endphp

    <div class="card mb-4 border-primary border-2">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="card-title mb-0">Fiche profil investisseur (étape 8)</h5>
                <p class="text-muted small mb-0">Scores agrégés + lecture comptable ; enregistrés à chaque ouverture de cette page.</p>
            </div>
            <div class="text-end">
                @if(!empty($metrics['profile_code']))
                    <span class="badge bg-primary">{{ $metrics['profile_code'] }}</span>
                @endif
                @if(isset($investorProfile) && $investorProfile?->computed_at)
                    <div class="small text-muted mt-1">Dernière mise à jour : {{ $investorProfile->computed_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</div>
                @endif
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <h6 class="text-uppercase small text-muted">Classement financier (automatique)</h6>
                    @if(!empty($finClass['libelle']))
                        <p class="mb-1 fw-semibold">{{ $finClass['libelle'] }}</p>
                        <p class="small text-muted mb-0">Code : <code>{{ $finClass['code'] ?? '—' }}</code>
                            @if(array_key_exists('financable', $finClass))
                                — Finançable : {{ ($finClass['financable'] ?? false) ? 'oui' : 'non' }}
                            @endif
                        </p>
                    @else
                        <p class="text-muted small mb-0">Indisponible sans écritures exploitables sur la période.</p>
                    @endif
                </div>
                <div class="col-md-4">
                    <h6 class="text-uppercase small text-muted">Scores issus du grand livre (0–100)</h6>
                    <ul class="list-unstyled small mb-0">
                        <li>Solvabilité : {{ isset($finScores['solvabilite']) ? number_format((float) $finScores['solvabilite'], 1, ',', ' ') : '—' }}</li>
                        <li>Rentabilité : {{ isset($finScores['rentabilite']) ? number_format((float) $finScores['rentabilite'], 1, ',', ' ') : '—' }}</li>
                        <li>Synthèse fiabilisée : {{ isset($finScores['global_fiabilise']) ? number_format((float) $finScores['global_fiabilise'], 1, ',', ' ') : '—' }}</li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6 class="text-uppercase small text-muted">Données intégrées au score</h6>
                    <p class="mb-1"><strong>{{ (int) ($fin['entries_count'] ?? 0) }}</strong> écriture(s) analysée(s)</p>
                    <p class="small text-muted mb-0">Le score de risque et le score de performance combinent indicateurs opérationnels (trésorerie, OCR, prévisions) et ratios PME lorsque l’historique comptable est suffisant.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Checklist « revue de dossier » (contrôles automatiques)</h5>
        </div>
        <div class="card-body p-0">
            @foreach($checklist as $item)
                <div class="checklist-row px-3 py-3 d-flex flex-column flex-md-row gap-2 gap-md-3 align-items-start">
                    <div class="flex-shrink-0">
                        @php
                            $badgeClass = match ($item['status']) {
                                'ok' => 'badge-status-ok',
                                'warning' => 'badge-status-warning',
                                default => 'badge-status-fail',
                            };
                            $badgeLabel = match ($item['status']) {
                                'ok' => 'Conforme',
                                'warning' => 'À compléter',
                                default => 'Critique',
                            };
                        @endphp
                        <span class="badge rounded-pill {{ $badgeClass }}">{{ $badgeLabel }}</span>
                    </div>
                    <div class="flex-grow-1">
                        <strong>{{ $item['label'] }}</strong>
                        <p class="text-muted small mb-0">{{ $item['detail'] }}</p>
                    </div>
                    @if(!empty($item['route']))
                        <div class="flex-shrink-0">
                            <a href="{{ route($item['route']) }}" class="btn btn-sm btn-outline-primary">Ouvrir</a>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Données financières (détail indicateurs)</h5>
                </div>
                <div class="card-body small">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><strong>Solde actuel (réalisé) :</strong> {{ number_format($metrics['breakdown']['solde_actuel'], 0, ',', ' ') }} FCFA</li>
                        <li class="mb-2"><strong>Solde projeté :</strong> {{ number_format($metrics['breakdown']['solde_projete'], 0, ',', ' ') }} FCFA</li>
                        <li class="mb-2"><strong>Fiabilité des prévisions (8 sem.) :</strong> {{ number_format($metrics['breakdown']['forecast_reliability'], 1, ',', ' ') }} %</li>
                        <li class="mb-2"><strong>Semaines en alerte (&gt; 30 %) :</strong> {{ $metrics['breakdown']['alert_weeks'] }}</li>
                        @if($metrics['breakdown']['ocr_verified_ratio'] !== null)
                            <li class="mb-2"><strong>Écritures OCR validées :</strong> {{ number_format($metrics['breakdown']['ocr_verified_ratio'], 1, ',', ' ') }} %</li>
                        @endif
                        @if($metrics['breakdown']['documents_pending_ratio'] !== null)
                            <li><strong>Documents en attente :</strong> {{ number_format($metrics['breakdown']['documents_pending_ratio'], 1, ',', ' ') }} % du stock</li>
                        @endif
                        @if(isset($metrics['breakdown']['risk_operational']))
                            <li class="mt-2 pt-2 border-top"><strong>Risque opérationnel (avant fusion compta) :</strong> {{ number_format($metrics['breakdown']['risk_operational'], 1, ',', ' ') }} / 100</li>
                        @endif
                        @if(isset($metrics['breakdown']['performance_operational']))
                            <li><strong>Performance opérationnelle (avant fusion compta) :</strong> {{ number_format($metrics['breakdown']['performance_operational'], 1, ',', ' ') }} / 100</li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Pondération des risques (agrégat interne)</h5>
                </div>
                <div class="card-body small">
                    <p class="text-muted">Composantes du score de risque :</p>
                    <ul class="mb-0">
                        <li>Liquidité / solde projeté : {{ number_format($metrics['breakdown']['liquidity_risk'], 1, ',', ' ') }}</li>
                        <li>Stress OCR : {{ number_format($metrics['breakdown']['ocr_risk'], 1, ',', ' ') }}</li>
                        <li>File documents : {{ number_format($metrics['breakdown']['backlog_risk'], 1, ',', ' ') }}</li>
                        <li>Dérives de prévision : {{ number_format($metrics['breakdown']['forecast_risk'], 1, ',', ' ') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0">Formulaire de dépôt — dossier d’investissement</h5>
            <span class="badge bg-secondary">Déclaratif</span>
        </div>
        <div class="card-body">
            <p class="text-muted small">
                Les montants déclaratifs doivent être <strong>alignés</strong> avec les livres et les états financiers qui seront transmis en annexe.
                Toute divergence devra être expliquée (retraitements, périmètre, normes).
            </p>
            <form method="post" action="{{ route('investor.investment-request.store') }}" class="row g-3" enctype="multipart/form-data">
                @csrf
                <div class="col-12">
                    <h6 class="text-muted text-uppercase small fw-bold mb-2">Identité &amp; représentation</h6>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Représentant légal déclaré <span class="text-danger">*</span></label>
                    <input type="text" name="legal_representative" class="form-control @error('legal_representative') is-invalid @enderror" value="{{ old('legal_representative', auth()->user()->name) }}" maxlength="255" required>
                    @error('legal_representative')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Montant recherché (FCFA) <span class="text-danger">*</span></label>
                    <input type="number" name="amount_requested" class="form-control @error('amount_requested') is-invalid @enderror" min="1" step="1000" value="{{ old('amount_requested') }}" required>
                    @error('amount_requested')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Horizon</label>
                    <select name="horizon" class="form-select @error('horizon') is-invalid @enderror">
                        <option value="">— Préciser plus tard —</option>
                        <option value="court" @selected(old('horizon') === 'court')>&lt; 12 mois</option>
                        <option value="moyen" @selected(old('horizon') === 'moyen')>12 à 36 mois</option>
                        <option value="long" @selected(old('horizon') === 'long')>&gt; 36 mois</option>
                    </select>
                    @error('horizon')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 mt-2">
                    <h6 class="text-muted text-uppercase small fw-bold mb-2">Photo &amp; pièce d’identité (KYC)</h6>
                </div>
                <div class="col-12">
                    <div class="alert alert-light border small mb-0">
                        <p class="mb-2"><strong>Photo requise :</strong> portrait récent, couleur, fond neutre, visage lisible (type photo d’identité).</p>
                        <p class="mb-2"><strong>Recto du document :</strong> la photo doit montrer clairement la photographie du titulaire, le nom, le prénom, la date et le lieu de naissance, le numéro du document et la date d’expiration (selon le type de pièce).</p>
                        <p class="mb-0"><strong>Verso du document :</strong> la photo doit montrer les informations complémentaires (adresse, dates d’émission / validité, signature, mention de l’autorité, etc.) selon le format de votre pièce.</p>
                    </div>
                </div>
                <div class="col-12">
                    @include('partials.camera-upload-hint')
                </div>
                <div class="col-md-6">
                    @include('partials.file-input-camera', [
                        'name' => 'photo',
                        'id' => 'inv_photo',
                        'label' => 'Photo du représentant (fichier image) *',
                        'accept' => 'image/jpeg,image/png,image/webp',
                        'capture' => 'user',
                        'help' => 'JPEG, PNG ou WebP — max. 5 Mo. Sur mobile : appareil photo frontal pour portrait.',
                        'required' => true,
                    ])
                </div>
                <div class="col-md-6">
                    <label class="form-label">Type de pièce <span class="text-danger">*</span></label>
                    <select name="identity_document_type" class="form-select @error('identity_document_type') is-invalid @enderror" required>
                        <option value="">— Choisir —</option>
                        <option value="cni" @selected(old('identity_document_type') === 'cni')>Carte nationale d’identité</option>
                        <option value="passport" @selected(old('identity_document_type') === 'passport')>Passeport</option>
                        <option value="residence_permit" @selected(old('identity_document_type') === 'residence_permit')>Titre de séjour / permis de résidence</option>
                        <option value="other" @selected(old('identity_document_type') === 'other')>Autre (préciser dans la note d’investissement si besoin)</option>
                    </select>
                    @error('identity_document_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Numéro du document <span class="text-danger">*</span></label>
                    <input type="text" name="identity_document_number" class="form-control @error('identity_document_number') is-invalid @enderror" value="{{ old('identity_document_number') }}" maxlength="64" required autocomplete="off">
                    @error('identity_document_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date d’expiration du document</label>
                    <input type="date" name="identity_document_expires_at" class="form-control @error('identity_document_expires_at') is-invalid @enderror" value="{{ old('identity_document_expires_at') }}">
                    @error('identity_document_expires_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Facultatif si la date figure sur les scans.</div>
                </div>
                <div class="col-md-6">
                    @include('partials.file-input-camera', [
                        'name' => 'identity_document_front',
                        'id' => 'inv_id_front',
                        'label' => 'Pièce d’identité — recto *',
                        'accept' => 'image/jpeg,image/png,image/webp,application/pdf',
                        'capture' => 'environment',
                        'help' => 'Image ou PDF — max. 10 Mo. Sur mobile : appareil arrière pour photographier le recto (ou choisir un PDF).',
                        'required' => true,
                    ])
                </div>
                <div class="col-md-6">
                    @include('partials.file-input-camera', [
                        'name' => 'identity_document_back',
                        'id' => 'inv_id_back',
                        'label' => 'Pièce d’identité — verso *',
                        'accept' => 'image/jpeg,image/png,image/webp,application/pdf',
                        'capture' => 'environment',
                        'help' => 'Image ou PDF — max. 10 Mo. Sur mobile : appareil arrière pour photographier le verso (ou choisir un PDF).',
                        'required' => true,
                    ])
                </div>

                <div class="col-12 mt-2">
                    <h6 class="text-muted text-uppercase small fw-bold mb-2">Références sur l’exercice déclaré</h6>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date de clôture des derniers comptes (N-1)</label>
                    <input type="date" name="fiscal_closing_at" class="form-control @error('fiscal_closing_at') is-invalid @enderror" value="{{ old('fiscal_closing_at') }}" required>
                    @error('fiscal_closing_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Chiffre d’affaires N-1 (FCFA) <span class="text-danger">*</span></label>
                    <input type="number" name="revenue_n1" class="form-control @error('revenue_n1') is-invalid @enderror" min="0" step="1000" value="{{ old('revenue_n1', isset($finBase['chiffre_affaires_ht']) ? (int) round((float) $finBase['chiffre_affaires_ht']) : null) }}" required>
                    @error('revenue_n1')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    @if(isset($finBase['chiffre_affaires_ht']))
                        <div class="form-text">Prérempli depuis les calculs comptables SYSCOHADA (base modèle Excel).</div>
                    @endif
                </div>
                <div class="col-md-4">
                    <label class="form-label">Capitaux propres N-1 (FCFA) <span class="text-danger">*</span></label>
                    <input type="number" name="equity_n1" class="form-control @error('equity_n1') is-invalid @enderror" step="1000" value="{{ old('equity_n1', isset($finBase['capitaux_propres_estimes']) ? (int) round((float) $finBase['capitaux_propres_estimes']) : null) }}" required>
                    @error('equity_n1')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    @if(isset($finBase['capitaux_propres_estimes']))
                        <div class="form-text">Prérempli depuis les calculs comptables SYSCOHADA (base modèle Excel).</div>
                    @endif
                </div>

                <div class="col-12 mt-2">
                    <h6 class="text-muted text-uppercase small fw-bold mb-2">Note d’investissement</h6>
                </div>
                <div class="col-12">
                    <label class="form-label">Projet, usage des fonds, risques et gouvernance <span class="text-danger">*</span></label>
                    <textarea name="purpose" class="form-control @error('purpose') is-invalid @enderror" rows="8" minlength="120" maxlength="5000" required placeholder="Usage des fonds, jalons, création de valeur, principaux risques, gouvernance, garanties ou sûretés envisagées, cohérence avec le plan de trésorerie…">{{ old('purpose') }}</textarea>
                    @error('purpose')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Minimum 120 caractères — niveau attendu pour une première lecture financier/comptable.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Pièces annexes prévues (hors plateforme)</label>
                    <textarea name="attachments_commitment" class="form-control @error('attachments_commitment') is-invalid @enderror" rows="3" minlength="30" maxlength="2000" required placeholder="Ex. bilan signé N-1, liasse fiscale, prévisionnel Excel, liste des créances / dettes…">{{ old('attachments_commitment') }}</textarea>
                    @error('attachments_commitment')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="certifies_accuracy" id="certifies_accuracy" class="form-check-input @error('certifies_accuracy') is-invalid @enderror" value="1" @checked(old('certifies_accuracy')) required>
                        <label class="form-check-label" for="certifies_accuracy">
                            Je certifie que les informations déclarées sont sincères et conformes à ma connaissance, et qu’elles seront
                            rapprochées des livres comptables et des pièces annexes fournies.
                            <span class="text-danger">*</span>
                        </label>
                        @error('certifies_accuracy')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Envoyer le dossier</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Historique des dépôts</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 small">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Représentant</th>
                        <th class="text-end">Montant</th>
                        <th>Horizon</th>
                        <th>Statut</th>
                        <th>Dernière analyse</th>
                        <th>Action workflow</th>
                        <th>Extrait</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                        <tr>
                            <td>{{ $req->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($req->legal_representative ?? '—', 24) }}</td>
                            <td class="text-end">{{ number_format($req->amount_requested, 0, ',', ' ') }} {{ $req->currency }}</td>
                            <td>
                                @if($req->horizon === 'court')
                                    &lt; 12 mois
                                @elseif($req->horizon === 'moyen')
                                    12–36 mois
                                @elseif($req->horizon === 'long')
                                    &gt; 36 mois
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @php
                                    $st = $req->status;
                                    $badge = match ($st) {
                                        'pending' => 'warning',
                                        'in_review' => 'info',
                                        'accepted' => 'success',
                                        'declined' => 'danger',
                                        default => 'secondary',
                                    };
                                    $label = match ($st) {
                                        'pending' => 'En attente',
                                        'in_review' => 'En analyse',
                                        'accepted' => 'Acceptée',
                                        'declined' => 'Refusée',
                                        default => $st,
                                    };
                                @endphp
                                <span class="badge bg-{{ $badge }}">{{ $label }}</span>
                            </td>
                            <td>
                                @if($req->reviewed_at)
                                    <div class="small">{{ $req->reviewed_at->format('d/m/Y H:i') }}</div>
                                    @if($req->review_note)
                                        <div class="text-muted small">{{ \Illuminate\Support\Str::limit($req->review_note, 80) }}</div>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if(in_array($req->status, ['pending', 'in_review'], true))
                                    <form method="post" action="{{ route('investor.investment-request.workflow', $req) }}" class="d-flex flex-column gap-2">
                                        @csrf
                                        <select name="next_status" class="form-select form-select-sm" required>
                                            @if($req->status === 'pending')
                                                <option value="in_review">Passer en analyse</option>
                                                <option value="declined">Refuser</option>
                                            @elseif($req->status === 'in_review')
                                                <option value="accepted">Accepter</option>
                                                <option value="declined">Refuser</option>
                                            @endif
                                        </select>
                                        <textarea name="review_note" rows="2" class="form-control form-control-sm" maxlength="2000" placeholder="Note d’analyse (obligatoire pour une décision finale)"></textarea>
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Mettre à jour</button>
                                    </form>
                                @else
                                    <span class="text-muted small">Décision finale</span>
                                @endif
                            </td>
                            <td>{{ \Illuminate\Support\Str::limit($req->purpose, 60) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Aucun dépôt enregistré.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($requests->hasPages())
            <div class="card-footer">
                {{ $requests->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>
</div>
@endsection
