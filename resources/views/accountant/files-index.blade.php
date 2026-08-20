@extends('layouts.app')

@section('title', 'Gestionnaire de fichiers | Cabinet')
@section('page_title', 'Gestionnaire de fichiers')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><strong>Gestionnaire</strong> de fichiers</h1>
        <p class="text-muted mb-0">Documents comptables (factures scannées/importées) de vos clients, séparés selon que l'OCR a réussi ou échoué à les lire.</p>
    </div>
    <a href="{{ route('accountant.dashboard') }}" class="btn btn-outline-secondary btn-sm">Tableau de bord cabinet</a>
</div>

<ul class="nav nav-pills mb-3">
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'success' ? 'active' : '' }}" href="{{ route('accountant.files.index', ['tab' => 'success']) }}">
            <i data-feather="check-circle" class="me-1" style="width:14px;height:14px;"></i>
            Réussi <span class="badge bg-light text-dark ms-1">{{ $successCount }}</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'failed' ? 'active' : '' }}" href="{{ route('accountant.files.index', ['tab' => 'failed']) }}">
            <i data-feather="alert-triangle" class="me-1" style="width:14px;height:14px;"></i>
            Échec <span class="badge bg-light text-dark ms-1">{{ $failedCount }}</span>
        </a>
    </li>
</ul>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-body p-0">
        {{-- Desktop --}}
        <div class="d-none d-md-block">
            @forelse($groups as $group)
                <div class="px-3 pt-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="avatar-circle bg-primary-subtle text-primary rounded-2 d-flex align-items-center justify-content-center fw-bold small" style="width: 28px; height: 28px; flex-shrink: 0;">
                            {{ strtoupper(substr($group['client_label'], 0, 2)) }}
                        </div>
                        <div class="fw-bold text-dark">{{ $group['client_label'] }}</div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-slate-700 text-uppercase fs-8 fw-bold border-bottom">
                            <tr>
                                <th class="py-2 px-3">Fichier</th>
                                <th class="py-2 px-3">Type</th>
                                <th class="py-2 px-3 text-center">Statut</th>
                                <th class="py-2 px-3 text-center">Confiance</th>
                                <th class="py-2 px-3 text-center">Conformité</th>
                                <th class="py-2 px-3">Importé le</th>
                                <th class="py-2 px-3 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($group['documents'] as $document)
                                @php
                                    $complianceBadge = $document->compliance_rate >= 100 ? 'success' : ($document->compliance_rate > 0 ? 'warning' : 'danger');
                                @endphp
                                <tr>
                                    <td class="py-2 px-3">{{ $document->original_name }}</td>
                                    <td class="py-2 px-3 text-muted small">{{ $document->document_type }}</td>
                                    <td class="py-2 px-3 text-center">
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
                                    <td class="py-2 px-3 text-center small">{{ number_format($document->confidence, 0, ',', ' ') }}%</td>
                                    <td class="py-2 px-3 text-center">
                                        <span class="badge bg-{{ $complianceBadge }}-subtle text-{{ $complianceBadge }}-emphasis">{{ number_format((float) $document->compliance_rate, 0, ',', ' ') }}%</span>
                                    </td>
                                    <td class="py-2 px-3 small text-muted">{{ $document->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="py-2 px-3 text-end">
                                        <a href="{{ route('accountant.files.open', $document) }}" class="btn btn-xs btn-primary rounded-pill px-2.5">
                                            {{ $document->status === 'validated' ? 'Voir' : 'Valider' }}
                                        </a>
                                    </td>
                                </tr>
                                @if($document->status === 'ocr_failed' && !empty($document->extracted_data['ocr_error']))
                                    <tr>
                                        <td colspan="7" class="px-3 pb-2 pt-0">
                                            <div class="alert alert-danger py-2 px-3 mb-0 small">{{ $document->extracted_data['ocr_error'] }}</div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @empty
                <div class="p-5 text-center text-muted">
                    <i data-feather="inbox" class="mb-2 text-slate-300" style="width: 40px; height: 40px;"></i>
                    <div class="fw-semibold fs-6">{{ $tab === 'failed' ? 'Aucun document en échec.' : 'Aucun document réussi.' }}</div>
                </div>
            @endforelse
        </div>

        {{-- Mobile --}}
        <div class="d-block d-md-none p-3">
            @forelse($groups as $group)
                <div class="fw-bold text-dark mb-2">{{ $group['client_label'] }}</div>
                @foreach($group['documents'] as $document)
                    @php
                        $complianceBadge = $document->compliance_rate >= 100 ? 'success' : ($document->compliance_rate > 0 ? 'warning' : 'danger');
                    @endphp
                    <div class="p-3 bg-light rounded-3 border d-flex flex-column gap-2 mb-3">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="fw-semibold text-dark small">{{ $document->original_name }}</div>
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
                        <div class="small text-muted">{{ $document->document_type }} · {{ $document->created_at->format('d/m/Y H:i') }} · Confiance {{ number_format($document->confidence, 0, ',', ' ') }}%</div>
                        <div>
                            <span class="badge bg-{{ $complianceBadge }}-subtle text-{{ $complianceBadge }}-emphasis">Conformité {{ number_format((float) $document->compliance_rate, 0, ',', ' ') }}%</span>
                        </div>
                        @if($document->status === 'ocr_failed' && !empty($document->extracted_data['ocr_error']))
                            <div class="alert alert-danger py-2 px-3 mb-0 small">{{ $document->extracted_data['ocr_error'] }}</div>
                        @endif
                        <a href="{{ route('accountant.files.open', $document) }}" class="btn btn-sm btn-primary rounded-pill align-self-start">
                            {{ $document->status === 'validated' ? 'Voir' : 'Valider' }}
                        </a>
                    </div>
                @endforeach
            @empty
                <div class="text-center text-muted py-4">
                    <i data-feather="inbox" class="mb-2 text-slate-300" style="width: 40px; height: 40px;"></i>
                    <div class="fw-semibold fs-6">{{ $tab === 'failed' ? 'Aucun document en échec.' : 'Aucun document réussi.' }}</div>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
