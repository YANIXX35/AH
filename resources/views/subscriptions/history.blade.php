@extends('layouts.app')

@section('title', 'Historique des abonnements | Sitiame Capital')
@section('page_title', 'Historique des abonnements')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1"><strong>Historique</strong> des abonnements</h1>
        <p class="text-muted mb-0">Traçabilité complète des changements de statut (simulation, expiration, etc.).</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('payments.sandbox') }}" class="btn btn-primary btn-sm">Aller aux paiements</a>
        <a href="{{ route('profile') }}" class="btn btn-outline-primary btn-sm">Retour au profil</a>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Statut</th>
                    <th>Type</th>
                    <th>Période</th>
                    <th>Source</th>
                    <th>Note</th>
                </tr>
                </thead>
                <tbody>
                @forelse($history as $row)
                    @php
                        $isPremium = (bool) ($row->is_premium ?? false);
                        $badgeClass = $isPremium ? 'bg-warning text-dark' : 'bg-success';
                        $label = $isPremium ? 'Premium' : 'Gratuit';
                        $icon = $isPremium ? '⭐' : '🟢';
                    @endphp
                    <tr>
                        <td>{{ $row->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $badgeClass }}">{{ $icon }} {{ strtoupper($label) }}</span>
                            <div class="small text-muted mt-1">
                                {{ strtoupper((string) ($row->from_status ?? 'unknown')) }} -> {{ strtoupper((string) $row->to_status) }}
                            </div>
                        </td>
                        <td>{{ $row->is_premium ? 'Payant / essai' : 'Gratuit' }}</td>
                        <td>
                            <div class="small">
                                Début: {{ $row->starts_at?->format('d/m/Y H:i') ?? '—' }}
                            </div>
                            <div class="small text-muted">
                                Fin: {{ $row->ends_at?->format('d/m/Y H:i') ?? '—' }}
                            </div>
                        </td>
                        <td><span class="badge bg-light text-dark">{{ $row->source }}</span></td>
                        <td class="small text-muted">{{ $row->note ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Aucun événement d’abonnement enregistré pour le moment.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($history->hasPages())
        <div class="card-footer">
            {{ $history->links() }}
        </div>
    @endif
</div>
@endsection
