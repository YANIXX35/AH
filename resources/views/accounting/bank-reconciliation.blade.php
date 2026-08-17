@extends('layouts.app')

@section('title', 'Rapprochement bancaire | Sitiame Capital')
@section('page_title', 'Comptabilité')

@section('content')
<div class="mb-4">
    <nav aria-label="Fil d'Ariane" class="mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('accounting') }}">Moteur comptable</a></li>
            <li class="breadcrumb-item active" aria-current="page">Rapprochement bancaire</li>
        </ol>
    </nav>
    <h1 class="h3 mb-1"><strong>Rapprochement bancaire</strong></h1>
    <p class="text-muted mb-0">
        Compare les flux <strong>trésorerie effectués</strong> avec les mouvements sur les comptes de <strong>classe 5</strong> (banques, caisse, assimilés — cadre OHADA).
        L’écart affiché est <strong>indicatif</strong> : le rapprochement ligne à ligne (relevés, pointage) reste à finaliser par le cabinet.
    </p>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="{{ route('accounting.bank-reconciliation') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-0">Du</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-0">Au</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">Actualiser</button>
            </div>
            <div class="col-md-3 text-md-end">
                <a href="{{ route('treasury.tracking') }}" class="btn btn-outline-secondary btn-sm">Suivi trésorerie</a>
                <a href="{{ route('treasury.balance') }}" class="btn btn-outline-secondary btn-sm">Soldes</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Trésorerie nette (effectué)</div>
                <div class="display-6 fw-bold text-primary">{{ number_format($treasuryNet, 0, ',', ' ') }} <span class="fs-6">FCFA</span></div>
                <p class="small text-muted mb-0 mt-2">Encaissements {{ number_format($treasuryEncaissements, 0, ',', ' ') }} − décaissements {{ number_format($treasuryDecaissements, 0, ',', ' ') }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Mouvements classe 5 (compta)</div>
                <div class="display-6 fw-bold text-success">{{ number_format($class5NetMovement, 0, ',', ' ') }} <span class="fs-6">FCFA</span></div>
                <p class="small text-muted mb-0 mt-2">Débit 5xx {{ number_format($class5Debit, 0, ',', ' ') }} − crédit 5xx {{ number_format($class5Credit, 0, ',', ' ') }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm {{ abs($deltaIndicative) < 0.01 ? 'border-success' : 'border-warning' }}">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Écart indicatif</div>
                <div class="display-6 fw-bold {{ abs($deltaIndicative) < 0.01 ? 'text-success' : 'text-warning' }}">{{ number_format($deltaIndicative, 0, ',', ' ') }} <span class="fs-6">FCFA</span></div>
                <p class="small text-muted mb-0 mt-2">Trésorerie nette − (débit 5 − crédit 5). Un écart peut refléter des écritures hors banque ou des dates différentes.</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Dernières opérations trésorerie (effectuées)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light"><tr><th>Date</th><th>Type</th><th>Montant</th><th>Réf.</th></tr></thead>
                        <tbody>
                            @forelse($recentTreasury as $t)
                                <tr>
                                    <td>{{ $t->transaction_date?->format('d/m/Y') }}</td>
                                    <td>{{ $t->type }}</td>
                                    <td class="text-end">{{ number_format((float) $t->amount, 0, ',', ' ') }}</td>
                                    <td class="small text-muted">{{ \Illuminate\Support\Str::limit($t->reference ?? '—', 24) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted text-center py-4">Aucune opération sur la période.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Dernières écritures touchant la classe 5</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light"><tr><th>Date</th><th>Débit</th><th>Crédit</th><th class="text-end">Montant</th></tr></thead>
                        <tbody>
                            @forelse($recentClass5Entries as $e)
                                <tr>
                                    <td>{{ $e->date?->format('d/m/Y') }}</td>
                                    <td class="small">{{ $e->debit_account }}</td>
                                    <td class="small">{{ $e->credit_account }}</td>
                                    <td class="text-end">{{ number_format((float) $e->amount, 0, ',', ' ') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted text-center py-4">Aucune écriture classe 5 sur la période.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
