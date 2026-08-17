@extends('layouts.app')

@section('title', 'Nouveau produit | Sitiame Capital')
@section('page_title', 'Nouveau produit')

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h3 mb-0"><strong>Nouveau</strong> produit</h2>
        <a href="{{ route('stock.index') }}" class="btn btn-outline-secondary btn-sm">Retour</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-12 col-xl-6">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('stock.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Nom du produit *</label>
                            <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">SKU / référence</label>
                            <input type="text" name="sku" class="form-control" value="{{ old('sku') }}">
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label">Unité</label>
                                <input type="text" name="unit" class="form-control" value="{{ old('unit', 'unité') }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Prix de vente</label>
                                <input type="number" step="0.01" min="0" name="sale_price" class="form-control" value="{{ old('sale_price', 0) }}">
                            </div>
                        </div>
                        <div class="mb-3 mt-3">
                            <label class="form-label">Seuil de réapprovisionnement</label>
                            <input type="number" step="0.01" min="0" name="reorder_threshold" class="form-control" value="{{ old('reorder_threshold') }}">
                            <small class="text-muted">Le produit sera signalé quand la quantité passe sous ce seuil. Laisser vide pour désactiver l'alerte.</small>
                        </div>

                        <p class="text-muted small">Le produit démarre à 0 en stock. Enregistre une première entrée depuis sa fiche pour l'approvisionner.</p>

                        <button type="submit" class="btn btn-primary">Créer le produit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
