@extends('layouts.app')

@section('title', 'Prospection | SITIAME CAPITAL')
@section('page_title', 'Prospection commerciale')

@push('styles')
<style>
    .soft-dashboard-body { background: linear-gradient(135deg, #f0f4ff 0%, #eef2f6 45%, #f0f9ff 100%); min-height: 100vh; font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; color: #1e293b; padding: 24px; }
    .soft-dashboard-container { background: #f8fafc; border-radius: 32px; border: 1px solid #e2e8f0; box-shadow: 0 20px 50px rgba(15, 23, 42, 0.06); padding: 24px; }
    .mockup-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.03); }
    .prospection-status-badge { border-radius: 999px; padding: 6px 16px; font-size: .78rem; font-weight: 700; }
    .status-draft { background:#f1f5f9; color:#475569; }
    .status-submitted { background:#dbeafe; color:#1d4ed8; }
    .status-under_review { background:#fef3c7; color:#92400e; }
    .status-approved { background:#dcfce7; color:#15803d; }
    .status-needs_revision { background:#ffedd5; color:#c2410c; }
    .status-rejected { background:#fee2e2; color:#b91c1c; }
</style>
@endpush

@section('content')
<div class="soft-dashboard-body">
    <div class="soft-dashboard-container">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 p-3 bg-white rounded-4 border">
            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('commercial.prospections.index') }}" class="btn btn-light rounded-circle p-2 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                    <i data-feather="arrow-left" style="width:20px; height:20px;"></i>
                </a>
                <div>
                    <h1 class="h4 fw-bold text-dark mb-0">{{ $prospection->title ?: 'Prospection sans titre' }}</h1>
                    <p class="text-muted small mb-0">Créée le {{ $prospection->created_at->format('d/m/Y H:i') }} · Modifiée le {{ $prospection->updated_at->format('d/m/Y H:i') }}</p>
                </div>
            </div>
            <span class="prospection-status-badge status-{{ $prospection->status }}">{{ $prospection->statusLabel() }}</span>
        </div>

        @if(session('status'))
            <div class="alert alert-success rounded-4 mb-4 border-0 shadow-sm">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger rounded-4 mb-4 border-0 shadow-sm">
                <ul class="mb-0 small">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="mockup-card p-4 mb-4">
            @if($prospection->hasContent())
                <h3 class="h6 fw-bold text-dark mb-2">Compte rendu</h3>
                <p class="text-dark" style="white-space: pre-wrap;">{{ $prospection->content }}</p>
            @endif

            @if($prospection->hasFile())
                <hr>
                <h3 class="h6 fw-bold text-dark mb-2">Fichier joint</h3>
                <div class="d-flex align-items-center gap-2 p-3 bg-light rounded-3">
                    <i data-feather="paperclip" style="width:18px;height:18px;"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $prospection->file_name }}</div>
                        <div class="text-muted small">{{ $prospection->formatted_file_size }}</div>
                    </div>
                    <a href="{{ route('commercial.prospections.download', $prospection) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">Télécharger</a>
                </div>
            @endif

            @if($prospection->admin_comment)
                <hr>
                <h3 class="h6 fw-bold text-dark mb-2">Commentaire de l'administration</h3>
                <p class="text-dark mb-0">{{ $prospection->admin_comment }}</p>
            @endif
        </div>

        <div class="d-flex justify-content-end gap-2 flex-wrap">
            @if($prospection->isEditable())
                <a href="{{ route('commercial.prospections.edit', $prospection) }}" class="btn btn-outline-primary rounded-pill px-4 fw-semibold">✏ Modifier</a>
                <form action="{{ route('commercial.prospections.submit', $prospection) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">📤 Envoyer à l'administration</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
