@extends('layouts.app')

@section('title', 'Branche Comparaison OCR | Sitiame Capital')
@section('page_title', 'Branche spéciale - Comparaison OCR')

@section('content')
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="card-title mb-1">Comparaison OCR vs écritures</h5>
                        <p class="text-muted mb-0">Visualisation dédiée pour comparer le document OCR et l’écriture comptable liée.</p>
                    </div>
                    <a href="{{ route('accounting.documents') }}" class="btn btn-sm btn-outline-secondary">
                        <i data-feather="arrow-left" class="me-1"></i>Retour aux documents
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Document</th>
                                    <th>Statut OCR</th>
                                    <th>Date</th>
                                    <th>Référence</th>
                                    <th>Montant TTC</th>
                                    <th>Débit</th>
                                    <th>Crédit</th>
                                    <th>Résultat</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($documents as $document)
                                    @php
                                        $entry = $document->entries->first();
                                        $extracted = (array) ($document->extracted_data ?? []);
                                        $primary = (array) ($extracted['ocr_detected_fields']['primary'] ?? []);
                                        $ocrDate = trim((string) ($extracted['invoice_date'] ?? $primary['invoice_date'] ?? ''));
                                        $ocrRef = trim((string) ($extracted['invoice_number'] ?? $primary['invoice_number'] ?? ''));
                                        $ocrAmount = is_numeric($extracted['amount_ttc'] ?? null) ? (float) $extracted['amount_ttc'] : null;
                                        $ocrDebit = trim((string) ($extracted['debit_account'] ?? ''));
                                        $ocrCredit = trim((string) ($extracted['credit_account'] ?? ''));

                                        $entryDate = $entry ? optional($entry->date)->format('Y-m-d') : '';
                                        $entryRef = $entry ? trim((string) $entry->document_reference) : '';
                                        $entryAmount = $entry ? (float) $entry->amount : null;
                                        $entryDebit = $entry ? trim((string) $entry->debit_account) : '';
                                        $entryCredit = $entry ? trim((string) $entry->credit_account) : '';

                                        $dateOk = $entry && $ocrDate !== '' && ($ocrDate === $entryDate || optional($entry->date)->format('d/m/Y') === $ocrDate);
                                        $refOk = $entry && $ocrRef !== '' && $entryRef !== '' && strcasecmp($ocrRef, $entryRef) === 0;
                                        $amountOk = $entry && $ocrAmount !== null && $entryAmount !== null && abs($ocrAmount - $entryAmount) <= 1;
                                        $debitOk = $entry && $ocrDebit !== '' && strcasecmp($ocrDebit, $entryDebit) === 0;
                                        $creditOk = $entry && $ocrCredit !== '' && strcasecmp($ocrCredit, $entryCredit) === 0;
                                        $okCount = ($dateOk ? 1 : 0) + ($refOk ? 1 : 0) + ($amountOk ? 1 : 0) + ($debitOk ? 1 : 0) + ($creditOk ? 1 : 0);
                                    @endphp
                                    <tr>
                                        <td>{{ $document->original_name }}</td>
                                        <td>
                                            @if($document->status === 'validated')
                                                <span class="badge bg-success-subtle text-success-emphasis">Validé</span>
                                            @elseif($document->status === 'ocr_failed')
                                                <span class="badge bg-danger-subtle text-danger-emphasis">OCR échoué</span>
                                            @else
                                                <span class="badge bg-warning-subtle text-warning-emphasis">À valider</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div>OCR: {{ $ocrDate !== '' ? $ocrDate : 'N/A' }}</div>
                                            <div class="small text-muted">Écriture: {{ $entryDate !== '' ? $entryDate : 'N/A' }}</div>
                                        </td>
                                        <td>
                                            <div>OCR: {{ $ocrRef !== '' ? $ocrRef : 'N/A' }}</div>
                                            <div class="small text-muted">Écriture: {{ $entryRef !== '' ? $entryRef : 'N/A' }}</div>
                                        </td>
                                        <td>
                                            <div>OCR: {{ $ocrAmount !== null ? number_format($ocrAmount, 2, ',', ' ') : 'N/A' }}</div>
                                            <div class="small text-muted">Écriture: {{ $entryAmount !== null ? number_format($entryAmount, 2, ',', ' ') : 'N/A' }}</div>
                                        </td>
                                        <td>
                                            <div>OCR: {{ $ocrDebit !== '' ? $ocrDebit : 'N/A' }}</div>
                                            <div class="small text-muted">Écriture: {{ $entryDebit !== '' ? $entryDebit : 'N/A' }}</div>
                                        </td>
                                        <td>
                                            <div>OCR: {{ $ocrCredit !== '' ? $ocrCredit : 'N/A' }}</div>
                                            <div class="small text-muted">Écriture: {{ $entryCredit !== '' ? $entryCredit : 'N/A' }}</div>
                                        </td>
                                        <td>
                                            @if(!$entry)
                                                <span class="badge bg-secondary-subtle text-secondary-emphasis">Pas d’écriture liée</span>
                                            @elseif($okCount === 5)
                                                <span class="badge bg-success-subtle text-success-emphasis">Cohérent</span>
                                            @elseif($okCount >= 3)
                                                <span class="badge bg-warning-subtle text-warning-emphasis">Partiel</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger-emphasis">Écart important</span>
                                            @endif
                                            <div class="small text-muted mt-1">{{ $okCount }}/5 champs cohérents</div>
                                        </td>
                                        <td>
                                            <a href="{{ route('accounting.documents.viewer', $document) }}" class="btn btn-sm btn-outline-secondary" title="Voir document">
                                                <i data-feather="eye" class="icon-sm"></i>
                                            </a>
                                            @if($entry)
                                                <a href="{{ route('accounting.entries.show', $entry) }}" class="btn btn-sm btn-outline-primary ms-1" title="Voir écriture">
                                                    <i data-feather="file-text" class="icon-sm"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">Aucun document disponible pour la comparaison.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
