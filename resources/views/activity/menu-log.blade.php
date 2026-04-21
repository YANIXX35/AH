@extends('layouts.app')

@section('title', 'Journal d\'activité | Sitiame Capitale')
@section('page_title', 'Journal d\'activité')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1"><strong>Journal</strong> d’activité (menu)</h1>
        <p class="text-muted mb-0">
            @if($showUserColumn)
                Historique des accès et actions enregistrés pour les zones du menu (tous les comptes).
            @else
                Historique de vos accès et actions dans les rubriques du menu latéral.
            @endif
        </p>
    </div>
    <a href="{{ route('dashboard') }}" class="btn btn-outline-primary btn-sm">Retour au tableau de bord</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Date / heure</th>
                    <th>Utilisateur connecté</th>
                    <th>Route</th>
                    <th>Méthode</th>
                    <th>Chemin</th>
                    <th>HTTP</th>
                    <th>IP</th>
                </tr>
                </thead>
                <tbody>
                @forelse($logs as $row)
                    <tr>
                        <td class="text-nowrap small">{{ $row->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') ?? '—' }}</td>
                        <td>
                            <div class="small fw-medium">{{ $row->user?->name ?? '—' }}</div>
                            <div class="small text-muted">{{ $row->user?->email ?? '—' }}</div>
                        </td>
                        <td><code class="small">{{ $row->route_name }}</code></td>
                        <td>
                            @php
                                $m = strtoupper($row->http_method);
                                $badge = match ($m) {
                                    'GET', 'HEAD' => 'bg-secondary',
                                    'POST' => 'bg-primary',
                                    'PUT', 'PATCH' => 'bg-warning text-dark',
                                    'DELETE' => 'bg-danger',
                                    default => 'bg-light text-dark',
                                };
                            @endphp
                            <span class="badge {{ $badge }}">{{ $m }}</span>
                        </td>
                        <td class="small text-break" style="max-width: 14rem;">{{ $row->path }}</td>
                        <td>
                            @php $st = (int) $row->status_code; @endphp
                            <span class="badge {{ $st >= 200 && $st < 300 ? 'bg-success' : ($st >= 400 ? 'bg-danger' : 'bg-secondary') }}">{{ $st }}</span>
                            @if($row->was_platform_admin)
                                <span class="badge bg-primary ms-1" title="Compte administrateur plateforme">Admin</span>
                            @endif
                        </td>
                        <td class="small text-muted text-nowrap">{{ $row->ip ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            Aucune action enregistrée pour le moment. Naviguez dans le menu pour alimenter ce journal.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($logs->hasPages())
        <div class="card-footer bg-white">
            {{ $logs->links() }}
        </div>
    @endif
</div>
@endsection
