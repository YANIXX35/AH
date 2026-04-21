@extends('layouts.app')

@section('title', 'Nouveau message support | ' . config('app.name'))
@section('page_title', 'Nouveau message')

@section('content')
<div class="container-fluid p-0" style="max-width: 720px;">
    <div class="mb-3">
        <a href="{{ route('support.tickets') }}" class="text-muted small text-decoration-none">← Retour aux demandes</a>
    </div>
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">Écrire au support</h5>
            <p class="text-muted small mb-0">Décrivez votre besoin : nous vous répondrons via ce fil et une notification dans l’app.</p>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('support.tickets.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Sujet</label>
                    <input type="text" name="subject" value="{{ old('subject') }}" class="form-control @error('subject') is-invalid @enderror" required maxlength="255" placeholder="Ex. : question sur le bilan">
                    @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Message</label>
                    <textarea name="message" rows="8" class="form-control @error('message') is-invalid @enderror" required placeholder="Votre message (minimum 10 caractères)">{{ old('message') }}</textarea>
                    @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('support.index') }}" class="btn btn-light border">Annuler</a>
                    <button type="submit" class="btn btn-primary">Envoyer</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
