@extends('layouts.app')

@section('title', 'Guides d\'Expertise & Lead Magnets | SITIAME CAPITAL')
@section('page_title', 'Guides Inbound & Lead Magnets')

@push('styles')
<style>
    .guides-bg {
        background-color: #f1f5f9;
        min-height: 100vh;
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        padding: 24px;
    }
    .guide-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.03);
        transition: all 0.25s ease;
    }
    .guide-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 40px -5px rgba(15, 23, 42, 0.08);
        border-color: #cbd5e1;
    }
    .guide-badge {
        background: #dbeafe;
        color: #1d4ed8;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 6px 14px;
        border-radius: 9999px;
    }
</style>
@endpush

@section('content')
<div class="guides-bg">
    <div class="container-fluid max-w-7xl mx-auto">
        
        <!-- Header Banner -->
        <div class="mockup-card p-4 p-md-5 mb-4 bg-dark text-white rounded-4 shadow-sm position-relative overflow-hidden">
            <div class="position-relative z-1">
                <span class="badge bg-primary text-white rounded-pill px-3 py-2 mb-3 fw-bold">MARKETING INBOUND & LEAD MAGNETS</span>
                <h1 class="display-6 fw-bold mb-2 text-white">Guides d'Expertise Financial Readiness 📚</h1>
                <p class="text-white-50 lead mb-0" style="max-width: 700px;">
                    Partagez ces supports de référence SYSCOHADA, Trésorerie Mobile Money et Investor Readiness pour convertir vos PME en clients abonnés.
                </p>
            </div>
        </div>

        <!-- 3 Dedicated Guides Cards Grid -->
        <div class="row g-4 mb-4">
            
            <!-- Guide 1 -->
            <div class="col-lg-4 col-md-6">
                <div class="guide-card p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="guide-badge">GUIDE COMPTABILITÉ</span>
                            <span class="text-muted small">PDF · 24 Pages</span>
                        </div>
                        <div class="bg-primary text-white rounded-4 p-3 mb-3 d-inline-block">
                            <i data-feather="book-open" style="width:32px; height:32px;"></i>
                        </div>
                        <h3 class="h5 fw-bold text-dark mb-2">Guide 1 : Réussir son Bilan SYSCOHADA Révisé</h3>
                        <p class="text-muted small mb-4">
                            Manuel pratique et prêt à l'emploi pour diriger la préparation des états financiers, bilans et comptes de résultat conformes OHADA.
                        </p>
                    </div>
                    <div>
                        <div class="p-3 bg-light rounded-3 mb-3">
                            <div class="text-muted small font-mono">Contenu : Bilan, TAFIRE/TFT, Note Annexes.</div>
                        </div>
                        <a href="{{ route('commercial.showcase') }}" class="btn btn-primary rounded-pill w-100 fw-bold py-2">
                            <i data-feather="download" class="me-1" style="width:16px; height:16px;"></i> Télécharger le Guide 1
                        </a>
                    </div>
                </div>
            </div>

            <!-- Guide 2 -->
            <div class="col-lg-4 col-md-6">
                <div class="guide-card p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="guide-badge bg-success text-white">GUIDE TRÉSORERIE</span>
                            <span class="text-muted small">PDF · 18 Pages</span>
                        </div>
                        <div class="bg-success text-white rounded-4 p-3 mb-3 d-inline-block">
                            <i data-feather="smartphone" style="width:32px; height:32px;"></i>
                        </div>
                        <h3 class="h5 fw-bold text-dark mb-2">Guide 2 : Trésorerie & Mobile Money</h3>
                        <p class="text-muted small mb-4">
                            Procédure pas à pas pour automatiser le rapprochement quotidien Wave, Orange Money et MTN Money avec la comptabilité générale.
                        </p>
                    </div>
                    <div>
                        <div class="p-3 bg-light rounded-3 mb-3">
                            <div class="text-muted small font-mono">Contenu : Rapprochement Mobile, Flux Caisse.</div>
                        </div>
                        <a href="{{ route('commercial.showcase') }}" class="btn btn-success rounded-pill w-100 fw-bold py-2 text-white">
                            <i data-feather="download" class="me-1" style="width:16px; height:16px;"></i> Télécharger le Guide 2
                        </a>
                    </div>
                </div>
            </div>

            <!-- Guide 3 -->
            <div class="col-lg-4 col-md-6">
                <div class="guide-card p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="guide-badge bg-purple text-white" style="background:#8b5cf6;">GUIDE LEVÉE DE FONDS</span>
                            <span class="text-muted small">PDF · 30 Pages</span>
                        </div>
                        <div class="bg-dark text-white rounded-4 p-3 mb-3 d-inline-block">
                            <i data-feather="trending-up" style="width:32px; height:32px;"></i>
                        </div>
                        <h3 class="h5 fw-bold text-dark mb-2">Guide 3 : Investor Readiness & Levée de Fonds</h3>
                        <p class="text-muted small mb-4">
                            Modèle de diagnostic de maturité financière FIRD pour préparer les PME aux due diligences des investisseurs et banques partenaires.
                        </p>
                    </div>
                    <div>
                        <div class="p-3 bg-light rounded-3 mb-3">
                            <div class="text-muted small font-mono">Contenu : Score FIRD, Data Room, Pitch Deck.</div>
                        </div>
                        <a href="{{ route('commercial.showcase') }}" class="btn btn-dark rounded-pill w-100 fw-bold py-2">
                            <i data-feather="download" class="me-1" style="width:16px; height:16px;"></i> Télécharger le Guide 3
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
