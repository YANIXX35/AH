@extends('layouts.app')

@section('title', 'Événements du Club | Sitiame Capital')
@section('page_title', 'Événements du Club')

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h3 mb-0">Événements du Club</h2>
        <a href="{{ route('sport.index') }}" class="btn btn-outline-secondary btn-sm">Retour</a>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-sm">
                <thead><tr><th>Date</th><th>Sujet</th><th>Description</th></tr></thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td>{{ $event->starts_on->format('d/m/Y H:i') }}</td>
                            <td>{{ $event->subject }}</td>
                            <td>{{ $event->description ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">Aucun événement pour l'instant.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
