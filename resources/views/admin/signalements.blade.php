@extends('layouts.app')

@section('title', 'Signalements & Diagnostics Système | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="d-flex justify-content-between align-items-start align-items-md-center flex-wrap gap-3 mb-4">
    <div>
        <nav aria-label="Fil d'Ariane" class="mb-2">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Administration</a></li>
                <li class="breadcrumb-item active" aria-current="page">Signalements &amp; Bugs</li>
            </ol>
        </nav>
        <h1 class="h3 mb-1"><strong>🚨 Signalements</strong> &amp; Diagnostics Système</h1>
        <p class="text-muted mb-0">Suivi complet des bugs, erreurs applicatives, état du serveur LWS et de la base de données en temps réel.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <form method="POST" action="{{ route('admin.signalements.clear-logs') }}" class="d-inline" onsubmit="return confirm('Vider laravel.log et purger les anciens signalements résolus ?');">
            @csrf
            <button type="submit" class="btn btn-outline-warning rounded-pill px-4 fw-semibold">
                🧹 Purger Logs & Résolus
            </button>
        </form>
    </div>
</div>

@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show mb-4">{{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- ======================== HEALTH CHECK CARDS ======================== --}}
<div class="row g-3 mb-4">
    {{-- BASE DE DONNÉES --}}
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid {{ $dbHealth['connected'] ? '#10b981' : '#ef4444' }} !important;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <p class="text-muted small mb-0 fw-semibold text-uppercase">Base de Données</p>
                    <span class="badge {{ $dbHealth['connected'] ? 'bg-success' : 'bg-danger' }} rounded-pill">
                        {{ $dbHealth['connected'] ? '🟢 Connectée' : '🔴 Hors ligne' }}
                    </span>
                </div>
                <p class="h5 mb-0 fw-bold {{ $dbHealth['connected'] ? 'text-success' : 'text-danger' }}">
                    {{ $dbHealth['driver'] }}
                </p>
                @if($dbHealth['connected'])
                    <div class="small text-muted mt-1">Ping : <strong>{{ $dbHealth['ping_ms'] }} ms</strong> · {{ $dbHealth['table_count'] }} tables</div>
                @else
                    <div class="small text-danger mt-1 text-truncate" title="{{ $dbHealth['error'] }}">{{ \Illuminate\Support\Str::limit((string) $dbHealth['error'], 60) }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ESPACE DISQUE LWS --}}
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid {{ $lwsHealth['disk_free_gb'] > 2 ? '#10b981' : '#f59e0b' }} !important;">
            <div class="card-body">
                <p class="text-muted small mb-1 fw-semibold text-uppercase">Serveur LWS — Disque</p>
                <p class="h5 mb-0 fw-bold text-dark">{{ number_format($lwsHealth['disk_free_gb'], 1) }} Go libres</p>
                <div class="small text-muted mt-1">
                    Total : {{ number_format($lwsHealth['disk_total_gb'], 1) }} Go ·
                    PHP {{ $lwsHealth['php_version'] }}
                </div>
                <div class="small text-muted mt-1">Mémoire : <strong>{{ $lwsHealth['memory_limit'] }}</strong></div>
            </div>
        </div>
    </div>

    {{-- DROITS STORAGE / CACHE --}}
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid {{ ($lwsHealth['storage_writable'] && $lwsHealth['bootstrap_cache_writable']) ? '#10b981' : '#ef4444' }} !important;">
            <div class="card-body">
                <p class="text-muted small mb-1 fw-semibold text-uppercase">Droits Dossiers</p>
                <div class="d-flex flex-column gap-1 mt-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <code class="small">/storage</code>
                        <span class="badge rounded-pill {{ $lwsHealth['storage_writable'] ? 'bg-success-subtle text-success border' : 'bg-danger-subtle text-danger border' }}">
                            {{ $lwsHealth['storage_writable'] ? '✓ 775 OK' : '✗ Lecture seule' }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <code class="small">/bootstrap/cache</code>
                        <span class="badge rounded-pill {{ $lwsHealth['bootstrap_cache_writable'] ? 'bg-success-subtle text-success border' : 'bg-danger-subtle text-danger border' }}">
                            {{ $lwsHealth['bootstrap_cache_writable'] ? '✓ 775 OK' : '✗ Lecture seule' }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <code class="small">Vues compilées</code>
                        <span class="badge rounded-pill {{ $lwsHealth['views_cached'] ? 'bg-primary-subtle text-primary border' : 'bg-secondary-subtle text-muted border' }}">
                            {{ $lwsHealth['views_cached'] ? 'Cachées' : 'Non cachées' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- BUGS ACTIFS --}}
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid {{ $openCount > 0 ? '#ef4444' : '#10b981' }} !important;">
            <div class="card-body">
                <p class="text-muted small mb-1 fw-semibold text-uppercase">Bugs Actifs</p>
                <p class="h3 mb-0 fw-bold {{ $openCount > 0 ? 'text-danger' : 'text-success' }}">{{ $openCount }}</p>
                <div class="small mt-1">
                    @if($criticalCount > 0)
                        <span class="badge bg-danger me-1">🔴 {{ $criticalCount }} CRITICAL</span>
                    @endif
                    <span class="text-muted">{{ $resolvedCount }} résolus · {{ $totalCount }} total</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ======================== ONGLETS ======================== --}}
<ul class="nav nav-tabs mb-4" id="signalementsTab">
    <li class="nav-item">
        <a class="nav-link active fw-semibold" id="bug-tab" data-bs-toggle="tab" href="#tab-bugs">
            🐛 Signalements Bugs <span class="badge bg-danger rounded-pill ms-1">{{ $openCount }}</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link fw-semibold" id="log-tab" data-bs-toggle="tab" href="#tab-logs">
            📋 Journal Système (laravel.log)
        </a>
    </li>
</ul>

<div class="tab-content">

    {{-- ========== TAB 1: BUG REPORTS ========== --}}
    <div class="tab-pane fade show active" id="tab-bugs">
        {{-- FILTRES --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body py-3">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Statut</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Tous</option>
                            <option value="OPEN" @selected($filters['status'] === 'OPEN')>🔴 Ouvert</option>
                            <option value="IN_PROGRESS" @selected($filters['status'] === 'IN_PROGRESS')>🟡 En cours</option>
                            <option value="RESOLVED" @selected($filters['status'] === 'RESOLVED')>🟢 Résolu</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Gravité</label>
                        <select name="severity" class="form-select form-select-sm">
                            <option value="">Toutes</option>
                            <option value="CRITICAL" @selected($filters['severity'] === 'CRITICAL')>🔴 CRITICAL</option>
                            <option value="HIGH" @selected($filters['severity'] === 'HIGH')>🟠 HIGH</option>
                            <option value="MEDIUM" @selected($filters['severity'] === 'MEDIUM')>🟡 MEDIUM</option>
                            <option value="LOW" @selected($filters['severity'] === 'LOW')>🔵 LOW</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Dashboard</label>
                        <select name="dashboard" class="form-select form-select-sm">
                            <option value="">Tous</option>
                            <option value="Administration" @selected($filters['dashboard'] === 'Administration')>🔷 Administration</option>
                            <option value="Comptable" @selected($filters['dashboard'] === 'Comptable')>📊 Cabinet Comptable</option>
                            <option value="Commercial" @selected($filters['dashboard'] === 'Commercial')>🤝 Commercial</option>
                            <option value="PME" @selected($filters['dashboard'] === 'PME')>🏢 Entreprise PME</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Recherche</label>
                        <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="Message, URL, fichier, classe...">
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-sm btn-primary rounded-pill">Filtrer</button>
                    </div>
                    <div class="col-md-2 d-grid">
                        <a href="{{ route('admin.signalements.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Réinitialiser</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- TABLEAU DES BUGS --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="card-title mb-0 fw-bold">Liste des Signalements</h5>
                <span class="badge bg-primary rounded-pill">{{ $bugReports->total() }} signalement(s)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="min-width:130px;">Date &amp; Heure</th>
                                <th>Gravité</th>
                                <th>Dashboard</th>
                                <th>Page / URL</th>
                                <th>Erreur</th>
                                <th>Utilisateur</th>
                                <th>Statut</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($bugReports as $bug)
                                @php
                                    $severityColors = [
                                        'CRITICAL' => ['bg' => 'danger', 'icon' => '🔴'],
                                        'HIGH' => ['bg' => 'warning text-dark', 'icon' => '🟠'],
                                        'MEDIUM' => ['bg' => 'info text-dark', 'icon' => '🟡'],
                                        'LOW' => ['bg' => 'secondary', 'icon' => '🔵'],
                                    ];
                                    $dashboardColors = [
                                        'Dashboard Administration' => 'primary',
                                        'Dashboard Cabinet Comptable' => 'info',
                                        'Dashboard Commercial' => 'success',
                                        'Dashboard Entreprise (PME)' => 'warning text-dark',
                                    ];
                                    $sev = $severityColors[$bug->severity] ?? ['bg' => 'secondary', 'icon' => '⚪'];
                                    $dashColor = $dashboardColors[$bug->dashboard] ?? 'secondary';
                                @endphp
                                <tr class="{{ $bug->status === 'OPEN' && $bug->severity === 'CRITICAL' ? 'table-danger' : '' }}">
                                    <td class="ps-3 text-nowrap small">
                                        <div class="fw-semibold">{{ $bug->created_at->format('d/m/Y') }}</div>
                                        <span class="badge bg-dark font-monospace" style="font-size:10px;">⏰ {{ $bug->created_at->format('H:i:s') }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $sev['bg'] }} rounded-pill">{{ $sev['icon'] }} {{ $bug->severity }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $dashColor }} rounded-pill px-2" style="font-size:10px;">
                                            {{ Str::after($bug->dashboard, 'Dashboard ') }}
                                        </span>
                                    </td>
                                    <td style="max-width: 200px;">
                                        <div class="small text-truncate fw-semibold text-dark" title="{{ $bug->page_url }}">
                                            {{ \Illuminate\Support\Str::after($bug->page_url, config('app.url')) ?: $bug->page_url }}
                                        </div>
                                        @if($bug->file)
                                            <div class="text-muted font-monospace" style="font-size:9px;">
                                                📄 {{ basename($bug->file) }}@if($bug->line):{{ $bug->line }}@endif
                                            </div>
                                        @endif
                                    </td>
                                    <td style="max-width: 220px;">
                                        <div class="small fw-semibold text-danger text-truncate" title="{{ $bug->message }}">{{ \Illuminate\Support\Str::limit($bug->message, 60) }}</div>
                                        <div class="text-muted" style="font-size:10px;">{{ class_basename($bug->error_class) }}</div>
                                    </td>
                                    <td class="small">
                                        @if($bug->user)
                                            <div class="fw-semibold">{{ $bug->user->name }}</div>
                                            <div class="text-muted" style="font-size:10px;">{{ $bug->user->email }}</div>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($bug->status === 'OPEN')
                                            <span class="badge bg-danger-subtle text-danger border rounded-pill">🔴 Ouvert</span>
                                        @elseif($bug->status === 'IN_PROGRESS')
                                            <span class="badge bg-warning-subtle text-warning border rounded-pill">🟡 En cours</span>
                                        @else
                                            <span class="badge bg-success-subtle text-success border rounded-pill">🟢 Résolu</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="d-inline-flex gap-1 align-items-center flex-wrap justify-content-end">
                                            @if($bug->stack_trace)
                                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#stackTraceModal{{ $bug->id }}"
                                                    title="Voir la Stack Trace complète">
                                                    🔍 Stack
                                                </button>
                                            @endif

                                            @if($bug->status !== 'RESOLVED')
                                                <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-2"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#resolveModal{{ $bug->id }}"
                                                    title="Marquer comme résolu">
                                                    ✓ Résoudre
                                                </button>
                                            @endif

                                            <form method="POST" action="{{ route('admin.signalements.destroy', $bug) }}" class="d-inline"
                                                onsubmit="return confirm('Supprimer ce signalement #{{ $bug->id }} ?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Supprimer">🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        <div style="font-size:2rem;">🎉</div>
                                        <div class="fw-semibold mt-2">Aucun bug signalé !</div>
                                        <div class="small text-muted mt-1">Votre plateforme est en bonne santé. Les erreurs apparaîtront ici automatiquement.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($bugReports->hasPages())
                <div class="card-footer bg-white">{{ $bugReports->links() }}</div>
            @endif
        </div>
    </div>

    {{-- ========== TAB 2: JOURNAL LARAVEL.LOG ========== --}}
    <div class="tab-pane fade" id="tab-logs">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
                <h5 class="card-title mb-0 fw-bold">📋 Journal Système — <code class="text-warning">storage/logs/laravel.log</code></h5>
                <span class="badge bg-secondary">120 dernières lignes</span>
            </div>
            <div class="card-body p-0">
                <pre id="laravelLogViewer" class="mb-0 p-3 text-white" style="
                    background: #0d1117;
                    font-size: 11px;
                    max-height: 600px;
                    overflow-y: auto;
                    white-space: pre-wrap;
                    word-break: break-all;
                    border-radius: 0 0 8px 8px;
                ">{{ $logFileContent }}</pre>
            </div>
        </div>
        <div class="text-muted small mt-2">
            ⚠️ Les lignes en <strong>rouge</strong> = erreurs critiques · <strong>jaune</strong> = warnings · <strong>blanc</strong> = info.
        </div>
    </div>
</div>

{{-- ======================== MODALS STACK TRACE & RÉSOLUTION ======================== --}}
@foreach($bugReports as $bug)

    {{-- Modal Stack Trace --}}
    @if($bug->stack_trace)
        <div class="modal fade" id="stackTraceModal{{ $bug->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header bg-dark text-white border-0">
                        <div>
                            <h5 class="modal-title fw-bold">🔍 Stack Trace — Signalement #{{ $bug->id }}</h5>
                            <div class="small text-muted mt-1">
                                <span class="badge bg-danger me-1">{{ $bug->severity }}</span>
                                {{ class_basename($bug->error_class) }} ·
                                {{ Str::after($bug->page_url, config('app.url')) }}
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div class="bg-danger-subtle px-4 py-2 border-bottom">
                            <strong>Message :</strong> {{ $bug->message }}
                        </div>
                        @if($bug->file)
                            <div class="bg-warning-subtle px-4 py-2 border-bottom small">
                                📄 <strong>Fichier :</strong> <code>{{ $bug->file }}</code>
                                @if($bug->line) — <strong>Ligne {{ $bug->line }}</strong>@endif
                            </div>
                        @endif
                        <pre class="m-0 p-4 text-white" style="
                            background: #0d1117;
                            font-size: 11px;
                            max-height: 500px;
                            overflow: auto;
                            white-space: pre-wrap;
                            word-break: break-all;
                        ">{{ $bug->stack_trace }}</pre>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Résolution --}}
    @if($bug->status !== 'RESOLVED')
        <div class="modal fade" id="resolveModal{{ $bug->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <form method="POST" action="{{ route('admin.signalements.resolve', $bug) }}">
                        @csrf
                        <div class="modal-header border-0">
                            <h5 class="modal-title fw-bold text-success">🟢 Marquer comme Résolu</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body pt-0">
                            <p class="text-muted small mb-3">
                                Bug <strong>#{{ $bug->id }}</strong> sur <strong>{{ $bug->dashboard }}</strong> :
                                <em>{{ \Illuminate\Support\Str::limit($bug->message, 80) }}</em>
                            </p>
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Note de résolution (optionnel)</label>
                                <textarea name="resolution_note" rows="3" class="form-control rounded-3"
                                    placeholder="Ex: Corrigé dans AdminController.php ligne 142 — constraint nullable ajoutée."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-sm btn-secondary rounded-pill" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-sm btn-success rounded-pill px-4 fw-semibold">✓ Confirmer la Résolution</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

@endforeach

@push('scripts')
<script>
    // Colorisation du journal LWS
    document.addEventListener('DOMContentLoaded', function () {
        const pre = document.getElementById('laravelLogViewer');
        if (!pre) return;

        const text = pre.textContent;
        const lines = text.split('\n');
        pre.innerHTML = lines.map(line => {
            const lower = line.toLowerCase();
            if (lower.includes('[error]') || lower.includes('[critical]') || lower.includes('exception') || lower.includes('fatal')) {
                return `<span style="color:#f87171;">${escapeHtml(line)}</span>`;
            } else if (lower.includes('[warning]') || lower.includes('[notice]')) {
                return `<span style="color:#fbbf24;">${escapeHtml(line)}</span>`;
            } else if (lower.includes('[info]')) {
                return `<span style="color:#6ee7b7;">${escapeHtml(line)}</span>`;
            } else {
                return `<span style="color:#94a3b8;">${escapeHtml(line)}</span>`;
            }
        }).join('\n');

        // Auto scroll vers le bas
        pre.scrollTop = pre.scrollHeight;
    });

    function escapeHtml(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
</script>
@endpush
@endsection
