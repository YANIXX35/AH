@extends('layouts.app')

@section('title', 'Mes demandes support | ' . config('app.name'))
@section('page_title', 'Support')

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-0">Mes demandes</h1>
            <p class="text-muted small mb-0">Fils de discussion avec le support.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('support.index') }}" class="btn btn-outline-secondary btn-sm">Centre d’aide</a>
            <a href="{{ route('support.tickets.create') }}" class="btn btn-primary btn-sm">Nouveau message</a>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Sujet</th>
                        <th>Statut</th>
                        <th>Dernière activité</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $ticket)
                        <tr>
                            <td>{{ \Illuminate\Support\Str::limit($ticket->subject, 60) }}</td>
                            <td>
                                @php
                                    $st = match ($ticket->status) {
                                        'open' => ['Ouvert', 'success'],
                                        'in_progress' => ['En cours', 'warning'],
                                        'closed' => ['Clos', 'secondary'],
                                        default => [$ticket->status, 'light'],
                                    };
                                @endphp
                                <span class="badge bg-{{ $st[1] }}">{{ $st[0] }}</span>
                            </td>
                            <td class="text-muted small">{{ $ticket->updated_at?->diffForHumans() }}</td>
                            <td class="text-end">
                                <a href="{{ route('support.tickets.show', $ticket) }}" class="btn btn-sm btn-light border">Ouvrir</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Aucune demande pour le moment.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tickets->hasPages())
            <div class="card-footer">{{ $tickets->links() }}</div>
        @endif
    </div>
</div>
@endsection
