@extends('layouts.app')

@section('title', 'Gestion paiements | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="d-flex justify-content-between align-items-start align-items-md-center flex-wrap gap-3 mb-4">
    <div>
        <nav aria-label="Fil d'Ariane admin" class="mb-2">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Administration</a></li>
                <li class="breadcrumb-item active" aria-current="page">Gestion paiements</li>
            </ol>
        </nav>
        <h1 class="h3 mb-1"><strong>Gestion</strong> complète des paiements</h1>
        <p class="text-muted mb-0">Suivi des transactions, références, horodatage à la seconde et reçus de paiement PDF.</p>
    </div>
    <div>
        <button type="button" class="btn btn-success rounded-pill px-4 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#simulatePaymentModal">
            🧪 Simuler un Paiement Test
        </button>
    </div>
</div>

@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show mb-4">{{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Transactions totales</p>
                <p class="h4 mb-0 fw-bold text-dark">{{ number_format($totalCount, 0, ',', ' ') }}</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Transactions validées</p>
                <p class="h4 mb-0 fw-bold text-success">{{ number_format($acceptedCount, 0, ',', ' ') }}</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Transactions rejetées</p>
                <p class="h4 mb-0 fw-bold text-danger">{{ number_format($rejectedCount, 0, ',', ' ') }}</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Montant cumulé</p>
                <p class="h4 mb-0 fw-bold text-primary">{{ number_format($totalAmount, 0, ',', ' ') }} XOF</p>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small">Statut</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ $st }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Pays</label>
                <select name="country" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    @foreach($countries as $c)
                        <option value="{{ $c }}" @selected(($filters['country'] ?? '') === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Opérateur</label>
                <select name="correspondent" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    @foreach($correspondents as $corr)
                        <option value="{{ $corr }}" @selected(($filters['correspondent'] ?? '') === $corr)>{{ $corr }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Utilisateur</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="0">Tous</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected((int) ($filters['user_id'] ?? 0) === (int) $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Du</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Au</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-4">
                <label class="form-label small">Recherche</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="référence, numéro, erreur...">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-sm btn-primary rounded-pill">Filtrer</button>
            </div>
            <div class="col-md-2 d-grid">
                <a href="{{ route('admin.payments.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Réinitialiser</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="card-title mb-0 fw-bold">Historique des Transactions</h5>
        <span class="badge bg-primary rounded-pill">{{ $payments->total() }} transaction(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Date &amp; Heure Exacte</th>
                        <th>Compte Client</th>
                        <th>Montant &amp; Opérateur</th>
                        <th>Statut</th>
                        <th>Référence Transaction</th>
                        <th>Abonnement</th>
                        <th class="text-end pe-3">Actions &amp; Reçu PDF</th>
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
                            <td class="ps-3 text-nowrap">
                                <div class="fw-semibold text-dark">{{ $payment->created_at?->format('d/m/Y') }}</div>
                                <span class="badge bg-dark font-monospace" style="font-size:11px;">
                                    ⏰ {{ $payment->created_at?->format('H:i:s') }}
                                </span>
                            </td>
                            <td>
                                @if($user)
                                    <div class="fw-bold text-dark">{{ $user->company_name ?: $user->name }}</div>
                                    <div class="small text-muted">{{ $user->email }}</div>
                                @else
                                    <span class="text-muted">Compte supprimé</span>
                                @endif
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ number_format((float) $payment->amount, 0, ',', ' ') }} {{ $payment->currency }}</div>
                                <div class="small text-muted">
                                    <span class="badge bg-light text-dark border px-2 py-0.5" style="font-size:10px;">{{ $payment->correspondent ?: 'MOBILE' }}</span>
                                    <span>{{ $payment->country }}</span>
                                </div>
                                @if($payment->payer_msisdn)
                                    <div class="small text-muted" style="font-size:10px;">📱 {{ $payment->payer_msisdn }}</div>
                                @endif
                            </td>
                            <td><span class="badge bg-{{ $statusClass }} rounded-pill px-2.5 py-1">{{ $status }}</span></td>
                            <td class="small text-break" style="max-width: 14rem;">
                                <code class="text-primary fw-semibold">{{ $payment->provider_reference ?: '—' }}</code>
                            </td>
                            <td>
                                @if($user)
                                    <span class="badge bg-{{ $user->hasActivePremiumPeriod() ? 'warning text-dark' : 'secondary' }} rounded-pill px-2 py-0.5">
                                        {{ $user->hasActivePremiumPeriod() ? 'Premium Actif' : 'Gratuit' }}
                                    </span>
                                    @if($user->premium_ends_at)
                                        <div class="small text-muted mt-1" style="font-size:10px;">Jusqu’au {{ $user->premium_ends_at->format('d/m/Y') }}</div>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end align-items-center">
                                    <!-- Reçu PDF -->
                                    <a href="{{ route('admin.payments.receipt', $payment) }}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 fw-semibold" title="Voir et imprimer le reçu officiel">
                                        📄 Reçu PDF
                                    </a>

                                    @if($user && ! $user->isPlatformAdmin() && ! $user->isAccountant())
                                        <form method="POST" action="{{ route('admin.payments.activate-premium', $payment) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success rounded-pill px-2" title="Ajouter +30j Premium">+30j</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i data-feather="credit-card" class="mb-2" style="width:36px; height:36px;"></i>
                                <div class="fw-semibold">Aucun paiement enregistré pour le moment.</div>
                                <div class="small text-muted mt-1">Utilisez le bouton <strong>🧪 Simuler un Paiement Test</strong> pour créer une transaction de démonstration.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($payments->hasPages())
        <div class="card-footer bg-white">
            {{ $payments->links() }}
        </div>
    @endif
</div>

<!-- MODAL SIMULATEUR DE PAIEMENT TEST -->
<div class="modal fade" id="simulatePaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST" action="{{ route('admin.payments.simulate') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark">🧪 Simuler un Paiement Test</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="text-muted small mb-3">
                        Génère une transaction de paiement validée avec horodatage exact à la seconde et activation automatique du Premium (+30 jours).
                    </p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Entreprise Client Cible</label>
                        <select name="user_id" class="form-select rounded-3" required>
                            <option value="">— Choisir l'entreprise client —</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->company_name ?: $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Montant Réglé (XOF)</label>
                            <select name="amount" class="form-select rounded-3" required>
                                <option value="15000">15 000 FCFA (Formule Starter)</option>
                                <option value="25000" selected>25 000 FCFA (Formule Premium Pro)</option>
                                <option value="50000">50 000 FCFA (Formule Entreprise)</option>
                                <option value="100000">100 000 FCFA (Formule Groupe)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Pays</label>
                            <select name="country" class="form-select rounded-3" required>
                                <option value="CI" selected>🇨🇮 Côte d'Ivoire</option>
                                <option value="SN">🇸🇳 Sénégal</option>
                                <option value="BF">🇧🇫 Burkina Faso</option>
                                <option value="ML">🇲🇱 Mali</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Opérateur / Moyen de Paiement</label>
                        <select name="correspondent" class="form-select rounded-3" required>
                            <option value="ORANGE_MONEY" selected>Orange Money (Mobile Money)</option>
                            <option value="WAVE">Wave Côte d'Ivoire</option>
                            <option value="MTN_MOMO">MTN Mobile Money</option>
                            <option value="MOOV_MONEY">Moov Money</option>
                            <option value="CARTE_BANCAIRE">Carte Bancaire (Visa / Mastercard)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">N° Téléphone Payeur (Facultatif)</label>
                        <input type="text" name="payer_msisdn" value="+2250700000000" class="form-control rounded-3" placeholder="+2250700000000">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-sm btn-success rounded-pill px-4 fw-semibold">
                        ✓ Valider &amp; Générer le Paiement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
