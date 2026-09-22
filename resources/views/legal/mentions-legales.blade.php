<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    @include('site.public-head', [
        'title' => 'SITIAME CAPITAL - Mentions Légales',
        'description' => 'Mentions légales de la plateforme PME360, éditée par SITIAME CAPITAL.',
    ])
</head>
<body class="min-h-screen bg-white font-sans text-slate-800 antialiased">
    @include('site.public-header')

    <main class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-brand-600">Document légal</p>
        <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Mentions Légales</h1>
        <p class="mt-2 text-sm text-slate-500">Dernière mise à jour : {{ \Illuminate\Support\Carbon::now()->translatedFormat('d F Y') }}</p>

        <div class="mt-10 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Les informations d'immatriculation de la société (forme juridique, capital social, numéro RCCM, numéro de compte contribuable / NIF, siège social, nom du représentant légal) doivent être complétées par la direction de SITIAME CAPITAL avant mise en production de cette page. Aucune information de cette nature n'a été inventée ; les champs sont indiqués ci-dessous en attente de confirmation.
        </div>

        <div class="prose prose-slate mt-10 max-w-none prose-h2:mt-10 prose-h2:text-xl prose-h2:font-bold prose-h2:text-slate-900 prose-p:leading-relaxed prose-p:text-slate-600 prose-li:text-slate-600">

            <h2>1. Éditeur du site</h2>
            <ul>
                <li>Raison sociale : SITIAME CAPITAL</li>
                <li>Forme juridique : <em>à compléter</em></li>
                <li>Capital social : <em>à compléter</em></li>
                <li>Numéro RCCM : <em>à compléter</em></li>
                <li>Numéro de compte contribuable (NIF) : <em>à compléter</em></li>
                <li>Siège social : <em>à compléter</em></li>
                <li>Représentant légal : <em>à compléter</em></li>
                <li>Téléphone : +225 27 24 52 30 43 / +225 07 09 16 13 81</li>
                <li>E-mail : <a href="mailto:contact@sitiame-capital.com" class="font-semibold text-brand-700 hover:text-brand-800">contact@sitiame-capital.com</a></li>
            </ul>

            <h2>2. Hébergement</h2>
            <p>Le site sitiame-capital.com est hébergé par LWS (Ligne Web Services). L'infrastructure de gestion comptable (ERPNext) est hébergée sur un serveur dédié géré par SITIAME CAPITAL.</p>

            <h2>3. Propriété intellectuelle</h2>
            <p>L'ensemble des contenus présents sur la Plateforme (textes, logos, éléments graphiques, logiciels) est la propriété de SITIAME CAPITAL ou de ses partenaires, sauf mention contraire, et est protégé par la législation applicable en matière de propriété intellectuelle.</p>

            <h2>4. Données personnelles</h2>
            <p>Le traitement des données personnelles est décrit dans notre <a href="{{ route('legal.confidentialite') }}" class="font-semibold text-brand-700 hover:text-brand-800">Politique de Confidentialité</a>.</p>

            <h2>5. Contact</h2>
            <p>Pour toute question relative au présent site, vous pouvez nous contacter à <a href="mailto:contact@sitiame-capital.com" class="font-semibold text-brand-700 hover:text-brand-800">contact@sitiame-capital.com</a>.</p>
        </div>
    </main>

    <footer class="border-t border-slate-200 bg-slate-950 py-12 text-slate-300">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-4 text-sm">
                <p class="text-slate-500">SITIAME CAPITAL © {{ date('Y') }} · Tous droits réservés</p>
                <div class="flex flex-wrap gap-4">
                    <a href="{{ route('legal.cgu') }}" class="text-slate-400 hover:text-white">CGU</a>
                    <a href="{{ route('legal.confidentialite') }}" class="text-slate-400 hover:text-white">Confidentialité</a>
                    <a href="{{ route('legal.mentions-legales') }}" class="text-slate-400 hover:text-white">Mentions légales</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
