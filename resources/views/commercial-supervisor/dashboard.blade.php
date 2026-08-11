@extends('layouts.app')

@section('title', 'Supervision Commerciale | ' . config('app.name'))
@section('page_title', 'Supervision Commerciale')

@push('styles')
<style>
    .admin-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    }
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
    <h1 class="h3 mb-1 fw-bold text-dark">Pilotage de <strong>l'équipe commerciale</strong></h1>
    <p class="text-muted mb-0">Vue d'ensemble en lecture seule : performance, commissions, prospections, pipeline et retours de tous les commerciaux.</p>
</div>

@include('partials.commercial-team-overview', [
    'prospectionsIndexRoute' => 'commercial-supervisor.prospections.index',
    'prospectsIndexRoute' => 'commercial-supervisor.prospects.index',
    'commercialShowRoute' => 'commercial-supervisor.commercial.show',
    'exportRoute' => 'commercial-supervisor.export',
])
@endsection
