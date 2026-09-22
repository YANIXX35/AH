<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    @include('site.public-head', [
        'title' => 'SITIAME CAPITAL - Politique de Confidentialité',
        'description' => 'Politique de confidentialité et protection des données personnelles de la plateforme PME360.',
    ])
</head>
<body class="min-h-screen bg-white font-sans text-slate-800 antialiased">
    @include('site.public-header')

    <main class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-brand-600">Document légal</p>
        <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Politique de Confidentialité</h1>
        <p class="mt-2 text-sm text-slate-500">Dernière mise à jour : {{ \Illuminate\Support\Carbon::now()->translatedFormat('d F Y') }}</p>

        <div class="prose prose-slate mt-10 max-w-none prose-h2:mt-10 prose-h2:text-xl prose-h2:font-bold prose-h2:text-slate-900 prose-p:leading-relaxed prose-p:text-slate-600 prose-li:text-slate-600">

            <h2>1. Responsable du traitement</h2>
            <p>SITIAME CAPITAL, éditeur de la plateforme PME360, est responsable du traitement des données personnelles collectées via sitiame-capital.com. Pour toute question relative à vos données, vous pouvez nous contacter à <a href="mailto:contact@sitiame-capital.com" class="font-semibold text-brand-700 hover:text-brand-800">contact@sitiame-capital.com</a>.</p>

            <h2>2. Données collectées</h2>
            <p>Selon votre usage de la Plateforme, nous collectons :</p>
            <ul>
                <li>Données d'identification et de contact fournies à l'inscription (nom, e-mail, téléphone, raison sociale) ;</li>
                <li>Documents transmis pour la vérification de votre entreprise (KYC/KYB) ;</li>
                <li>Données comptables et commerciales que vous saisissez sur la Plateforme (factures, mouvements de stock, paiements) ;</li>
                <li>Données de connexion et de navigation (journal d'authentification, adresse IP, type de navigateur), y compris via des outils de mesure d'audience.</li>
            </ul>

            <h2>3. Finalités du traitement</h2>
            <p>Vos données sont utilisées pour : fournir et sécuriser l'accès à la Plateforme, traiter vos opérations de facturation et de comptabilité, vérifier l'identité de votre entreprise, assurer le support client, améliorer la Plateforme et mesurer son audience.</p>

            <h2>4. Mesure d'audience et cookies</h2>
            <p>La Plateforme utilise un outil de mesure d'audience (PostHog) afin d'analyser l'utilisation du site et d'améliorer nos services. Cet outil peut déposer des cookies ou utiliser le stockage local de votre navigateur. Conformément à la réglementation applicable, cet outil n'est chargé qu'après votre consentement, que vous pouvez à tout moment retirer ou modifier via le bandeau de gestion des cookies affiché sur le site.</p>

            <h2>5. Partage des données</h2>
            <p>Vos données ne sont partagées qu'avec les prestataires nécessaires au fonctionnement de la Plateforme (hébergement, système de gestion comptable interne, prestataire de paiement Mobile Money) et ne sont ni vendues ni louées à des tiers à des fins commerciales.</p>

            <h2>6. Durée de conservation</h2>
            <p>Vos données sont conservées pendant la durée de votre relation contractuelle avec SITIAME CAPITAL, puis archivées ou supprimées conformément aux obligations légales de conservation applicables en matière comptable et fiscale.</p>

            <h2>7. Vos droits</h2>
            <p>Conformément à la réglementation applicable en matière de protection des données personnelles, vous disposez d'un droit d'accès, de rectification, d'opposition et de suppression de vos données. Vous pouvez exercer ces droits en écrivant à <a href="mailto:contact@sitiame-capital.com" class="font-semibold text-brand-700 hover:text-brand-800">contact@sitiame-capital.com</a>.</p>

            <h2>8. Sécurité</h2>
            <p>SITIAME CAPITAL met en œuvre des mesures techniques et organisationnelles raisonnables pour protéger vos données contre l'accès non autorisé, la perte ou l'altération.</p>

            <h2>9. Modification de la présente politique</h2>
            <p>Cette politique peut être mise à jour. La date de dernière mise à jour figure en haut de cette page. En cas de modification substantielle, les utilisateurs enregistrés en seront informés.</p>
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
