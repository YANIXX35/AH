@extends('layouts.app')

@section('title', 'Éditer '.$product->name.' | Sitiame Capitale')
@section('page_title', 'Éditer '.$product->name)

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h3 mb-0"><strong>Éditer</strong> {{ $product->name }}</h2>
        <a href="{{ route('stock.show', $product) }}" class="btn btn-outline-secondary btn-sm">Retour à la fiche</a>
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
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="text-muted mb-3">Quantité et valorisation actuelles</h6>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Quantité en stock</label>
                            <input type="text" class="form-control" value="{{ number_format((float) $product->quantity_on_hand, 2, ',', ' ') }} {{ $product->unit }}" disabled>
                        </div>
                        <div class="col-6">
                            <label class="form-label">CUMP actuel</label>
                            <input type="text" class="form-control" value="{{ number_format((float) $product->average_cost, 0, ',', ' ') }} FCFA" disabled>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2">
                        Ces valeurs sont calculées automatiquement à partir des mouvements de stock et ne sont pas modifiables ici — utilise le formulaire de mouvement sur la fiche produit pour les faire évoluer.
                    </small>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <form action="{{ route('stock.update', $product) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label">Nom du produit *</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" required value="{{ old('name', $product->name) }}">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">SKU / référence</label>
                            <input type="text" name="sku" class="form-control" value="{{ old('sku', $product->sku) }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Unité</label>
                            @if ($hasMovements)
                                <input type="text" class="form-control" value="{{ $product->unit }}" disabled>
                                <small class="text-muted">Non modifiable après le premier mouvement de stock : changer l'unité rendrait l'historique des quantités déjà enregistrées incohérent.</small>
                            @else
                                <input type="text" name="unit" class="form-control" value="{{ old('unit', $product->unit) }}">
                                <small class="text-muted">Modifiable tant qu'aucun mouvement n'a été enregistré sur ce produit.</small>
                            @endif
                        </div>

                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label">Prix de vente</label>
                                <input type="number" step="0.01" min="0" name="sale_price" class="form-control @error('sale_price') is-invalid @enderror" value="{{ old('sale_price', $product->sale_price) }}">
                                @error('sale_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6">
                                <label class="form-label">Seuil de réapprovisionnement</label>
                                <input type="number" step="0.01" min="0" name="reorder_threshold" class="form-control @error('reorder_threshold') is-invalid @enderror" value="{{ old('reorder_threshold', $product->reorder_threshold) }}">
                                @error('reorder_threshold')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary mt-3">Enregistrer les modifications</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
