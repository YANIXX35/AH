@extends('layouts.app')

@section('title', 'Prospection | SITIAME CAPITAL')
@section('page_title', 'Prospection commerciale')

@push('styles')
<style>
    .soft-dashboard-body { background: linear-gradient(135deg, #f0f4ff 0%, #eef2f6 45%, #f0f9ff 100%); min-height: 100vh; font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; color: #1e293b; padding: 24px; }
    .soft-dashboard-container { background: #f8fafc; border-radius: 32px; border: 1px solid #e2e8f0; box-shadow: 0 20px 50px rgba(15, 23, 42, 0.06); padding: 24px; }
    .mockup-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.03); }
    .prospection-status-badge { border-radius: 999px; padding: 5px 12px; font-size: .72rem; font-weight: 700; }
    .status-draft { background:#f1f5f9; color:#475569; }
    .status-submitted { background:#dbeafe; color:#1d4ed8; }
    .status-under_review { background:#fef3c7; color:#92400e; }
    .status-approved { background:#dcfce7; color:#15803d; }
    .status-needs_revision { background:#ffedd5; color:#c2410c; }
    .status-rejected { background:#fee2e2; color:#b91c1c; }
    @media (max-width: 767.98px) { .soft-dashboard-body { padding: 10px 8px; } .soft-dashboard-container { padding: 10px; border-radius: 20px; } }
</style>
@endpush

@section('content')
<div class="soft-dashboard-body">
    <div class="soft-dashboard-container">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 p-3 bg-white rounded-4 border">
            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('commercial.dashboard') }}" class="btn btn-light rounded-circle p-2 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                    <i data-feather="arrow-left" style="width:20px; height:20px;"></i>
                </a>
                <div>
                    <h1 class="h4 fw-bold text-dark mb-0">📊 Prospection</h1>
                    <p class="text-muted small mb-0">Racontez votre prospection librement — texte, fichier, ou les deux.</p>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('commercial.prospections.create') }}" class="btn btn-primary rounded-pill px-4 fw-semibold">
                    <i data-feather="edit-3" class="me-1" style="width:14px; height:14px;"></i> Écrire une prospection
                </a>
            </div>
        </div>

        @if(session('status'))
            <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4 border-0 shadow-sm" role="alert">
                {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="mockup-card p-4">
            <h3 class="h6 fw-bold text-dark mb-3">Mes prospections</h3>

            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Titre</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th>Dernière modification</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($prospections as $p)
                            <tr>
                                <td class="fw-semibold text-dark">{{ $p->title ?: '(sans titre)' }}</td>
                                <td class="text-muted small">{{ $p->typeLabel() }}</td>
                                <td><span class="prospection-status-badge status-{{ $p->status }}">{{ $p->statusLabel() }}</span></td>
                                <td class="text-muted small">{{ $p->updated_at->format('d/m/Y H:i') }}</td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <a href="{{ route('commercial.prospections.show', $p) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">👁 Voir</a>
                                        @if($p->isEditable())
                                            <a href="{{ route('commercial.prospections.edit', $p) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">✏ Modifier</a>
                                        @endif
                                        @if($p->status === \App\Models\CommercialProspection::STATUS_DRAFT)
                                            <form action="{{ route('commercial.prospections.destroy', $p) }}" method="POST" onsubmit="return confirm('Supprimer ce brouillon ?');" class="d-inline">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2">🗑</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    Aucune prospection pour le moment. Cliquez sur « Écrire une prospection » pour commencer.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-block d-md-none">
                @forelse($prospections as $p)
                    <div class="card p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="fw-bold text-dark">{{ $p->title ?: '(sans titre)' }}</div>
                            <span class="prospection-status-badge status-{{ $p->status }}">{{ $p->statusLabel() }}</span>
                        </div>
                        <div class="text-muted small mb-2">{{ $p->typeLabel() }} · {{ $p->updated_at->format('d/m/Y H:i') }}</div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('commercial.prospections.show', $p) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Voir</a>
                            @if($p->isEditable())
                                <a href="{{ route('commercial.prospections.edit', $p) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">Modifier</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">Aucune prospection pour le moment.</div>
                @endforelse
            </div>

            <div class="mt-3">{{ $prospections->links() }}</div>
        </div>
    </div>
</div>
@endsection
