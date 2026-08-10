@php
    $pageTitle = $title ?? 'SITIAME CAPITAL';
    $pageDescription = $description ?? '';
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/png" href="{{ asset('images/sitiam.png') }}">
<link rel="apple-touch-icon" href="{{ asset('images/sitiam.png') }}">
@if($pageDescription !== '')
    <meta name="description" content="{{ $pageDescription }}">
@endif
<title>{{ $pageTitle }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
@vite('resources/css/app.css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body { font-family: 'DM Sans', system-ui, sans-serif; }
</style>
<style>
    html { scroll-behavior: smooth; }
    @media (prefers-reduced-motion: reduce) {
        html { scroll-behavior: auto; }
    }
</style>
