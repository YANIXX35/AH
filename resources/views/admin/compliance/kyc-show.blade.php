@extends('layouts.app')

@section('title', 'Dossier KYC/KYB')
@section('page_title', 'Validation KYC/KYB')

@section('content')
<div class="container-fluid p-0">
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="mb-1">{{ $kycUser->company_name ?: $kycUser->name }}</h5>
            <p class="text-muted mb-2">{{ $kycUser->email }}</p>
            <p class="mb-0">Statut : <span class="badge bg-{{ $kycUser->kyc_status === 'approved' ? 'success' : ($kycUser->kyc_status === 'rejected' ? 'danger' : 'warning text-dark') }}">{{ strtoupper((string) $kycUser->kyc_status) }}</span></p>
            @if($kycUser->kyc_rejection_reason)
                <div class="alert alert-danger mt-2 mb-0">{{ $kycUser->kyc_rejection_reason }}</div>
            @endif
            @if($kycUser->kyc_status === 'rejected')
                <form method="POST" action="{{ route('admin.compliance.kyc.resubmit', $kycUser) }}" class="mt-3">
                    @csrf
                    <button class="btn btn-sm btn-outline-warning">Resoumettre le dossier KYC</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h5 class="card-title mb-0">Pièces soumises</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Type</th><th>Nom</th><th>Soumis</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                    @forelse($kycUser->kycDocuments as $doc)
                        <tr>
                            <td>{{ $doc->document_type }}</td>
                            <td>{{ $doc->original_name ?: basename($doc->stored_path) }}</td>
                            <td>{{ optional($doc->submitted_at)->format('d/m/Y H:i') ?: '-' }}</td>
                            <td>{{ strtoupper($doc->status) }}</td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" target="_blank" href="{{ route('admin.compliance.kyc.document.stream', $doc) }}">Visualiser</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">Aucune pièce jointe.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h6>Valider le dossier</h6>
                    <form method="POST" action="{{ route('admin.compliance.kyc.approve', $kycUser) }}">
                        @csrf
                        <textarea name="note" class="form-control mb-2" rows="3" placeholder="Note de validation (optionnel)"></textarea>
                        <button class="btn btn-success">Approuver KYC/KYB</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h6>Rejeter le dossier</h6>
                    <form method="POST" action="{{ route('admin.compliance.kyc.reject', $kycUser) }}">
                        @csrf
                        <textarea name="reason" class="form-control mb-2" rows="3" placeholder="Motif de rejet" required></textarea>
                        <button class="btn btn-danger">Rejeter KYC/KYB</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
