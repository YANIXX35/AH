@extends('layouts.app')

@section('title', 'Suivi des Portefeuilles Commerciaux | Cabinet Comptable')
@section('page_title', 'Suivi des Commerciaux')

@push('styles')
<style>
    .mondays-container {
        background-color: #f8fafc;
        min-height: 100vh;
    }
    .mondays-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03), 0 4px 12px rgba(0, 0, 0, 0.02);
        transition: all 0.2s ease-in-out;
    }
    .mondays-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
    }
    .commercial-avatar {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: linear-gradient(135deg, #3b82f6, #6366f1);
        color: #ffffff;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }
    .timestamp-badge {
        background: #f1f5f9;
        color: #334155;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 4px 10px;
        font-family: monospace;
        font-size: 0.8rem;
        font-weight: 600;
    }
</style>
@endpush

@section('content')
<div class="mondays-container pb-5">
    
    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <div class="text-muted small fw-semibold">
                <i data-feather="users" class="me-1" style="width:14px; height:14px;"></i>
                Cabinet Comptable — Supervision Force Commerciale
            </div>
            <h1 class="h3 fw-bold text-dark mb-0">Suivi des Portefeuilles Commerciaux 📊</h1>
            <p class="text-muted small mb-0">Traçabilité en temps réel et horodatage des PME apportées par chaque commercial.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('accountant.dashboard') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-semibold">
                &larr; Tableau de bord cabinet
            </a>
            <a href="{{ route('accountant.clients.index') }}" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold">
                <i data-feather="folder" class="me-1" style="width:14px; height:14px;"></i> Tous les Dossiers Clients
            </a>
        </div>
    </div>

    <!-- STATS CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="mondays-card p-3">
                <div class="text-muted small fw-semibold mb-1">👥 Force Commerciale</div>
                <div class="fs-3 fw-bold text-dark">{{ $totalCommercials }}</div>
                <div class="text-muted small">Commerciaux actifs</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="mondays-card p-3">
                <div class="text-muted small fw-semibold mb-1">🏢 Total PME Apportées</div>
                <div class="fs-3 fw-bold text-primary">{{ $totalClientsReferred }}</div>
                <div class="text-muted small">Entreprises inscrites</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="mondays-card p-3">
                <div class="text-muted small fw-semibold mb-1">⏱️ Essais 30j Actifs</div>
                <div class="fs-3 fw-bold text-success">{{ $activeTrials }}</div>
                <div class="text-muted small">Périodes d'essai en cours</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="mondays-card p-3">
                <div class="text-muted small fw-semibold mb-1">⚠️ Essais Expirés</div>
                <div class="fs-3 fw-bold text-danger">{{ $expiredTrials }}</div>
                <div class="text-muted small">À convertir / relancer</div>
            </div>
        </div>
    </div>

    <!-- SELECTION ET CARTES COMMERCIAUX -->
    <h5 class="fw-bold text-dark mb-3">👔 Équipe Commerciale &amp; Volumes d'Acquisition</h5>
    <div class="row g-3 mb-4">
        @forelse($commercials as $comm)
            @php
                $createdCount = $comm->createdClients->count();
                $lastClient = $comm->createdClients->first();
                $isFiltered = request('commercial_id') == $comm->id;
            @endphp
            <div class="col-md-4">
                <div class="mondays-card p-3 position-relative {{ $isFiltered ? 'border-primary shadow-sm bg-primary-subtle' : '' }}">
                    <div class="d-flex align-items-center gap-3">
                        <div class="commercial-avatar">
                            {{ strtoupper(substr($comm->name ?? 'C', 0, 2)) }}
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <h6 class="fw-bold text-dark mb-0 text-truncate">{{ $comm->name }}</h6>
                            <div class="text-muted small text-truncate">{{ $comm->email }}</div>
                            <div class="mt-1 d-flex align-items-center gap-2">
                                <span class="badge bg-primary rounded-pill px-2.5 py-1">{{ $createdCount }} PME créée(s)</span>
                            </div>
                        </div>
                    </div>

                    @if($lastClient)
                        <div class="mt-3 pt-2 border-top text-muted small">
                            <i data-feather="clock" class="me-1" style="width:12px; height:12px;"></i>
                            Dernier ajout : <strong>{{ $lastClient->created_at?->format('d/m/Y H:i:s') }}</strong>
                        </div>
                    @else
                        <div class="mt-3 pt-2 border-top text-muted small italic">
                            Aucune PME ajoutée pour le moment.
                        </div>
                    @endif

                    <div class="mt-3">
                        @if($isFiltered)
                            <a href="{{ route('accountant.commercials.index') }}" class="btn btn-sm btn-outline-secondary w-100 rounded-pill fw-semibold">
                                Réinitialiser le filtre &times;
                            </a>
                        @else
                            <a href="{{ route('accountant.commercials.index', ['commercial_id' => $comm->id]) }}" class="btn btn-sm btn-outline-primary w-100 rounded-pill fw-semibold">
                                Voir le portefeuille ({{ $createdCount }}) &rarr;
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="mondays-card p-4 text-center text-muted">
                    <i data-feather="user-x" class="mb-2" style="width:36px; height:36px;"></i>
                    <h6>Aucun commercial trouvé</h6>
                    <p class="small mb-0">Créez un compte avec le rôle commercial pour commencer le suivi.</p>
                </div>
            </div>
        @endforelse
    </div>

    <!-- TABLEAU DE TRAÇABILITÉ HORODATÉE -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0">
            📌 Historique &amp; Horodatage des Entreprises
            @if($selectedCommercial)
                <span class="text-primary fs-6 fw-normal"> (Filtré par : <strong>{{ $selectedCommercial->name }}</strong>)</span>
            @endif
        </h5>
        @if($selectedCommercial)
            <a href="{{ route('accountant.commercials.index') }}" class="btn btn-xs btn-link text-decoration-none">Voir tous les commerciaux</a>
        @endif
    </div>

    <div class="mondays-card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="min-width: 850px;">
                <thead class="bg-light text-uppercase fs-8 fw-bold border-bottom">
                    <tr>
                        <th class="py-3 px-4">🏢 Entreprise / Raison Sociale</th>
                        <th class="py-3 px-3">👤 Commercial (Apporteur)</th>
                        <th class="py-3 px-3">👤 Dirigeant / Contact</th>
                        <th class="py-3 px-3 text-center">⏰ Date &amp; Heure d'Ajout</th>
                        <th class="py-3 px-3 text-center">⏱️ Statut Essai 30j</th>
                        <th class="py-3 px-4 text-end">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @php
                        $listToDisplay = $selectedCommercial ? $selectedCommercial->createdClients : $referredClients;
                    @endphp

                    @forelse($listToDisplay as $client)
                        @php
                            $creator = $client->creator ?? $selectedCommercial;
                            $now = \Carbon\Carbon::now();
                            $endsAt = $client->premium_ends_at;
                            $isTrialActive = $client->is_premium && $endsAt && $endsAt->isFuture();
                            $daysLeft = ($isTrialActive && $endsAt) ? max(0, $now->diffInDays($endsAt, false)) : 0;
                        @endphp
                        <tr>
                            <!-- Entreprise -->
                            <td class="py-3 px-4">
                                <div class="fw-bold text-dark">{{ $client->company_name ?: ($client->name ?: 'Sans nom') }}</div>
                                <div class="d-flex align-items-center gap-1 mt-1">
                                    @if($client->company_sigle)
                                        <span class="badge bg-light text-dark border px-2 py-0.5" style="font-size:10px;">{{ $client->company_sigle }}</span>
                                    @endif
                                    @if($client->company_tax_id)
                                        <span class="badge bg-info-subtle text-info px-2 py-0.5" style="font-size:10px;">NIF: {{ $client->company_tax_id }}</span>
                                    @endif
                                    @if($client->city)
                                        <span class="text-muted small ms-1">📍 {{ $client->city }}</span>
                                    @endif
                                </div>
                            </td>

                            <!-- Commercial Apporteur -->
                            <td class="py-3 px-3">
                                @if($creator)
                                    <div class="fw-semibold text-dark">{{ $creator->name }}</div>
                                    <div class="text-muted small" style="font-size:11px;">{{ $creator->email }}</div>
                                @else
                                    <span class="text-muted small">Non attribué</span>
                                @endif
                            </td>

                            <!-- Dirigeant -->
                            <td class="py-3 px-3">
                                <div class="fw-semibold text-dark">{{ $client->name }}</div>
                                <div class="text-muted small" style="font-size:11px;">{{ $client->email }}</div>
                                @if($client->phone)
                                    <div class="text-muted small" style="font-size:11px;">📱 {{ $client->phone }}</div>
                                @endif
                            </td>

                            <!-- Date & Heure (Horodatage) -->
                            <td class="py-3 px-3 text-center">
                                @if($client->created_at)
                                    <div class="timestamp-badge d-inline-block">
                                        📅 {{ $client->created_at->format('d/m/Y') }} &nbsp; ⏰ {{ $client->created_at->format('H:i:s') }}
                                    </div>
                                    <div class="text-muted small mt-1" style="font-size:10px;">
                                        ({{ $client->created_at->diffForHumans() }})
                                    </div>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>

                            <!-- Statut Essai -->
                            <td class="py-3 px-3 text-center">
                                @if($isTrialActive)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 rounded-pill fw-semibold">
                                        ✓ Essai Actif ({{ ceil($daysLeft) }}j restants)
                                    </span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1.5 rounded-pill fw-semibold">
                                        ✗ Essai Expiré
                                    </span>
                                @endif
                            </td>

                            <!-- Action -->
                            <td class="py-3 px-4 text-end">
                                <a href="{{ route('accountant.clients.show', $client->id) }}" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold">
                                    <i data-feather="folder" class="me-1" style="width:12px; height:12px;"></i> Dossier
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i data-feather="inbox" class="mb-2" style="width:36px; height:36px;"></i>
                                <div class="fw-semibold">Aucune entreprise trouvée pour cet enregistrement.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
