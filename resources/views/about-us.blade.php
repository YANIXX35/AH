<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    @include('site.public-head', [
        'title' => 'À propos de nous | SITIAME CAPITAL',
        'description' => 'Présentation de SITIAME CAPITAL.',
    ])
</head>
<body class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50 font-sans text-slate-800 antialiased">
    @include('site.public-header')

    <main>
        <section class="relative overflow-hidden bg-slate-950 text-white">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-brand-950/70 to-slate-900" aria-hidden="true"></div>
            <img src="https://sitiame-capital.com/assets/images/slider/slide1.jpg" alt="" class="absolute inset-0 h-full w-full object-cover opacity-30 mix-blend-overlay">
            <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-300/90">À propos</p>
                <h1 class="mt-4 max-w-4xl text-3xl font-semibold tracking-tight sm:text-4xl">Qui sommes-nous</h1>
                <p class="mt-5 max-w-3xl text-lg leading-relaxed text-slate-200">
                    Sitiame Capital est votre partenaire de confiance en matière de conseils aux PME et d'investissement en capital.
                </p>
            </div>
        </section>

        <section class="mx-auto max-w-5xl px-4 py-14 sm:px-6 lg:px-8">
            <div class="rounded-3xl border border-slate-100 bg-white p-8 shadow-soft-lg sm:p-10">
                <article>
                    <h2 class="text-xl font-semibold tracking-tight text-slate-900">Notre mission</h2>
                    <p class="mt-3 leading-relaxed text-slate-600">Accompagner les entreprises et les porteurs de projets dans leurs stratégies de croissance, de financement et de structuration.</p>
                </article>
            </div>
        </section>
    </main>

    <footer class="border-t border-white/10 bg-slate-950 py-12 text-slate-300">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-3 lg:px-8">
            <div>
                <img src="https://sitiame-capital.com/assets/images/logo.png" alt="Logo Sitiame" class="h-12 w-auto opacity-95 sm:h-14">
                <p class="mt-4 text-sm leading-relaxed text-slate-400">Conseils aux PME et investissement en capital.</p>
            </div>
            <div>
                <h4 class="text-sm font-semibold uppercase tracking-wider text-white">Liens</h4>
                <ul class="mt-4 space-y-2 text-sm">
                    <li><a href="{{ route('documentation') }}" class="text-slate-400 transition hover:text-white">Documentation</a></li>
                    <li><a href="{{ route('pricing') }}" class="text-slate-400 transition hover:text-white">Tarifs</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-sm font-semibold uppercase tracking-wider text-white">Contact</h4>
                <ul class="mt-4 space-y-2 text-sm text-slate-400">
                    <li>contact@sitiame-capital.com</li>
                </ul>
            </div>
        </div>
        <p class="mt-10 border-t border-white/10 pt-8 text-center text-xs text-slate-500">SITIAME CAPITAL © {{ date('Y') }} · Tous droits réservés</p>
    </footer>
</body>
</html>
