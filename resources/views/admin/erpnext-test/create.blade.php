@extends('layouts.app')

@section('title', 'Nouvelle facture test | ERPNext Test | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="container-fluid py-4">
    <h1 class="h3 mb-3">Nouvelle facture test ERPNext</h1>

    <form method="POST" action="{{ route('admin.erpnext-test.store') }}">
        @csrf

        <div class="mb-3">
            <label class="form-label" for="user_id">PME</label>
            <select class="form-select" id="user_id" name="user_id" required>
                <option value="">— Choisir une PME —</option>
                @foreach ($pmes as $pme)
                    <option value="{{ $pme->id }}">{{ $pme->company_name ?? $pme->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label" for="posting_date">Date de facturation</label>
                <input type="date" class="form-control" id="posting_date" name="posting_date" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="due_date">Date d'échéance</label>
                <input type="date" class="form-control" id="due_date" name="due_date" value="{{ now()->addDays(30)->toDateString() }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="warehouse">Entrepôt</label>
                @if (count($warehouses) > 0)
                    <select class="form-select" id="warehouse" name="warehouse">
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse }}" {{ $warehouse === config('services.erpnext.default_warehouse') ? 'selected' : '' }}>{{ $warehouse }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="text" class="form-control" id="warehouse" name="warehouse" value="{{ config('services.erpnext.default_warehouse') }}" placeholder="Nom exact de l'entrepôt ERPNext">
                @endif
            </div>
            <div class="col-md-3">
                <label class="form-label" for="tax_template">Gabarit de TVA</label>
                @if (count($taxTemplates) > 0)
                    <select class="form-select" id="tax_template" name="tax_template">
                        <option value="">— Aucune TVA —</option>
                        @foreach ($taxTemplates as $template)
                            <option value="{{ $template }}" {{ $template === config('services.erpnext.default_tax_template') ? 'selected' : '' }}>{{ $template }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="text" class="form-control" id="tax_template" name="tax_template" value="{{ config('services.erpnext.default_tax_template') }}" placeholder="Nom exact du gabarit de TVA ERPNext">
                @endif
            </div>
        </div>

        <label class="form-label">Lignes de facture</label>
        <div id="lines-container">
            <div class="row g-2 mb-2 line-row">
                <div class="col-6"><input type="text" class="form-control" name="lines[0][description]" placeholder="Désignation" required></div>
                <div class="col-2"><input type="number" step="0.01" min="0.01" class="form-control" name="lines[0][quantity]" placeholder="Qté" required></div>
                <div class="col-3"><input type="number" step="0.01" min="0" class="form-control" name="lines[0][unit_price]" placeholder="Prix unitaire (XOF)" required></div>
            </div>
        </div>
        <button type="button" id="add-line" class="btn btn-outline-secondary btn-sm mb-3">+ Ajouter une ligne</button>

        <div>
            <button type="submit" class="btn btn-primary">Créer la facture (ERPNext + copie locale)</button>
        </div>
    </form>
</div>

<script>
    (function () {
        var container = document.getElementById('lines-container');
        var addButton = document.getElementById('add-line');
        var index = 1;

        addButton.addEventListener('click', function () {
            var row = document.createElement('div');
            row.className = 'row g-2 mb-2 line-row';
            row.innerHTML =
                '<div class="col-6"><input type="text" class="form-control" name="lines[' + index + '][description]" placeholder="Désignation" required></div>' +
                '<div class="col-2"><input type="number" step="0.01" min="0.01" class="form-control" name="lines[' + index + '][quantity]" placeholder="Qté" required></div>' +
                '<div class="col-3"><input type="number" step="0.01" min="0" class="form-control" name="lines[' + index + '][unit_price]" placeholder="Prix unitaire (XOF)" required></div>';
            container.appendChild(row);
            index += 1;
        });
    })();
</script>
@endsection
