@extends('layouts.app')

@section('title', 'Cabinet comptable | Sitiame Capitale')
@section('page_title', 'Cabinet comptable')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1"><strong>Tableau de bord</strong> cabinet</h1>
    <p class="text-muted mb-0">Vue d’ensemble des dossiers entreprises : volumes, anomalies et raccourcis vers les dossiers clients.</p>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Dossiers clients</div>
                <div class="display-6 fw-bold text-primary">{{ number_format($clientCount) }}</div>
                <p class="small text-muted mb-0">Comptes entreprise (hors admin / cabinet).</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Écritures (tous dossiers)</div>
                <div class="display-6 fw-bold">{{ number_format($entriesTotal) }}</div>
                <p class="small text-muted mb-0">Grand livre agrégé.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 border-warning">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Documents à traiter</div>
                <div class="display-6 fw-bold text-warning">{{ number_format($documentsPending) }}</div>
                <p class="small text-muted mb-0">En attente ou OCR à corriger.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 border-danger">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Écritures OCR « stress »</div>
                <div class="display-6 fw-bold text-danger">{{ number_format($ocrStressEntries) }}</div>
                <p class="small text-muted mb-0">À rapprocher ou saisir manuellement.</p>
            </div>
        </div>
    </div>
</div>

{{-- Hub Comptabilité : même structure que le menu latéral, accès métier une fois un dossier ouvert. --}}
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm overflow-hidden accountant-compta-hub">
            <div class="card-header accountant-compta-hub__header d-flex flex-wrap align-items-center justify-content-between gap-2 py-3">
                <div class="d-flex align-items-center gap-2 text-white">
                    <span class="accountant-compta-hub__icon" aria-hidden="true">📚</span>
                    <div>
                        <div class="fw-semibold">Comptabilité</div>
                        <div class="small opacity-75">Moteur comptable et saisie — raccourcis professionnels</div>
                    </div>
                </div>
                @if($accountingWorkspaceOpen)
                    <span class="badge bg-light text-dark">Dossier : {{ \Illuminate\Support\Str::limit($accountingWorkspaceLabel, 42) }}</span>
                @else
                    <span class="badge bg-warning text-dark">Ouvrez un dossier pour activer les liens</span>
                @endif
            </div>
            <div class="card-body bg-light accountant-compta-hub__body">
                <div class="row g-4">
                    <div class="col-lg-4">
                        <div class="accountant-compta-hub__section-title">Saisie des données</div>
                        <ul class="list-unstyled mb-0 accountant-compta-hub__list">
                            <li>
                                <a href="{{ route('accounting') }}" class="accountant-compta-hub__link"><span class="me-2">✍️</span>Gestion des écritures</a>
                            </li>
                            <li>
                                <a href="{{ route('accounting.documents') }}" class="accountant-compta-hub__link"><span class="me-2">📄</span>Gestion des documents</a>
                            </li>
                            <li>
                                <a href="{{ route('accounting.plan') }}" class="accountant-compta-hub__link"><span class="me-2">📋</span>Plan comptable OHADA</a>
                            </li>
                        </ul>
                    </div>
                    <div class="col-lg-8">
                        <div class="accountant-compta-hub__section-title">Moteur comptable</div>
                        <div class="row g-2">
                            <div class="col-md-6 col-xl-4">
                                <a href="{{ route('accounting') }}#moteur-ecritures" class="accountant-compta-hub__tile">
                                    <span class="accountant-compta-hub__tile-icon">⚡</span>
                                    <span class="accountant-compta-hub__tile-text">Génération d’écritures</span>
                                    <span class="accountant-compta-hub__tile-hint">Saisie &amp; pièces</span>
                                </a>
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <a href="{{ route('accounting.report.journal') }}" class="accountant-compta-hub__tile">
                                    <span class="accountant-compta-hub__tile-icon">📖</span>
                                    <span class="accountant-compta-hub__tile-text">Journal</span>
                                    <span class="accountant-compta-hub__tile-hint">Chronologique</span>
                                </a>
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <a href="{{ route('accounting.report.grand-livre') }}" class="accountant-compta-hub__tile">
                                    <span class="accountant-compta-hub__tile-icon">📑</span>
                                    <span class="accountant-compta-hub__tile-text">Grand livre</span>
                                    <span class="accountant-compta-hub__tile-hint">Par compte</span>
                                </a>
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <a href="{{ route('accounting.report.balance') }}" class="accountant-compta-hub__tile">
                                    <span class="accountant-compta-hub__tile-icon">⚖️</span>
                                    <span class="accountant-compta-hub__tile-text">Balance</span>
                                    <span class="accountant-compta-hub__tile-hint">Équilibre</span>
                                </a>
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <a href="{{ route('accounting.bank-reconciliation') }}" class="accountant-compta-hub__tile">
                                    <span class="accountant-compta-hub__tile-icon">🏦</span>
                                    <span class="accountant-compta-hub__tile-text">Rapprochement bancaire</span>
                                    <span class="accountant-compta-hub__tile-hint">Trésorerie vs classe 5</span>
                                </a>
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <a href="{{ route('accounting.monthly-closing') }}" class="accountant-compta-hub__tile">
                                    <span class="accountant-compta-hub__tile-icon">📅</span>
                                    <span class="accountant-compta-hub__tile-text">Clôture mensuelle</span>
                                    <span class="accountant-compta-hub__tile-hint">Contrôles &amp; repère</span>
                                </a>
                            </div>
                        </div>
                        <p class="small text-muted mb-0 mt-3">
                            Les écrans comptables s’appliquent au <strong>dossier ouvert en session</strong> (menu latéral ou fiche client → « Ouvrir — Comptabilité »).
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .accountant-compta-hub__header {
        background: linear-gradient(135deg, #1e2a3a 0%, #2c3e50 100%);
        border-bottom: 1px solid rgba(255,255,255,.08);
    }
    .accountant-compta-hub__icon { font-size: 1.35rem; line-height: 1; }
    .accountant-compta-hub__section-title {
        font-size: 0.7rem;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #6c757d;
        font-weight: 600;
        margin-bottom: 0.75rem;
        padding-bottom: 0.35rem;
        border-bottom: 1px solid #dee2e6;
    }
    .accountant-compta-hub__list li + li { margin-top: 0.35rem; }
    .accountant-compta-hub__link {
        display: flex;
        align-items: center;
        padding: 0.45rem 0.65rem;
        border-radius: 0.375rem;
        color: #212529;
        text-decoration: none;
        font-weight: 500;
        transition: background .15s ease, color .15s ease;
    }
    .accountant-compta-hub__link:hover {
        background: #fff;
        color: #0d6efd;
        box-shadow: 0 1px 3px rgba(0,0,0,.06);
    }
    .accountant-compta-hub__tile {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        height: 100%;
        min-height: 5.5rem;
        padding: 0.75rem 0.85rem;
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 0.5rem;
        text-decoration: none;
        color: #212529;
        transition: border-color .15s ease, box-shadow .15s ease, transform .12s ease;
    }
    .accountant-compta-hub__tile:hover {
        border-color: #0d6efd;
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.12);
        transform: translateY(-1px);
        color: #0d6efd;
    }
    .accountant-compta-hub__tile-icon { font-size: 1.15rem; line-height: 1; margin-bottom: 0.35rem; }
    .accountant-compta-hub__tile-text { font-weight: 600; font-size: 0.9rem; }
    .accountant-compta-hub__tile-hint { font-size: 0.72rem; color: #6c757d; margin-top: 0.2rem; }
    .accountant-compta-hub__tile:hover .accountant-compta-hub__tile-hint { color: #495057; }
</style>
@endpush

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">Inscriptions récentes</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th>Entreprise / contact</th>
                            <th>E-mail</th>
                            <th class="text-end">Inscrit le</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($recentClients as $u)
                            <tr>
                                <td>
                                    <strong>{{ $u->company_name ?: $u->name }}</strong>
                                    @if($u->company_name)
                                        <div class="small text-muted">{{ $u->name }}</div>
                                    @endif
                                </td>
                                <td class="small">{{ $u->email }}</td>
                                <td class="text-end small">{{ $u->created_at?->format('d/m/Y') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('accountant.clients.show', $u) }}" class="btn btn-sm btn-outline-primary">Fiche</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">Aucun dossier client pour l’instant.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h6 class="fw-semibold">Volume trésorerie enregistré</h6>
                <p class="mb-0"><span class="fs-4 fw-bold">{{ number_format($treasuryVolume, 0, ',', ' ') }}</span> <span class="text-muted">FCFA (transactions effectuées, tous clients)</span></p>
            </div>
        </div>
        <div class="card border-primary border-2">
            <div class="card-body">
                <h6 class="fw-semibold mb-2">Actions</h6>
                <a href="{{ route('accountant.clients.index') }}" class="btn btn-primary w-100 mb-2">Tous les dossiers clients</a>
                <p class="small text-muted mb-0">Ouvrez un dossier depuis la fiche client pour travailler sur sa comptabilité, sa trésorerie ou sa FIRD dans le menu habituel.</p>
            </div>
        </div>
    </div>
</div>
@endsection
