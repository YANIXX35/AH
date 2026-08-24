@extends('layouts.app')

@section('title', 'Historique de mes analyses | Sitiame Capital')
@section('page_title', 'Historique de mes analyses')

@section('content')
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-1">Historique de mes analyses</h5>
                    <p class="text-muted mb-0">Toutes les notes et décisions que vous avez enregistrées, tous dossiers confondus.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @forelse($notes as $note)
                <div class="border-bottom py-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <a href="{{ route('analyst.pme.show', $note->user_id) }}" class="fw-semibold text-decoration-none">
                            {{ $note->company?->company_name ?: $note->company?->name }}
                        </a>
                        <span class="small text-muted">{{ $note->created_at?->format('d/m/Y H:i') }}</span>
                    </div>
                    <div>{{ $note->note }}</div>
                </div>
            @empty
                <p class="text-muted text-center py-4 mb-0">Vous n'avez encore enregistré aucune note.</p>
            @endforelse

            {{ $notes->links() }}
        </div>
    </div>
@endsection
