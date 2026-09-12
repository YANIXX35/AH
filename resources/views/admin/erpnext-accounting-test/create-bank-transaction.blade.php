@extends('layouts.app')

@section('title', 'Nouvelle ligne de relevé ERPNext | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="container-fluid py-4">
    <h1 class="h3 mb-3">Nouvelle ligne de relevé bancaire ERPNext</h1>

    @if (isset($result) && $result)
        <div class="alert alert-success">
            Transaction créée : <strong>{{ $result['name'] ?? '' }}</strong> (statut : {{ $result['status'] ?? '' }})
        </div>
    @endif

    @if (isset($error) && $error)
        <div class="alert alert-danger">{{ $error }}</div>
    @endif

    <form method="GET" action="{{ route('admin.erpnext-accounting-test.create-bank-transaction') }}" class="mb-3">
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
        <form method="POST" action="{{ route('admin.erpnext-accounting-test.store-bank-transaction') }}">
            @csrf
            <input type="hidden" name="user_id" value="{{ $selectedUserId }}">

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label" for="account_number">Compte de trésorerie</label>
                    <select class="form-select" id="account_number" name="account_number" required>
                        <option value="">— Compte —</option>
                        @foreach ($accounts as $account)
                            @if (! $account['is_group'] && $account['root_type'] === 'Asset')
                                <option value="{{ \Illuminate\Support\Str::before($account['account_name'], '-') }}">{{ $account['account_name'] }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="date">Date</label>
                    <input type="date" class="form-control" id="date" name="date" value="{{ now()->toDateString() }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="amount">Montant</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" id="amount" name="amount" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="direction">Sens</label>
                    <select class="form-select" id="direction" name="direction" required>
                        <option value="deposit">Dépôt</option>
                        <option value="withdrawal">Retrait</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="reference_number">Référence</label>
                    <input type="text" class="form-control" id="reference_number" name="reference_number">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="description">Description</label>
                <input type="text" class="form-control" id="description" name="description" required>
            </div>

            <button type="submit" class="btn btn-primary">Créer la ligne de relevé</button>
        </form>
    @elseif ($selectedUserId)
        <div class="alert alert-warning">Aucun compte trouvé pour cette PME (vérifiez qu'elle est bien provisionnée sur ERPNext).</div>
    @endif
</div>
@endsection
