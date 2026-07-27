@extends('layouts.app')

@section('title', 'Gestion Commerciale | Administration PME360')
@section('page_title', 'Suivi & Acquisition Commerciale')

@push('styles')
<style>
    .admin-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    }
    .admin-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .admin-badge-success { background: #dcfce7; color: #15803d; }
    .admin-badge-danger { background: #fee2e2; color: #991b1b; }
    .admin-badge-info { background: #dbeafe; color: #1d4ed8; }
    .admin-badge-warning { background: #ffedd5; color: #c2410c; }
</style>
@endpush

@section('content')
<div class="container-fluid p-0">
    <!-- Title -->
    <div class="mb-4">
        <h1 class="h3 mb-2 fw-bold text-dark">Suivi de l'Acquisition Commerciale</h1>
        <p class="text-muted">Consultez l'activité de vos commerciaux, la liste des clients inscrits et le bilan des essais gratuits.</p>
    </div>

    <!-- Metrics Cards Row -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="card admin-card border-0 p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Commerciaux Actifs</span>
                    <span class="admin-badge admin-badge-info">Équipe</span>
                </div>
                <div class="h2 fw-bold text-dark mb-1">{{ number_format($totalCommercials) }}</div>
                <div class="text-muted small">Nombre de commerciaux dans la base.</div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card admin-card border-0 p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Clients Acquis</span>
                    <span class="admin-badge admin-badge-info">Total</span>
                </div>
                <div class="h2 fw-bold text-dark mb-1">{{ number_format($totalClientsReferred) }}</div>
                <div class="text-muted small">Entreprises parrainées au total.</div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card admin-card border-0 p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Essais Actifs</span>
                    <span class="admin-badge admin-badge-success">En cours</span>
                </div>
                <div class="h2 fw-bold text-dark mb-1">{{ number_format($activeTrials) }}</div>
                <div class="text-muted small">Périodes d'essai d'un mois en cours.</div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card admin-card border-0 p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Essais Expirés</span>
                    <span class="admin-badge admin-badge-danger">Expirés</span>
                </div>
                <div class="h2 fw-bold text-dark mb-1">{{ number_format($expiredTrials) }}</div>
                <div class="text-muted small">Essais terminés sans abonnement payé.</div>
            </div>
        </div>
    </div>

    <!-- Activities by Sales Rep -->
    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="card admin-card border-0 p-4 mb-4">
                <h3 class="h5 fw-bold text-dark mb-3">Détail par Commercial</h3>
                
                @forelse($commercials as $commercial)
                    <div class="border rounded-3 p-3 mb-3 bg-light bg-opacity-10">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                            <h4 class="h6 fw-bold mb-0 text-dark">
                                <i data-feather="user" class="me-1 text-primary" style="width:16px; height:16px;"></i>
                                {{ $commercial->name }} 
                                <span class="text-muted font-normal">({{ $commercial->email }})</span>
                            </h4>
                            <span class="badge bg-primary rounded-pill px-2">
                                {{ $commercial->createdClients->count() }} client(s) ajouté(s)
                            </span>
                        </div>

                        @if($commercial->createdClients->isNotEmpty())
                            <div class="table-responsive mt-2">
                                <table class="table table-sm table-hover align-middle mb-0 bg-white border rounded">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Client</th>
                                            <th>Entreprise</th>
                                            <th>Date & Heure d'ajout</th>
                                            <th>Fin de l'essai</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($commercial->createdClients as $client)
                                            @php
                                                $isTrialActive = $client->is_premium && $client->premium_ends_at && $client->premium_ends_at->isFuture();
                                            @endphp
                                            <tr>
                                                <td class="fw-semibold text-dark">{{ $client->name }}</td>
                                                <td>{{ $client->company_name }}</td>
                                                <td>
                                                    <span class="text-dark">{{ $client->created_at->format('d/m/Y') }}</span>
                                                    <span class="text-muted small ms-1">à {{ $client->created_at->format('H:i') }}</span>
                                                </td>
                                                <td>
                                                    @if($client->premium_ends_at)
                                                        {{ $client->premium_ends_at->format('d/m/Y') }}
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($isTrialActive)
                                                        <span class="admin-badge admin-badge-success">Essai Actif</span>
                                                    @else
                                                        <span class="admin-badge admin-badge-danger">Essai Expiré</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-muted small italic py-2">
                                Aucun client parrainé par ce commercial pour le moment.
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-center py-4 text-muted">
                        Aucun commercial configuré. Définissez `role_key = 'commercial'` sur un utilisateur pour le lister ici.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Trace Audit Log column -->
        <div class="col-12 col-lg-4">
            <div class="card admin-card border-0 p-4 mb-4">
                <h3 class="h5 fw-bold text-dark mb-3">Dernières Inscriptions</h3>
                <div class="list-group list-group-flush">
                    @forelse($referredClients->take(8) as $client)
                        <div class="list-group-item px-0 py-3 border-0 border-bottom">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1 fw-bold text-dark">{{ $client->company_name }}</h6>
                                <small class="text-muted">{{ $client->created_at->diffForHumans() }}</small>
                            </div>
                            <p class="mb-1 small text-muted">
                                Ajouté par le commercial : <strong>{{ $client->creator ? $client->creator->name : 'Inconnu' }}</strong>
                            </p>
                            <small class="text-muted">
                                Date : {{ $client->created_at->format('d/m/Y H:i') }}
                            </small>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted">
                            Aucun client parrainé enregistré.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
