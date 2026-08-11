@extends('layouts.app')

@section('title', 'Pipeline de prospects | Supervision Commerciale | ' . config('app.name'))
@section('page_title', 'Supervision Commerciale')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1"><strong>Pipeline</strong> de prospects</h1>
    <p class="text-muted mb-0">Tous les prospects de l'équipe commerciale — consultation seule.</p>
</div>

@include('partials.commercial-prospects-list', ['showRoute' => 'commercial-supervisor.commercial.show'])
@endsection
