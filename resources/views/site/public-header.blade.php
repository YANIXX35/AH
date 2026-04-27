{{-- Navigation publique : menus déroulants (desktop) + menu mobile responsive --}}
@php
    $home = route('home');
    $isHome = request()->routeIs('home');
    $linkBase = 'rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-200';
    $linkInactive = 'text-slate-600 hover:bg-slate-50/90 hover:text-brand-700';
    $linkActive = 'bg-brand-50 text-brand-800';
@endphp
<header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 shadow-soft backdrop-blur-md">
    <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-2 text-xs text-slate-600 sm:flex-row sm:items-center sm:justify-between sm:gap-3 sm:py-3 sm:text-sm sm:px-6 lg:px-8">
        <div class="flex items-center gap-2">
            <i class="fa fa-calendar shrink-0 text-brand-600"></i>
            <span class="leading-tight">Lundi - Vendredi : 8:00 à 17:00</span>
        </div>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
            <a href="tel:+2252724523043" class="hover:text-blue-700"><i class="fa fa-phone mr-1"></i> +225 2724523043</a>
            <a href="tel:+2250709161381" class="hover:text-blue-700"><i class="fa fa-phone mr-1"></i> +225 0709161381</a>
            <span class="hidden min-[400px]:inline"><i class="fa fa-envelope mr-1"></i> contact@sitiame-capital.com</span>
        </div>
    </div>

    <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
        <a href="{{ $home }}" class="flex min-w-0 shrink items-center gap-2">
            <img src="{{ asset('images/sitiam.png') }}" alt="Logo Sitiame" class="h-10 w-auto sm:h-12 lg:h-14" />
        </a>

        {{-- Navigation desktop (lg+) : menus déroulants --}}
        <nav class="hidden items-center gap-1 lg:flex" aria-label="Navigation principale">
            {{-- Découvrir --}}
            <div class="group relative">
                <button type="button" class="{{ $linkBase }} {{ $linkInactive }} inline-flex items-center gap-1" aria-expanded="false" aria-haspopup="true">
                    Découvrir
                    <i class="fa fa-chevron-down text-[10px] opacity-70"></i>
                </button>
                <div class="invisible absolute left-0 top-full z-50 mt-1 min-w-[220px] rounded-xl border border-slate-100 bg-white py-2 opacity-0 shadow-soft-lg ring-1 ring-slate-900/5 transition-all duration-200 group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
                    <a href="{{ $home }}" class="{{ $linkBase }} {{ request()->routeIs('home') ? $linkActive : $linkInactive }} block w-full text-left">Accueil</a>
                    <a href="{{ route('about-us') }}" class="{{ $linkBase }} {{ request()->routeIs('about-us') ? $linkActive : $linkInactive }} block w-full text-left">À propos de nous</a>
                    <a href="{{ $isHome ? '#expertise' : $home.'#expertise' }}" class="{{ $linkBase }} {{ $linkInactive }} block w-full text-left">Nos services</a>
                    <a href="{{ $isHome ? '#formulaires' : $home.'#formulaires' }}" class="{{ $linkBase }} {{ $linkInactive }} block w-full text-left">Nos formulaires</a>
                </div>
            </div>

            {{-- Plateforme --}}
            <div class="group relative">
                <button type="button" class="{{ $linkBase }} {{ $linkInactive }} inline-flex items-center gap-1">
                    Plateforme
                    <i class="fa fa-chevron-down text-[10px] opacity-70"></i>
                </button>
                <div class="invisible absolute left-0 top-full z-50 mt-1 min-w-[220px] rounded-xl border border-slate-100 bg-white py-2 opacity-0 shadow-soft-lg ring-1 ring-slate-900/5 transition-all duration-200 group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
                    <a href="{{ route('pricing') }}" class="{{ $linkBase }} {{ request()->routeIs('pricing') ? $linkActive : $linkInactive }} block w-full text-left">Tarifs</a>
                    <a href="{{ route('documentation') }}" class="{{ $linkBase }} {{ request()->routeIs('documentation') ? $linkActive : $linkInactive }} block w-full text-left">Documentation</a>
                </div>
            </div>

            <a href="{{ $isHome ? '#contact' : $home.'#contact' }}" class="{{ $linkBase }} {{ $linkInactive }}">Nous contacter</a>

            <a href="{{ route('login') }}" class="ml-1 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-800 hover:shadow-md">Mon Espace</a>
        </nav>

        {{-- Bouton menu mobile --}}
        <button type="button" id="public-nav-toggle" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 lg:hidden" aria-controls="public-mobile-nav" aria-expanded="false" aria-label="Ouvrir le menu">
            <i class="fa fa-bars text-lg" id="public-nav-icon-open"></i>
            <i class="fa fa-times hidden text-lg" id="public-nav-icon-close"></i>
        </button>
    </div>

    {{-- Panneau mobile --}}
    <div id="public-mobile-nav" class="hidden border-t border-slate-100 bg-white lg:hidden">
        <nav class="mx-auto max-w-7xl space-y-4 px-4 py-4 sm:px-6" aria-label="Navigation mobile">
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Découvrir</p>
                <div class="flex flex-col gap-1">
                    <a href="{{ $home }}" class="rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs('home') ? 'bg-brand-50 text-brand-800' : 'text-slate-700 hover:bg-slate-50' }}">Accueil</a>
                    <a href="{{ route('about-us') }}" class="rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs('about-us') ? 'bg-brand-50 text-brand-800' : 'text-slate-700 hover:bg-slate-50' }}">À propos de nous</a>
                    <a href="{{ $isHome ? '#expertise' : $home.'#expertise' }}" class="rounded-lg px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Nos services</a>
                    <a href="{{ $isHome ? '#formulaires' : $home.'#formulaires' }}" class="rounded-lg px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Nos formulaires</a>
                </div>
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Plateforme</p>
                <div class="flex flex-col gap-1">
                    <a href="{{ route('pricing') }}" class="rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs('pricing') ? 'bg-brand-50 text-brand-800' : 'text-slate-700 hover:bg-slate-50' }}">Tarifs</a>
                    <a href="{{ route('documentation') }}" class="rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs('documentation') ? 'bg-brand-50 text-brand-800' : 'text-slate-700 hover:bg-slate-50' }}">Documentation</a>
                </div>
            </div>
            <a href="{{ $isHome ? '#contact' : $home.'#contact' }}" class="block rounded-lg px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Nous contacter</a>
            <a href="{{ route('login') }}" class="block rounded-lg bg-brand-700 px-4 py-3 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-brand-800">Mon Espace</a>
        </nav>
    </div>
</header>

<script>
(function () {
    var btn = document.getElementById('public-nav-toggle');
    var panel = document.getElementById('public-mobile-nav');
    var iconOpen = document.getElementById('public-nav-icon-open');
    var iconClose = document.getElementById('public-nav-icon-close');
    if (!btn || !panel) return;
    btn.addEventListener('click', function () {
        panel.classList.toggle('hidden');
        var expanded = !panel.classList.contains('hidden');
        btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        if (iconOpen && iconClose) {
            iconOpen.classList.toggle('hidden', expanded);
            iconClose.classList.toggle('hidden', !expanded);
        }
    });
})();
</script>
