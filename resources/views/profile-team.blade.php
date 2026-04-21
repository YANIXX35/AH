@extends('layouts.app')

@section('title', 'Équipe & licence | ' . config('app.name'))
@section('page_title', 'Équipe (licence entreprise)')

@section('content')
<div class="container-fluid p-0">
    <nav aria-label="Fil d'Ariane" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('profile') }}">Profil</a></li>
            <li class="breadcrumb-item active" aria-current="page">Équipe</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('profile.team.history') }}" class="btn btn-outline-primary btn-sm">Historique entreprise</a>
    </div>

    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Sièges licence</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2">Clé : <code class="user-select-all">{{ $license->license_key }}</code></p>
                    <p class="mb-2">
                        Utilisation : <strong>{{ $seatsUsed }} / {{ $seatsMax }}</strong> comptes entreprise
                    </p>
                    @if(!$license->isUsable())
                        <p class="text-danger small mb-0">Cette licence n’est plus active (révoquée ou expirée). Vous ne pouvez pas ajouter de collaborateur.</p>
                    @elseif(!$canAdd)
                        <p class="text-muted small mb-0">Tous les sièges sont utilisés. Pour ajouter quelqu’un, un administrateur doit augmenter le plafond ou libérer un siège.</p>
                    @else
                        <p class="text-muted small mb-0">Vous pouvez encore créer <strong>{{ $seatsMax - $seatsUsed }}</strong> compte(s) pour le même NIF que votre entreprise.</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Nouveau collaborateur</h5>
                </div>
                <div class="card-body">
                    @if($canAdd)
                        <p class="small text-muted mb-3">Le compte reprend les mêmes informations entreprise (NIF, adresse, paramètres fiscaux) que le vôtre. Définissez seulement l’identité du collaborateur et ses identifiants de connexion.</p>
                        <form method="post" action="{{ route('profile.team.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Nom complet</label>
                                <input type="text" name="name" value="{{ old('name') }}" required class="form-control @error('name') is-invalid @enderror">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">E-mail professionnel</label>
                                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="off" class="form-control @error('email') is-invalid @enderror">
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Mot de passe</label>
                                <input type="password" name="password" required class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirmer le mot de passe</label>
                                <input type="password" name="password_confirmation" required class="form-control" autocomplete="new-password">
                            </div>
                            <button type="submit" class="btn btn-primary">Créer le compte</button>
                        </form>
                    @else
                        <p class="text-muted mb-0">Création de collaborateur indisponible pour l’instant.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">Comptes rattachés à cette licence</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th>Nom</th>
                        <th>E-mail</th>
                        <th>Créé le</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($teammates as $mate)
                        <tr>
                            <td>{{ $mate->name }}</td>
                            <td>{{ $mate->email }}</td>
                            <td class="small text-muted">{{ $mate->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Journal d’actions de l’entreprise</h5>
            <span class="badge bg-secondary">{{ $activityLogs->count() }}</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Utilisateur connecté</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Détail</th>
                        <th>IP</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($activityLogs as $log)
                        <tr>
                            <td class="small text-nowrap">{{ $log['created_at']?->format('d/m/Y H:i:s') ?? '—' }}</td>
                            <td>
                                <div class="small fw-medium">
                                    {{ $log['actor_name'] ?? '—' }}
                                    @if((int) ($log['actor_id'] ?? 0) === (int) auth()->id())
                                        <span class="badge bg-primary ms-1">toi</span>
                                    @endif
                                </div>
                                @if(!empty($log['actor_email']))
                                    <div class="small text-muted">{{ $log['actor_email'] }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ ($log['source'] ?? '') === 'treasury' ? 'bg-success' : 'bg-info text-dark' }}">
                                    {{ $log['label'] ?? '—' }}
                                </span>
                            </td>
                            <td class="small">{{ $log['action'] ?? '—' }}</td>
                            <td class="small text-break" style="max-width: 26rem;">
                                {{ \Illuminate\Support\Str::limit((string) ($log['detail'] ?? '—'), 180) }}
                            </td>
                            <td class="small text-muted text-nowrap">{{ $log['ip'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Aucune action enregistrée pour cette entreprise.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
