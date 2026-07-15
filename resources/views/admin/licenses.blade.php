@extends('layouts.app')

@section('title', 'Licences entreprise | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="mb-4">
    <nav aria-label="Fil d'Ariane admin" class="mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Administration</a></li>
            <li class="breadcrumb-item active" aria-current="page">Licences multi-utilisateurs</li>
        </ol>
    </nav>
    <h1 class="h3 mb-1"><strong>Licences</strong> entreprise</h1>
    <p class="text-muted mb-0">Chaque licence est liée à <strong>une seule entreprise</strong> (NIF), jusqu’à <strong>{{ $defaultMaxSeats }}</strong> comptes utilisateurs (valeur par défaut à la création). Attribuez-la aux comptes déjà existants ci-dessous, ou laissez l’entreprise s’inscrire avec la clé et le même NIF.</p>
</div>

@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">
        <ul class="mb-0 small">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
    </div>
@endif

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">Attribuer une licence à une entreprise existante</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">Liste dérivée des comptes <strong>entreprise</strong> ayant un NIF renseigné : tous les utilisateurs partageant ce NIF recevront le même rattachement licence.</p>
                @if($enterprises->isEmpty())
                    <p class="text-muted mb-0">Aucune entreprise avec NIF trouvée. Complétez les fiches utilisateurs (NIF) puis réessayez.</p>
                @elseif($licensesAssignable->isEmpty())
                    <p class="text-muted mb-0">Aucune licence active disponible. Générez une clé dans le bloc « Nouvelle licence ».</p>
                @else
                    <form method="post" action="{{ route('admin.licenses.assign') }}" class="row g-3 align-items-end">
                        @csrf
                        <div class="col-md-5">
                            <label class="form-label">Entreprise (NIF)</label>
                            <select name="company_tax_id" class="form-select" required>
                                <option value="">— Choisir —</option>
                                @foreach($enterprises as $ent)
                                    <option value="{{ $ent->tax_norm }}" @selected(old('company_tax_id') === $ent->tax_norm)>
                                        {{ $ent->company_name ?: 'Sans nom' }} — {{ $ent->tax_norm }} ({{ $ent->users_count }} compte(s))
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Licence</label>
                            <select name="enterprise_license_id" class="form-select" required>
                                <option value="">— Choisir —</option>
                                @foreach($licensesAssignable as $lic)
                                    <option value="{{ $lic->id }}" @selected((string) old('enterprise_license_id') === (string) $lic->id)>
                                        {{ $lic->license_key }}{{ $lic->label ? ' — '.$lic->label : '' }}
                                        ({{ $lic->max_seats }} sièges{{ $lic->assigned_company_tax_id ? ' ; NIF '.$lic->assigned_company_tax_id : '' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Attribuer</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">Nouvelle licence</h5>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.licenses.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Libellé (optionnel)</label>
                        <input type="text" name="label" value="{{ old('label') }}" class="form-control" placeholder="Ex. : Société ABC — pack équipe">
                        <div class="form-text">Aide-mémoire interne pour l’admin.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nombre de sièges</label>
                        <input type="number" name="max_seats" value="{{ old('max_seats', $defaultMaxSeats) }}" min="1" max="{{ $defaultMaxSeats }}" class="form-control">
                        <div class="form-text">Plafond : {{ $defaultMaxSeats }} comptes entreprise pour la même licence (premier compte + collègues avec la même clé et le même NIF).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date d’expiration (optionnel)</label>
                        <input type="date" name="expires_at" value="{{ old('expires_at') }}" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes internes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Référence devis, contact…">{{ old('notes') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Générer la clé</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Licences émises</h5>
                <span class="badge bg-secondary">{{ $licenses->total() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Clé</th>
                            <th>Entreprise (NIF)</th>
                            <th>Sièges</th>
                            <th>Statut</th>
                            <th>Créée</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($licenses as $lic)
                            @php
                                $used = $lic->seatsUsed();
                            @endphp
                            <tr>
                                <td>
                                    <code class="user-select-all small">{{ $lic->license_key }}</code>
                                    @if($lic->label)
                                        <div class="text-muted small">{{ $lic->label }}</div>
                                    @endif
                                </td>
                                <td class="small">
                                    @if($lic->assigned_company_tax_id)
                                        <code>{{ $lic->assigned_company_tax_id }}</code>
                                    @else
                                        <span class="text-muted">Non attribuée</span>
                                    @endif
                                </td>
                                <td>{{ $used }} / {{ $lic->max_seats }}</td>
                                <td>
                                    @if($lic->revoked_at)
                                        <span class="badge bg-danger">Révoquée</span>
                                    @elseif($lic->expires_at && $lic->expires_at->isPast())
                                        <span class="badge bg-warning text-dark">Expirée</span>
                                    @else
                                        <span class="badge bg-success">Active</span>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ $lic->created_at->format('d/m/Y H:i') }}</td>
                                <td class="text-end">
                                    @if(!$lic->revoked_at)
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#edit-license-{{ $lic->id }}">Modifier</button>
                                        <form method="post" action="{{ route('admin.licenses.revoke', $lic) }}" class="d-inline" onsubmit="return confirm('Révoquer cette licence ?');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Révoquer</button>
                                        </form>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            </tr>
                            @if(!$lic->revoked_at)
                                <tr class="collapse" id="edit-license-{{ $lic->id }}">
                                    <td colspan="6" class="bg-light">
                                        <form method="post" action="{{ route('admin.licenses.update', $lic) }}" class="row g-2 align-items-end py-2">
                                            @csrf
                                            @method('PUT')
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1">Libellé</label>
                                                <input type="text" name="label" value="{{ $lic->label }}" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small mb-1">Sièges (min. {{ $used }})</label>
                                                <input type="number" name="max_seats" value="{{ $lic->max_seats }}" min="{{ max(1, $used) }}" max="{{ $defaultMaxSeats }}" class="form-control form-control-sm" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small mb-1">Expiration</label>
                                                <input type="date" name="expires_at" value="{{ $lic->expires_at?->format('Y-m-d') }}" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1">Notes internes</label>
                                                <input type="text" name="notes" value="{{ $lic->notes }}" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-md-2">
                                                <button type="submit" class="btn btn-sm btn-primary w-100">Enregistrer</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Aucune licence pour l’instant.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($licenses->hasPages())
                <div class="card-footer bg-white">{{ $licenses->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
