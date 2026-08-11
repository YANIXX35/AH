@extends('layouts.app')

@section('title', 'Sauvegardes base de données | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="mb-4">
    <nav aria-label="Fil d'Ariane admin" class="mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Administration</a></li>
            <li class="breadcrumb-item active" aria-current="page">Sauvegardes base de données</li>
        </ol>
    </nav>
    <h1 class="h3 mb-1"><strong>Sauvegardes</strong> de la base de données</h1>
    <p class="text-muted mb-0">
        Export complet et indépendant de la base de production (hébergée sur LWS), pour pouvoir restaurer les données
        en cas d'incident. Générée automatiquement toutes les 4h, conservée {{ $keepCount }} sauvegardes maximum.
    </p>
</div>

@if(session('status'))
    <div class="alert alert-success border-0 shadow-sm">{{ session('status') }}</div>
@endif
@error('backup')
    <div class="alert alert-danger border-0 shadow-sm">{{ $message }}</div>
@enderror

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <div class="fw-semibold">{{ $backups->count() }} sauvegarde(s) disponible(s)</div>
            <div class="text-muted small">Fichiers stockés hors de la base de données elle-même (disque privé du serveur applicatif).</div>
        </div>
        <form action="{{ route('admin.backups.run') }}" method="POST" onsubmit="return confirm('Lancer une sauvegarde complète maintenant ?');">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i data-feather="database" class="me-1" style="width:16px;height:16px;"></i> Sauvegarder maintenant
            </button>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        @if($backups->isEmpty())
            <div class="text-center text-muted py-5">Aucune sauvegarde pour le moment.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Fichier</th>
                            <th>Date</th>
                            <th>Taille</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($backups as $backup)
                            <tr>
                                <td class="font-monospace small">{{ $backup['filename'] }}</td>
                                <td>{{ $backup['created_at']->format('d/m/Y H:i') }}</td>
                                <td>{{ number_format($backup['size'] / 1024 / 1024, 2) }} Mo</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.backups.download', $backup['filename']) }}" class="btn btn-sm btn-outline-primary">
                                        <i data-feather="download" style="width:14px;height:14px;"></i> Télécharger
                                    </a>
                                    <form action="{{ route('admin.backups.destroy', $backup['filename']) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer définitivement cette sauvegarde ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i data-feather="trash-2" style="width:14px;height:14px;"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
