@extends('layouts.app')

@section('title', 'Validation document | Sitiame Capitale')
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
                    @if($document->status === 'ocr_failed' && !empty($document->extracted_data['ocr_error']))
                        <div class="alert alert-danger">
                            <div class="fw-semibold mb-2">OCR en échec</div>
                            <pre class="mb-0 small">{{ $document->extracted_data['ocr_error'] }}</pre>
                        </div>
                    @endif
                    <a href="{{ asset('storage/' . $document->stored_path) }}" target="_blank" class="btn btn-icon btn-outline-primary" title="Ouvrir le document">
                        <i data-feather="file-text" class="icon-sm"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">
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
                            <input type="text" name="partner" value="{{ old('partner', $document->extracted_data['partner'] ?? '') }}" class="form-control @error('partner') is-invalid @enderror">
                            @error('partner')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date facture</label>
                            <input type="date" name="invoice_date" value="{{ old('invoice_date', $document->extracted_data['invoice_date'] ?? now()->toDateString()) }}" class="form-control @error('invoice_date') is-invalid @enderror">
                            @error('invoice_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">N° facture</label>
                            <input type="text" name="invoice_number" value="{{ old('invoice_number', $document->extracted_data['invoice_number'] ?? '') }}" class="form-control @error('invoice_number') is-invalid @enderror">
                            @error('invoice_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Montant HT</label>
                            <input type="number" step="0.01" name="amount_ht" value="{{ old('amount_ht', $document->extracted_data['amount_ht'] ?? '') }}" class="form-control @error('amount_ht') is-invalid @enderror">
                            @error('amount_ht')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Montant TTC</label>
                            <input type="number" step="0.01" name="amount_ttc" value="{{ old('amount_ttc', $document->extracted_data['amount_ttc'] ?? '') }}" class="form-control @error('amount_ttc') is-invalid @enderror">
                            @error('amount_ttc')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Montant TVA</label>
                            <input type="number" step="0.01" name="tva" value="{{ old('tva', $document->extracted_data['tva'] ?? '') }}" class="form-control @error('tva') is-invalid @enderror">
                            @error('tva')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Devise</label>
                            <input type="text" name="currency" value="{{ old('currency', $document->extracted_data['currency'] ?? 'FCFA') }}" class="form-control @error('currency') is-invalid @enderror">
                            @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Compte débit</label>
                            <input type="text" name="debit_account" value="{{ old('debit_account', $document->extracted_data['debit_account'] ?? ($document->document_type === 'Achat' ? '6 Achat' : ($document->document_type === 'Vente' ? '5 Caisse' : ($document->document_type === 'Reçu' ? '5 Caisse' : '6 Achat')))) }}" class="form-control @error('debit_account') is-invalid @enderror">
                            @error('debit_account')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Compte crédit</label>
                            <input type="text" name="credit_account" value="{{ old('credit_account', $document->extracted_data['credit_account'] ?? ($document->document_type === 'Achat' ? '5 Caisse' : ($document->document_type === 'Vente' ? '7 Vente' : ($document->document_type === 'Reçu' ? '7 Vente' : '5 Caisse')))) }}" class="form-control @error('credit_account') is-invalid @enderror">
                            @error('credit_account')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-success">Valider et générer l'écriture</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
