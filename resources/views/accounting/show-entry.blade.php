@extends('layouts.app')

@section('title', 'Détails Écriture | Sitiame Capitale')
@section('page_title', 'Détails de l’écriture comptable')

@section('content')
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Détails de l’écriture</h5>
                    <p class="text-muted">Visualisez toutes les informations de l’écriture comptable.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-body">
                    @php
                        $badge = $entry->getOcrBadge();
                        $documentName = $entry->getSourceDocumentName();
                        $documentUrl = $documentName ? route('accounting.entries.document.viewer', $entry) : null;
                        $ocrSummaryLines = $entry->getOcrSummaryLines();
                        $badgeColor = [
                            'success' => 'bg-light-success text-success',
                            'warning' => 'bg-light-warning text-warning',
                            'danger' => 'bg-light-danger text-danger',
                            'secondary' => 'bg-light-secondary text-secondary',
                        ];
                    @endphp

                    <dl class="row mb-0">
                        <dt class="col-sm-4">Date</dt>
                        <dd class="col-sm-8">{{ $entry->date->format('d/m/Y') }}</dd>

                        <dt class="col-sm-4">Type de document</dt>
                        <dd class="col-sm-8">{{ $entry->document_type }}</dd>

                        <dt class="col-sm-4">Référence</dt>
                        <dd class="col-sm-8">{{ $entry->document_reference ?? '-' }}</dd>

                        <dt class="col-sm-4">Description</dt>
                        <dd class="col-sm-8">{{ $entry->description }}</dd>

                        <dt class="col-sm-4">Compte débit</dt>
                        <dd class="col-sm-8">{{ $entry->debit_account }}</dd>

                        <dt class="col-sm-4">Compte crédit</dt>
                        <dd class="col-sm-8">{{ $entry->credit_account }}</dd>

                        <dt class="col-sm-4">Montant</dt>
                        <dd class="col-sm-8">{{ number_format($entry->amount, 2, ',', ' ') }} FCFA</dd>

                        <dt class="col-sm-4">Justificatif</dt>
                        <dd class="col-sm-8">
                            @if($documentUrl)
                                <a href="{{ $documentUrl }}" target="_blank">{{ $documentName ?: 'Visualiser le document' }}</a>
                            @else
                                Aucun fichier
                            @endif
                        </dd>

                        <dt class="col-sm-4">Statut OCR</dt>
                        <dd class="col-sm-8">
                            <span class="badge {{ $badgeColor[$badge['color']] ?? 'bg-light-secondary text-secondary' }}">{{ $badge['text'] }}</span>
                            @if(!empty($ocrSummaryLines))
                                <ul class="small text-muted mt-2 mb-0">
                                    @foreach($ocrSummaryLines as $summaryLine)
                                        <li>{{ $summaryLine }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </dd>

                        @if($entry->ocr_status === 'failed')
                            <dt class="col-sm-4">Détail erreur OCR</dt>
                            <dd class="col-sm-8">
                                <pre class="bg-light border rounded p-3 small mb-0" style="white-space: pre-wrap;">{{ $entry->getOcrRawText() ?: 'Aucun détail disponible.' }}</pre>
                            </dd>
                        @endif
                    </dl>

                    <div class="mt-4">
                        <a href="{{ route('accounting') }}" class="btn btn-outline-secondary">Retour au journal</a>
                        <a href="{{ route('accounting.entries.edit', $entry) }}" class="btn btn-primary ms-2">Modifier</a>
                    </div>

                    @if($documentUrl && $entry->ocr_status === 'failed')
                        <div class="mt-3">
                            <form action="{{ route('accounting.entries.ocr.retry', $entry) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-warning">
                                    <i data-feather="refresh-cw" class="me-1"></i>Relancer OCR
                                </button>
                            </form>
                        </div>
                    @endif

                    @if(!empty($autoCorrectionProposal))
                        <div class="card mt-4 border-success">
                            <div class="card-header bg-light">
                                <strong>Aperçu de correction automatique OCR</strong>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">
                                    L’application a détecté des valeurs plus cohérentes dans le document. Vérifiez les changements proposés avant confirmation.
                                </p>

                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-3">
                                        <thead>
                                            <tr>
                                                <th>Champ</th>
                                                <th>Valeur actuelle</th>
                                                <th>Valeur proposée</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($autoCorrectionProposal['changes'] as $change)
                                                <tr>
                                                    <td><strong>{{ $change['label'] }}</strong></td>
                                                    <td>{{ $change['before'] }}</td>
                                                    <td class="text-success">{{ $change['after'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="small text-muted mb-3">
                                    Valeurs OCR détectées :
                                    HT {{ number_format((float) ($autoCorrectionProposal['normalized']['amount_ht'] ?? 0), 2, ',', ' ') }} FCFA,
                                    TVA {{ number_format((float) ($autoCorrectionProposal['normalized']['tva'] ?? 0), 2, ',', ' ') }} FCFA,
                                    TTC {{ number_format((float) ($autoCorrectionProposal['normalized']['amount_ttc'] ?? 0), 2, ',', ' ') }} FCFA.
                                </div>

                                <form action="{{ route('accounting.entries.ocr.auto-correct', $entry) }}" method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="confirm_auto_correction" value="1">
                                    <button type="submit" class="btn btn-success">
                                        <i data-feather="zap" class="me-1"></i>Confirmer la correction automatique
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif

                    @if($entry->ocr_status === 'failed')
                        <div class="card mt-4 border-warning">
                            <div class="card-header bg-light">
                                <strong>Fallback local - validation manuelle guidée</strong>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">Si l'OCR échoue, vous pouvez valider manuellement cette écriture avec une trace d'audit.</p>
                                <form action="{{ route('accounting.entries.ocr.manual', $entry) }}" method="POST">
                                    @csrf
                                    <div class="form-check mb-2">
                                        <input class="form-check-input @error('confirm_document_read') is-invalid @enderror" type="checkbox" value="1" id="confirm_document_read" name="confirm_document_read" {{ old('confirm_document_read') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="confirm_document_read">J'ai vérifié visuellement le document source.</label>
                                        @error('confirm_document_read')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input @error('confirm_amount_match') is-invalid @enderror" type="checkbox" value="1" id="confirm_amount_match" name="confirm_amount_match" {{ old('confirm_amount_match') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="confirm_amount_match">Le montant de l'écriture correspond au justificatif.</label>
                                        @error('confirm_amount_match')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input @error('confirm_reference_checked') is-invalid @enderror" type="checkbox" value="1" id="confirm_reference_checked" name="confirm_reference_checked" {{ old('confirm_reference_checked') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="confirm_reference_checked">La référence/document a été contrôlé(e).</label>
                                        @error('confirm_reference_checked')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Commentaire de validation manuelle</label>
                                        <textarea name="manual_comment" class="form-control @error('manual_comment') is-invalid @enderror" rows="3" placeholder="Expliquez brièvement pourquoi vous validez manuellement cette écriture.">{{ old('manual_comment') }}</textarea>
                                        @error('manual_comment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                    <button type="submit" class="btn btn-warning">
                                        <i data-feather="shield" class="me-1"></i>Valider manuellement
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
