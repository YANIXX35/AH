@extends('layouts.app')

@section('title', 'Nouvelle écriture ERPNext | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="container-fluid py-4">
    <h1 class="h3 mb-3">Nouvelle écriture ERPNext</h1>

    @if (isset($result) && $result)
        <div class="alert alert-success">
            Écriture créée et soumise : <strong>{{ $result['name'] ?? '' }}</strong> (statut : {{ $result['docstatus'] ?? '' }})
        </div>
    @endif

    @if (isset($error) && $error)
        <div class="alert alert-danger">{{ $error }}</div>
    @endif

    <form method="GET" action="{{ route('admin.erpnext-accounting-test.create-entry') }}" class="mb-3">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="user_id_selector">PME</label>
                <select class="form-select" id="user_id_selector" name="user_id" onchange="this.form.submit()">
                    <option value="">— Choisir une PME —</option>
                    @foreach ($pmes as $pme)
                        <option value="{{ $pme->id }}" {{ (string) $selectedUserId === (string) $pme->id ? 'selected' : '' }}>{{ $pme->company_name ?? $pme->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>

    @if (! empty($accounts))
        <form method="POST" action="{{ route('admin.erpnext-accounting-test.store-entry') }}">
            @csrf
            <input type="hidden" name="user_id" value="{{ $selectedUserId }}">

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label" for="voucher_type">Type d'écriture</label>
                    <select class="form-select" id="voucher_type" name="voucher_type" required>
                        <option value="Journal Entry">Journal Entry</option>
                        <option value="Bank Entry">Bank Entry</option>
                        <option value="Cash Entry">Cash Entry</option>
                        <option value="Contra Entry">Contra Entry</option>
                        <option value="Opening Entry">Opening Entry</option>
                        <option value="Depreciation Entry">Depreciation Entry</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="posting_date">Date de saisie</label>
                    <input type="date" class="form-control" id="posting_date" name="posting_date" value="{{ now()->toDateString() }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="reference_number">Numéro de référence</label>
                    <input type="text" class="form-control" id="reference_number" name="reference_number">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="reference_date">Date de référence</label>
                    <input type="date" class="form-control" id="reference_date" name="reference_date">
                </div>
            </div>

            <label class="form-label">Lignes comptables</label>
            <div id="lines-container">
                <div class="row g-2 mb-2 line-row">
                    <div class="col-6">
                        <select class="form-select" name="lines[0][account_number]" required>
                            <option value="">— Compte —</option>
                            @foreach ($accounts as $account)
                                @if (! $account['is_group'])
                                    <option value="{{ \Illuminate\Support\Str::before($account['account_name'], '-') }}">{{ $account['account_name'] }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-3"><input type="number" step="0.01" min="0" class="form-control" name="lines[0][debit]" placeholder="Débit" value="0" required></div>
                    <div class="col-3"><input type="number" step="0.01" min="0" class="form-control" name="lines[0][credit]" placeholder="Crédit" value="0" required></div>
                </div>
                <div class="row g-2 mb-2 line-row">
                    <div class="col-6">
                        <select class="form-select" name="lines[1][account_number]" required>
                            <option value="">— Compte —</option>
                            @foreach ($accounts as $account)
                                @if (! $account['is_group'])
                                    <option value="{{ \Illuminate\Support\Str::before($account['account_name'], '-') }}">{{ $account['account_name'] }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-3"><input type="number" step="0.01" min="0" class="form-control" name="lines[1][debit]" placeholder="Débit" value="0" required></div>
                    <div class="col-3"><input type="number" step="0.01" min="0" class="form-control" name="lines[1][credit]" placeholder="Crédit" value="0" required></div>
                </div>
            </div>
            <button type="button" id="add-line" class="btn btn-outline-secondary btn-sm mb-3">+ Ajouter une ligne</button>

            <div class="row g-3 mb-3">
                <div class="col-md-3"><strong>Total Débit : </strong><span id="total-debit">0</span></div>
                <div class="col-md-3"><strong>Total Crédit : </strong><span id="total-credit">0</span></div>
            </div>

            <button type="submit" class="btn btn-primary">Créer et soumettre l'écriture</button>
        </form>

        <script>
            (function () {
                var accountsOptionsHtml = document.querySelector('#lines-container .line-row select').innerHTML;
                var container = document.getElementById('lines-container');
                var addButton = document.getElementById('add-line');
                var index = 2;

                addButton.addEventListener('click', function () {
                    var row = document.createElement('div');
                    row.className = 'row g-2 mb-2 line-row';
                    row.innerHTML =
                        '<div class="col-6"><select class="form-select" name="lines[' + index + '][account_number]" required>' + accountsOptionsHtml + '</select></div>' +
                        '<div class="col-3"><input type="number" step="0.01" min="0" class="form-control" name="lines[' + index + '][debit]" placeholder="Débit" value="0" required></div>' +
                        '<div class="col-3"><input type="number" step="0.01" min="0" class="form-control" name="lines[' + index + '][credit]" placeholder="Crédit" value="0" required></div>';
                    container.appendChild(row);
                    index += 1;
                    recomputeTotals();
                });

                function recomputeTotals() {
                    var totalDebit = 0;
                    var totalCredit = 0;
                    document.querySelectorAll('input[name*="[debit]"]').forEach(function (input) {
                        totalDebit += parseFloat(input.value) || 0;
                    });
                    document.querySelectorAll('input[name*="[credit]"]').forEach(function (input) {
                        totalCredit += parseFloat(input.value) || 0;
                    });
                    document.getElementById('total-debit').textContent = totalDebit.toFixed(2);
                    document.getElementById('total-credit').textContent = totalCredit.toFixed(2);
                }

                container.addEventListener('input', function (event) {
                    if (event.target.matches('input[name*="[debit]"], input[name*="[credit]"]')) {
                        recomputeTotals();
                    }
                });

                recomputeTotals();
            })();
        </script>
    @elseif ($selectedUserId)
        <div class="alert alert-warning">Aucun compte trouvé pour cette PME (vérifiez qu'elle est bien provisionnée sur ERPNext).</div>
    @endif
</div>
@endsection
