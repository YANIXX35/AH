@extends('layouts.app')

@section('title', 'Prospection | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@push('styles')
<style>
    .prospection-status-badge { border-radius: 999px; padding: 6px 16px; font-size: .78rem; font-weight: 700; }
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
            <li class="breadcrumb-item"><a href="{{ route('admin.prospections.index') }}">Prospections commerciales</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $prospection->title ?: 'Prospection' }}</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h1 class="h4 mb-1">{{ $prospection->title ?: 'Prospection sans titre' }}</h1>
            <p class="text-muted mb-0">
                Commercial : <strong>{{ $prospection->commercial?->name }}</strong> ({{ $prospection->commercial?->email }})
                · Envoyée le {{ $prospection->submitted_at?->format('d/m/Y H:i') ?? '—' }}
            </p>
        </div>
        <span class="prospection-status-badge status-{{ $prospection->status }}">{{ $prospection->statusLabel() }}</span>
    </div>
</div>

@if(session('status'))
    <div class="alert alert-success border-0 shadow-sm">{{ session('status') }}</div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                @if($prospection->hasContent())
                    <h2 class="h6 mb-2">Compte rendu</h2>
                    <p style="white-space: pre-wrap;">{{ $prospection->content }}</p>
                @else
                    <p class="text-muted mb-0">Aucun texte — voir le fichier joint.</p>
                @endif

                @if($prospection->hasFile())
                    <hr>
                    <h2 class="h6 mb-2">Fichier joint</h2>
                    <div class="d-flex align-items-center gap-2 p-3 bg-light rounded-3">
                        <i data-feather="paperclip" style="width:18px;height:18px;"></i>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $prospection->file_name }}</div>
                            <div class="text-muted small">{{ $prospection->formatted_file_size }}</div>
                        </div>
                        <a href="{{ asset('storage/' . $prospection->file_path) }}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3">Télécharger / Ouvrir</a>
                    </div>
                @endif
            </div>
        </div>

        @if($prospection->admin_comment)
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 mb-2">Commentaire administratif précédent</h2>
                <p class="mb-1">{{ $prospection->admin_comment }}</p>
                @if($prospection->reviewer)
                    <div class="text-muted small">— {{ $prospection->reviewer->name }}, le {{ $prospection->reviewed_at?->format('d/m/Y H:i') }}</div>
                @endif
            </div>
        </div>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h6 mb-3">Décision</h2>
                <form method="POST" action="{{ route('admin.prospections.approve', $prospection) }}" class="mb-2">
                    @csrf
                    <div class="mb-2">
                        <textarea name="admin_comment" rows="3" class="form-control form-control-sm" placeholder="Commentaire (facultatif)"></textarea>
                    </div>
                    <button type="submit" class="btn btn-success w-100 rounded-pill">✅ Valider</button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.prospections.request-revision', $prospection) }}" class="mb-2">
                    @csrf
                    <div class="mb-2">
                        <textarea name="admin_comment" rows="3" class="form-control form-control-sm" placeholder="Expliquez ce qui doit être corrigé…"></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning w-100 rounded-pill">↩ Demander une correction</button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.prospections.reject', $prospection) }}" onsubmit="return confirm('Rejeter définitivement cette prospection ?');">
                    @csrf
                    <div class="mb-2">
                        <textarea name="admin_comment" rows="3" class="form-control form-control-sm" placeholder="Motif du rejet (facultatif)"></textarea>
                    </div>
                    <button type="submit" class="btn btn-outline-danger w-100 rounded-pill">❌ Rejeter</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
