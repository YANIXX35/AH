@extends('layouts.app')

@section('title', 'Mon Solde | SITIAME CAPITAL')
@section('page_title', 'Mon Solde')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><strong>Mon</strong> Solde</h1>
        <p class="text-muted mb-0">Vos commissions calculées en temps réel à partir des clients que vous avez ajoutés.</p>
    </div>
    <a href="{{ route('commercial.dashboard') }}" class="btn btn-outline-secondary btn-sm">Tableau de bord</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100" style="background: #0F2747;">
            <div class="card-body">
                <div class="text-uppercase small fw-bold" style="color: #F2D89B; letter-spacing: 0.05em;">Solde total</div>
                <div class="display-6 fw-bold text-white mt-1">{{ number_format($totalBalance, 0, ',', ' ') }} FCFA</div>
                <div class="small mt-1" style="color: #C7CEDB;">{{ $totalClients }} client{{ $totalClients > 1 ? 's' : '' }} ajouté{{ $totalClients > 1 ? 's' : '' }} au total</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body">
                <div class="text-uppercase small fw-bold text-muted">Bonus d'ajout de clients</div>
                <div class="h3 fw-bold mt-1 mb-0">{{ number_format($totalSignupEarnings, 0, ',', ' ') }} FCFA</div>
                <div class="small text-muted mt-1">{{ number_format($signupBonusTier1, 0, ',', ' ') }} F pour chacun des {{ $tier1Slots }} premiers clients, {{ number_format($signupBonusTier2, 0, ',', ' ') }} F ensuite</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body">
                <div class="text-uppercase small fw-bold text-muted">Commissions de renouvellement</div>
                <div class="h3 fw-bold mt-1 mb-0">{{ number_format($totalRenewalEarnings, 0, ',', ' ') }} FCFA</div>
                <div class="small text-muted mt-1">{{ number_format($renewalBonus, 0, ',', ' ') }} F à chaque renouvellement payé, après l'essai gratuit</div>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info border-0 rounded-4 shadow-sm mb-4">
    <i data-feather="info" class="me-2" style="width:16px;height:16px;"></i>
    <strong>Comment ça marche :</strong>
    quand vous ajoutez un client, vous gagnez {{ number_format($signupBonusTier1, 0, ',', ' ') }} F pour chacun de vos {{ $tier1Slots }} premiers clients (à vie), puis {{ number_format($signupBonusTier2, 0, ',', ' ') }} F pour chaque client suivant — cette part est acquise dès l'ajout.
    Ensuite, une fois la période d'essai gratuite terminée, vous gagnez {{ number_format($renewalBonus, 0, ',', ' ') }} F à chaque fois que ce client repaie réellement son abonnement.
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="min-width: 750px;">
                <thead class="bg-light text-slate-700 text-uppercase fs-8 fw-bold border-bottom">
                    <tr>
                        <th class="py-3 px-3 text-center" style="width: 50px;">#</th>
                        <th class="py-3 px-3">Client / Entreprise</th>
                        <th class="py-3 px-3 text-center">Ajouté le</th>
                        <th class="py-3 px-3 text-end">Bonus d'ajout</th>
                        <th class="py-3 px-3 text-center">Renouvellements</th>
                        <th class="py-3 px-3 text-end">Commission renouvellement</th>
                        <th class="py-3 px-3 text-end">Sous-total</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="py-2.5 px-3 text-center fw-bold text-muted small align-middle">{{ $row['rank'] }}</td>
                        <td class="py-2.5 px-3 align-middle">
                            <div class="fw-semibold text-dark fs-7">{{ $row['client']->company_name ?: $row['client']->name }}</div>
                            <div class="text-muted small">{{ $row['client']->email }}</div>
                        </td>
                        <td class="py-2.5 px-3 text-center text-slate-500 small align-middle">{{ $row['client']->created_at?->format('d/m/Y') }}</td>
                        <td class="py-2.5 px-3 text-end align-middle">
                            {{ number_format($row['signup_bonus'], 0, ',', ' ') }} F
                            @if($row['rank'] <= $tier1Slots)
                                <span class="badge bg-light-warning text-warning ms-1" style="font-size:9px;">Palier 1</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-center align-middle">
                            @if($row['renewal_count'] > 0)
                                <span class="badge bg-light-success text-success">{{ $row['renewal_count'] }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-end align-middle">{{ number_format($row['renewal_earnings'], 0, ',', ' ') }} F</td>
                        <td class="py-2.5 px-3 text-end fw-bold align-middle">{{ number_format($row['subtotal'], 0, ',', ' ') }} F</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            Aucun client ajouté pour le moment. <a href="{{ route('commercial.dashboard', ['action' => 'add-client']) }}">Ajoutez votre premier client</a> pour commencer à gagner des commissions.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
