@extends('layouts.app')

@section('title', 'Gestion & Paiement des Salaires | ' . config('app.name'))
@section('page_title', 'Paiement des Salaires & Bulletins de Paie')

@push('styles')
<style>
    .payroll-bg {
        background-color: #f1f5f9;
        min-height: 100vh;
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        padding: 24px;
    }
    .payroll-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.03);
    }
</style>
@endpush

@section('content')
<div class="payroll-bg">
    <div class="container-fluid max-w-7xl mx-auto">
        
        <!-- Header Bar -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <span class="badge bg-primary text-white rounded-pill px-3 py-1 mb-2 fw-semibold">PAIE & TRÉSORERIE PME</span>
                <h1 class="h3 fw-bold text-dark mb-1">Paiement des Salaires & Bulletins de Paie 💳</h1>
                <p class="text-muted small mb-0">Gestion des lots de salaire avec synchronisation automatique en Comptabilité SYSCOHADA et Trésorerie.</p>
            </div>
            <a href="{{ route('payroll.create') }}" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm">
                + Nouveau Lot de Paie
            </a>
        </div>

        <!-- Alert Notification -->
        @if(session('status'))
            <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4 border-0 shadow-sm" role="alert">
                <i data-feather="check-circle" class="me-2 text-success"></i>
                {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Summary Metric Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="payroll-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small text-uppercase fw-bold">TOTAL SALAIRES VERSÉS</span>
                        <span class="badge bg-success text-white rounded-pill">Net Payer</span>
                    </div>
                    <div class="display-6 fw-bold text-dark mb-1">{{ number_format($totalPaid, 0, ',', ' ') }} FCFA</div>
                    <div class="text-muted small">Montant net décaissé aux salariés.</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="payroll-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small text-uppercase fw-bold">SALAIRES BRUTS</span>
                        <span class="badge bg-primary text-white rounded-pill">M_BRUT</span>
                    </div>
                    <div class="display-6 fw-bold text-dark mb-1">{{ number_format($totalGross, 0, ',', ' ') }} FCFA</div>
                    <div class="text-muted small">Rémunérations brutes chargées.</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="payroll-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small text-uppercase fw-bold">COTISATIONS CNPS</span>
                        <span class="badge bg-info text-white rounded-pill">Social</span>
                    </div>
                    <div class="display-6 fw-bold text-dark mb-1">{{ number_format($totalCnps, 0, ',', ' ') }} FCFA</div>
                    <div class="text-muted small">Part patronale & salariale CNPS.</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="payroll-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small text-uppercase fw-bold">IMPÔTS SUR SALAIRES</span>
                        <span class="badge bg-warning text-dark rounded-pill">ITS / CN</span>
                    </div>
                    <div class="display-6 fw-bold text-dark mb-1">{{ number_format($totalIts, 0, ',', ' ') }} FCFA</div>
                    <div class="text-muted small">Retenues fiscales à la source.</div>
                </div>
            </div>
        </div>

        <!-- Payroll Runs Table Card -->
        <div class="payroll-card p-4 mb-4" id="historique-paie" style="scroll-margin-top: 90px;">
            <h3 class="h5 fw-bold text-dark mb-4">Historique des Lots de Paie</h3>
            <div class="table-responsive">
                <table class="table table-hover align-middle border-0 mb-0">
                    <thead>
                        <tr class="text-muted text-uppercase small" style="font-size:0.75rem;">
                            <th class="border-0">Intitulé & Période</th>
                            <th class="border-0">Date de Virement</th>
                            <th class="border-0">Mode de Règlement</th>
                            <th class="border-0">Salariés</th>
                            <th class="border-0">Total Net Versé</th>
                            <th class="border-0">Statut Sync</th>
                            <th class="border-0 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payrolls as $payroll)
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark">{{ $payroll->title }}</div>
                                    <div class="text-muted small">{{ $payroll->period_month }}</div>
                                </td>
                                <td>{{ $payroll->payment_date->format('d/m/Y') }}</td>
                                <td>
                                    <span class="badge bg-light text-dark border rounded-pill px-3 py-1">
                                        {{ $payroll->payment_method_label }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary rounded-pill">{{ $payroll->items->count() }} salarié(s)</span>
                                </td>
                                <td>
                                    <div class="fw-bold text-success">{{ number_format($payroll->total_net, 0, ',', ' ') }} FCFA</div>
                                </td>
                                <td>
                                    <span class="badge {{ $payroll->status_badge_class }} rounded-pill px-3 py-1">
                                        {{ $payroll->status_label }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('payroll.show', $payroll->id) }}" class="btn btn-sm btn-light rounded-pill border px-3">
                                        Détails
                                    </a>
                                    @if($payroll->status === 'draft')
                                        <form action="{{ route('payroll.sync', $payroll->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 fw-bold ms-1" onclick="return confirm('Valider et générer les écritures comptables et trésorerie ?');">
                                                Synchroniser
                                            </button>
                                        </form>
                                    @endif
                                    <form action="{{ route('payroll.destroy', $payroll->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce lot de paie ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light text-danger rounded-pill border ms-1 px-2">
                                            <i data-feather="trash-2" style="width:14px; height:14px;"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i data-feather="credit-card" class="mb-2" style="width:40px; height:40px; opacity:0.3;"></i>
                                    <div>Aucun lot de paie enregistré.</div>
                                    <a href="{{ route('payroll.create') }}" class="btn btn-sm btn-outline-primary rounded-pill mt-2">
                                        + Ajouter le premier lot de paie
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
@endsection
