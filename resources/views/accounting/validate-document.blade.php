@extends('layouts.app')

@section('title', 'Validation document | Sitiame Capital')
@section('page_title', 'Validation du document comptable')

@section('content')
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Validation OCR</h5>
                    <p class="text-muted">Vérifiez et corrigez les données extraites avant génération de l’écriture comptable.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Document</h5>
                    <p><strong>Nom :</strong> {{ $document->original_name }}</p>
                    <p><strong>Type détecté :</strong> {{ $document->document_type }}</p>
                    <p><strong>Confiance :</strong> {{ number_format($document->confidence, 2, ',', ' ') }}%</p>
                    @php($missingRequired = (array) ($document->extracted_data['ocr_missing_required_fields'] ?? []))
                    @php($complianceBadge = $document->compliance_rate >= 100 ? 'success' : ($document->compliance_rate > 0 ? 'warning' : 'danger'))
                    <p>
                        <strong>Taux de conformité :</strong>
                        <span class="badge bg-{{ $complianceBadge }}-subtle text-{{ $complianceBadge }}-emphasis">{{ number_format((float) $document->compliance_rate, 0, ',', ' ') }}%</span>
                        @if(!empty($missingRequired))
                            <span class="small text-muted">— informations obligatoires manquantes : {{ implode(', ', $missingRequired) }}</span>
                        @endif
                    </p>
                    <div class="d-flex gap-2 flex-wrap mb-3">
                        <a href="{{ route('accounting.documents.viewer', $document) }}" class="btn btn-icon btn-outline-primary" title="Ouvrir le document">
                            <i data-feather="file-text" class="icon-sm"></i>
                        </a>
                        <a href="{{ route('accounting', ['prefill_document' => $document->id]) }}#moteur-ecritures" class="btn btn-sm btn-outline-info">
                            Pré-remplir dans le moteur comptable
                        </a>
                    </div>
                    @if($document->status === 'ocr_failed' && !empty($document->extracted_data['ocr_error']))
                        <div class="alert alert-danger">
                            <div class="fw-semibold mb-2">OCR en échec</div>
                            <pre class="mb-0 small">{{ $document->extracted_data['ocr_error'] }}</pre>
                        </div>
                    @endif
                    @if(!empty($document->extracted_data['ocr_detected_fields']))
                        @php($ocrDetected = $document->extracted_data['ocr_detected_fields'])
                        @php($ocrFieldConfidence = $document->extracted_data['ocr_field_confidence'] ?? [])
                        @php($ocrLowConfidenceFields = $document->extracted_data['ocr_low_confidence_fields'] ?? [])
                        <div class="card border mt-3">
                            <div class="card-body">
                                <h6 class="mb-2">Informations OCR détectées</h6>
                                <div class="small text-muted mb-2">Aperçu automatique des données reconnues dans le document.</div>
                                <ul class="small mb-2">
                                    <li><strong>Titre :</strong> {{ $ocrDetected['document_title'] ?? 'N/A' }}</li>
                                    <li><strong>Email(s) :</strong> {{ !empty($ocrDetected['contacts']['emails']) ? implode(', ', $ocrDetected['contacts']['emails']) : 'N/A' }}</li>
                                    <li><strong>Téléphone(s) :</strong> {{ !empty($ocrDetected['contacts']['phones']) ? implode(', ', $ocrDetected['contacts']['phones']) : 'N/A' }}</li>
                                    <li><strong>Identifiant(s) fiscal(aux) :</strong> {{ !empty($ocrDetected['identifiers']['tax_ids']) ? implode(', ', $ocrDetected['identifiers']['tax_ids']) : 'N/A' }}</li>
                                </ul>
                                <details>
                                    <summary class="small">Voir les données OCR complètes</summary>
                                    <pre class="small mt-2 mb-0">{{ json_encode($ocrDetected, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                </details>
                                @if(!empty($ocrLowConfidenceFields))
                                    <div class="alert alert-warning mt-2 mb-0 py-2 small">
                                        Champs à vérifier en priorité :
                                        <strong>{{ implode(', ', $ocrLowConfidenceFields) }}</strong>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">
                    @php($ocrFieldConfidence = $document->extracted_data['ocr_field_confidence'] ?? [])
                    @php($ocrLowConfidenceLookup = array_flip($document->extracted_data['ocr_low_confidence_fields'] ?? []))
                    @php($ocrPrimary = $document->extracted_data['ocr_detected_fields']['primary'] ?? [])
                    @if(!empty($document->extracted_data['ocr_review_required']))
                        <div class="alert alert-warning py-2">
                            OCR via OCR.space : relecture manuelle recommandée avant validation finale.
                        </div>
                    @endif
                    <form action="{{ route('accounting.documents.validate.store', $document) }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Type de document</label>
                            <select name="document_type" class="form-select @error('document_type') is-invalid @enderror">
                                @foreach(['Achat', 'Vente', 'Reçu', 'Justificatif'] as $type)
                                    <option value="{{ $type }}" {{ $document->document_type === $type ? 'selected' : '' }}>{{ $type }}</option>
                                @endforeach
                            </select>
                            @error('document_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Partenaire</label>
                            <input type="text" name="partner" value="{{ old('partner', $document->extracted_data['partner'] ?? ($ocrPrimary['partner_name'] ?? '')) }}" class="form-control @error('partner') is-invalid @enderror {{ isset($ocrLowConfidenceLookup['partner_name']) ? 'border-warning' : '' }}">
                            @if(isset($ocrFieldConfidence['partner_name']))
                                <div class="form-text">Confiance OCR: {{ number_format((float) $ocrFieldConfidence['partner_name'], 1, ',', ' ') }}%</div>
                            @endif
                            @error('partner')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date facture</label>
                            <input type="date" name="invoice_date" value="{{ old('invoice_date', $document->extracted_data['invoice_date'] ?? ($ocrPrimary['invoice_date'] ?? now()->toDateString())) }}" class="form-control @error('invoice_date') is-invalid @enderror {{ isset($ocrLowConfidenceLookup['invoice_date']) ? 'border-warning' : '' }}">
                            @if(isset($ocrFieldConfidence['invoice_date']))
                                <div class="form-text">Confiance OCR: {{ number_format((float) $ocrFieldConfidence['invoice_date'], 1, ',', ' ') }}%</div>
                            @endif
                            @error('invoice_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">N° facture</label>
                            <input type="text" name="invoice_number" value="{{ old('invoice_number', $document->extracted_data['invoice_number'] ?? ($ocrPrimary['invoice_number'] ?? '')) }}" class="form-control @error('invoice_number') is-invalid @enderror {{ isset($ocrLowConfidenceLookup['invoice_number']) ? 'border-warning' : '' }}">
                            @if(isset($ocrFieldConfidence['invoice_number']))
                                <div class="form-text">Confiance OCR: {{ number_format((float) $ocrFieldConfidence['invoice_number'], 1, ',', ' ') }}%</div>
                            @endif
                            @error('invoice_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Montant HT</label>
                            <input type="number" step="0.01" id="validateAmountHt" name="amount_ht" value="{{ old('amount_ht', $document->extracted_data['amount_ht'] ?? ($ocrPrimary['amount_ht'] ?? '')) }}" class="form-control @error('amount_ht') is-invalid @enderror {{ isset($ocrLowConfidenceLookup['amount_ht']) ? 'border-warning' : '' }}">
                            @if(isset($ocrFieldConfidence['amount_ht']))
                                <div class="form-text">Confiance OCR: {{ number_format((float) $ocrFieldConfidence['amount_ht'], 1, ',', ' ') }}%</div>
                            @endif
                            @error('amount_ht')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Montant TTC</label>
                            <input type="number" step="0.01" id="validateAmountTtc" name="amount_ttc" value="{{ old('amount_ttc', $document->extracted_data['amount_ttc'] ?? ($ocrPrimary['amount_ttc'] ?? '')) }}" class="form-control @error('amount_ttc') is-invalid @enderror {{ isset($ocrLowConfidenceLookup['amount_ttc']) ? 'border-warning' : '' }}">
                            @if(isset($ocrFieldConfidence['amount_ttc']))
                                <div class="form-text">Confiance OCR: {{ number_format((float) $ocrFieldConfidence['amount_ttc'], 1, ',', ' ') }}%</div>
                            @endif
                            @error('amount_ttc')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Montant TVA</label>
                            <input type="number" step="0.01" id="validateAmountTva" name="tva" value="{{ old('tva', $document->extracted_data['tva'] ?? ($ocrPrimary['amount_tva'] ?? '')) }}" class="form-control @error('tva') is-invalid @enderror">
                            @error('tva')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div id="amountConsistencyWarning" class="alert alert-danger py-2 small d-none"></div>
                        <div class="mb-3">
                            <label class="form-label">Devise</label>
                            <input type="text" name="currency" value="{{ old('currency', $document->extracted_data['currency'] ?? 'FCFA') }}" class="form-control @error('currency') is-invalid @enderror {{ isset($ocrLowConfidenceLookup['currency']) ? 'border-warning' : '' }}">
                            @if(isset($ocrFieldConfidence['currency']))
                                <div class="form-text">Confiance OCR: {{ number_format((float) $ocrFieldConfidence['currency'], 1, ',', ' ') }}%</div>
                            @endif
                            @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3 position-relative">
                            <label class="form-label">Compte débit</label>
                            <input type="text" class="form-control" placeholder="Rechercher un compte (code ou libellé)…" autocomplete="off" data-account-search="debit" value="{{ old('debit_account', $document->extracted_data['debit_account'] ?? '') }}">
                            <input type="hidden" name="debit_account" id="debitAccountValue" value="{{ old('debit_account', $document->extracted_data['debit_account'] ?? '') }}" required>
                            <div class="list-group position-absolute w-100 shadow-sm" style="z-index: 20; display:none; max-height: 260px; overflow-y: auto;" data-account-results="debit"></div>
                            @error('debit_account')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3 position-relative">
                            <label class="form-label">Compte crédit</label>
                            <input type="text" class="form-control" placeholder="Rechercher un compte (code ou libellé)…" autocomplete="off" data-account-search="credit" value="{{ old('credit_account', $document->extracted_data['credit_account'] ?? '') }}">
                            <input type="hidden" name="credit_account" id="creditAccountValue" value="{{ old('credit_account', $document->extracted_data['credit_account'] ?? '') }}" required>
                            <div class="list-group position-absolute w-100 shadow-sm" style="z-index: 20; display:none; max-height: 260px; overflow-y: auto;" data-account-results="credit"></div>
                            @error('credit_account')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-success">Valider et générer l'écriture</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function setupAccountPickers() {
            ['debit', 'credit'].forEach(function (side) {
                const searchInput = document.querySelector('[data-account-search="' + side + '"]');
                const resultsBox = document.querySelector('[data-account-results="' + side + '"]');
                const hiddenInput = document.getElementById(side + 'AccountValue');
                if (!searchInput || !resultsBox || !hiddenInput) {
                    return;
                }

                let debounceTimer = null;
                let abortController = null;

                function runSearch() {
                    const q = searchInput.value.trim();
                    if (abortController) {
                        abortController.abort();
                    }
                    abortController = new AbortController();

                    const params = new URLSearchParams();
                    if (q) params.set('q', q);

                    fetch('{{ route('accounting.comptes.search') }}?' + params.toString(), {
                        signal: abortController.signal,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (accounts) {
                            resultsBox.innerHTML = '';
                            if (!accounts.length) {
                                resultsBox.style.display = 'none';
                                return;
                            }
                            accounts.forEach(function (account) {
                                const item = document.createElement('button');
                                item.type = 'button';
                                item.className = 'list-group-item list-group-item-action py-1';
                                item.innerHTML = '<strong>' + account.numero_compte + '</strong> — ' + account.libelle_compte;
                                item.addEventListener('click', function () {
                                    hiddenInput.value = account.label;
                                    searchInput.value = account.label;
                                    resultsBox.style.display = 'none';
                                });
                                resultsBox.appendChild(item);
                            });
                            resultsBox.style.display = 'block';
                        })
                        .catch(function () { /* requête annulée ou erreur réseau, on ignore */ });
                }

                searchInput.addEventListener('input', function () {
                    hiddenInput.value = '';
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(runSearch, 200);
                });
                searchInput.addEventListener('focus', runSearch);

                document.addEventListener('click', function (event) {
                    if (!resultsBox.contains(event.target) && event.target !== searchInput) {
                        resultsBox.style.display = 'none';
                    }
                });
            });
        })();

        // Garde-fou visible : signale toute incohérence HT + TVA ≠ TTC pendant la
        // correction manuelle, pour ne jamais laisser passer silencieusement une
        // erreur — que ce soit un reste de mauvaise lecture OCR ou une saisie
        // manuelle qui casse l'identité comptable.
        (function setupAmountConsistencyCheck() {
            const htInput = document.getElementById('validateAmountHt');
            const ttcInput = document.getElementById('validateAmountTtc');
            const tvaInput = document.getElementById('validateAmountTva');
            const warningBox = document.getElementById('amountConsistencyWarning');
            if (!htInput || !ttcInput || !tvaInput || !warningBox) {
                return;
            }

            function checkConsistency() {
                const ht = parseFloat(htInput.value) || 0;
                const tva = parseFloat(tvaInput.value) || 0;
                const ttc = parseFloat(ttcInput.value) || 0;
                const expectedTtc = ht + tva;
                const delta = Math.abs(expectedTtc - ttc);

                if (delta > 1) {
                    warningBox.textContent = 'Incohérence : HT (' + ht.toLocaleString('fr-FR') + ') + TVA (' + tva.toLocaleString('fr-FR') + ') = ' + expectedTtc.toLocaleString('fr-FR') + ', ce qui ne correspond pas au TTC saisi (' + ttc.toLocaleString('fr-FR') + '). Vérifiez ces trois montants avant de valider.';
                    warningBox.classList.remove('d-none');
                } else {
                    warningBox.classList.add('d-none');
                }
            }

            [htInput, ttcInput, tvaInput].forEach(function (input) {
                input.addEventListener('input', checkConsistency);
            });
            checkConsistency();
        })();
    </script>
@endsection
