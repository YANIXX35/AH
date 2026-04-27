@extends('layouts.app')

@section('title', 'KYC/KYB | Admin')
@section('page_title', 'Centre de conformité KYC/KYB')

@section('content')
<div class="container-fluid p-0">
    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Soumis</div><div class="h4 mb-0">{{ $stats['submitted'] }}</div></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Approuvés</div><div class="h4 mb-0">{{ $stats['approved'] }}</div></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Rejetés</div><div class="h4 mb-0">{{ $stats['rejected'] }}</div></div></div></div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Dossiers entreprises</h5>
            <form method="GET" class="d-flex gap-2">
                <select name="status" class="form-select form-select-sm">
                    @foreach(['all' => 'Tous', 'submitted' => 'Soumis', 'pending' => 'En attente', 'approved' => 'Approuvés', 'rejected' => 'Rejetés'] as $k => $label)
                        <option value="{{ $k }}" {{ $status === $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-outline-primary">Filtrer</button>
            </form>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead><tr><th>Entreprise</th><th>Email</th><th>Statut KYC</th><th>Soumis le</th><th>Pièces</th><th>Actions</th></tr></thead>
                    <tbody>
                    @forelse($users as $u)
                        <tr>
                            <td>{{ $u->company_name ?: $u->name }}</td>
                            <td>{{ $u->email }}</td>
                            <td><span class="badge bg-{{ $u->kyc_status === 'approved' ? 'success' : ($u->kyc_status === 'rejected' ? 'danger' : 'warning text-dark') }}">{{ strtoupper((string) $u->kyc_status) }}</span></td>
                            <td>{{ optional($u->kyc_submitted_at)->format('d/m/Y H:i') ?? '-' }}</td>
                            <td>{{ $u->kycDocuments->count() }}</td>
                            <td class="text-end">
                                @if($u->kyc_status === 'rejected')
                                    <form method="POST" action="{{ route('admin.compliance.kyc.resubmit', $u) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-warning">Resoumettre</button>
                                    </form>
                                @endif
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.compliance.kyc.show', $u) }}">Ouvrir</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">Aucun dossier.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-2">{{ $users->links() }}</div>
        </div>
    </div>
</div>
@endsection
