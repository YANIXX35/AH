@extends('layouts.app')

@section('title', 'Dossiers clients | Cabinet')
@section('page_title', 'Dossiers clients')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><strong>Dossiers</strong> clients</h1>
        <p class="text-muted mb-0">Recherchez une entreprise et ouvrez sa fiche pour accéder aux outils.</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#addAccountantClientModal">
            <i data-feather="plus-circle" class="me-1" style="width:16px; height:16px;"></i> Ajouter Client / Entreprise
        </button>
        <a href="{{ route('accountant.dashboard') }}" class="btn btn-outline-secondary btn-sm">Tableau de bord cabinet</a>
    </div>
</div>

@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show rounded-3 mb-3 border-0 shadow-sm" role="alert">
        <i data-feather="check-circle" class="me-2 text-success"></i>
        {{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

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

<!-- Add Accountant Client Modal -->
<div class="modal fade" id="addAccountantClientModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="addAccountantClientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="addAccountantClientModalLabel">Nouveau Dossier Client / Entreprise</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('accountant.clients.store') }}" method="POST">
                @csrf
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nom du responsable client</label>
                        <input type="text" name="name" class="form-control rounded-3" placeholder="ex: Jean Dupont" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Adresse E-mail pro</label>
                        <input type="email" name="email" class="form-control rounded-3" placeholder="ex: contact@societe.ci" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nom de l'entreprise</label>
                        <input type="text" name="company_name" class="form-control rounded-3" placeholder="ex: Societe Ivoirienne SARL" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Mot de passe de connexion initial</label>
                        <input type="text" name="password" class="form-control rounded-3" value="Sitiame{{ date('Y') }}!" placeholder="Min. 8 caractères" required>
                        <div class="form-text small text-muted">Transmettez ces identifiants au client pour sa première connexion.</div>
                    </div>
                    <div class="p-3 bg-light rounded-3 small text-muted border-0 shadow-none">
                        💡 À la création du dossier, le client bénéficiera automatiquement de **1 mois d'accès gratuit** à la plateforme.
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">Créer le dossier client</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('action') === 'add-client') {
            const modalEl = document.getElementById('addAccountantClientModal');
            if (modalEl) {
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }
        }
    });
</script>
@endpush
@endsection
