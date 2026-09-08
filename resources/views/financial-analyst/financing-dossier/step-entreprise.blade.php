@php($badge = fn ($field) => in_array($field, $prefilledFields, true) ? '<span class="badge bg-info-subtle text-info-emphasis ms-1">pré-rempli</span>' : '')

<h6 class="mb-3">Identité juridique</h6>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label class="form-label">Raison sociale * {!! $badge('legal_name') !!}</label>
        <input type="text" name="legal_name" class="form-control" value="{{ old('legal_name', $dossier->legal_name) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Nom commercial {!! $badge('trade_name') !!}</label>
        <input type="text" name="trade_name" class="form-control" value="{{ old('trade_name', $dossier->trade_name) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Forme juridique *</label>
        <select name="legal_form" class="form-select" required>
            <option value="">Sélectionner…</option>
            @foreach(['Entreprise individuelle','SARL','SARLU','SA','SAS','SASU','Coopérative','Association','Autre'] as $opt)
                <option value="{{ $opt }}" {{ old('legal_form', $dossier->legal_form) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Numéro RCCM * {!! $badge('rccm_number') !!}</label>
        <input type="text" name="rccm_number" class="form-control" value="{{ old('rccm_number', $dossier->rccm_number) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Numéro de compte contribuable * {!! $badge('taxpayer_number') !!}</label>
        <input type="text" name="taxpayer_number" class="form-control" value="{{ old('taxpayer_number', $dossier->taxpayer_number) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Date de création *</label>
        <input type="date" name="incorporation_date" class="form-control" value="{{ old('incorporation_date', optional($dossier->incorporation_date)->toDateString()) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Adresse du siège * {!! $badge('registered_office') !!}</label>
        <input type="text" name="registered_office" class="form-control" value="{{ old('registered_office', $dossier->registered_office) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Ville et pays *</label>
        <input type="text" name="city_country" class="form-control" value="{{ old('city_country', $dossier->city_country) }}" required>
    </div>
</div>

<h6 class="mb-3">Activité et organisation</h6>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label class="form-label">Secteur d'activité * {!! $badge('business_sector') !!}</label>
        <select name="business_sector" class="form-select" required>
            <option value="">Sélectionner…</option>
            @foreach(['Agriculture','Commerce','Industrie','BTP','Transport','Services','Numérique','Santé','Éducation','Autre'] as $opt)
                <option value="{{ $opt }}" {{ old('business_sector', $dossier->business_sector) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Activité principale * {!! $badge('main_activity') !!}</label>
        <input type="text" name="main_activity" class="form-control" value="{{ old('main_activity', $dossier->main_activity) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Nombre de salariés</label>
        <input type="number" min="0" name="employee_count" class="form-control" value="{{ old('employee_count', $dossier->employee_count) }}">
    </div>
    <div class="col-md-8">
        <label class="form-label">Site internet</label>
        <input type="text" name="website" class="form-control" value="{{ old('website', $dossier->website) }}">
    </div>
    <div class="col-12">
        <label class="form-label">Historique et principales réalisations *</label>
        <textarea name="company_history" class="form-control" rows="3" required>{{ old('company_history', $dossier->company_history) }}</textarea>
    </div>
</div>

<h6 class="mb-3">Capital et gouvernance</h6>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Capital social (FCFA) *</label>
        <input type="number" step="0.01" min="0" name="share_capital" class="form-control" value="{{ old('share_capital', $dossier->share_capital) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Représentant légal * {!! $badge('authorized_representative') !!}</label>
        <input type="text" name="authorized_representative" class="form-control" value="{{ old('authorized_representative', $dossier->authorized_representative) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Principaux associés et pourcentages *</label>
        <input type="text" name="major_shareholders" class="form-control" value="{{ old('major_shareholders', $dossier->major_shareholders) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Bénéficiaires effectifs *</label>
        <input type="text" name="beneficial_owners" class="form-control" value="{{ old('beneficial_owners', $dossier->beneficial_owners) }}" required>
    </div>
</div>
