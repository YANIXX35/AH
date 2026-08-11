@extends('layouts.app')

@section('title', 'Pipeline de prospects | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="mb-4">
    <nav aria-label="Fil d'Ariane admin" class="mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Administration</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.commercial-dashboard') }}">Dashboard Commercial</a></li>
            <li class="breadcrumb-item active" aria-current="page">Pipeline de prospects</li>
        </ol>
    </nav>
    <h1 class="h3 mb-1"><strong>Pipeline</strong> de prospects</h1>
    <p class="text-muted mb-0">Tous les prospects de l'équipe commerciale.</p>
</div>

@include('partials.commercial-prospects-list', ['showRoute' => 'admin.commercial-dashboard.show'])
@endsection
