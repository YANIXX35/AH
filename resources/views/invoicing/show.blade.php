@extends('layouts.app')

@section('title', 'Facture '.$invoice->invoice_number.' | Sitiame Capitale')
@section('page_title', 'Facture '.$invoice->invoice_number)

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h3 mb-1"><strong>Facture</strong> {{ $invoice->invoice_number }}</h2>
            <p class="text-muted small mb-0">{{ $invoice->client_name }} · Émise le {{ $invoice->issue_date->format('d/m/Y') }} · Échéance {{ $invoice->due_date->format('d/m/Y') }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('invoicing.pdf', $invoice) }}" class="btn btn-outline-secondary btn-sm">Télécharger PDF</a>
            <a href="{{ route('invoicing.index') }}" class="btn btn-outline-secondary btn-sm">Retour</a>
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

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="mb-3">Lignes</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="text-end">Qté</th>
                                    <th class="text-end">Prix unitaire</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoice->items as $item)
                                    <tr>
                                        <td>{{ $item->description }}</td>
                                        <td class="text-end">{{ $item->quantity }}</td>
                                        <td class="text-end">{{ number_format((float) $item->unit_price, 0, ',', ' ') }}</td>
                                        <td class="text-end">{{ number_format((float) $item->line_total, 0, ',', ' ') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end">
                        <table class="table table-sm" style="width: 320px;">
                            <tr><th>Sous-total</th><td class="text-end">{{ number_format((float) $invoice->subtotal, 0, ',', ' ') }} {{ $invoice->currency }}</td></tr>
                            <tr><th>TVA ({{ $invoice->tax_rate }}%)</th><td class="text-end">{{ number_format((float) $invoice->tax_amount, 0, ',', ' ') }} {{ $invoice->currency }}</td></tr>
                            <tr><th>Total</th><td class="text-end fw-bold">{{ number_format((float) $invoice->total_amount, 0, ',', ' ') }} {{ $invoice->currency }}</td></tr>
                            <tr><th>Réglé</th><td class="text-end text-success">{{ number_format((float) $invoice->amount_paid, 0, ',', ' ') }} {{ $invoice->currency }}</td></tr>
                            <tr><th>Solde dû</th><td class="text-end fw-bold {{ $invoice->balanceDue() > 0 ? 'text-danger' : '' }}">{{ number_format($invoice->balanceDue(), 0, ',', ' ') }} {{ $invoice->currency }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Encaissements</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th class="text-end">Montant</th>
                                    <th>Méthode</th>
                                    <th>Référence</th>
                                    <th>Trésorerie liée</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($invoice->payments as $payment)
                                    <tr>
                                        <td>{{ $payment->paid_at->format('d/m/Y') }}</td>
                                        <td class="text-end">{{ number_format((float) $payment->amount, 0, ',', ' ') }} {{ $invoice->currency }}</td>
                                        <td>{{ $payment->method ?? '—' }}</td>
                                        <td>{{ $payment->reference ?? '—' }}</td>
                                        <td>
                                            @if ($payment->treasuryTransaction)
                                                <a href="{{ route('treasury.edit', $payment->treasuryTransaction) }}">{{ $payment->treasuryTransaction->mobile_method ? ucfirst($payment->treasuryTransaction->mobile_method) : 'Voir' }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-3">Aucun encaissement pour l'instant.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            @if ($invoice->status !== 'paid' && $invoice->status !== 'cancelled')
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="mb-3">Enregistrer un encaissement</h5>
                        <form action="{{ route('invoicing.payments.store', $invoice) }}" method="POST" id="payment-form">
                            @csrf

                            @if ($unlinkedTreasuryTransactions->count() > 0)
                                <div class="mb-3">
                                    <label class="form-label">Mouvement de trésorerie existant (optionnel)</label>
                                    <select name="treasury_transaction_id" id="treasury_transaction_id" class="form-select">
                                        <option value="">— Encaissement manuel —</option>
                                        @foreach ($unlinkedTreasuryTransactions as $tx)
                                            <option value="{{ $tx->id }}" data-amount="{{ $tx->amount }}" data-date="{{ $tx->transaction_date->format('Y-m-d') }}">
                                                {{ $tx->transaction_date->format('d/m/Y') }} — {{ number_format((float) $tx->amount, 0, ',', ' ') }} FCFA — {{ $tx->description }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Inclut les mouvements confirmés par le rapprochement Mobile Money.</small>
                                </div>
                            @endif

                            <div class="mb-3">
                                <label class="form-label">Montant *</label>
                                <input type="number" step="0.01" min="0.01" max="{{ $invoice->balanceDue() }}" name="amount" id="payment_amount" class="form-control" required value="{{ old('amount', $invoice->balanceDue()) }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Date *</label>
                                <input type="date" name="paid_at" id="payment_date" class="form-control" required value="{{ old('paid_at', now()->toDateString()) }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Méthode</label>
                                <select name="method" id="payment_method" class="form-select">
                                    <option value="">— Sélectionner —</option>
                                    <option value="Wave">Wave</option>
                                    <option value="Orange Money">Orange Money</option>
                                    <option value="MTN Money">MTN Money</option>
                                    <option value="Moov Money">Moov Money</option>
                                    <option value="Virement bancaire">Virement bancaire</option>
                                    <option value="Chèque">Chèque</option>
                                    <option value="Espèces">Espèces</option>
                                    <option value="autre">Autre…</option>
                                </select>
                                <input type="text" id="payment_method_other" class="form-control mt-2 d-none" placeholder="Préciser la méthode">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Référence</label>
                                <input type="text" name="reference" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Compte de trésorerie (pour l'écriture comptable)</label>
                                <select name="treasury_account_code" class="form-select">
                                    <option value="">— Ne pas générer d'écriture —</option>
                                    @foreach ($treasuryAccounts as $account)
                                        <option value="{{ $account->prefix }}">{{ $account->prefix }} — {{ $account->label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <button type="submit" class="btn btn-success w-100">Enregistrer l'encaissement</button>
                        </form>
                    </div>
                </div>
            @endif

            @if ($invoice->status !== 'cancelled' && (float) $invoice->amount_paid <= 0)
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3">Annuler la facture</h5>
                        <form action="{{ route('invoicing.cancel', $invoice) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Motif *</label>
                                <input type="text" name="reason" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('Annuler cette facture ? Le numéro reste réservé (conformité e-invoicing).');">Annuler la facture</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
(function () {
    const select = document.getElementById('treasury_transaction_id');
    if (!select) return;
    select.addEventListener('change', function () {
        const option = select.options[select.selectedIndex];
        const amount = option.getAttribute('data-amount');
        const date = option.getAttribute('data-date');
        if (amount) document.getElementById('payment_amount').value = amount;
        if (date) document.getElementById('payment_date').value = date;
    });
})();

(function () {
    const methodSelect = document.getElementById('payment_method');
    const otherInput = document.getElementById('payment_method_other');
    const form = document.getElementById('payment-form');
    if (!methodSelect || !otherInput || !form) return;

    methodSelect.addEventListener('change', function () {
        otherInput.classList.toggle('d-none', methodSelect.value !== 'autre');
    });

    form.addEventListener('submit', function () {
        if (methodSelect.value === 'autre') {
            const custom = otherInput.value.trim();
            const option = methodSelect.options[methodSelect.selectedIndex];
            option.value = custom;
        }
    });
})();
</script>
@endsection
