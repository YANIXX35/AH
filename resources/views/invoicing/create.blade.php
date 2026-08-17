@extends('layouts.app')

@section('title', 'Nouvelle facture | Sitiame Capital')
@section('page_title', 'Nouvelle facture')

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h3 mb-0"><strong>Nouvelle</strong> facture</h2>
        <a href="{{ route('invoicing.index') }}" class="btn btn-outline-secondary btn-sm">Retour</a>
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

    <form action="{{ route('invoicing.store') }}" method="POST" id="invoice-form">
        @csrf

        <div class="row g-3">
            <div class="col-12 col-xl-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3">Client</h5>
                        <div class="mb-3">
                            <label class="form-label">Nom / raison sociale *</label>
                            <input type="text" name="client_name" class="form-control" required value="{{ old('client_name') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact (téléphone / email)</label>
                            <input type="text" name="client_contact" class="form-control" value="{{ old('client_contact') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Adresse</label>
                            <input type="text" name="client_address" class="form-control" value="{{ old('client_address') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">NIF / identifiant fiscal</label>
                            <input type="text" name="client_tax_id" class="form-control" value="{{ old('client_tax_id') }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3">Détails</h5>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label">Date d'émission *</label>
                                <input type="date" name="issue_date" class="form-control" required value="{{ old('issue_date', now()->toDateString()) }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Échéance *</label>
                                <input type="date" name="due_date" class="form-control" required value="{{ old('due_date', now()->addDays(30)->toDateString()) }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label">TVA (%)</label>
                                <input type="number" step="0.01" min="0" max="100" name="tax_rate" id="tax_rate" class="form-control" value="{{ old('tax_rate', 0) }}">
                            </div>
                        </div>
                        <div class="mb-0 mt-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Lignes de facturation</h5>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="add-line">+ Ajouter une ligne</button>
                        </div>

                        <div class="table-responsive">
                            <table class="table" id="items-table">
                                <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th style="width: 120px;">Quantité</th>
                                        <th style="width: 160px;">Prix unitaire</th>
                                        <th style="width: 160px;" class="text-end">Total ligne</th>
                                        <th style="width: 40px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="items-body"></tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end">
                            <table class="table table-sm" style="width: 320px;">
                                <tr><th>Sous-total</th><td class="text-end" id="summary-subtotal">0</td></tr>
                                <tr><th>TVA</th><td class="text-end" id="summary-tax">0</td></tr>
                                <tr><th>Total</th><td class="text-end fw-bold" id="summary-total">0</td></tr>
                            </table>
                        </div>

                        <button type="submit" class="btn btn-primary">Créer la facture</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<template id="item-row-template">
    <tr class="item-row">
        <td><input type="text" name="items[__INDEX__][description]" class="form-control" required></td>
        <td><input type="number" step="0.01" min="0.01" name="items[__INDEX__][quantity]" class="form-control item-quantity" value="1" required></td>
        <td><input type="number" step="0.01" min="0" name="items[__INDEX__][unit_price]" class="form-control item-price" value="0" required></td>
        <td class="text-end item-line-total">0</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-line">✕</button></td>
    </tr>
</template>

<script>
(function () {
    const body = document.getElementById('items-body');
    const template = document.getElementById('item-row-template');
    const taxRateInput = document.getElementById('tax_rate');
    let index = 0;

    function formatNumber(value) {
        return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 }).format(value || 0);
    }

    function recalculate() {
        let subtotal = 0;
        body.querySelectorAll('.item-row').forEach((row) => {
            const qty = parseFloat(row.querySelector('.item-quantity').value) || 0;
            const price = parseFloat(row.querySelector('.item-price').value) || 0;
            const lineTotal = qty * price;
            row.querySelector('.item-line-total').textContent = formatNumber(lineTotal);
            subtotal += lineTotal;
        });
        const taxRate = parseFloat(taxRateInput.value) || 0;
        const taxAmount = subtotal * taxRate / 100;
        document.getElementById('summary-subtotal').textContent = formatNumber(subtotal);
        document.getElementById('summary-tax').textContent = formatNumber(taxAmount);
        document.getElementById('summary-total').textContent = formatNumber(subtotal + taxAmount);
    }

    function addRow() {
        const html = template.innerHTML.replaceAll('__INDEX__', String(index));
        const wrapper = document.createElement('tbody');
        wrapper.innerHTML = html;
        const row = wrapper.firstElementChild;
        body.appendChild(row);
        index++;

        row.querySelectorAll('.item-quantity, .item-price').forEach((input) => {
            input.addEventListener('input', recalculate);
        });
        row.querySelector('.remove-line').addEventListener('click', () => {
            row.remove();
            recalculate();
        });
    }

    document.getElementById('add-line').addEventListener('click', addRow);
    taxRateInput.addEventListener('input', recalculate);

    addRow();
})();
</script>
@endsection
