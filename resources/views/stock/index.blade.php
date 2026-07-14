@extends('layouts.app')

@section('title', 'Gestion de stock | Sitiame Capitale')
@section('page_title', 'Gestion de stock')

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h3 mb-1"><strong>Stock</strong> <span class="badge bg-secondary">Module optionnel</span></h2>
            <p class="text-muted small mb-0">Suivi des quantités et valorisation au coût unitaire moyen pondéré (CUMP). Pertinent pour le négoce/distribution.</p>
        </div>
        <a href="{{ route('stock.create') }}" class="btn btn-primary btn-sm">+ Nouveau produit</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-4">
            <div class="card"><div class="card-body">
                <div class="text-muted small">Valeur du stock</div>
                <div class="h4 mb-0">{{ number_format($totalValue, 0, ',', ' ') }} FCFA</div>
            </div></div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card"><div class="card-body">
                <div class="text-muted small">Produits sous seuil</div>
                <div class="h4 mb-0 {{ $lowStockCount > 0 ? 'text-danger' : '' }}">{{ $lowStockCount }}</div>
            </div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Nom</th>
                            <th class="text-end">Quantité</th>
                            <th class="text-end">CUMP</th>
                            <th class="text-end">Valeur</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr class="{{ $product->isBelowThreshold() ? 'table-warning' : '' }}">
                                <td>{{ $product->sku ?? '—' }}</td>
                                <td>
                                    <a href="{{ route('stock.show', $product) }}">{{ $product->name }}</a>
                                    @if ($product->isBelowThreshold())
                                        <span class="badge bg-warning text-dark ms-1">Sous seuil</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format((float) $product->quantity_on_hand, 2, ',', ' ') }} {{ $product->unit }}</td>
                                <td class="text-end">{{ number_format((float) $product->average_cost, 0, ',', ' ') }}</td>
                                <td class="text-end">{{ number_format($product->stockValue(), 0, ',', ' ') }} FCFA</td>
                                <td><a href="{{ route('stock.show', $product) }}" class="btn btn-sm btn-outline-primary">Détail</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Aucun produit pour l'instant.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $products->links() }}
        </div>
    </div>
</div>
@endsection
