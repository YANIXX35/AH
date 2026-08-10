<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mot de passe oublié</title>
    <link rel="icon" type="image/png" href="{{ asset('images/sitiam.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/sitiam.png') }}">
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-slate-50">
<div class="mx-auto max-w-md px-4 py-12">
    <div class="rounded-2xl bg-white p-6 shadow">
        <div class="mb-5 flex justify-center">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2">
                <img src="{{ asset('images/sitiam.png') }}" alt="Logo Sitiame Capital" class="h-12 w-auto">
            </a>
        </div>
        <h1 class="text-2xl font-bold text-slate-900 mb-2">Mot de passe oublié</h1>
        <p class="text-sm text-slate-600 mb-6">Entrez votre e-mail, nous enverrons un code OTP de confirmation.</p>

        @if(session('status'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                <ul class="space-y-1">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="post" action="{{ route('password.otp.send') }}" class="space-y-4">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">E-mail</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200">
            </div>
            <button type="submit" class="w-full rounded-xl bg-orange-500 px-4 py-3 font-semibold text-white hover:bg-orange-600">Envoyer le code OTP</button>
            <a href="{{ route('login') }}" class="block text-center text-sm text-orange-600 hover:text-orange-700">Retour à la connexion</a>
        </form>
    </div>
</div>
</body>
</html>

