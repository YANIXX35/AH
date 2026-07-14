@extends('layouts.app')

@section('title', 'Documents Comptables | Sitiame Capitale')
@section('page_title', 'Documents Comptables')

@section('content')
    <style>
        .mobile-doc-card {
            border: 1px solid #e9ecef;
            border-radius: .55rem;
            background: #fff;
            box-shadow: 0 1px 2px rgba(17, 24, 39, 0.04);
        }
        .mobile-doc-card + .mobile-doc-card {
            margin-top: .75rem;
        }
        .mobile-doc-summary {
            cursor: pointer;
            list-style: none;
            padding: .8rem .95rem;
            position: relative;
        }
        .mobile-doc-summary::-webkit-details-marker {
            display: none;
        }
        .mobile-doc-summary .title {
            font-weight: 600;
            word-break: break-word;
        }
        .mobile-doc-summary::after {
            content: "▸";
            position: absolute;
            right: .95rem;
            top: .85rem;
            color: #6c757d;
            transition: transform .2s ease;
        }
        .mobile-doc-card[open] .mobile-doc-summary::after {
            transform: rotate(90deg);
        }
        .mobile-doc-body {
            border-top: 1px solid #f1f3f5;
            padding: .75rem .95rem .9rem;
        }
        .mobile-doc-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .4rem .8rem;
            font-size: .85rem;
        }
        .mobile-doc-grid .label {
            color: #6c757d;
            font-size: .76rem;
            text-transform: uppercase;
            letter-spacing: .02em;
        }
        .mobile-doc-submenu details + details {
            margin-top: .45rem;
        }
        .mobile-doc-submenu summary {
            cursor: pointer;
            font-size: .85rem;
            font-weight: 600;
            color: #0d6efd;
            list-style: none;
        }
        .mobile-doc-submenu summary::-webkit-details-marker {
            display: none;
        }
        .mobile-doc-submenu details {
            border: 1px solid #edf1f5;
            border-radius: .45rem;
            padding: .45rem .55rem;
            background: #fbfcfe;
        }
        .mobile-doc-submenu details[open] {
            background: #f5f9ff;
            border-color: #dbe8ff;
        }
        @media (min-width: 992px) {
            .mobile-doc-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: .55rem 1rem;
            }
            .mobile-doc-body {
                padding: .9rem 1.1rem 1rem;
            }
        }
    </style>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Importation des documents</h5>
                    <p class="text-muted">Chargez des factures, reçus ou justificatifs pour extraction OCR et validation.</p>
                    @include('partials.camera-upload-hint')
                    @if($errors->any())
                        <div class="alert alert-danger mt-3 mb-0">
                            @foreach($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('accounting.documents.upload') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Sélectionnez les fichiers (plusieurs possibles)</label>
                            <div class="border rounded p-4" id="drop-zone">
                                <p class="mb-2">Glissez-déposez vos fichiers ici ou cliquez pour sélectionner.</p>
                                <input type="file" name="documents[]" id="documents" class="form-control @error('documents') is-invalid @enderror" multiple accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.csv,image/*" style="cursor: pointer;" />
                                @error('documents')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @error('documents.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Formats autorisés : PDF, JPG, PNG, XLS, XLSX, CSV · Taille max application : 20 MB · Les PDF/images sont traités localement avec PaddleOCR, en GPU si disponible sinon en CPU.</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="documents_camera">Ou photographier un justificatif (un fichier)</label>
                            <div class="d-flex gap-2 flex-wrap align-items-stretch">
                                <input type="file" name="documents[]" id="documents_camera" class="form-control flex-grow-1" style="min-width: 200px;" accept="image/*" capture="environment">
                                <button type="button" class="btn btn-outline-secondary btn-sm js-webcam-open align-self-center text-nowrap px-3" data-webcam-for="documents_camera" data-webcam-facing="environment" title="Webcam (navigateur web)">
                                    <span aria-hidden="true">📷</span> Web
                                </button>
                            </div>
                            <div class="form-text">Sur mobile : appareil photo arrière. Sur ordinateur : bouton <strong>Web</strong> pour la webcam, ou choix d’un fichier. Vous pouvez envoyer seul ou en complément des fichiers ci-dessus.</div>
                        </div>
                        <button type="submit" class="btn btn-primary">Importer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h5 class="card-title mb-0">Documents importés</h5>
                        <a href="{{ route('accounting.documents.comparison') }}" class="btn btn-sm btn-outline-primary">
                            <i data-feather="git-branch" class="me-1"></i>Branche comparaison OCR
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive d-none">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Client / Fournisseur</th>
                                    <th>Référence</th>
                                    <th>Date facture</th>
                                    <th>Montants OCR</th>
                                    <th>Confiance</th>
                                    <th>Conformité</th>
                                    <th>Détection</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($documents as $document)
                                    @php
                                        $extracted = (array) ($document->extracted_data ?? []);
                                        $primary = (array) ($extracted['ocr_detected_fields']['primary'] ?? []);
                                        $rich = (array) ($extracted['ocr_detected_fields'] ?? []);
                                        $linkedEntry = $document->entries->first();
                                        $partner = trim((string) ($extracted['partner'] ?? $primary['partner_name'] ?? ''));
                                        $supplier = trim((string) ($primary['supplier_name'] ?? $primary['supplier'] ?? ''));
                                        $client = trim((string) ($primary['client_name'] ?? $primary['client'] ?? ''));
                                        $counterpartyLabel = $partner !== '' ? $partner : ($supplier !== '' ? $supplier : ($client !== '' ? $client : 'Non détecté'));
                                        $counterpartyRole = $supplier !== ''
                                            ? 'Fournisseur'
                                            : ($client !== '' ? 'Client' : 'Partenaire');
                                        $invoiceRef = trim((string) ($extracted['invoice_number'] ?? $primary['invoice_number'] ?? ''));
                                        $invoiceDate = trim((string) ($extracted['invoice_date'] ?? $primary['invoice_date'] ?? ''));
                                        $currency = trim((string) ($extracted['currency'] ?? $primary['currency'] ?? 'FCFA'));
                                        $amountHt = is_numeric($extracted['amount_ht'] ?? null) ? (float) $extracted['amount_ht'] : null;
                                        $amountTva = is_numeric($extracted['tva'] ?? null) ? (float) $extracted['tva'] : null;
                                        $amountTtc = is_numeric($extracted['amount_ttc'] ?? null) ? (float) $extracted['amount_ttc'] : null;
                                        $emails = (array) ($rich['contacts']['emails'] ?? []);
                                        $phones = (array) ($rich['contacts']['phones'] ?? []);
                                        $websites = (array) ($rich['contacts']['websites'] ?? []);
                                        $addressCandidates = (array) ($rich['addresses']['address_candidates'] ?? []);
                                        $supplierAddress = trim((string) ($rich['addresses']['supplier_address'] ?? ''));
                                        $clientAddress = trim((string) ($rich['addresses']['client_address'] ?? ''));
                                        $taxIds = (array) ($rich['identifiers']['tax_ids'] ?? []);
                                        $businessIds = (array) ($rich['identifiers']['business_ids'] ?? []);
                                        $paymentTerms = (array) ($rich['payment']['terms'] ?? []);
                                        $paymentMethods = (array) ($rich['payment']['payment_methods'] ?? []);
                                        $dueDates = (array) ($rich['payment']['due_dates'] ?? []);
                                        $ibans = (array) ($rich['banking']['iban'] ?? []);
                                        $bics = (array) ($rich['banking']['bic_swift'] ?? []);
                                        $bankAccounts = (array) ($rich['banking']['account_numbers'] ?? []);
                                        $ocrAmountTtc = is_numeric($extracted['amount_ttc'] ?? null) ? (float) $extracted['amount_ttc'] : null;
                                        $entryAmount = $linkedEntry ? (float) $linkedEntry->amount : null;
                                        $dateMatch = $linkedEntry
                                            ? (($invoiceDate !== '' && optional($linkedEntry->date)->format('Y-m-d') === $invoiceDate) || ($invoiceDate !== '' && optional($linkedEntry->date)->format('d/m/Y') === $invoiceDate))
                                            : false;
                                        $refMatch = $linkedEntry
                                            ? ($invoiceRef !== '' && trim((string) $linkedEntry->document_reference) !== '' && strcasecmp(trim((string) $linkedEntry->document_reference), $invoiceRef) === 0)
                                            : false;
                                        $amountMatch = $linkedEntry && $ocrAmountTtc !== null
                                            ? abs($entryAmount - $ocrAmountTtc) <= 1
                                            : false;
                                        $debitMatch = $linkedEntry
                                            ? (trim((string) ($extracted['debit_account'] ?? '')) !== '' && strcasecmp(trim((string) $linkedEntry->debit_account), trim((string) ($extracted['debit_account'] ?? ''))) === 0)
                                            : false;
                                        $creditMatch = $linkedEntry
                                            ? (trim((string) ($extracted['credit_account'] ?? '')) !== '' && strcasecmp(trim((string) $linkedEntry->credit_account), trim((string) ($extracted['credit_account'] ?? ''))) === 0)
                                            : false;
                                    @endphp
                                    <tr>
                                        <td>{{ $document->original_name }}</td>
                                        <td>{{ $document->document_type }}</td>
                                        <td>
                                            @if($document->status === 'pending_validation')
                                                <span class="badge bg-warning-subtle text-warning-emphasis">À valider</span>
                                            @elseif($document->status === 'validated')
                                                <span class="badge bg-success-subtle text-success-emphasis">Validé</span>
                                            @elseif($document->status === 'ocr_failed')
                                                <span class="badge bg-danger-subtle text-danger-emphasis">OCR échoué</span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ ucfirst(str_replace('_', ' ', $document->status)) }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="fw-semibold">{{ $counterpartyLabel }}</div>
                                            <small class="text-muted">{{ $counterpartyRole }}</small>
                                        </td>
                                        <td>{{ $invoiceRef !== '' ? $invoiceRef : '—' }}</td>
                                        <td>{{ $invoiceDate !== '' ? $invoiceDate : '—' }}</td>
                                        <td>
                                            <div class="small">HT: <strong>{{ $amountHt !== null ? number_format($amountHt, 2, ',', ' ') : '—' }}</strong></div>
                                            <div class="small">TVA: <strong>{{ $amountTva !== null ? number_format($amountTva, 2, ',', ' ') : '—' }}</strong></div>
                                            <div class="small">TTC: <strong>{{ $amountTtc !== null ? number_format($amountTtc, 2, ',', ' ') : '—' }}</strong> {{ $currency }}</div>
                                        </td>
                                        <td>{{ number_format($document->confidence, 2, ',', ' ') }}%</td>
                                        <td>
                                            @php($missingRequired = (array) ($document->extracted_data['ocr_missing_required_fields'] ?? []))
                                            @php($complianceBadge = $document->compliance_rate >= 100 ? 'success' : ($document->compliance_rate > 0 ? 'warning' : 'danger'))
                                            <span class="badge bg-{{ $complianceBadge }}-subtle text-{{ $complianceBadge }}-emphasis"
                                                  @if(!empty($missingRequired)) title="Manquant : {{ implode(', ', $missingRequired) }}" @endif>
                                                {{ number_format((float) $document->compliance_rate, 0, ',', ' ') }}%
                                            </span>
                                        </td>
                                        <td>{{ $document->created_at->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <a href="{{ route('accounting.documents.viewer', $document) }}" class="btn btn-sm btn-icon btn-outline-secondary" title="Voir le document">
                                                <i data-feather="eye" class="icon-sm"></i>
                                            </a>
                                            @if(!empty($document->extracted_data))
                                                <a href="{{ route('accounting', ['prefill_document' => $document->id]) }}#moteur-ecritures" class="btn btn-sm btn-icon btn-outline-info ms-1" title="Pré-remplir le moteur comptable">
                                                    <i data-feather="edit-3" class="icon-sm"></i>
                                                </a>
                                            @endif
                                            <a href="{{ route('accounting.documents.validate', $document) }}" class="btn btn-sm btn-icon btn-primary ms-1" title="Valider le document">
                                                <i data-feather="check-circle" class="icon-sm"></i>
                                            </a>
                                            @if($document->status === 'ocr_failed')
                                                <form action="{{ route('accounting.documents.ocr.retry', $document) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-icon btn-outline-warning ms-1" title="Relancer OCR">
                                                        <i data-feather="refresh-cw" class="icon-sm"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            <form action="{{ route('accounting.documents.destroy', $document) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce document et ses écritures liées ?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-icon btn-outline-danger ms-1" title="Supprimer le document">
                                                    <i data-feather="trash-2" class="icon-sm"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @if(!empty($document->extracted_data))
                                        <tr>
                                            <td colspan="10" class="bg-light">
                                                <div class="small text-muted mb-1"><strong>Traçabilité OCR</strong></div>
                                                <div class="table-responsive">
                                                    <table class="table table-sm mb-0">
                                                        <tbody>
                                                            <tr>
                                                                <td class="text-muted" style="width: 180px;">N° facture</td>
                                                                <td>{{ $invoiceRef !== '' ? $invoiceRef : 'Non détecté' }}</td>
                                                                <td class="text-muted" style="width: 180px;">Date facture</td>
                                                                <td>{{ $invoiceDate !== '' ? $invoiceDate : 'Non détectée' }}</td>
                                                            </tr>
                                                            <tr>
                                                                <td class="text-muted">Fournisseur détecté</td>
                                                                <td>{{ $supplier !== '' ? $supplier : 'Non détecté' }}</td>
                                                                <td class="text-muted">Client détecté</td>
                                                                <td>{{ $client !== '' ? $client : 'Non détecté' }}</td>
                                                            </tr>
                                                            <tr>
                                                                <td class="text-muted">Compte débit OCR</td>
                                                                <td>{{ $extracted['debit_account'] ?? 'Non calculé' }}</td>
                                                                <td class="text-muted">Compte crédit OCR</td>
                                                                <td>{{ $extracted['credit_account'] ?? 'Non calculé' }}</td>
                                                            </tr>
                                                            <tr>
                                                                <td class="text-muted">Emails / Téléphones</td>
                                                                <td>{{ !empty($emails) ? implode(', ', $emails) : 'Non détecté' }}<br><small class="text-muted">{{ !empty($phones) ? implode(', ', $phones) : 'Téléphone non détecté' }}</small></td>
                                                                <td class="text-muted">Site web</td>
                                                                <td>{{ !empty($websites) ? implode(', ', $websites) : 'Non détecté' }}</td>
                                                            </tr>
                                                            <tr>
                                                                <td class="text-muted">Identifiants fiscaux</td>
                                                                <td>{{ !empty($taxIds) ? implode(', ', $taxIds) : 'Non détecté' }}</td>
                                                                <td class="text-muted">Identifiants business</td>
                                                                <td>{{ !empty($businessIds) ? implode(', ', $businessIds) : 'Non détecté' }}</td>
                                                            </tr>
                                                            <tr>
                                                                <td class="text-muted">Adresse fournisseur</td>
                                                                <td>{{ $supplierAddress !== '' ? $supplierAddress : (!empty($addressCandidates) ? $addressCandidates[0] : 'Non détectée') }}</td>
                                                                <td class="text-muted">Adresse client</td>
                                                                <td>{{ $clientAddress !== '' ? $clientAddress : (!empty($addressCandidates[1]) ? $addressCandidates[1] : 'Non détectée') }}</td>
                                                            </tr>
                                                            <tr>
                                                                <td class="text-muted">Conditions de paiement</td>
                                                                <td>{{ !empty($paymentTerms) ? implode(' | ', $paymentTerms) : 'Non détectées' }}</td>
                                                                <td class="text-muted">Échéances</td>
                                                                <td>{{ !empty($dueDates) ? implode(', ', $dueDates) : 'Non détectées' }}</td>
                                                            </tr>
                                                            <tr>
                                                                <td class="text-muted">Moyens de paiement</td>
                                                                <td>{{ !empty($paymentMethods) ? implode(', ', $paymentMethods) : 'Non détectés' }}</td>
                                                                <td class="text-muted">Coordonnées bancaires</td>
                                                                <td>
                                                                    IBAN: {{ !empty($ibans) ? implode(', ', $ibans) : '—' }}<br>
                                                                    BIC: {{ !empty($bics) ? implode(', ', $bics) : '—' }}<br>
                                                                    Compte: {{ !empty($bankAccounts) ? implode(', ', $bankAccounts) : '—' }}
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                    @if($linkedEntry)
                                        <tr>
                                            <td colspan="10" class="bg-white">
                                                <div class="small text-muted mb-1"><strong>Comparaison document OCR vs écriture liée</strong></div>
                                                <div class="table-responsive">
                                                    <table class="table table-sm mb-0">
                                                        <tbody>
                                                            <tr>
                                                                <td class="text-muted" style="width: 220px;">Date facture</td>
                                                                <td>OCR: {{ $invoiceDate !== '' ? $invoiceDate : 'Non détectée' }} | Écriture: {{ optional($linkedEntry->date)->format('Y-m-d') ?? 'N/A' }}</td>
                                                                <td>{!! $dateMatch ? '<span class="badge bg-success-subtle text-success-emphasis">OK</span>' : '<span class="badge bg-warning-subtle text-warning-emphasis">Écart</span>' !!}</td>
                                                            </tr>
                                                            <tr>
                                                                <td class="text-muted">Référence</td>
                                                                <td>OCR: {{ $invoiceRef !== '' ? $invoiceRef : 'Non détectée' }} | Écriture: {{ $linkedEntry->document_reference ?: 'N/A' }}</td>
                                                                <td>{!! $refMatch ? '<span class="badge bg-success-subtle text-success-emphasis">OK</span>' : '<span class="badge bg-warning-subtle text-warning-emphasis">Écart</span>' !!}</td>
                                                            </tr>
                                                            <tr>
                                                                <td class="text-muted">Montant TTC</td>
                                                                <td>OCR: {{ $ocrAmountTtc !== null ? number_format($ocrAmountTtc, 2, ',', ' ') : 'Non détecté' }} | Écriture: {{ number_format((float) $linkedEntry->amount, 2, ',', ' ') }}</td>
                                                                <td>{!! $amountMatch ? '<span class="badge bg-success-subtle text-success-emphasis">OK</span>' : '<span class="badge bg-warning-subtle text-warning-emphasis">Écart</span>' !!}</td>
                                                            </tr>
                                                            <tr>
                                                                <td class="text-muted">Compte débit</td>
                                                                <td>OCR: {{ $extracted['debit_account'] ?? 'Non calculé' }} | Écriture: {{ $linkedEntry->debit_account }}</td>
                                                                <td>{!! $debitMatch ? '<span class="badge bg-success-subtle text-success-emphasis">OK</span>' : '<span class="badge bg-warning-subtle text-warning-emphasis">Écart</span>' !!}</td>
                                                            </tr>
                                                            <tr>
                                                                <td class="text-muted">Compte crédit</td>
                                                                <td>OCR: {{ $extracted['credit_account'] ?? 'Non calculé' }} | Écriture: {{ $linkedEntry->credit_account }}</td>
                                                                <td>{!! $creditMatch ? '<span class="badge bg-success-subtle text-success-emphasis">OK</span>' : '<span class="badge bg-warning-subtle text-warning-emphasis">Écart</span>' !!}</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                    @if($document->status === 'ocr_failed' && !empty($document->extracted_data['ocr_error']))
                                        <tr>
                                            <td colspan="10" class="bg-danger-subtle">
                                                <small class="text-danger-emphasis d-block mb-1"><strong>Détail OCR</strong></small>
                                                <pre class="mb-0 small">{{ $document->extracted_data['ocr_error'] }}</pre>
                                            </td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center text-muted">Aucun document importé.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-block" id="documentsAccordionList">
                        @forelse($documents as $document)
                            @php
                                $extracted = (array) ($document->extracted_data ?? []);
                                $primary = (array) ($extracted['ocr_detected_fields']['primary'] ?? []);
                                $rich = (array) ($extracted['ocr_detected_fields'] ?? []);
                                $linkedEntry = $document->entries->first();
                                $partner = trim((string) ($extracted['partner'] ?? $primary['partner_name'] ?? ''));
                                $supplier = trim((string) ($primary['supplier_name'] ?? $primary['supplier'] ?? ''));
                                $client = trim((string) ($primary['client_name'] ?? $primary['client'] ?? ''));
                                $counterpartyLabel = $partner !== '' ? $partner : ($supplier !== '' ? $supplier : ($client !== '' ? $client : 'Non détecté'));
                                $counterpartyRole = $supplier !== ''
                                    ? 'Fournisseur'
                                    : ($client !== '' ? 'Client' : 'Partenaire');
                                $invoiceRef = trim((string) ($extracted['invoice_number'] ?? $primary['invoice_number'] ?? ''));
                                $invoiceDate = trim((string) ($extracted['invoice_date'] ?? $primary['invoice_date'] ?? ''));
                                $currency = trim((string) ($extracted['currency'] ?? $primary['currency'] ?? 'FCFA'));
                                $amountHt = is_numeric($extracted['amount_ht'] ?? null) ? (float) $extracted['amount_ht'] : null;
                                $amountTva = is_numeric($extracted['tva'] ?? null) ? (float) $extracted['tva'] : null;
                                $amountTtc = is_numeric($extracted['amount_ttc'] ?? null) ? (float) $extracted['amount_ttc'] : null;
                            @endphp

                            <details class="mobile-doc-card js-doc-accordion-item">
                                <summary class="mobile-doc-summary">
                                    <div class="d-flex justify-content-between align-items-start gap-2 pe-4">
                                        <div class="title">{{ $document->original_name }}</div>
                                        @if($document->status === 'pending_validation')
                                            <span class="badge bg-warning-subtle text-warning-emphasis">À valider</span>
                                        @elseif($document->status === 'validated')
                                            <span class="badge bg-success-subtle text-success-emphasis">Validé</span>
                                        @elseif($document->status === 'ocr_failed')
                                            <span class="badge bg-danger-subtle text-danger-emphasis">OCR échoué</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ ucfirst(str_replace('_', ' ', $document->status)) }}</span>
                                        @endif
                                    </div>
                                    <div class="small text-muted mt-1">{{ $document->document_type }} · {{ $document->created_at->format('d/m/Y H:i') }} · Confiance {{ number_format($document->confidence, 2, ',', ' ') }}% · Conformité {{ number_format((float) $document->compliance_rate, 0, ',', ' ') }}%</div>
                                </summary>

                                <div class="mobile-doc-body">
                                    <div class="mobile-doc-grid mb-2">
                                        <div>
                                            <div class="label">Partenaire</div>
                                            <div>{{ $counterpartyLabel }}</div>
                                            <small class="text-muted">{{ $counterpartyRole }}</small>
                                        </div>
                                        <div>
                                            <div class="label">Référence</div>
                                            <div>{{ $invoiceRef !== '' ? $invoiceRef : '—' }}</div>
                                        </div>
                                        <div>
                                            <div class="label">Date facture</div>
                                            <div>{{ $invoiceDate !== '' ? $invoiceDate : '—' }}</div>
                                        </div>
                                        <div>
                                            <div class="label">Montant TTC</div>
                                            <div>{{ $amountTtc !== null ? number_format($amountTtc, 2, ',', ' ') : '—' }} {{ $currency }}</div>
                                        </div>
                                    </div>

                                    <div class="mobile-doc-submenu js-doc-submenu">
                                        <details class="js-doc-submenu-item">
                                            <summary>Traçabilité OCR</summary>
                                            <div class="small mt-2">
                                                HT: {{ $amountHt !== null ? number_format($amountHt, 2, ',', ' ') : '—' }}<br>
                                                TVA: {{ $amountTva !== null ? number_format($amountTva, 2, ',', ' ') : '—' }}<br>
                                                TTC: {{ $amountTtc !== null ? number_format($amountTtc, 2, ',', ' ') : '—' }} {{ $currency }}
                                            </div>
                                        </details>

                                        @if($linkedEntry)
                                            <details class="js-doc-submenu-item">
                                                <summary>Comparaison avec l’écriture</summary>
                                                <div class="small mt-2">
                                                    Référence écriture: {{ $linkedEntry->document_reference ?: 'N/A' }}<br>
                                                    Date écriture: {{ optional($linkedEntry->date)->format('Y-m-d') ?? 'N/A' }}<br>
                                                    Montant écriture: {{ number_format((float) $linkedEntry->amount, 2, ',', ' ') }}<br>
                                                    Débit: {{ $linkedEntry->debit_account }}<br>
                                                    Crédit: {{ $linkedEntry->credit_account }}
                                                </div>
                                            </details>
                                        @endif

                                        @if($document->status === 'ocr_failed' && !empty($document->extracted_data['ocr_error']))
                                            <details class="js-doc-submenu-item">
                                                <summary>Détail erreur OCR</summary>
                                                <pre class="small mt-2 mb-0">{{ $document->extracted_data['ocr_error'] }}</pre>
                                            </details>
                                        @endif
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                        <a href="{{ route('accounting.documents.viewer', $document) }}" class="btn btn-sm btn-outline-secondary">
                                            <i data-feather="eye" class="me-1"></i>Voir
                                        </a>
                                        @if(!empty($document->extracted_data))
                                            <a href="{{ route('accounting', ['prefill_document' => $document->id]) }}#moteur-ecritures" class="btn btn-sm btn-outline-info">
                                                <i data-feather="edit-3" class="me-1"></i>Pré-remplir
                                            </a>
                                        @endif
                                        <a href="{{ route('accounting.documents.validate', $document) }}" class="btn btn-sm btn-primary">
                                            <i data-feather="check-circle" class="me-1"></i>Valider
                                        </a>
                                        @if($document->status === 'ocr_failed')
                                            <form action="{{ route('accounting.documents.ocr.retry', $document) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-warning">
                                                    <i data-feather="refresh-cw" class="me-1"></i>Relancer OCR
                                                </button>
                                            </form>
                                        @endif
                                        <form action="{{ route('accounting.documents.destroy', $document) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce document et ses écritures liées ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i data-feather="trash-2" class="me-1"></i>Supprimer
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </details>
                        @empty
                            <div class="text-center text-muted py-3">Aucun document importé.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('documents');

        dropZone.addEventListener('click', () => fileInput.click());
        dropZone.addEventListener('dragover', (event) => {
            event.preventDefault();
            dropZone.classList.add('border-primary');
        });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('border-primary'));
        dropZone.addEventListener('drop', (event) => {
            event.preventDefault();
            dropZone.classList.remove('border-primary');
            fileInput.files = event.dataTransfer.files;
        });

        // Accordéon pro: une seule fiche document ouverte à la fois.
        (function setupDocumentsAccordion() {
            const container = document.getElementById('documentsAccordionList');
            if (!container) return;

            const items = Array.from(container.querySelectorAll('.js-doc-accordion-item'));
            items.forEach(function (item) {
                item.addEventListener('toggle', function () {
                    if (!item.open) return;
                    items.forEach(function (other) {
                        if (other !== item) {
                            other.removeAttribute('open');
                        }
                    });
                });
            });
        })();

        // Sous-menus: une seule section interne ouverte par document.
        (function setupNestedSubmenus() {
            const groups = Array.from(document.querySelectorAll('.js-doc-submenu'));
            groups.forEach(function (group) {
                const nestedItems = Array.from(group.querySelectorAll('.js-doc-submenu-item'));
                nestedItems.forEach(function (item) {
                    item.addEventListener('toggle', function () {
                        if (!item.open) return;
                        nestedItems.forEach(function (other) {
                            if (other !== item) {
                                other.removeAttribute('open');
                            }
                        });
                    });
                });
            });
        })();
    </script>
@endsection
