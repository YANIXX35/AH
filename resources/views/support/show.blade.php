@extends('layouts.app')

@section('title', $ticket->subject . ' | Support | ' . config('app.name'))
@section('page_title', 'Conversation')

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <a href="{{ route('support.tickets') }}" class="text-muted small text-decoration-none">← Mes demandes</a>
            <h1 class="h4 mt-2 mb-1">{{ $ticket->subject }}</h1>
            @php
                $st = match ($ticket->status) {
                    'open' => ['Ouvert', 'success'],
                    'in_progress' => ['En cours', 'warning'],
                    'closed' => ['Clos', 'secondary'],
                    default => [$ticket->status, 'light'],
                };
            @endphp
            <span class="badge bg-{{ $st[1] }}">{{ $st[0] }}</span>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            @foreach($ticket->messages as $msg)
                <div class="border-bottom pb-3 mb-3 @if($loop->last) border-0 mb-0 pb-0 @endif">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong class="small">
                            @if($msg->is_staff_reply)
                                <span class="text-primary">Équipe {{ config('app.name') }}</span>
                            @else
                                {{ $msg->user?->name ?? 'Vous' }}
                            @endif
                        </strong>
                        <span class="text-muted small">{{ $msg->created_at?->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="text-body small" style="white-space: pre-wrap;">{{ $msg->body }}</div>
                </div>
            @endforeach
        </div>
    </div>

    @if($ticket->status !== 'closed')
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('support.tickets.messages.store', $ticket) }}">
                    @csrf
                    <label class="form-label">Ajouter un message</label>
                    <textarea name="message" rows="4" class="form-control @error('message') is-invalid @enderror" required placeholder="Votre réponse">{{ old('message') }}</textarea>
                    @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="mt-2 text-end">
                        <button type="submit" class="btn btn-primary btn-sm">Envoyer</button>
                    </div>
                </form>
            </div>
        </div>
    @else
        <p class="text-muted small">Ce fil est clos. <a href="{{ route('support.tickets.create') }}">Ouvrir une nouvelle demande</a>.</p>
    @endif
</div>
@endsection
