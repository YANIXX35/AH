@extends('layouts.app')

@section('title', 'Club Sportif | Sitiame Capital')
@section('page_title', 'Club Sportif')

@section('content')
<div class="container-fluid p-0">
    <h2 class="h3 mb-4">Club Sportif</h2>
    <div class="row g-3">
        <div class="col-md-4">
            <a href="{{ route('sport.members') }}" class="text-decoration-none text-reset">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-primary">Membres</h5>
                        <p class="text-muted small mb-0">Liste des membres du club, gérés directement sur ERPNext.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('sport.cotisations') }}" class="text-decoration-none text-reset">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-primary">Cotisations</h5>
                        <p class="text-muted small mb-0">Factures de cotisation générées automatiquement par ERPNext.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('sport.events') }}" class="text-decoration-none text-reset">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-primary">Événements</h5>
                        <p class="text-muted small mb-0">Matchs, entraînements et événements créés sur ERPNext.</p>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>
@endsection
