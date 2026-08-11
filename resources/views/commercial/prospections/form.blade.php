@extends('layouts.app')

@section('title', 'Prospection | SITIAME CAPITAL')
@section('page_title', 'Prospection commerciale')

@push('styles')
<style>
    .soft-dashboard-body { background: linear-gradient(135deg, #f0f4ff 0%, #eef2f6 45%, #f0f9ff 100%); min-height: 100vh; font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; color: #1e293b; padding: 24px; }
    .soft-dashboard-container { background: #f8fafc; border-radius: 32px; border: 1px solid #e2e8f0; box-shadow: 0 20px 50px rgba(15, 23, 42, 0.06); padding: 24px; }
    .mockup-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.03); }
    @media (max-width: 767.98px) { .soft-dashboard-body { padding: 10px 8px; } .soft-dashboard-container { padding: 10px; border-radius: 20px; } }
</style>
@endpush

@php
    $isEdit = $prospection->exists;
    $action = $isEdit ? route('commercial.prospections.update', $prospection) : route('commercial.prospections.store');
@endphp

@section('content')
<div class="soft-dashboard-body">
    <div class="soft-dashboard-container">

        <div class="d-flex align-items-center gap-3 mb-4 p-3 bg-white rounded-4 border">
            <a href="{{ route('commercial.prospections.index') }}" class="btn btn-light rounded-circle p-2 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                <i data-feather="arrow-left" style="width:20px; height:20px;"></i>
            </a>
            <div>
                <h1 class="h4 fw-bold text-dark mb-0">{{ $isEdit ? 'Modifier la prospection' : 'Nouvelle prospection' }}</h1>
                <p class="text-muted small mb-0">Écrivez librement, ou joignez un fichier — rien n'est obligatoire, sauf d'avoir au moins l'un des deux.</p>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger rounded-4 mb-4 border-0 shadow-sm">
                <ul class="mb-0 small">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form action="{{ $action }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if($isEdit) @method('PUT') @endif

            <div class="mockup-card p-4 mb-4">
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-dark">Titre (facultatif)</label>
                    <input type="text" name="title" class="form-control form-control-lg rounded-3" placeholder="Ex : Prospection semaine 32" value="{{ old('title', $prospection->title) }}">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-dark">Compte rendu de prospection</label>
                    <textarea name="content" rows="12" class="form-control rounded-3" placeholder="Racontez librement votre prospection : entreprises contactées, appels effectués, résultats, difficultés, prochaines actions…">{{ old('content', $prospection->content) }}</textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-dark">📎 Joindre un fichier (facultatif)</label>
                    <input type="file" name="file" class="form-control rounded-3" accept=".xlsx,.xls,.csv,.pdf,.doc,.docx">
                    <div class="form-text">Formats acceptés : Excel, CSV, PDF, Word. Taille max 20 Mo.</div>

                    @if($isEdit && $prospection->hasFile())
                        <div class="mt-2 p-2 bg-light rounded-3 d-flex justify-content-between align-items-center">
                            <span class="small"><i data-feather="paperclip" style="width:14px;height:14px;" class="me-1"></i>{{ $prospection->file_name }} ({{ $prospection->formatted_file_size }})</span>
                            <label class="small text-danger mb-0">
                                <input type="checkbox" name="remove_file" value="1"> Supprimer
                            </label>
                        </div>
                    @endif
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 flex-wrap">
                <button type="submit" class="btn btn-outline-primary rounded-pill px-4 fw-semibold">
                    💾 Enregistrer comme brouillon
                </button>
                <button type="submit" name="submit_now" value="1" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                    📤 Envoyer à l'administration
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
