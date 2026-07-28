@extends('layouts.app')

@section('title', 'Sitiame Finance Club | Réseau & Communauté PME')
@section('page_title', 'Sitiame Finance Club 🌐')

@push('styles')
<style>
    .club-bg {
        background-color: #f1f5f9;
        min-height: 100vh;
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        padding: 24px;
    }
    .club-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.03);
    }
</style>
@endpush

@section('content')
<div class="club-bg">
    <div class="container-fluid max-w-7xl mx-auto">
        
        <!-- Hero Header Banner -->
        <div class="club-card p-4 p-md-5 mb-4 bg-dark text-white rounded-4 shadow-sm position-relative overflow-hidden">
            <div class="position-relative z-1">
                <span class="badge bg-warning text-dark rounded-pill px-3 py-2 mb-3 fw-bold">COMMUNAUTÉ SITIAME CAPITAL</span>
                <h1 class="display-5 fw-bold mb-2 text-white">Sitiame Finance Club 🌐</h1>
                <p class="text-white-50 lead mb-4" style="max-width: 750px;">
                    Le réseau exclusif de dirigeants et décideurs financiers de PME en Afrique de l'Ouest. Webinaires mensuels, ateliers pratiques et opportunités de co-investissement.
                </p>
                <a href="{{ route('commercial.dashboard', ['action' => 'add-prospect']) }}" class="btn btn-warning text-dark rounded-pill px-4 py-2 fw-bold">
                    + Inviter une PME au Club
                </a>
            </div>
        </div>

        <!-- Club Features Grid -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="club-card p-4 h-100">
                    <div class="bg-primary text-white rounded-4 p-3 mb-3 d-inline-block">
                        <i data-feather="video" style="width:28px; height:28px;"></i>
                    </div>
                    <h4 class="h5 fw-bold text-dark mb-2">Webinaires Mensuels</h4>
                    <p class="text-muted small mb-0">
                        Sessions virtuelles interactives animées par nos experts comptables et financiers sur la gestion de trésorerie, la fiscalité et le SYSCOHADA.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="club-card p-4 h-100">
                    <div class="bg-success text-white rounded-4 p-3 mb-3 d-inline-block">
                        <i data-feather="briefcase" style="width:28px; height:28px;"></i>
                    </div>
                    <h4 class="h5 fw-bold text-dark mb-2">Deal Flow & Co-Investissement</h4>
                    <p class="text-muted small mb-0">
                        Accès privilégié aux opportunités de financement, partenariats bancaires et levées de fonds FIRD réservés aux PME membres.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="club-card p-4 h-100">
                    <div class="bg-purple text-white rounded-4 p-3 mb-3 d-inline-block" style="background:#8b5cf6;">
                        <i data-feather="users" style="width:28px; height:28px;"></i>
                    </div>
                    <h4 class="h5 fw-bold text-dark mb-2">Réseau Privé Dirigeants</h4>
                    <p class="text-muted small mb-0">
                        Mise en relation directe entre dirigeants de PME, RAF, experts-comptables et investisseurs institutionnels.
                    </p>
                </div>
            </div>
        </div>

        <!-- Monthly Webinars Schedule -->
        <div class="club-card p-4 mb-4">
            <h3 class="h5 fw-bold text-dark mb-3">📅 Programme des Webinaires à venir</h3>
            <div class="table-responsive">
                <table class="table table-hover align-middle border-0 mb-0">
                    <thead>
                        <tr class="text-muted text-uppercase small" style="font-size:0.75rem;">
                            <th class="border-0">Thème du Webinaire</th>
                            <th class="border-0">Intervenant</th>
                            <th class="border-0">Date & Heure</th>
                            <th class="border-0 text-end">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-bold text-dark">Réussir la Clôture de l'Exercice Comptable SYSCOHADA</td>
                            <td>Expert Sitiame Capital</td>
                            <td><span class="badge bg-light text-dark border">15 Décembre · 14:00 - 15:30</span></td>
                            <td class="text-end"><span class="badge bg-success rounded-pill px-3 py-1">Inscriptions Ouvertes</span></td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-dark">Rapprochement Mobile Money & Gestion des Flux de Caisse</td>
                            <td>Spécialiste Trésorerie</td>
                            <td><span class="badge bg-light text-dark border">10 Janvier · 10:00 - 11:30</span></td>
                            <td class="text-end"><span class="badge bg-primary rounded-pill px-3 py-1">À Venir</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
@endsection
