@extends('layouts.app')

@section('title', 'Demandes d’investissement | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="mb-4">
    <nav aria-label="Fil d’Ariane admin" class="mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Administration</a></li>
            <li class="breadcrumb-item active" aria-current="page">Demandes d’investissement</li>
        </ol>
    </nav>
    <h1 class="h3 mb-1"><strong>Réception</strong> des demandes d’investissement</h1>
    <p class="text-muted mb-0">Dépôts issus de l’espace « Préparation investisseurs » : prise en charge, analyse et décision (hors mission de certification des comptes).</p>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-6 col-md">
        <div class="card border-0 shadow-sm h-100 border-warning border-start border-4">
            <div class="card-body py-3">
                <p class="text-muted small mb-0">En attente</p>
                <p class="h4 mb-0">{{ number_format($counts['pending'], 0, ',', ' ') }}</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="card border-0 shadow-sm h-100 border-info border-start border-4">
            <div class="card-body py-3">
                <p class="text-muted small mb-0">En analyse</p>
                <p class="h4 mb-0">{{ number_format($counts['in_review'], 0, ',', ' ') }}</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <p class="text-muted small mb-0">Acceptées</p>
                <p class="h4 mb-0 text-success">{{ number_format($counts['accepted'], 0, ',', ' ') }}</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <p class="text-muted small mb-0">Refusées</p>
                <p class="h4 mb-0 text-danger">{{ number_format($counts['declined'], 0, ',', ' ') }}</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <p class="text-muted small mb-0">Total</p>
                <p class="h4 mb-0">{{ number_format($counts['all'], 0, ',', ' ') }}</p>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="{{ route('admin.investment-requests.index') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="status">Statut</label>
                <select name="status" id="status" class="form-select">
                    <option value="all" @selected($status === 'all')>Tous</option>
                    <option value="pending" @selected($status === 'pending')>En attente</option>
                    <option value="in_review" @selected($status === 'in_review')>En analyse</option>
                    <option value="accepted" @selected($status === 'accepted')>Acceptées</option>
                    <option value="declined" @selected($status === 'declined')>Refusées</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label" for="q">Recherche (entreprise, nom, email)</label>
                <input type="search" name="q" id="q" class="form-control" value="{{ $q }}" placeholder="…">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filtrer</button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('admin.investment-requests.index') }}" class="btn btn-outline-secondary w-100">Réinitialiser</a>
            </div>
        </form>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-bordered bg-white shadow-sm align-middle">
        <thead class="table-light">
            <tr>
                <th>Date</th>
                <th>Entreprise</th>
                <th>Contact</th>
                <th class="text-end">Montant (XOF)</th>
                <th>Statut</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($requests as $req)
                @php
                    $st = $req->status;
                    $badge = match ($st) {
                        'pending' => 'warning',
                        'in_review' => 'info',
                        'accepted' => 'success',
                        'declined' => 'danger',
                        default => 'secondary',
                    };
                    $label = match ($st) {
                        'pending' => 'En attente',
                        'in_review' => 'En analyse',
                        'accepted' => 'Acceptée',
                        'declined' => 'Refusée',
                        default => $st,
                    };
                @endphp
                <tr>
                    <td class="text-nowrap small">{{ $req->created_at->format('d/m/Y H:i') }}</td>
                    <td class="fw-medium">{{ $req->user->company_name ?? $req->user->name }}</td>
                    <td class="small">{{ $req->user->email }}</td>
                    <td class="text-end">{{ number_format((float) $req->amount_requested, 0, ',', ' ') }}</td>
                    <td><span class="badge bg-{{ $badge }}">{{ $label }}</span></td>
                    <td class="text-nowrap">
                        <a href="{{ route('admin.investment-requests.show', $req) }}" class="btn btn-sm btn-outline-primary">Ouvrir</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-5">Aucune demande pour ces critères.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($requests->hasPages())
    <div class="d-flex justify-content-center mt-3">
        {{ $requests->links('pagination::bootstrap-5') }}
    </div>
@endif
@endsection
