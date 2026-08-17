@extends('layouts.app')

@section('title', 'Clôture mensuelle | Sitiame Capital')
@section('page_title', 'Comptabilité')

@section('content')
<div class="mb-4">
    <nav aria-label="Fil d'Ariane" class="mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('accounting') }}">Moteur comptable</a></li>
            <li class="breadcrumb-item active" aria-current="page">Clôture mensuelle</li>
        </ol>
    </nav>
    <h1 class="h3 mb-1"><strong>Clôture mensuelle</strong></h1>
    <p class="text-muted mb-0">
        Grille de contrôle avant clôture : journal, balance et rapprochement. L’enregistrement ci-dessous sert de <strong>repère métier</strong> (traçabilité) ; il ne verrouille pas techniquement la saisie.
    </p>
</div>

@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="{{ route('accounting.monthly-closing') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-0">Mois civil</label>
                <input type="month" name="month" value="{{ $yearMonth }}" class="form-control" required>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">Afficher</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fw-semibold">Contrôles — {{ ucfirst($periodLabel) }}</span>
                @if($closure)
                    <span class="badge bg-success">Clôture enregistrée le {{ $closure->closed_at->format('d/m/Y H:i') }}</span>
                @else
                    <span class="badge bg-secondary">Non clôturé</span>
                @endif
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>Écritures comptables sur le mois</span>
                    <span>
                        @if($checkJournal)
                            <span class="badge bg-success">OK ({{ $entriesCount }})</span>
                        @else
                            <span class="badge bg-warning text-dark">À compléter (0)</span>
                        @endif
                    </span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>Opérations trésorerie « effectuées »</span>
                    <span>
                        @if($checkTreasury)
                            <span class="badge bg-success">OK ({{ $treasuryEffectueCount }})</span>
                        @else
                            <span class="badge bg-secondary">Aucune / N/A</span>
                        @endif
                    </span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>Balance &amp; rapports du mois</span>
                    <span class="d-flex gap-1 flex-wrap">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('accounting.report.balance') }}?{{ $reportQuery }}">Balance</a>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('accounting.report.journal') }}?{{ $reportQuery }}">Journal</a>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('accounting.bank-reconciliation', ['date_from' => $bankRecoFrom, 'date_to' => $bankRecoTo]) }}">Rapprochement</a>
                    </span>
                </li>
            </ul>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Enregistrer la clôture du mois</div>
            <div class="card-body">
                <form method="post" action="{{ route('accounting.monthly-closing.store') }}">
                    @csrf
                    <input type="hidden" name="year_month" value="{{ $yearMonth }}">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Notes (facultatif)</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Ex. : pointage banque validé, TVA déclarée…">{{ old('notes', $closure?->notes) }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Valider la clôture {{ $yearMonth }}</button>
                </form>
                @if($closure && $closure->closedBy)
                    <p class="small text-muted mt-3 mb-0">Dernier enregistrement par {{ $closure->closedBy->name ?? $closure->closedBy->email }}.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
