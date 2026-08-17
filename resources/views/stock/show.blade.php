@extends('layouts.app')

@section('title', $product->name.' | Sitiame Capital')
@section('page_title', $product->name)

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h3 mb-1"><strong>{{ $product->name }}</strong></h2>
            <p class="text-muted small mb-0">{{ $product->sku ?? 'Sans SKU' }} · {{ $product->unit }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('stock.edit', $product) }}" class="btn btn-outline-primary btn-sm">Éditer</a>
            <form action="{{ route('stock.destroy', $product) }}" method="POST" onsubmit="return confirm('Supprimer ce produit ? S\'il a déjà des mouvements, il sera archivé (conservé pour l\'historique) plutôt que supprimé.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm">Supprimer</button>
            </form>
            <a href="{{ route('stock.index') }}" class="btn btn-outline-secondary btn-sm">Retour</a>
        </div>
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

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card"><div class="card-body">
                <div class="text-muted small">Quantité en stock</div>
                <div class="h4 mb-0">{{ number_format((float) $product->quantity_on_hand, 2, ',', ' ') }} {{ $product->unit }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card"><div class="card-body">
                <div class="text-muted small">CUMP</div>
                <div class="h4 mb-0">{{ number_format((float) $product->average_cost, 0, ',', ' ') }} FCFA</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card"><div class="card-body">
                <div class="text-muted small">Valeur du stock</div>
                <div class="h4 mb-0">{{ number_format($product->stockValue(), 0, ',', ' ') }} FCFA</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card"><div class="card-body">
                <div class="text-muted small">Prix de vente</div>
                <div class="h4 mb-0">{{ number_format((float) $product->sale_price, 0, ',', ' ') }} FCFA</div>
            </div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Enregistrer un mouvement</h5>
                    <form action="{{ route('stock.movements.store', $product) }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Type *</label>
                            <select name="type" id="movement_type" class="form-select" required>
                                <option value="entree">Entrée (approvisionnement)</option>
                                <option value="sortie">Sortie (vente / consommation)</option>
                                <option value="ajustement">Ajustement (correction inventaire)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantité *</label>
                            <input type="number" step="0.01" name="quantity" class="form-control" required>
                            <small class="text-muted" id="quantity-help">Positive pour une entrée.</small>
                        </div>
                        <div class="mb-3" id="unit-cost-group">
                            <label class="form-label">Coût unitaire</label>
                            <input type="number" step="0.01" min="0" name="unit_cost" class="form-control">
                            <small class="text-muted">Requis pour une entrée (recalcule le CUMP).</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date *</label>
                            <input type="date" name="movement_date" class="form-control" required value="{{ now()->toDateString() }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Motif</label>
                            <input type="text" name="reason" class="form-control" placeholder="Réception fournisseur, vente, inventaire…">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Enregistrer</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Historique des mouvements</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th class="text-end">Quantité</th>
                                    <th class="text-end">Coût unitaire</th>
                                    <th class="text-end">Solde après</th>
                                    <th>Motif</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($product->movements as $movement)
                                    <tr>
                                        <td>{{ $movement->movement_date->format('d/m/Y') }}</td>
                                        <td>
                                            @php
                                                $badge = ['entree' => 'success', 'sortie' => 'danger', 'ajustement' => 'secondary'][$movement->type] ?? 'secondary';
                                            @endphp
                                            <span class="badge bg-{{ $badge }}">{{ ucfirst($movement->type) }}</span>
                                        </td>
                                        <td class="text-end">{{ $movement->quantity > 0 ? '+' : '' }}{{ number_format((float) $movement->quantity, 2, ',', ' ') }}</td>
                                        <td class="text-end">{{ $movement->unit_cost !== null ? number_format((float) $movement->unit_cost, 0, ',', ' ') : '—' }}</td>
                                        <td class="text-end">{{ number_format((float) $movement->quantity_after, 2, ',', ' ') }}</td>
                                        <td>{{ $movement->reason ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">Aucun mouvement pour l'instant.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const typeSelect = document.getElementById('movement_type');
    const help = document.getElementById('quantity-help');
    const unitCostGroup = document.getElementById('unit-cost-group');

    function update() {
        if (typeSelect.value === 'sortie') {
            help.textContent = 'Positive : quantité retirée du stock.';
            unitCostGroup.style.display = 'none';
        } else if (typeSelect.value === 'ajustement') {
            help.textContent = 'Positive pour augmenter le stock, négative pour le corriger à la baisse.';
            unitCostGroup.style.display = '';
        } else {
            help.textContent = 'Positive pour une entrée.';
            unitCostGroup.style.display = '';
        }
    }

    typeSelect.addEventListener('change', update);
    update();
})();
</script>
@endsection
