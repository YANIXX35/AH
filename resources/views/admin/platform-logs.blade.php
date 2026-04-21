@extends('layouts.app')

@section('title', 'Journalisation plateforme | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="mb-4">
    <nav aria-label="Fil d'Ariane admin" class="mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Administration</a></li>
            <li class="breadcrumb-item active" aria-current="page">Journalisation plateforme</li>
        </ol>
    </nav>
    <h1 class="h3 mb-1"><strong>Journalisation</strong> complète</h1>
    <p class="text-muted mb-0">Vue consolidée des actions (menu, trésorerie, comptabilité) avec filtres.</p>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Module</label>
                <select name="module" class="form-select">
                    <option value="all" @selected(($filters['module'] ?? 'all') === 'all')>Tous</option>
                    <option value="menu" @selected(($filters['module'] ?? '') === 'menu')>Menu</option>
                    <option value="treasury" @selected(($filters['module'] ?? '') === 'treasury')>Trésorerie</option>
                    <option value="accounting" @selected(($filters['module'] ?? '') === 'accounting')>Comptabilité</option>
                    <option value="auth" @selected(($filters['module'] ?? '') === 'auth')>Authentification</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Utilisateur connecté (acteur)</label>
                <select name="actor_user_id" class="form-select">
                    <option value="0">Tous</option>
                    @foreach($actors as $a)
                        <option value="{{ $a->id }}" @selected((int) ($filters['actor_user_id'] ?? 0) === (int) $a->id)>
                            {{ $a->name }} ({{ $a->email }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Du</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Au</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Recherche</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="nom, action..." class="form-control">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">Filtrer</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Événements</h5>
        <span class="badge bg-secondary">{{ $logs->count() }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Utilisateur connecté</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Détail</th>
                    <th>IP</th>
                </tr>
                </thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="small text-nowrap">{{ $log['created_at']?->format('d/m/Y H:i:s') ?? '—' }}</td>
                        <td>
                            <div class="small fw-medium">
                                {{ $log['actor_name'] ?? '—' }}
                                @if((int) ($log['actor_id'] ?? 0) === (int) auth()->id())
                                    <span class="badge bg-primary ms-1">toi</span>
                                @endif
                            </div>
                            @if(!empty($log['actor_email']))
                                <div class="small text-muted">{{ $log['actor_email'] }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{
                                ($log['module'] ?? '') === 'menu' ? 'bg-info text-dark' :
                                (($log['module'] ?? '') === 'treasury' ? 'bg-success' :
                                (($log['module'] ?? '') === 'auth' ? 'bg-primary' : 'bg-warning text-dark'))
                            }}">
                                {{ $log['module'] ?? '—' }}
                            </span>
                        </td>
                        <td class="small">{{ $log['action'] ?? '—' }}</td>
                        <td class="small text-break" style="max-width: 30rem;">
                            {{ \Illuminate\Support\Str::limit((string) ($log['detail'] ?? '—'), 200) }}
                        </td>
                        <td class="small text-muted text-nowrap">{{ $log['ip'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Aucun événement pour ces filtres.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

