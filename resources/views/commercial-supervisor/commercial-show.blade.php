@extends('layouts.app')

@section('title', $commercial->name . ' | Supervision Commerciale | ' . config('app.name'))
@section('page_title', 'Supervision Commerciale')

@push('styles')
<style>
    .admin-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03); }
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
    <nav aria-label="Fil d'Ariane" class="mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('commercial-supervisor.dashboard') }}">Supervision Commerciale</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $commercial->name }}</li>
        </ol>
    </nav>
    <h1 class="h3 mb-1 fw-bold text-dark">{{ $commercial->name }}</h1>
    <p class="text-muted mb-0">{{ $commercial->email }}</p>
</div>

@include('partials.commercial-detail-overview')
@endsection
