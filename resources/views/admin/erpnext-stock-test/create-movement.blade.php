@extends('layouts.app')

@section('title', 'Nouveau mouvement de stock ERPNext | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="container-fluid py-4">
    <h1 class="h3 mb-3">Nouveau mouvement de stock ERPNext</h1>

    @if (isset($result) && $result)
        <div class="alert alert-success">
            Mouvement enregistré : <strong>{{ $result['name'] ?? '' }}</strong> (docstatus : {{ $result['docstatus'] ?? '' }})
        </div>
    @endif

    @if (isset($error) && $error)
        <div class="alert alert-danger">{{ $error }}</div>
    @endif

    <form method="GET" action="{{ route('admin.erpnext-stock-test.create-movement') }}" class="mb-3">
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

    @if ($selectedUserId)
        <form method="POST" action="{{ route('admin.erpnext-stock-test.store-movement') }}">
            @csrf
            <input type="hidden" name="user_id" value="{{ $selectedUserId }}">

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label" for="item_description">Article</label>
                    <input type="text" class="form-control" id="item_description" name="item_description" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="movement_type">Sens</label>
                    <select class="form-select" id="movement_type" name="movement_type" required>
                        <option value="in">Entrée</option>
                        <option value="out">Sortie</option>
                        <option value="ajustement">Ajustement (quantité absolue)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="quantity">Quantité</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" id="quantity" name="quantity" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="unit_rate">Coût unitaire</label>
                    <input type="number" step="0.01" min="0" class="form-control" id="unit_rate" name="unit_rate">
                    <div class="form-text">Non utilisé pour un ajustement.</div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Enregistrer le mouvement</button>
        </form>
    @endif
</div>
@endsection
