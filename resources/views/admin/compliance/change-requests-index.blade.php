@extends('layouts.app')

@section('title', 'Validations comptables | Admin')
@section('page_title', 'Validations comptables — vue d\'ensemble')

@section('content')
<div class="container-fluid p-0">
    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">En attente</div><div class="h4 mb-0">{{ $stats['pending'] }}</div></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Approuvées</div><div class="h4 mb-0">{{ $stats['approved'] }}</div></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Refusées</div><div class="h4 mb-0">{{ $stats['rejected'] }}</div></div></div></div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Demandes de modification / suppression comptables</h5>
            <form method="GET" class="d-flex gap-2">
                <select name="status" class="form-select form-select-sm">
                    @foreach(['all' => 'Toutes', 'pending' => 'En attente', 'approved' => 'Approuvées', 'rejected' => 'Refusées'] as $k => $label)
                        <option value="{{ $k }}" {{ $status === $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-outline-primary">Filtrer</button>
            </form>
        </div>
        <div class="card-body">
            <p class="text-muted small">Vue en lecture seule — un comptable (n'importe lequel, non rattaché à une entreprise précise) approuve ou refuse depuis son propre espace.</p>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Entreprise</th>
                            <th>Demandé par</th>
                            <th>Action</th>
                            <th>Élément</th>
                            <th>Demandé le</th>
                            <th>Statut</th>
                            <th>Traité par</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($requests as $r)
                        <tr>
                            <td>{{ $r->workspace?->company_name ?: $r->workspace?->name ?: '—' }}</td>
                            <td>{{ $r->requester?->name ?? '—' }}</td>
                            <td>{{ ['update' => 'Modification', 'delete' => 'Suppression', 'cancel' => 'Annulation'][$r->action] ?? $r->action }}</td>
                            <td>{{ $r->subject_label }}</td>
                            <td class="small">{{ $r->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <span class="badge bg-{{ $r->status === 'approved' ? 'success' : ($r->status === 'rejected' ? 'danger' : 'warning text-dark') }}">{{ strtoupper($r->status) }}</span>
                            </td>
                            <td>
                                @if($r->reviewer)
                                    <div class="small fw-medium">{{ $r->reviewer->name }}</div>
                                    <div class="small text-muted">{{ optional($r->reviewed_at)->format('d/m/Y H:i') }}</div>
                                    @if($r->review_note)
                                        <div class="small text-muted">« {{ \Illuminate\Support\Str::limit($r->review_note, 80) }} »</div>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">Aucune demande.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-2">{{ $requests->links() }}</div>
        </div>
    </div>
</div>
@endsection
