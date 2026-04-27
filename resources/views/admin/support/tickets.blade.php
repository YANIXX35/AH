@extends('layouts.app')

@section('title', 'Tickets support | Admin')
@section('page_title', 'Tickets support')

@section('content')
<div class="container-fluid p-0">
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Statut</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="all" @selected($status === 'all')>Tous</option>
                        <option value="open" @selected($status === 'open')>Ouverts</option>
                        <option value="in_progress" @selected($status === 'in_progress')>En cours</option>
                        <option value="closed" @selected($status === 'closed')>Clos</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small mb-1">Attribué à</label>
                    <select name="assignee" class="form-select form-select-sm">
                        <option value="0">Tous</option>
                        @foreach($assignableUsers as $u)
                            <option value="{{ $u->id }}" @selected((int) $assignee === (int) $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-sm btn-primary" type="submit">Filtrer</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Sujet</th>
                        <th>Client</th>
                        <th>Attribué à</th>
                        <th>Statut</th>
                        <th>Dernière activité</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $ticket)
                        <tr>
                            <td>{{ \Illuminate\Support\Str::limit($ticket->subject, 65) }}</td>
                            <td class="small">{{ $ticket->user?->name }}<br><span class="text-muted">{{ $ticket->user?->email }}</span></td>
                            <td class="small">{{ $ticket->assignedTo?->name ?? 'Non attribué' }}</td>
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
                            <td class="small text-muted">{{ $ticket->updated_at?->diffForHumans() }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.support.tickets.show', $ticket) }}" class="btn btn-sm btn-light border">Ouvrir</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Aucun ticket.</td>
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
