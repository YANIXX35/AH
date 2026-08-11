@extends('layouts.app')

@section('title', 'Dashboard Commercial | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@push('styles')
<style>
    .admin-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    }
    .admin-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .admin-badge-success { background: #dcfce7; color: #15803d; }
    .admin-badge-danger { background: #fee2e2; color: #991b1b; }
    .admin-badge-info { background: #dbeafe; color: #1d4ed8; }
    .admin-badge-warning { background: #ffedd5; color: #c2410c; }
    .prospection-status-badge { border-radius: 999px; padding: 4px 10px; font-size: .7rem; font-weight: 700; }
    .status-submitted { background:#dbeafe; color:#1d4ed8; }
    .status-under_review { background:#fef3c7; color:#92400e; }
    .status-approved { background:#dcfce7; color:#15803d; }
    .status-needs_revision { background:#ffedd5; color:#c2410c; }
    .status-rejected { background:#fee2e2; color:#b91c1c; }
</style>
@endpush

@section('content')
<div class="mb-4">
    <nav aria-label="Fil d'Ariane admin" class="mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Administration</a></li>
            <li class="breadcrumb-item active" aria-current="page">Dashboard Commercial</li>
        </ol>
    </nav>
    <h1 class="h3 mb-1 fw-bold text-dark">Pilotage de <strong>l'équipe commerciale</strong></h1>
    <p class="text-muted mb-0">Performance, commissions (lecture seule), prospections, pipeline et retours de tous les commerciaux.</p>
</div>

@include('partials.commercial-team-overview', ['prospectionsIndexRoute' => 'admin.prospections.index'])
@endsection
