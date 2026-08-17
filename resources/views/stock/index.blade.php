@extends('layouts.app')

@section('title', 'Gestion de stock | Sitiame Capital')
@section('page_title', 'Gestion de Stock')

@push('styles')
    <style>
        .mondays-container { background-color: #f8fafc; min-height: 100vh; }
        .mondays-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); transition: all 0.2s ease; }
        .mondays-card:hover { border-color: #cbd5e1; box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
        .mondays-hero-date { font-size: 0.85rem; font-weight: 500; color: #64748b; }
        .mondays-hero-title { font-size: 1.85rem; font-weight: 700; color: #0f172a; margin-top: 2px; margin-bottom: 12px; }
        .mondays-pill-bar { display: inline-flex; align-items: center; gap: 16px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 9999px; padding: 6px 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); flex-wrap: wrap; }
        .mondays-pill-item { font-size: 0.84rem; font-weight: 600; color: #334155; display: flex; align-items: center; gap: 6px; }
        .mondays-badge { display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .mondays-badge-success { background: #dcfce7; color: #15803d; }
        .mondays-badge-pending { background: #f3e8ff; color: #7e22ce; }
        .mondays-badge-info { background: #dbeafe; color: #1d4ed8; }
        .mondays-badge-warning { background: #ffedd5; color: #c2410c; }
        .mondays-metric-val { font-size: 1.65rem; font-weight: 700; color: #0f172a; line-height: 1.2; }
    </style>
@endpush

@section('content')
<div class="mondays-container pb-4">
    <!-- HERO MONDAYS HEADER -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2">
            <div>
                <div class="mondays-hero-date">
                    <i data-feather="calendar" class="me-1" style="width:14px; height:14px;"></i>
                    {{ \Carbon\Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </div>
                <h1 class="mondays-hero-title">
                    Gestion de Stock — {{ explode(' ', auth()->user()?->name ?? 'Utilisateur')[0] }} 👋
                </h1>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="{{ route('stock.create') }}" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold">
                    <i data-feather="plus" class="me-1" style="width:14px; height:14px;"></i> Nouveau Produit
                </a>
            </div>
        </div>

        <!-- BARRE DE PILULES KPI EN-TÊTE -->
        <div class="mondays-pill-bar">
            <div class="mondays-pill-item">
                <span class="text-primary">📦</span> <strong>Valeur du stock :</strong> {{ number_format($totalValue, 0, ',', ' ') }} FCFA
            </div>
            <div class="mondays-pill-item text-muted">|</div>
            <div class="mondays-pill-item">
                <span class="text-warning">⚠️</span> <strong>Sous seuil :</strong> {{ $lowStockCount }} produit(s)
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success border-0 rounded-3 mb-3 shadow-sm">{{ session('success') }}</div>
    @endif

    <!-- METRICS CARDS GRID -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6">
            <div class="card mondays-card h-100 border-0 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Valeur Totale du Stock</span>
                    <span class="mondays-badge mondays-badge-success">CUMP</span>
                </div>
                <div class="mondays-metric-val text-primary mb-1">{{ number_format($totalValue, 0, ',', ' ') }} <small class="fs-6 fw-normal text-muted">FCFA</small></div>
                <div class="text-muted small">Valorisation au coût unitaire moyen.</div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card mondays-card h-100 border-0 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Produits Sous Seuil</span>
                    <span class="mondays-badge {{ $lowStockCount > 0 ? 'mondays-badge-warning' : 'mondays-badge-info' }}">
                        {{ $lowStockCount > 0 ? 'Réappro' : 'Optimal' }}
                    </span>
                </div>
                <div class="mondays-metric-val {{ $lowStockCount > 0 ? 'text-danger' : 'text-dark' }} mb-1">{{ $lowStockCount }} <small class="fs-6 fw-normal text-muted">référence(s)</small></div>
                <div class="text-muted small">Stock sous le niveau minimum.</div>
            </div>
        </div>
    </div>

    @if ($archivedCount > 0)
        <p class="text-muted small mb-3 fs-7">{{ $archivedCount }} produit{{ $archivedCount > 1 ? 's' : '' }} archivé{{ $archivedCount > 1 ? 's' : '' }} masqué{{ $archivedCount > 1 ? 's' : '' }} de cette liste.</p>
    @endif

    <!-- MAIN TABLE CARD -->
    <div class="card mondays-card border-0">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Nom du produit</th>
                            <th class="text-end">Quantité en stock</th>
                            <th class="text-end">CUMP</th>
                            <th class="text-end">Valeur totale</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr>
                                <td class="fw-semibold text-muted">{{ $product->sku ?? '—' }}</td>
                                <td>
                                    <a href="{{ route('stock.show', $product) }}" class="fw-bold text-primary">{{ $product->name }}</a>
                                    @if ($product->isBelowThreshold())
                                        <span class="mondays-badge mondays-badge-warning ms-1">Sous seuil</span>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold">{{ number_format((float) $product->quantity_on_hand, 2, ',', ' ') }} {{ $product->unit }}</td>
                                <td class="text-end">{{ number_format((float) $product->average_cost, 0, ',', ' ') }}</td>
                                <td class="text-end fw-semibold text-success">{{ number_format($product->stockValue(), 0, ',', ' ') }} FCFA</td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a href="{{ route('stock.show', $product) }}" class="btn btn-sm btn-outline-primary rounded-pill">Détail</a>
                                        <form action="{{ route('stock.destroy', $product) }}" method="POST" onsubmit="return confirm('Supprimer ce produit ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill">Supprimer</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Aucun produit pour l'instant.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                {{ $products->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
