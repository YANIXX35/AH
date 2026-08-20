{{-- Corps partagé de la fiche détaillée d'un commercial (clients + détail commissions, prospects,
     prospections, historique de connexion). Utilisé par admin/commercial-show.blade.php et
     commercial-supervisor/commercial-show.blade.php. --}}
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card admin-card border-0 p-3 h-100">
            <div class="text-muted small fw-semibold text-uppercase mb-1">Clients parrainés</div>
            <div class="h3 fw-bold text-dark mb-0">{{ number_format($totalClients) }}</div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card admin-card border-0 p-3 h-100">
            <div class="text-muted small fw-semibold text-uppercase mb-1">Commissions gagnées</div>
            <div class="h3 fw-bold text-dark mb-0">{{ number_format($totalEarned) }} F</div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card admin-card border-0 p-3 h-100">
            <div class="text-muted small fw-semibold text-uppercase mb-1">Déjà versées</div>
            <div class="h3 fw-bold text-success mb-0">{{ number_format($totalPaid) }} F</div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card admin-card border-0 p-3 h-100">
            <div class="text-muted small fw-semibold text-uppercase mb-1">Restant dû</div>
            <div class="h3 fw-bold text-warning mb-0">{{ number_format($remaining) }} F</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 col-xl-7">
        <div id="commercial-portefeuille" class="card admin-card border-0 p-4 mb-4" style="scroll-margin-top: 90px;">
            <h3 class="h6 fw-bold text-dark mb-3">Clients parrainés — détail des commissions</h3>
            @if($clientRows->isEmpty())
                <div class="text-muted small text-center py-3">Aucun client parrainé pour le moment.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Client</th>
                                <th class="text-center">Payé</th>
                                <th class="text-center">Rang</th>
                                <th class="text-end">Prime inscription</th>
                                <th class="text-center">Renouvellements</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($clientRows as $row)
                                <tr>
                                    <td class="small">
                                        <div class="fw-semibold text-dark">{{ $row['client']->name }}</div>
                                        <div class="text-muted">{{ $row['client']->company_name }}</div>
                                    </td>
                                    <td class="text-center">
                                        @if($row['has_paid'])
                                            <span class="badge bg-success">Oui</span>
                                        @else
                                            <span class="badge bg-secondary">Non</span>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $row['rank'] ?? '—' }}</td>
                                    <td class="text-end">{{ number_format($row['signup_bonus']) }} F</td>
                                    <td class="text-center">{{ $row['renewal_count'] }}</td>
                                    <td class="text-end fw-semibold">{{ number_format($row['subtotal']) }} F</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div id="commercial-prospections" class="card admin-card border-0 p-4 mb-4" style="scroll-margin-top: 90px;">
            <h3 class="h6 fw-bold text-dark mb-3">Prospections envoyées</h3>
            @if($prospections->isEmpty())
                <div class="text-muted small text-center py-3">Aucune prospection envoyée.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Titre</th>
                                <th>Statut</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($prospections as $p)
                                <tr>
                                    <td class="small fw-semibold">{{ $p->title ?: '(sans titre)' }}</td>
                                    <td><span class="prospection-status-badge status-{{ $p->status }}">{{ $p->statusLabel() }}</span></td>
                                    <td class="small text-muted">{{ $p->created_at->format('d/m/Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div id="commercial-pipeline" class="card admin-card border-0 p-4 mb-4" style="scroll-margin-top: 90px;">
            <h3 class="h6 fw-bold text-dark mb-3">Pipeline de prospects</h3>
            @if($prospects->isEmpty())
                <div class="text-muted small text-center py-3">Aucun prospect enregistré.</div>
            @else
                <ul class="list-group list-group-flush">
                    @foreach($prospects as $prospect)
                        <li class="list-group-item px-0 border-0 border-bottom">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-semibold text-dark small">{{ $prospect->name }}</span>
                                <span class="badge {{ $prospect->status_badge_class }}">{{ $prospect->status_label }}</span>
                            </div>
                            <div class="text-muted small">{{ $prospect->company_name }} · {{ $prospect->created_at->diffForHumans() }}</div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div id="commercial-historique" class="card admin-card border-0 p-4" style="scroll-margin-top: 90px;">
            <h3 class="h6 fw-bold text-dark mb-3">Historique de connexion</h3>
            @if($loginHistory->isEmpty())
                <div class="text-muted small text-center py-3">Aucune connexion enregistrée.</div>
            @else
                <ul class="list-group list-group-flush">
                    @foreach($loginHistory as $login)
                        <li class="list-group-item px-0 border-0 border-bottom d-flex justify-content-between">
                            <span class="small text-dark">{{ $login->created_at->format('d/m/Y H:i') }}</span>
                            <span class="text-muted small">{{ $login->created_at->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
