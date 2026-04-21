@extends('layouts.app')

@section('title', 'Dossiers clients | Cabinet')
@section('page_title', 'Dossiers clients')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><strong>Dossiers</strong> clients</h1>
        <p class="text-muted mb-0">Recherchez une entreprise et ouvrez sa fiche pour accéder aux outils.</p>
    </div>
    <a href="{{ route('accountant.dashboard') }}" class="btn btn-outline-secondary btn-sm">Tableau de bord cabinet</a>
</div>

<form method="get" action="{{ route('accountant.clients.index') }}" class="row g-2 mb-3">
    <div class="col-md-6 col-lg-4">
        <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Nom, société, e-mail…">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary">Rechercher</button>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        @forelse($enterpriseGroups as $group)
            <div class="border-bottom p-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <div>
                        <h6 class="mb-1">{{ $group['company_name'] }}</h6>
                        <p class="small text-muted mb-0">
                            @if(!empty($group['company_tax_id'])) NIF: {{ $group['company_tax_id'] }} · @endif
                            @if(!empty($group['enterprise_license_id'])) Licence #{{ $group['enterprise_license_id'] }} · @endif
                            {{ $group['users_count'] }} utilisateur(s)
                        </p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Utilisateur</th>
                            <th>E-mail</th>
                            <th class="text-end">Inscription</th>
                            <th class="text-end">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($group['users'] as $u)
                            <tr>
                                <td>{{ $u->name }}</td>
                                <td class="small">{{ $u->email }}</td>
                                <td class="text-end small">{{ $u->created_at?->format('d/m/Y') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('accountant.clients.show', $u) }}" class="btn btn-sm btn-outline-primary">Fiche dossier</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="text-center text-muted py-4">Aucun résultat.</div>
        @endforelse
    </div>
</div>
@endsection
