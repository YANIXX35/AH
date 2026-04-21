@extends('layouts.app')

@section('title', 'Gestion paiements | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="mb-4">
    <nav aria-label="Fil d'Ariane admin" class="mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Administration</a></li>
            <li class="breadcrumb-item active" aria-current="page">Gestion paiements</li>
        </ol>
    </nav>
    <h1 class="h3 mb-1"><strong>Gestion</strong> complète des paiements</h1>
    <p class="text-muted mb-0">Suivi des transactions, références, erreurs et actions d’abonnement (Premium / Gratuit).</p>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Transactions totales</p>
                <p class="h4 mb-0">{{ number_format($totalCount, 0, ',', ' ') }}</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Transactions validées</p>
                <p class="h4 mb-0 text-success">{{ number_format($acceptedCount, 0, ',', ' ') }}</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Transactions rejetées</p>
                <p class="h4 mb-0 text-danger">{{ number_format($rejectedCount, 0, ',', ' ') }}</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Montant cumulé</p>
                <p class="h4 mb-0">{{ number_format($totalAmount, 0, ',', ' ') }}</p>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Statut</label>
                <select name="status" class="form-select">
                    <option value="">Tous</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ $st }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Pays</label>
                <select name="country" class="form-select">
                    <option value="">Tous</option>
                    @foreach($countries as $c)
                        <option value="{{ $c }}" @selected(($filters['country'] ?? '') === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Opérateur</label>
                <select name="correspondent" class="form-select">
                    <option value="">Tous</option>
                    @foreach($correspondents as $corr)
                        <option value="{{ $corr }}" @selected(($filters['correspondent'] ?? '') === $corr)>{{ $corr }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Utilisateur</label>
                <select name="user_id" class="form-select">
                    <option value="0">Tous</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected((int) ($filters['user_id'] ?? 0) === (int) $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Du</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Au</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Recherche</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="référence, numéro, erreur...">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary">Filtrer</button>
            </div>
            <div class="col-md-2 d-grid">
                <a href="{{ route('admin.payments.index') }}" class="btn btn-outline-secondary">Réinitialiser</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Transactions</h5>
        <span class="badge bg-secondary">{{ $payments->total() }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Compte</th>
                        <th>Paiement</th>
                        <th>Statut</th>
                        <th>Référence</th>
                        <th>Erreur</th>
                        <th>Abonnement</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        @php
                            $user = $payment->user;
                            $status = strtoupper((string) $payment->status);
                            $statusClass = in_array($status, ['COMPLETED', 'ACCEPTED', 'SUBMITTED']) ? 'success' : (in_array($status, ['REJECTED', 'FAILED']) ? 'danger' : 'secondary');
                        @endphp
                        <tr>
                            <td class="small text-nowrap">{{ $payment->created_at?->format('d/m/Y H:i') }}</td>
                            <td>
                                @if($user)
                                    <div class="small fw-semibold">{{ $user->name }}</div>
                                    <div class="small text-muted">{{ $user->email }}</div>
                                @else
                                    <span class="text-muted">Compte supprimé</span>
                                @endif
                            </td>
                            <td>
                                <div class="small text-nowrap">{{ number_format((float) $payment->amount, 0, ',', ' ') }} {{ $payment->currency }}</div>
                                <div class="small text-muted">{{ $payment->country }} · {{ $payment->correspondent }}</div>
                                <div class="small text-muted">{{ $payment->payer_msisdn }}</div>
                            </td>
                            <td><span class="badge bg-{{ $statusClass }}">{{ $status }}</span></td>
                            <td class="small text-break" style="max-width: 14rem;">{{ $payment->provider_reference ?: '—' }}</td>
                            <td class="small text-danger" style="max-width: 18rem;">
                                {{ \Illuminate\Support\Str::limit((string) ($payment->failure_reason ?? '—'), 140) }}
                            </td>
                            <td>
                                @if($user)
                                    <span class="badge bg-{{ $user->hasActivePremiumPeriod() ? 'warning text-dark' : 'secondary' }}">
                                        {{ $user->hasActivePremiumPeriod() ? 'Premium' : 'Gratuit' }}
                                    </span>
                                    @if($user->premium_ends_at)
                                        <div class="small text-muted mt-1">Jusqu’au {{ $user->premium_ends_at->format('d/m/Y') }}</div>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($user && ! $user->isPlatformAdmin() && ! $user->isAccountant())
                                    <div class="d-flex justify-content-end gap-1">
                                        <form method="POST" action="{{ route('admin.payments.activate-premium', $payment) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success">+30j Premium</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.payments.set-free', $payment) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Mode gratuit</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Aucun paiement trouvé.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($payments->hasPages())
        <div class="card-footer">
            {{ $payments->links() }}
        </div>
    @endif
</div>
@endsection
