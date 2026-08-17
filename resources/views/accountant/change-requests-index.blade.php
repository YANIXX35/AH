@extends('layouts.app')

@section('title', 'Validation comptable | Cabinet')
@section('page_title', 'Validation des modifications')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><strong>Validation</strong> comptable</h1>
        <p class="text-muted mb-0">Toute modification ou suppression d'écriture, de document ou de facture demandée par un client attend votre validation ici.</p>
    </div>
    <a href="{{ route('accountant.dashboard') }}" class="btn btn-outline-secondary btn-sm">Tableau de bord cabinet</a>
</div>

@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show rounded-3 mb-3 border-0 shadow-sm" role="alert">
        {{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<form method="GET" class="d-flex gap-2 mb-3">
    <select name="status" class="form-select form-select-sm" style="max-width: 220px;" onchange="this.form.submit()">
        @foreach(['pending' => 'En attente', 'approved' => 'Approuvées', 'rejected' => 'Refusées', 'all' => 'Toutes'] as $k => $label)
            <option value="{{ $k }}" {{ $status === $k ? 'selected' : '' }}>{{ $label }} @if($k === 'pending') ({{ $pendingCount }}) @endif</option>
        @endforeach
    </select>
</form>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Entreprise</th>
                        <th>Demandé par</th>
                        <th>Action</th>
                        <th>Élément</th>
                        <th>Demandé le</th>
                        <th>Statut</th>
                        <th class="text-end">Décision</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($requests as $r)
                    <tr>
                        <td>{{ $r->workspace?->company_name ?: $r->workspace?->name ?: '—' }}</td>
                        <td>
                            <div class="small fw-medium">{{ $r->requester?->name ?? '—' }}</div>
                            <div class="small text-muted">{{ $r->requester?->email }}</div>
                        </td>
                        <td>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis">
                                {{ ['update' => 'Modification', 'delete' => 'Suppression', 'cancel' => 'Annulation'][$r->action] ?? $r->action }}
                            </span>
                        </td>
                        <td>
                            {{ $r->subject_label }}
                            @if($r->action === 'update' && !empty($r->payload))
                                <details class="small text-muted mt-1">
                                    <summary>Détail proposé</summary>
                                    <code class="d-block mt-1">{{ \Illuminate\Support\Str::limit(json_encode($r->payload, JSON_UNESCAPED_UNICODE), 300) }}</code>
                                </details>
                            @endif
                        </td>
                        <td class="small">{{ $r->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            @if($r->status === 'pending')
                                <span class="badge bg-warning-subtle text-warning-emphasis">En attente</span>
                            @elseif($r->status === 'approved')
                                <span class="badge bg-success-subtle text-success-emphasis">Approuvée</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger-emphasis">Refusée</span>
                            @endif
                            @if($r->status !== 'pending' && $r->reviewer)
                                <div class="small text-muted mt-1">par {{ $r->reviewer->name }}</div>
                            @endif
                        </td>
                        <td class="text-end">
                            @if($r->status === 'pending')
                                <div class="d-inline-flex gap-1">
                                    <form method="POST" action="{{ route('accountant.change-requests.approve', $r) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-success" onclick="return confirm('Approuver et appliquer cette demande ?');">Approuver</button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $r->id }}">Refuser</button>
                                </div>

                                <div class="modal fade" id="rejectModal{{ $r->id }}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="{{ route('accountant.change-requests.reject', $r) }}">
                                                @csrf
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Refuser la demande</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <label class="form-label">Motif du refus</label>
                                                    <textarea name="note" class="form-control" rows="3" required minlength="5"></textarea>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                                                    <button type="submit" class="btn btn-danger">Refuser</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <span class="text-muted small">{{ $r->review_note }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Aucune demande.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-2">{{ $requests->links() }}</div>
@endsection
