@extends('layouts.app')

@section('title', 'Fiche entreprise (FIRD) | Sitiame Capital')
@section('page_title', 'Fiche entreprise')

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between flex-wrap align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1"><strong>Fiche d’identification</strong> — entreprise</h1>
            <p class="text-muted mb-0 small">
                Renseignements alignés sur une fiche type FIRD (identification et renseignements divers). Ces données alimentent les états et mises à jour ultérieures dans l’application.
            </p>
        </div>
        <a href="{{ route('profile') }}" class="btn btn-outline-secondary btn-sm">
            <i class="align-middle" data-feather="arrow-left"></i> Retour au profil
        </a>
    </div>

    <form method="POST" action="{{ route('profile.company.fird.update') }}" class="pb-4" enctype="multipart/form-data">
        @csrf

        {{-- Bloc 1 : identité juridique --}}
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0">1. Identification générale</h5>
                <p class="text-muted small mb-0 mt-1">Dénomination, sigle, adresse, fiscalité, exercice</p>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Dénomination sociale</label>
                        <input type="text" name="company_name" value="{{ old('company_name', $user->company_name) }}" class="form-control @error('company_name') is-invalid @enderror" placeholder="Raison sociale">
                        @error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ZF — Désignation détaillée de l’entreprise (optionnel)</label>
                        <input type="text" name="company_designation" value="{{ old('company_designation', $user->company_designation) }}" class="form-control @error('company_designation') is-invalid @enderror" placeholder="Libellé officiel étendu si différent">
                        @error('company_designation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sigle usuel</label>
                        <input type="text" name="company_sigle" value="{{ old('company_sigle', $user->company_sigle) }}" class="form-control @error('company_sigle') is-invalid @enderror">
                        @error('company_sigle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">N° identification fiscale</label>
                        <input type="text" name="company_tax_id" value="{{ old('company_tax_id', $user->company_tax_id) }}" class="form-control @error('company_tax_id') is-invalid @enderror">
                        @error('company_tax_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Exercice clos le</label>
                        <input type="date" name="fiscal_year_end_date" value="{{ old('fiscal_year_end_date', optional($user->fiscal_year_end_date)?->format('Y-m-d')) }}" class="form-control @error('fiscal_year_end_date') is-invalid @enderror">
                        @error('fiscal_year_end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Durée de l’exercice (mois)</label>
                        <input type="number" name="fiscal_year_duration_months" min="1" max="24" value="{{ old('fiscal_year_duration_months', $user->fiscal_year_duration_months) }}" class="form-control @error('fiscal_year_duration_months') is-invalid @enderror" placeholder="12">
                        @error('fiscal_year_duration_months')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Bloc 2 : exercice comptable --}}
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0">2. Exercice comptable & arrêtés</h5>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">ZA — Exercice comptable du</label>
                        <input type="date" name="accounting_period_from" value="{{ old('accounting_period_from', optional($user->accounting_period_from)?->format('Y-m-d')) }}" class="form-control @error('accounting_period_from') is-invalid @enderror">
                        @error('accounting_period_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">AU</label>
                        <input type="date" name="accounting_period_to" value="{{ old('accounting_period_to', optional($user->accounting_period_to)?->format('Y-m-d')) }}" class="form-control @error('accounting_period_to') is-invalid @enderror">
                        @error('accounting_period_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">ZB — Date d’arrêté effectif des comptes</label>
                        <input type="date" name="accounts_effective_closing_date" value="{{ old('accounts_effective_closing_date', optional($user->accounts_effective_closing_date)?->format('Y-m-d')) }}" class="form-control @error('accounts_effective_closing_date') is-invalid @enderror">
                        @error('accounts_effective_closing_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ZC — Exercice précédent clos le</label>
                        <input type="date" name="previous_fiscal_year_end" value="{{ old('previous_fiscal_year_end', optional($user->previous_fiscal_year_end)?->format('Y-m-d')) }}" class="form-control @error('previous_fiscal_year_end') is-invalid @enderror">
                        @error('previous_fiscal_year_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Durée exercice précédent (mois)</label>
                        <input type="number" name="previous_fiscal_year_duration_months" min="1" max="24" value="{{ old('previous_fiscal_year_duration_months', $user->previous_fiscal_year_duration_months) }}" class="form-control @error('previous_fiscal_year_duration_months') is-invalid @enderror">
                        @error('previous_fiscal_year_duration_months')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Bloc 3 : registres --}}
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0">3. Registres & codes</h5>
                <p class="text-muted small mb-0 mt-1">Saisissez vos numéros et <strong>joignez une copie du registre de commerce</strong> (scan ou photo).</p>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">ZD — Greffe / N° registre du commerce (RCCM)</label>
                        <input type="text" name="rccm" value="{{ old('rccm', $user->rccm) }}" class="form-control @error('rccm') is-invalid @enderror">
                        @error('rccm')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ZD — N° répertoire des entreprises</label>
                        <input type="text" name="company_directory_number" value="{{ old('company_directory_number', $user->company_directory_number) }}" class="form-control @error('company_directory_number') is-invalid @enderror">
                        @error('company_directory_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">ZE — N° caisse sociale</label>
                        <input type="text" name="social_security_number" value="{{ old('social_security_number', $user->social_security_number) }}" class="form-control @error('social_security_number') is-invalid @enderror">
                        @error('social_security_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">ZE — N° code importateur</label>
                        <input type="text" name="importer_code" value="{{ old('importer_code', $user->importer_code) }}" class="form-control @error('importer_code') is-invalid @enderror">
                        @error('importer_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">ZE — Code activité principale (NAF / APE)</label>
                        <input type="text" name="primary_activity_code" value="{{ old('primary_activity_code', $user->primary_activity_code) }}" class="form-control @error('primary_activity_code') is-invalid @enderror">
                        @error('primary_activity_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <hr class="my-2">
                        <h6 class="fw-semibold mb-2">Joindre l’attestation DFE / NIF et le registre de commerce</h6>
                        <p class="small text-muted mb-2">Déposez une copie de l’<strong>attestation DFE / NIF</strong> et du <strong>RCCM</strong> (extrait K-bis ou équivalent), PDF ou image lisible, max. 5 Mo. Facultatif mais recommandé pour la conformité du dossier.</p>
                        @include('partials.camera-upload-hint')
                        <div class="row g-3 align-items-end">
                            <div class="col-lg-6">
                                @include('partials.file-input-camera', [
                                    'name' => 'company_logo',
                                    'id' => 'fird_company_logo',
                                    'label' => 'Fichier — attestation DFE / NIF',
                                    'accept' => '.pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,image/*',
                                    'capture' => 'environment',
                                    'help' => 'PDF, Word ou photo (max. 5 Mo). Bouton « Web » : webcam sur ordinateur.',
                                ])
                                @if($user->company_logo)
                                    <p class="small text-muted mb-1">Document déjà enregistré</p>
                                    <a href="{{ route('company-documents.view', ['user' => $user, 'type' => 'company_logo']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Voir le fichier joint</a>
                                @endif
                            </div>
                            <div class="col-lg-6">
                                @include('partials.file-input-camera', [
                                    'name' => 'trade_register',
                                    'id' => 'fird_trade_register',
                                    'label' => 'Fichier — registre de commerce / extrait K-bis',
                                    'accept' => '.pdf,.jpg,.jpeg,.png,.webp,image/*',
                                    'capture' => 'environment',
                                    'help' => 'PDF ou photo (max. 5 Mo). Bouton « Web » : webcam sur ordinateur.',
                                ])
                                @if($user->trade_register_file)
                                    <p class="small text-muted mb-1">Document déjà enregistré</p>
                                    <a href="{{ route('company-documents.view', ['user' => $user, 'type' => 'trade_register']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Voir le fichier joint</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Bloc 4 : coordonnées --}}
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0">4. Coordonnées</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Téléphone</label>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control @error('phone') is-invalid @enderror">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Télécopie (fax)</label>
                        <input type="text" name="company_fax" value="{{ old('company_fax', $user->company_fax) }}" class="form-control @error('company_fax') is-invalid @enderror">
                        @error('company_fax')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Code postal / BP / Ville</label>
                        <div class="input-group">
                            <input type="text" name="postal_code" value="{{ old('postal_code', $user->postal_code) }}" class="form-control" placeholder="Code">
                            <input type="text" name="po_box" value="{{ old('po_box', $user->po_box) }}" class="form-control" placeholder="BP">
                            <input type="text" name="city" value="{{ old('city', $user->city) }}" class="form-control" placeholder="Ville">
                        </div>
                        @error('city')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Adresse (ligne courte)</label>
                        <input type="text" name="address" value="{{ old('address', $user->address) }}" class="form-control @error('address') is-invalid @enderror" placeholder="Complément">
                        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">ZH — Adresse géographique complète</label>
                        <textarea name="full_geographic_address" rows="3" class="form-control @error('full_geographic_address') is-invalid @enderror" placeholder="Bâtiment, rue, quartier, pays…">{{ old('full_geographic_address', $user->full_geographic_address) }}</textarea>
                        @error('full_geographic_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Bloc 5 : activité & contact --}}
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0">5. Activité & contact complémentaire</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">ZI — Désignation précise de l’activité principale</label>
                        <textarea name="main_activity_description" rows="3" class="form-control @error('main_activity_description') is-invalid @enderror">{{ old('main_activity_description', $user->main_activity_description) }}</textarea>
                        @error('main_activity_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">ZI — % capacité de production utilisée</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" max="100" name="production_capacity_utilization_pct" value="{{ old('production_capacity_utilization_pct', $user->production_capacity_utilization_pct) }}" class="form-control @error('production_capacity_utilization_pct') is-invalid @enderror">
                            <span class="input-group-text">%</span>
                        </div>
                        @error('production_capacity_utilization_pct')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Personne à contacter — nom</label>
                        <input type="text" name="contact_person_name" value="{{ old('contact_person_name', $user->contact_person_name) }}" class="form-control @error('contact_person_name') is-invalid @enderror">
                        @error('contact_person_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Qualité / fonction</label>
                        <input type="text" name="contact_person_title" value="{{ old('contact_person_title', $user->contact_person_title) }}" class="form-control @error('contact_person_title') is-invalid @enderror">
                        @error('contact_person_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Adresse du contact</label>
                        <textarea name="contact_person_address" rows="2" class="form-control @error('contact_person_address') is-invalid @enderror">{{ old('contact_person_address', $user->contact_person_address) }}</textarea>
                        @error('contact_person_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Bloc 6 : expert-comptable & CAC --}}
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0">6. Expert-comptable & commissaires aux comptes</h5>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Type d’intervenant comptable</label>
                        <select name="accountant_type" class="form-select @error('accountant_type') is-invalid @enderror">
                            <option value="">—</option>
                            <option value="none" {{ old('accountant_type', $user->accountant_type) === 'none' ? 'selected' : '' }}>Non renseigné</option>
                            <option value="internal" {{ old('accountant_type', $user->accountant_type) === 'internal' ? 'selected' : '' }}>Professionnel interne</option>
                            <option value="firm" {{ old('accountant_type', $user->accountant_type) === 'firm' ? 'selected' : '' }}>Cabinet / expert externe</option>
                        </select>
                        @error('accountant_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Nom (ou cabinet)</label>
                        <input type="text" name="accountant_name" value="{{ old('accountant_name', $user->accountant_name) }}" class="form-control @error('accountant_name') is-invalid @enderror">
                        @error('accountant_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Adresse</label>
                        <textarea name="accountant_address" rows="2" class="form-control @error('accountant_address') is-invalid @enderror">{{ old('accountant_address', $user->accountant_address) }}</textarea>
                        @error('accountant_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Téléphone</label>
                        <input type="text" name="accountant_phone" value="{{ old('accountant_phone', $user->accountant_phone) }}" class="form-control @error('accountant_phone') is-invalid @enderror">
                        @error('accountant_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <p class="small text-muted mb-2">Commissaires aux comptes (jusqu’à 5)</p>
                @for($a = 0; $a < 5; $a++)
                    @php
                        $aud = $user->company_auditors[$a] ?? [];
                        $audName = old('auditor_name.'.$a, $aud['name'] ?? '');
                        $audAddr = old('auditor_address.'.$a, $aud['address'] ?? '');
                    @endphp
                    <div class="row g-2 mb-2 border-bottom pb-2">
                        <div class="col-md-5">
                            <input type="text" name="auditor_name[]" value="{{ $audName }}" class="form-control form-control-sm" placeholder="Nom">
                        </div>
                        <div class="col-md-7">
                            <input type="text" name="auditor_address[]" value="{{ $audAddr }}" class="form-control form-control-sm" placeholder="Adresse">
                        </div>
                    </div>
                @endfor
            </div>
        </div>

        {{-- Bloc 7 : conformité --}}
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0">7. États financiers — certification & approbation</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">États financiers certifiés</label>
                        <select name="certified_financial_statements" class="form-select @error('certified_financial_statements') is-invalid @enderror">
                            <option value="">—</option>
                            <option value="not_subject" {{ old('certified_financial_statements', $user->certified_financial_statements) === 'not_subject' ? 'selected' : '' }}>Non assujettie</option>
                            <option value="no_refusal" {{ old('certified_financial_statements', $user->certified_financial_statements) === 'no_refusal' ? 'selected' : '' }}>Non (refus)</option>
                            <option value="yes_reserves" {{ old('certified_financial_statements', $user->certified_financial_statements) === 'yes_reserves' ? 'selected' : '' }}>Oui avec réserves</option>
                            <option value="yes_no_reserves" {{ old('certified_financial_statements', $user->certified_financial_statements) === 'yes_no_reserves' ? 'selected' : '' }}>Oui sans réserves</option>
                        </select>
                        @error('certified_financial_statements')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Approuvés par l’assemblée générale</label>
                        <select name="approved_by_general_assembly" class="form-select @error('approved_by_general_assembly') is-invalid @enderror">
                            <option value="">—</option>
                            <option value="not_subject" {{ old('approved_by_general_assembly', $user->approved_by_general_assembly) === 'not_subject' ? 'selected' : '' }}>Non assujettie</option>
                            <option value="no" {{ old('approved_by_general_assembly', $user->approved_by_general_assembly) === 'no' ? 'selected' : '' }}>Non</option>
                            <option value="yes" {{ old('approved_by_general_assembly', $user->approved_by_general_assembly) === 'yes' ? 'selected' : '' }}>Oui</option>
                        </select>
                        @error('approved_by_general_assembly')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nom du signataire des états financiers</label>
                        <input type="text" name="financial_statements_signatory_name" value="{{ old('financial_statements_signatory_name', $user->financial_statements_signatory_name) }}" class="form-control @error('financial_statements_signatory_name') is-invalid @enderror">
                        @error('financial_statements_signatory_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Qualité du signataire</label>
                        <input type="text" name="signatory_qualification" value="{{ old('signatory_qualification', $user->signatory_qualification) }}" class="form-control @error('signatory_qualification') is-invalid @enderror">
                        @error('signatory_qualification')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date de signature</label>
                        <input type="date" name="financial_statements_signature_date" value="{{ old('financial_statements_signature_date', optional($user->financial_statements_signature_date)?->format('Y-m-d')) }}" class="form-control @error('financial_statements_signature_date') is-invalid @enderror">
                        @error('financial_statements_signature_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <p class="small text-muted mb-0 mt-2">La signature manuscrite peut être conservée lors d’un dépôt officiel ; ici nous enregistrons les données structurées.</p>
            </div>
        </div>

        {{-- Bloc 8 : banques --}}
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0">8. Domiciliations bancaires</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th style="width:45%">Banque</th>
                                <th>Numéro de compte</th>
                            </tr>
                        </thead>
                        <tbody>
                            @for($b = 0; $b < 8; $b++)
                                @php
                                    $bankRow = $user->company_bank_accounts[$b] ?? [];
                                    $bn = old('bank_name.'.$b, $bankRow['bank'] ?? '');
                                    $ba = old('bank_account_number.'.$b, $bankRow['account_number'] ?? '');
                                @endphp
                                <tr>
                                    <td><input type="text" name="bank_name[]" value="{{ $bn }}" class="form-control form-control-sm" placeholder="Nom de la banque"></td>
                                    <td><input type="text" name="bank_account_number[]" value="{{ $ba }}" class="form-control form-control-sm" placeholder="IBAN / RIB selon usage"></td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('profile') }}" class="btn btn-light border">Annuler</a>
            <button type="submit" class="btn btn-primary">Enregistrer la fiche entreprise</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.feather) {
            window.feather.replace();
        }
    });
</script>
@endpush
