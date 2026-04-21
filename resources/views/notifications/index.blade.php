@extends('layouts.app')

@section('title', 'Notifications | ' . config('app.name'))
@section('page_title', 'Notifications')

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Notifications</h1>
        @if($notifications->isNotEmpty())
            <form action="{{ route('notifications.read-all') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">Tout marquer comme lu</button>
            </form>
        @endif
    </div>

    <div class="card">
        <div class="list-group list-group-flush">
            @forelse($notifications as $n)
                <div class="list-group-item {{ $n->read_at ? '' : 'bg-light' }}">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $n->title }}</div>
                            @if($n->body)
                                <div class="text-muted small mt-1">{{ $n->body }}</div>
                            @endif
                            <div class="text-muted small mt-1">{{ $n->created_at?->diffForHumans() }}</div>
                        </div>
                        <div class="d-flex flex-column align-items-end gap-1">
                            @if($n->action_url)
                                <a href="{{ $n->action_url }}" class="btn btn-sm btn-outline-primary">Voir</a>
                            @endif
                            @if(!$n->read_at)
                                <form action="{{ route('notifications.read', $n) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-link p-0 small">Marquer lu</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="list-group-item text-muted text-center py-5">Aucune notification.</div>
            @endforelse
        </div>
        @if($notifications->hasPages())
            <div class="card-footer">{{ $notifications->links() }}</div>
        @endif
    </div>
</div>
@endsection
