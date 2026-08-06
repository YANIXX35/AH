@extends('layouts.app')

@section('title', 'Documents clients | Cabinet')
@section('page_title', 'Documents clients')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><strong>Documents</strong> clients</h1>
        <p class="text-muted mb-0">Attestations DFE / NIF et registres de commerce déposés pour chaque dossier client. Prévisualisez avant de valider, remplacez si besoin.</p>
    </div>
    <a href="{{ route('accountant.clients.index') }}" class="btn btn-outline-secondary btn-sm">Dossiers clients</a>
</div>

@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show rounded-3 mb-3 border-0 shadow-sm" role="alert">
        <i data-feather="check-circle" class="me-2 text-success"></i>
        {{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-3 border-0 shadow-sm" role="alert">
        <i data-feather="alert-triangle" class="me-2 text-danger"></i>
        {{ $errors->first() }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<form method="get" action="{{ route('accountant.documents.index') }}" class="row g-2 mb-3">
    <div class="col-md-6 col-lg-4">
        <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Nom du client ou de l'entreprise…">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary">Rechercher</button>
    </div>
</form>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="min-width: 850px;">
                <thead class="bg-light text-slate-700 text-uppercase fs-8 fw-bold border-bottom">
                    <tr>
                        <th class="py-3 px-3">Client / Entreprise</th>
                        <th class="py-3 px-3">Attestation DFE / NIF</th>
                        <th class="py-3 px-3">Registre de commerce</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($clients as $client)
                    <tr>
                        <td class="py-3 px-3 align-middle">
                            <div class="fw-bold text-dark fs-7">{{ $client->company_name ?: $client->name }}</div>
                            <div class="text-muted small">{{ $client->name }} &middot; {{ $client->email }}</div>
                        </td>
                        <td class="py-3 px-3 align-middle">
                            @include('accountant.partials.document-cell', ['client' => $client, 'type' => 'company_logo', 'path' => $client->company_logo])
                        </td>
                        <td class="py-3 px-3 align-middle">
                            @include('accountant.partials.document-cell', ['client' => $client, 'type' => 'trade_register', 'path' => $client->trade_register_file])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center py-5 text-muted">
                            @if($search !== '')
                                Aucun client ne correspond à « {{ $search }} ».
                            @else
                                Aucun dossier client pour le moment. <a href="{{ route('accountant.clients.index', ['action' => 'add-client']) }}">Ajoutez-en un</a>.
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
