@extends('layouts.app')

@section('title', 'Plan comptable de référence | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="mb-4">
    <nav aria-label="Fil d’Ariane admin" class="mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Administration</a></li>
            <li class="breadcrumb-item active" aria-current="page">Plan comptable de référence</li>
        </ol>
    </nav>
    <h1 class="h3 mb-1"><strong>Plan comptable</strong> de référence (SYSCOHADA)</h1>
    <p class="text-muted mb-0">Ce plan est installé automatiquement pour tout nouveau compte et à chaque utilisation du bouton « Réinitialiser » côté client.</p>
</div>

@if(session('status'))
    <div class="alert alert-success border-0 shadow-sm">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger border-0 shadow-sm">
        <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-lg-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Comptes dans le plan</div>
                <div class="h3 mb-0">{{ number_format($total, 0, ',', ' ') }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Entreprises utilisant ce plan</div>
                <div class="h3 mb-0">{{ number_format($companiesUsingDefaults, 0, ',', ' ') }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">Répartition par classe</div>
                <div class="d-flex flex-wrap gap-2">
                    @forelse($byClass as $classe => $count)
                        <span class="badge bg-light text-dark border">Classe {{ $classe }} : {{ $count }}</span>
                    @empty
                        <span class="text-muted small">Aucun compte chargé.</span>
                    @endforelse
                </div>
                @if($lastUpdatedAt)
                    <div class="text-muted small mt-2">Dernière mise à jour : {{ \Carbon\Carbon::parse($lastUpdatedAt)->format('d/m/Y H:i') }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 mb-2">Remplacer le plan de référence</h2>
                <p class="text-muted small mb-3">
                    Fichier Excel (.xlsx/.xls) ou CSV avec au minimum les colonnes <code>Classe</code>, <code>Compte</code>, <code>Intitulé</code>.
                    Les colonnes <code>Type</code>, <code>Observation</code>, <code>Nature</code>, <code>Catégorie BCEAO</code>, <code>Flux TAFIRE</code>,
                    <code>Éligible TVA</code>, <code>Éligible échéancier</code>, <code>Lié immobilisation</code> sont reprises si présentes.
                </p>
                <form method="post" action="{{ route('admin.plan-comptable.upload') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-8">
                        <label class="form-label mb-1" for="plan_file">Fichier</label>
                        <input type="file" class="form-control" id="plan_file" name="plan_file" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div class="col-4">
                        <button class="btn btn-primary w-100" type="submit">Remplacer</button>
                    </div>
                </form>
                <p class="text-danger small mt-3 mb-0">
                    <i data-feather="alert-triangle" style="width:14px;height:14px;"></i>
                    Ceci remplace intégralement le plan de référence. Les entreprises déjà existantes ne sont pas modifiées tant que vous n'utilisez pas
                    « Appliquer aux comptes existants » ci-contre, ou tant qu'elles ne cliquent pas elles-mêmes sur « Réinitialiser ».
                </p>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 mb-2">Propager aux comptes existants</h2>
                <p class="text-muted small mb-3">
                    Remplace le plan comptable de toutes les entreprises qui en ont déjà un ({{ number_format($companiesUsingDefaults, 0, ',', ' ') }} compte(s))
                    par la version actuelle du plan de référence ci-dessus. Les écritures comptables déjà saisies ne sont pas affectées.
                </p>
                <form method="post" action="{{ route('admin.plan-comptable.apply-to-existing') }}"
                      onsubmit="return confirm('Remplacer le plan comptable de {{ $companiesUsingDefaults }} entreprise(s) déjà existante(s) par le plan de référence actuel ?');">
                    @csrf
                    <button class="btn btn-outline-danger" type="submit">Appliquer aux comptes existants</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 mb-0">Comptes du plan de référence</h2>
            <form method="get" class="d-flex gap-2">
                <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Rechercher un compte ou un libellé…" style="min-width: 260px;">
                <button class="btn btn-sm btn-outline-secondary" type="submit">Rechercher</button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:70px;">Classe</th>
                        <th style="width:120px;">Compte</th>
                        <th>Intitulé</th>
                        <th style="width:100px;">Type</th>
                        <th style="width:140px;">Catégorie</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $account)
                        <tr>
                            <td class="fw-bold">{{ $account->classe }}</td>
                            <td><code>{{ $account->numero_compte }}</code></td>
                            <td>{{ $account->libelle_compte }}</td>
                            <td class="text-muted small">{{ $account->type_compte }}</td>
                            <td class="text-muted small">{{ $account->category }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Aucun compte trouvé.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            {{ $accounts->links() }}
        </div>
    </div>
</div>
@endsection
