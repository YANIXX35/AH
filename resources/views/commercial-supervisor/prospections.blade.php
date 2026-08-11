@extends('layouts.app')

@section('title', 'Prospections commerciales | Supervision Commerciale | ' . config('app.name'))
@section('page_title', 'Supervision Commerciale')

@push('styles')
<style>
    .prospection-status-badge { border-radius: 999px; padding: 5px 12px; font-size: .72rem; font-weight: 700; }
    .status-submitted { background:#dbeafe; color:#1d4ed8; }
    .status-under_review { background:#fef3c7; color:#92400e; }
    .status-approved { background:#dcfce7; color:#15803d; }
    .status-needs_revision { background:#ffedd5; color:#c2410c; }
    .status-rejected { background:#fee2e2; color:#b91c1c; }
</style>
@endpush

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1"><strong>Prospections</strong> commerciales</h1>
    <p class="text-muted mb-0">Comptes rendus de prospection envoyés par les commerciaux — consultation seule.</p>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Commercial</label>
                <select name="commercial_id" class="form-select">
                    <option value="0">Tous</option>
                    @foreach($commercials as $c)
                        <option value="{{ $c->id }}" @selected((int) $filters['commercial_id'] === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Statut</label>
                <select name="status" class="form-select">
                    <option value="">Tous</option>
                    @foreach(\App\Models\CommercialProspection::STATUS_LABELS as $key => $label)
                        @if($key !== 'draft')
                            <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">Filtrer</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Commercial</th>
                        <th>Prospection</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Statut</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($prospections as $p)
                        <tr>
                            <td class="small">{{ $p->commercial?->name ?? '—' }}</td>
                            <td class="small fw-semibold">{{ $p->title ?: '(sans titre)' }}</td>
                            <td class="small text-muted">{{ $p->submitted_at?->format('d/m/Y') ?? '—' }}</td>
                            <td class="small text-muted">{{ $p->typeLabel() }}</td>
                            <td><span class="prospection-status-badge status-{{ $p->status }}">{{ $p->statusLabel() }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('commercial-supervisor.prospections.show', $p) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">👁 Voir</a>
                                @if($p->hasFile())
                                    <a href="{{ asset('storage/' . $p->file_path) }}" download="{{ $p->file_name }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">⬇ Télécharger</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5">Aucune prospection trouvée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $prospections->links() }}</div>
    </div>
</div>
@endsection
