@extends('layouts.app')

@section('title', 'Stock ERPNext Test | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">{{ $pme->company_name ?? $pme->name }} — Stock</h1>
        <a href="{{ route('admin.erpnext-stock-test.index') }}">← Retour</a>
    </div>

    @if ($stockLevelsError)
        <div class="alert alert-danger">{{ $stockLevelsError }}</div>
    @else
        <table class="table table-sm">
            <thead><tr><th>Article</th><th>Entrepôt</th><th>Quantité en stock</th></tr></thead>
            <tbody>
                @forelse ($stockLevels as $row)
                    <tr>
                        <td>{{ $row['item_code'] }}</td>
                        <td>{{ $row['warehouse'] }}</td>
                        <td>{{ number_format($row['actual_qty'], 2, ',', ' ') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">Aucun article en stock pour cette PME.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif
</div>
@endsection
