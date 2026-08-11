{{-- Corps partagé du pilotage commercial (KPIs, performance, prospections, pipeline, feedback, documents).
     Utilisé par admin/commercial-dashboard.blade.php et commercial-supervisor/dashboard.blade.php pour ne
     jamais faire diverger les deux vues. Attend une variable $prospectionsIndexRoute (nom de route). --}}
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

<!-- Graphiques -->
<div class="row g-3 mb-4">
    <div class="col-12 col-xl-7">
        <div class="card admin-card border-0 p-4 h-100">
            <h3 class="h6 fw-bold text-dark mb-3">Clients parrainés par commercial</h3>
            @if($rows->isEmpty())
                <div class="text-muted small text-center py-4">Aucune donnée à afficher.</div>
            @else
                <div style="height: 260px;"><canvas id="commercialClientsChart"></canvas></div>
            @endif
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="card admin-card border-0 p-4 h-100">
            <h3 class="h6 fw-bold text-dark mb-3">Commissions par commercial (gagné vs versé)</h3>
            @if($rows->isEmpty() || $grandTotalEarned == 0)
                <div class="text-muted small text-center py-4">Aucune commission enregistrée pour le moment.</div>
            @else
                <div style="height: 260px;"><canvas id="commercialCommissionsChart"></canvas></div>
            @endif
        </div>
    </div>
</div>

@if($monthlyLeaderboard->isNotEmpty())
<div class="card admin-card border-0 p-4 mb-4">
    <h3 class="h6 fw-bold text-dark mb-3">🏆 Classement du mois</h3>
    <div class="row g-3">
        @foreach($monthlyLeaderboard as $index => $entry)
            <div class="col-md-4 col-sm-6">
                <div class="d-flex align-items-center gap-2 p-2 border rounded-3">
                    <span class="badge {{ $index === 0 ? 'bg-warning text-dark' : 'bg-light text-dark border' }}" style="font-size: .9rem;">#{{ $index + 1 }}</span>
                    <div>
                        <div class="fw-semibold text-dark small">
                            @isset($commercialShowRoute)
                                <a href="{{ route($commercialShowRoute, $entry['commercial']->id) }}">{{ $entry['commercial']->name }}</a>
                            @else
                                {{ $entry['commercial']->name }}
                            @endisset
                        </div>
                        <div class="text-muted small">{{ $entry['clientsThisMonth'] }} client(s) · {{ $entry['prospectionsThisMonth'] }} prospection(s)</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif

<div class="row">
    <!-- Performance par commercial -->
    <div class="col-12 col-xl-8">
        <div class="card admin-card border-0 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h3 class="h5 fw-bold text-dark mb-0">Performance par commercial</h3>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">Triés par commissions gagnées</span>
                    @isset($exportRoute)
                        <a href="{{ route($exportRoute) }}" class="btn btn-sm btn-outline-secondary">⬇ Exporter CSV</a>
                    @endisset
                </div>
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
                                        <div class="fw-semibold text-dark">
                                            @isset($commercialShowRoute)
                                                <a href="{{ route($commercialShowRoute, $row['commercial']->id) }}">{{ $row['commercial']->name }}</a>
                                            @else
                                                {{ $row['commercial']->name }}
                                            @endisset
                                        </div>
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
                    Les montants sont en lecture seule ici. La validation des versements se fait toujours côté cabinet comptable.
                </p>
            @endif
        </div>

        <!-- Prospections récentes -->
        <div class="card admin-card border-0 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h5 fw-bold text-dark mb-0">Prospections récentes</h3>
                <a href="{{ route($prospectionsIndexRoute) }}" class="btn btn-sm btn-outline-primary">Voir toutes les prospections</a>
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
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h5 fw-bold text-dark mb-0">Pipeline de prospects</h3>
                @isset($prospectsIndexRoute)
                    <a href="{{ route($prospectsIndexRoute) }}" class="btn btn-sm btn-outline-primary">Voir tout</a>
                @endisset
            </div>
            @if($prospectStatusStats->isEmpty())
                <div class="text-muted small">Aucun prospect enregistré.</div>
            @else
                <div style="height: 180px;" class="mb-3"><canvas id="prospectPipelineChart"></canvas></div>
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
                <div style="height: 180px;" class="mb-3"><canvas id="prospectionStatusChart"></canvas></div>
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
        <div class="card admin-card border-0 p-4 mb-4">
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

        <!-- Historique de connexions -->
        <div class="card admin-card border-0 p-4">
            <h3 class="h5 fw-bold text-dark mb-3">Connexions récentes</h3>
            @if($recentLogins->isEmpty())
                <div class="text-muted small">Aucune connexion enregistrée.</div>
            @else
                <ul class="list-group list-group-flush">
                    @foreach($recentLogins as $login)
                        <li class="list-group-item px-0 border-0 border-bottom d-flex justify-content-between">
                            <span class="fw-semibold text-dark small">{{ $login->user->name ?? '—' }}</span>
                            <span class="text-muted small">{{ $login->created_at->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') { return; }

    var clientsEl = document.getElementById('commercialClientsChart');
    if (clientsEl) {
        new Chart(clientsEl.getContext('2d'), {
            type: 'bar',
            data: {
                labels: @json($rows->pluck('commercial.name')),
                datasets: [{
                    label: 'Clients parrainés',
                    data: @json($rows->pluck('totalClients')),
                    backgroundColor: 'rgba(13, 110, 253, 0.35)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                    maxBarThickness: 36
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } } }
            }
        });
    }

    var commissionsEl = document.getElementById('commercialCommissionsChart');
    if (commissionsEl) {
        new Chart(commissionsEl.getContext('2d'), {
            type: 'bar',
            data: {
                labels: @json($rows->pluck('commercial.name')),
                datasets: [
                    {
                        label: 'Gagné',
                        data: @json($rows->pluck('totalEarned')),
                        backgroundColor: 'rgba(13, 110, 253, 0.35)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 1,
                        borderRadius: 4
                    },
                    {
                        label: 'Versé',
                        data: @json($rows->pluck('totalPaid')),
                        backgroundColor: 'rgba(25, 135, 84, 0.35)',
                        borderColor: 'rgba(25, 135, 84, 1)',
                        borderWidth: 1,
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    var pipelineEl = document.getElementById('prospectPipelineChart');
    if (pipelineEl) {
        new Chart(pipelineEl.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: @json(collect($prospectStatusStats->keys())->map(fn ($s) => $prospectLabels[$s] ?? ucfirst($s))),
                datasets: [{
                    data: @json($prospectStatusStats->values()),
                    backgroundColor: ['#0dcaf0', '#ffc107', '#0d6efd', '#198754', '#6c757d', '#adb5bd']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } }
            }
        });
    }

    var prospectionEl = document.getElementById('prospectionStatusChart');
    if (prospectionEl) {
        new Chart(prospectionEl.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: @json(collect($prospectionStats->keys())->map(fn ($s) => $prospectionLabels[$s] ?? ucfirst($s))),
                datasets: [{
                    data: @json($prospectionStats->values()),
                    backgroundColor: ['#0d6efd', '#ffc107', '#198754', '#fd7e14', '#dc3545', '#6c757d']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } }
            }
        });
    }
});
</script>
@endpush
