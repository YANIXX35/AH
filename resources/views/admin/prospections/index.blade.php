@extends('layouts.app')

@section('title', 'Prospections commerciales | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

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
    <nav aria-label="Fil d'Ariane admin" class="mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Administration</a></li>
            <li class="breadcrumb-item active" aria-current="page">Prospections commerciales</li>
        </ol>
    </nav>
    <h1 class="h3 mb-1"><strong>Prospections</strong> commerciales</h1>
    <p class="text-muted mb-0">Comptes rendus de prospection envoyés par les commerciaux.</p>
</div>

@if(session('status'))
    <div class="alert alert-success border-0 shadow-sm">{{ session('status') }}</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-lg-2 col-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body text-center py-3">
            <div class="text-muted small">Total</div><div class="h4 mb-0">{{ $stats['total'] }}</div>
        </div></div>
    </div>
    <div class="col-lg-2 col-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body text-center py-3">
            <div class="text-muted small">Cette semaine</div><div class="h4 mb-0">{{ $stats['this_week'] }}</div>
        </div></div>
    </div>
    <div class="col-lg-2 col-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body text-center py-3">
            <div class="text-muted small">En attente</div><div class="h4 mb-0 text-primary">{{ $stats['pending'] }}</div>
        </div></div>
    </div>
    <div class="col-lg-2 col-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body text-center py-3">
            <div class="text-muted small">Validées</div><div class="h4 mb-0 text-success">{{ $stats['approved'] }}</div>
        </div></div>
    </div>
    <div class="col-lg-2 col-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body text-center py-3">
            <div class="text-muted small">À corriger</div><div class="h4 mb-0 text-warning">{{ $stats['needs_revision'] }}</div>
        </div></div>
    </div>
    <div class="col-lg-2 col-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body text-center py-3">
            <div class="text-muted small">Rejetées</div><div class="h4 mb-0 text-danger">{{ $stats['rejected'] }}</div>
        </div></div>
    </div>
</div>

@if($byCommercial->isNotEmpty())
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6 mb-2">Par commercial</h2>
        <div class="d-flex flex-wrap gap-2">
            @foreach($byCommercial as $name => $count)
                <span class="badge bg-light text-dark border">{{ $name ?? '—' }} : {{ $count }}</span>
            @endforeach
        </div>
    </div>
</div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Commercial</label>
                <select name="commercial_id" class="form-select">
                    <option value="0">Tous</option>
                    @foreach($commercials as $c)
                        <option value="{{ $c->id }}" @selected((int) $filters['commercial_id'] === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
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
            <div class="col-md-2">
                <label class="form-label">Du</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Au</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="form-control">
            </div>
            <div class="col-md-2">
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
                                <a href="{{ route('admin.prospections.show', $p) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">👁 Voir</a>
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
