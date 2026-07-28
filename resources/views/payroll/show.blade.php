@extends('layouts.app')

@section('title', $payroll->title . ' | ' . config('app.name'))
@section('page_title', 'Fiche du Lot de Paie')

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
        
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <a href="{{ route('payroll.index') }}" class="text-decoration-none small text-muted fw-semibold">&larr; Retour à la liste des salaires</a>
                <h1 class="h3 fw-bold text-dark mb-1 mt-1">{{ $payroll->title }} 💳</h1>
                <p class="text-muted small mb-0">Période : {{ $payroll->period_month }} · Date de virement : {{ $payroll->payment_date->format('d/m/Y') }}</p>
            </div>

            <div class="d-flex align-items-center gap-2">
                <span class="badge {{ $payroll->status_badge_class }} px-3 py-2 rounded-pill fs-6">
                    {{ $payroll->status_label }}
                </span>
                @if($payroll->status === 'draft')
                    <form action="{{ route('payroll.sync', $payroll->id) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" onclick="return confirm('Valider et générer les écritures comptables et trésorerie ?');">
                            ⚡ Synchroniser Comptabilité & Trésorerie
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <!-- Alert Notification -->
        @if(session('status'))
            <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4 border-0 shadow-sm" role="alert">
                <i data-feather="check-circle" class="me-2 text-success"></i>
                {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Financial Summary Grid -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="payroll-card p-4 h-100">
                    <div class="text-muted small text-uppercase fw-bold mb-1">SALAIRES BRUTS</div>
                    <div class="h3 fw-bold text-dark mb-0">{{ number_format($payroll->total_gross, 0, ',', ' ') }} FCFA</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="payroll-card p-4 h-100">
                    <div class="text-muted small text-uppercase fw-bold mb-1">TOTAL COTISATIONS CNPS</div>
                    <div class="h3 fw-bold text-dark mb-0">{{ number_format($payroll->total_cnps, 0, ',', ' ') }} FCFA</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="payroll-card p-4 h-100">
                    <div class="text-muted small text-uppercase fw-bold mb-1">TOTAL IMPÔTS RETENUS</div>
                    <div class="h3 fw-bold text-dark mb-0">{{ number_format($payroll->total_its, 0, ',', ' ') }} FCFA</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="payroll-card p-4 h-100 bg-dark text-white">
                    <div class="text-white-50 small text-uppercase fw-bold mb-1">NET TOTAL DÉCAISSÉ</div>
                    <div class="h3 fw-bold text-success mb-0">{{ number_format($payroll->total_net, 0, ',', ' ') }} FCFA</div>
                </div>
            </div>
        </div>

        <!-- Automatic Synchronization Summary Box -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="payroll-card p-4 h-100">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width:36px; height:36px;">
                            <i data-feather="book" style="width:18px; height:18px;"></i>
                        </div>
                        <h4 class="h5 fw-bold text-dark mb-0">Écriture Comptable SYSCOHADA</h4>
                    </div>

                    @if($payroll->status === 'synced')
                        <div class="p-3 bg-light rounded-4 border mb-2">
                            <div class="d-flex justify-content-between small font-mono">
                                <span>Débit 661100 (Rémunérations directes) :</span>
                                <span class="fw-bold">{{ number_format($payroll->total_gross, 0, ',', ' ') }} FCFA</span>
                            </div>
                            <div class="d-flex justify-content-between small font-mono mt-1">
                                <span>Crédit 421100 (Personnel Rémunérations dues) :</span>
                                <span class="fw-bold text-success">{{ number_format($payroll->total_net, 0, ',', ' ') }} FCFA</span>
                            </div>
                            @if($payroll->total_cnps > 0)
                                <div class="d-flex justify-content-between small font-mono mt-1">
                                    <span>Crédit 431100 (Sécurité Sociale / CNPS) :</span>
                                    <span>{{ number_format($payroll->total_cnps, 0, ',', ' ') }} FCFA</span>
                                </div>
                            @endif
                            @if($payroll->total_its > 0)
                                <div class="d-flex justify-content-between small font-mono mt-1">
                                    <span>Crédit 447100 (État Impôts sur salaires) :</span>
                                    <span>{{ number_format($payroll->total_its, 0, ',', ' ') }} FCFA</span>
                                </div>
                            @endif
                        </div>
                        <span class="badge bg-success rounded-pill px-3 py-1">
                            <i data-feather="check" style="width:12px; height:12px;"></i> Écriture enregistrée au Journal Général
                        </span>
                    @else
                        <div class="text-muted small py-3">
                            L'écriture comptable sera automatiquement générée lors de la synchronisation (Débit 6611 / Crédit 4211, 4311, 4471).
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-md-6">
                <div class="payroll-card p-4 h-100">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="bg-success text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width:36px; height:36px;">
                            <i data-feather="dollar-sign" style="width:18px; height:18px;"></i>
                        </div>
                        <h4 class="h5 fw-bold text-dark mb-0">Mouvement de Trésorerie</h4>
                    </div>

                    @if($payroll->status === 'synced')
                        <div class="p-3 bg-light rounded-4 border mb-2">
                            <div class="d-flex justify-content-between small">
                                <span>Mode de Règlement :</span>
                                <span class="fw-bold">{{ $payroll->payment_method_label }}</span>
                            </div>
                            <div class="d-flex justify-content-between small mt-1">
                                <span>Compte Source :</span>
                                <span class="fw-bold">{{ $payroll->payment_account }}</span>
                            </div>
                            <div class="d-flex justify-content-between small mt-1">
                                <span>Montant décaissé :</span>
                                <span class="fw-bold text-danger">-{{ number_format($payroll->total_net, 0, ',', ' ') }} FCFA</span>
                            </div>
                        </div>
                        <span class="badge bg-success rounded-pill px-3 py-1">
                            <i data-feather="check" style="width:12px; height:12px;"></i> Décaissement enregistré en Trésorerie
                        </span>
                    @else
                        <div class="text-muted small py-3">
                            Le décaissement de trésorerie ({{ $payroll->payment_method_label }}) sera enregistré dès la synchronisation.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Employees Table Card -->
        <div class="payroll-card p-4 mb-4">
            <h4 class="h5 fw-bold text-dark mb-4">Détail des Salariés ({{ $payroll->items->count() }})</h4>
            <div class="table-responsive">
                <table class="table table-hover align-middle border-0 mb-0">
                    <thead>
                        <tr class="text-muted text-uppercase small" style="font-size:0.75rem;">
                            <th class="border-0">Salarié</th>
                            <th class="border-0">Matricule & Poste</th>
                            <th class="border-0">Salaire Base</th>
                            <th class="border-0">Primes</th>
                            <th class="border-0">CNPS</th>
                            <th class="border-0">Impôts ITS</th>
                            <th class="border-0 text-end">Net à Payer</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payroll->items as $item)
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark">{{ $item->employee_name }}</div>
                                    @if($item->payment_details)
                                        <div class="text-muted small" style="font-size:0.75rem;">RIB/Tel: {{ $item->payment_details }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $item->employee_matricule ?? '—' }}</div>
                                    <div class="text-muted small">{{ $item->employee_job ?? 'Employé' }}</div>
                                </td>
                                <td>{{ number_format($item->base_salary, 0, ',', ' ') }} FCFA</td>
                                <td>{{ number_format($item->bonuses, 0, ',', ' ') }} FCFA</td>
                                <td>{{ number_format($item->cnps_employee + $item->cnps_employer, 0, ',', ' ') }} FCFA</td>
                                <td>{{ number_format($item->its_tax, 0, ',', ' ') }} FCFA</td>
                                <td class="text-end">
                                    <div class="fw-bold text-success fs-6">{{ number_format($item->net_payable, 0, ',', ' ') }} FCFA</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
@endsection
