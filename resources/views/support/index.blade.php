@extends('layouts.app')

@section('title', 'Aide & support | ' . config('app.name'))
@section('page_title', 'Aide & support')

@section('content')
<div class="container-fluid p-0">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">Questions fréquentes</h5>
                    <p class="text-muted small mb-0">Réponses rapides avant d’ouvrir un ticket.</p>
                </div>
                <div class="card-body">
                    <div class="accordion" id="faqAccordion">
                        @foreach($faq as $i => $item)
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faqHeading{{ $i }}">
                                    <button class="accordion-button {{ $i > 0 ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse{{ $i }}" aria-expanded="{{ $i === 0 ? 'true' : 'false' }}">
                                        {{ $item['q'] ?? '' }}
                                    </button>
                                </h2>
                                <div id="faqCollapse{{ $i }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body text-muted">
                                        {{ $item['a'] ?? '' }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-primary border-opacity-25 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Besoin d’aide personnalisée ?</h5>
                    <p class="text-muted small">Envoyez un message à l’équipe {{ config('app.name') }}. Vous serez notifié dans l’application à chaque réponse.</p>
                    <a href="{{ route('support.tickets.create') }}" class="btn btn-primary w-100 mb-2">Nouveau message au support</a>
                    <a href="{{ route('support.tickets') }}" class="btn btn-outline-secondary w-100">Mes demandes</a>
                </div>
            </div>
            <div class="card mt-3 border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title">Documentation</h6>
                    <p class="text-muted small mb-2">Synthèse des fonctionnalités et des méthodes de calcul (PDF).</p>
                    <a href="{{ route('documentation.synthese') }}" class="btn btn-outline-primary btn-sm w-100">Télécharger la synthèse (PDF)</a>
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-body small text-muted">
                    <strong class="text-dark d-block mb-1">Astuce</strong>
                    Joignez le contexte (écran, période, message d’erreur) pour un traitement plus rapide.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
