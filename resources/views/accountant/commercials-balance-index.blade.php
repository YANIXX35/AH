@extends('layouts.app')

@section('title', 'Suivi Solde Commerciaux | Cabinet')
@section('page_title', 'Suivi Solde Commerciaux')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><strong>Suivi</strong> Solde Commerciaux</h1>
        <p class="text-muted mb-0">Commissions gagnées, montants déjà versés et restes à payer, pour chaque commercial.</p>
    </div>
    <a href="{{ route('accountant.dashboard') }}" class="btn btn-outline-secondary btn-sm">Tableau de bord cabinet</a>
</div>

@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show rounded-3 mb-3 border-0 shadow-sm" role="alert">
        <i data-feather="check-circle" class="me-2 text-success"></i>
        {{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body">
                <div class="text-uppercase small fw-bold text-muted">Total gagné (tous commerciaux)</div>
                <div class="h3 fw-bold mt-1 mb-0">{{ number_format($grandTotalEarned, 0, ',', ' ') }} FCFA</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body">
                <div class="text-uppercase small fw-bold text-muted">Total déjà versé</div>
                <div class="h3 fw-bold mt-1 mb-0 text-success">{{ number_format($grandTotalPaid, 0, ',', ' ') }} FCFA</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100" style="background: #0F2747;">
            <div class="card-body">
                <div class="text-uppercase small fw-bold" style="color: #F2D89B;">Reste à payer (tous commerciaux)</div>
                <div class="h3 fw-bold mt-1 mb-0 text-white">{{ number_format($grandTotalRemaining, 0, ',', ' ') }} FCFA</div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="min-width: 750px;">
                <thead class="bg-light text-slate-700 text-uppercase fs-8 fw-bold border-bottom">
                    <tr>
                        <th class="py-3 px-3">Commercial</th>
                        <th class="py-3 px-3 text-center">Clients ajoutés</th>
                        <th class="py-3 px-3 text-end">Total gagné</th>
                        <th class="py-3 px-3 text-end">Déjà versé</th>
                        <th class="py-3 px-3 text-end">Reste à payer</th>
                        <th class="py-3 px-3 text-center">Statut</th>
                        <th class="py-3 px-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="py-2.5 px-3 align-middle">
                            <div class="fw-semibold text-dark fs-7">{{ $row['commercial']->name }}</div>
                            <div class="text-muted small">{{ $row['commercial']->email }}</div>
                        </td>
                        <td class="py-2.5 px-3 text-center align-middle">{{ $row['totalClients'] }}</td>
                        <td class="py-2.5 px-3 text-end align-middle">{{ number_format($row['totalEarned'], 0, ',', ' ') }} F</td>
                        <td class="py-2.5 px-3 text-end align-middle text-success">{{ number_format($row['totalPaid'], 0, ',', ' ') }} F</td>
                        <td class="py-2.5 px-3 text-end fw-bold align-middle">{{ number_format($row['remaining'], 0, ',', ' ') }} F</td>
                        <td class="py-2.5 px-3 text-center align-middle">
                            @if($row['remaining'] <= 0)
                                <span class="badge bg-light-success text-success">À jour</span>
                            @else
                                <span class="badge bg-light-warning text-warning">Reste à payer</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-end align-middle">
                            <a href="{{ route('accountant.commercials-balance.show', $row['commercial']) }}" class="btn btn-xs btn-primary rounded-pill px-2.5 fw-semibold">
                                Voir le détail
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">Aucun commercial pour le moment.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
