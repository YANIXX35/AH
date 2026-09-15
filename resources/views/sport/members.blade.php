@extends('layouts.app')

@section('title', 'Membres du Club | Sitiame Capital')
@section('page_title', 'Membres du Club')

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h3 mb-0">Membres du Club</h2>
        <a href="{{ route('sport.index') }}" class="btn btn-outline-secondary btn-sm">Retour</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-xl-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Nouveau membre</h5>
                    <form action="{{ route('sport.members.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Nom *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Téléphone</label>
                            <input type="text" name="mobile" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Créer</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Liste des membres</h5>
                    @if ($error)
                        <div class="alert alert-danger">{{ $error }}</div>
                    @else
                        <table class="table table-sm">
                            <thead><tr><th>Nom</th><th>Téléphone</th><th>Email</th></tr></thead>
                            <tbody>
                                @forelse ($members as $member)
                                    <tr>
                                        <td>{{ $member['customer_name'] }}</td>
                                        <td>{{ $member['mobile_no'] ?? '—' }}</td>
                                        <td>{{ $member['email_id'] ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-4">Aucun membre pour l'instant.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
