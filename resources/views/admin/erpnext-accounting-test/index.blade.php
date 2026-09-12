@extends('layouts.app')

@section('title', 'Comptabilité ERPNext Test | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Comptabilité ERPNext — Test</h1>
        <a href="{{ route('admin.erpnext-accounting-test.create-entry') }}" class="btn btn-outline-primary">+ Nouvelle écriture</a>
    </div>

    <form method="GET" action="{{ route('admin.erpnext-accounting-test.show') }}">
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label" for="user_id">PME (provisionnée sur ERPNext)</label>
                <select class="form-select" id="user_id" name="user_id" required>
                    <option value="">— Choisir une PME —</option>
                    @foreach ($pmes as $pme)
                        <option value="{{ $pme->id }}">{{ $pme->company_name ?? $pme->name }} ({{ $pme->erpnext_company_name }})</option>
                    @endforeach
                </select>
                @if ($pmes->isEmpty())
                    <div class="form-text text-danger">Aucune PME provisionnée trouvée (erpnext_company_name vide pour tout le monde).</div>
                @endif
            </div>
            <div class="col-md-3">
                <label class="form-label" for="from_date">Du</label>
                <input type="date" class="form-control" id="from_date" name="from_date" value="{{ now()->startOfYear()->toDateString() }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="to_date">Au</label>
                <input type="date" class="form-control" id="to_date" name="to_date" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Afficher</button>
            </div>
        </div>
    </form>
</div>
@endsection
