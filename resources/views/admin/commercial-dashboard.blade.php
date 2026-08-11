@extends('layouts.app')

@section('title', 'Dashboard Commercial | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

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
    .prospection-status-badge { border-radius: 999px; padding: 4px 10px; font-size: .7rem; font-weight: 700; }
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
            <li class="breadcrumb-item active" aria-current="page">Dashboard Commercial</li>
        </ol>
    </nav>
    <h1 class="h3 mb-1 fw-bold text-dark">Pilotage de <strong>l'équipe commerciale</strong></h1>
    <p class="text-muted mb-0">Performance, commissions (lecture seule), prospections, pipeline et retours de tous les commerciaux.</p>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-xl-2 col-sm-4 col-6">
        <div class="card admin-card border-0 p-3 h-100">
            <div class="text-muted small fw-semibold text-uppercase mb-1">Commerciaux</div>
            <div class="h3 fw-bold text-dark mb-0">{{ number_format($totalCommercials) }}</div>
        </div>
    </div>
    <div class="col-xl-2 col-sm-4 col-6">
        <div class="card admin-card border-0 p-3 h-100">
            <div class="text-muted small fw-semibold text-uppercase mb-1">Clients parrainés</div>
            <div class="h3 fw-bold text-dark mb-0">{{ number_format($totalClientsReferred) }}</div>
        </div>
    </div>
    <div class="col-xl-2 col-sm-4 col-6">
        <div class="card admin-card border-0 p-3 h-100">
            <div class="text-muted small fw-semibold text-uppercase mb-1">Ce mois-ci</div>
            <div class="h3 fw-bold text-dark mb-0">{{ number_format($referredClientsThisMonth) }}</div>
        </div>
    </div>
    <div class="col-xl-2 col-sm-4 col-6">
        <div class="card admin-card border-0 p-3 h-100">
            <div class="text-muted small fw-semibold text-uppercase mb-1">Commissions dues</div>
            <div class="h3 fw-bold text-dark mb-0">{{ number_format($grandTotalEarned) }}</div>
            <div class="text-muted small">FCFA gagnés (total)</div>
        </div>
    </div>
    <div class="col-xl-2 col-sm-4 col-6">
        <div class="card admin-card border-0 p-3 h-100">
            <div class="text-muted small fw-semibold text-uppercase mb-1">Déjà versées</div>
            <div class="h3 fw-bold text-success mb-0">{{ number_format($grandTotalPaid) }}</div>
            <div class="text-muted small">FCFA</div>
        </div>
    </div>
    <div class="col-xl-2 col-sm-4 col-6">
        <div class="card admin-card border-0 p-3 h-100">
            <div class="text-muted small fw-semibold text-uppercase mb-1">Restant dû</div>
            <div class="h3 fw-bold text-warning mb-0">{{ number_format($grandTotalRemaining) }}</div>
            <div class="text-muted small">FCFA</div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Performance par commercial -->
    <div class="col-12 col-xl-8">
        <div class="card admin-card border-0 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h5 fw-bold text-dark mb-0">Performance par commercial</h3>
                <span class="text-muted small">Triés par commissions gagnées</span>
            </div>
            @if($rows->isEmpty())
                <div class="text-center py-4 text-muted">Aucun commercial configuré.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Commercial</th>
                                <th class="text-center">Clients</th>
                                <th class="text-center">Prospects</th>
                                <th class="text-end">Gagné</th>
                                <th class="text-end">Versé</th>
                                <th class="text-end">Restant</th>
                                <th>Dernière activité</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $row['commercial']->name }}</div>
                                        <div class="text-muted small">{{ $row['commercial']->email }}</div>
                                    </td>
                                    <td class="text-center">{{ $row['totalClients'] }}</td>
                                    <td class="text-center">{{ $row['prospectsCount'] }}</td>
                                    <td class="text-end">{{ number_format($row['totalEarned']) }} F</td>
                                    <td class="text-end text-success">{{ number_format($row['totalPaid']) }} F</td>
                                    <td class="text-end text-warning fw-semibold">{{ number_format($row['remaining']) }} F</td>
                                    <td>
                                        <div class="small text-muted">
                                            Connexion : {{ $row['lastLoginAt'] ? \Illuminate\Support\Carbon::parse($row['lastLoginAt'])->diffForHumans() : '—' }}
                                        </div>
                                        <div class="small text-muted">
                                            Prospection : {{ $row['lastProspectionAt'] ? \Illuminate\Support\Carbon::parse($row['lastProspectionAt'])->diffForHumans() : '—' }}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mt-3 mb-0">
                    Les montants sont en lecture seule ici. La validation des versements se fait toujours côté cabinet comptable
                    (<a href="{{ route('accountant.commercials-balance.index') }}">Suivi Solde Commerciaux</a>).
                </p>
            @endif
        </div>

        <!-- Prospections récentes -->
        <div class="card admin-card border-0 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h5 fw-bold text-dark mb-0">Prospections récentes</h3>
                <a href="{{ route('admin.prospections.index') }}" class="btn btn-sm btn-outline-primary">Voir toutes les prospections</a>
            </div>
            @if($recentProspections->isEmpty())
                <div class="text-center py-4 text-muted">Aucune prospection envoyée pour le moment.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Commercial</th>
                                <th>Titre</th>
                                <th>Statut</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentProspections as $prospection)
                                <tr>
                                    <td>{{ $prospection->commercial->name ?? '—' }}</td>
                                    <td>{{ $prospection->title ?: 'Sans titre' }}</td>
                                    <td><span class="prospection-status-badge status-{{ $prospection->status }}">{{ $prospection->statusLabel() }}</span></td>
                                    <td class="text-muted small">{{ $prospection->created_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Colonne latérale -->
    <div class="col-12 col-xl-4">
        <!-- Pipeline de prospects -->
        <div class="card admin-card border-0 p-4 mb-4">
            <h3 class="h5 fw-bold text-dark mb-3">Pipeline de prospects</h3>
            @if($prospectStatusStats->isEmpty())
                <div class="text-muted small">Aucun prospect enregistré.</div>
            @else
                @php
                    $prospectLabels = [
                        'nouveau' => 'Nouveau Lead',
                        'contacte' => 'Contacté',
                        'qualifie' => 'Qualifié',
                        'client' => 'Converti en Client',
                        'sans_suite' => 'Sans suite',
                    ];
                @endphp
                <ul class="list-group list-group-flush">
                    @foreach($prospectStatusStats as $status => $count)
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center border-0 border-bottom">
                            <span class="text-dark">{{ $prospectLabels[$status] ?? ucfirst($status) }}</span>
                            <span class="badge bg-primary rounded-pill">{{ $count }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <!-- Prospections par statut -->
        <div class="card admin-card border-0 p-4 mb-4">
            <h3 class="h5 fw-bold text-dark mb-3">Prospections par statut</h3>
            @if($prospectionStats->isEmpty())
                <div class="text-muted small">Aucune prospection.</div>
            @else
                @php
                    $prospectionLabels = \App\Models\CommercialProspection::STATUS_LABELS;
                @endphp
                <ul class="list-group list-group-flush">
                    @foreach($prospectionStats as $status => $count)
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center border-0 border-bottom">
                            <span class="prospection-status-badge status-{{ $status }}">{{ $prospectionLabels[$status] ?? ucfirst($status) }}</span>
                            <span class="badge bg-secondary rounded-pill">{{ $count }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <!-- Retours commerciaux -->
        <div class="card admin-card border-0 p-4 mb-4">
            <h3 class="h5 fw-bold text-dark mb-3">Derniers retours (feedback)</h3>
            @if($recentFeedback->isEmpty())
                <div class="text-muted small">Aucun retour envoyé.</div>
            @else
                <ul class="list-group list-group-flush">
                    @foreach($recentFeedback as $feedback)
                        <li class="list-group-item px-0 border-0 border-bottom">
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold text-dark">{{ $feedback->user->name ?? '—' }}</span>
                                <span class="text-warning">{{ str_repeat('★', (int) $feedback->rating) }}{{ str_repeat('☆', 5 - (int) $feedback->rating) }}</span>
                            </div>
                            <div class="text-muted small">{{ $feedback->satisfaction_label }} · {{ $feedback->created_at->diffForHumans() }}</div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <!-- Documents importés -->
        <div class="card admin-card border-0 p-4">
            <h3 class="h5 fw-bold text-dark mb-3">Documents importés récemment</h3>
            @if($recentDocuments->isEmpty())
                <div class="text-muted small">Aucun document importé.</div>
            @else
                <ul class="list-group list-group-flush">
                    @foreach($recentDocuments as $document)
                        <li class="list-group-item px-0 border-0 border-bottom">
                            <div class="fw-semibold text-dark small">{{ $document->original_name }}</div>
                            <div class="text-muted small">{{ $document->user->name ?? '—' }} · {{ $document->created_at->diffForHumans() }}</div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection
