<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    @include('site.public-head', [
        'title' => 'SITIAME CAPITAL - Conditions Générales d\'Utilisation',
        'description' => 'Conditions générales d\'utilisation de la plateforme PME360 par SITIAME CAPITAL.',
    ])
</head>
<body class="min-h-screen bg-white font-sans text-slate-800 antialiased">
    @include('site.public-header')

    <main class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-brand-600">Document légal</p>
        <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Conditions Générales d'Utilisation</h1>
        <p class="mt-2 text-sm text-slate-500">Dernière mise à jour : {{ \Illuminate\Support\Carbon::now()->translatedFormat('d F Y') }}</p>

        <div class="prose prose-slate mt-10 max-w-none prose-h2:mt-10 prose-h2:text-xl prose-h2:font-bold prose-h2:text-slate-900 prose-p:leading-relaxed prose-p:text-slate-600 prose-li:text-slate-600">

            <h2>1. Objet</h2>
            <p>Les présentes Conditions Générales d'Utilisation (CGU) régissent l'accès et l'utilisation de la plateforme PME360, éditée par SITIAME CAPITAL, accessible à l'adresse sitiame-capital.com (ci-après « la Plateforme »). La Plateforme propose aux petites et moyennes entreprises des outils de gestion : facturation, suivi de stock, encaissement Mobile Money, tableau de bord financier et transmission de documents KYC/KYB. Toute inscription ou utilisation de la Plateforme implique l'acceptation pleine et entière des présentes CGU.</p>

            <h2>2. Inscription et compte entreprise</h2>
            <p>L'inscription nécessite la création d'un compte entreprise, la fourniture d'informations exactes sur l'entreprise (raison sociale, contacts, documents justificatifs le cas échéant) et l'acceptation des présentes CGU ainsi que de la <a href="{{ route('legal.confidentialite') }}" class="font-semibold text-brand-700 hover:text-brand-800">Politique de Confidentialité</a>. L'utilisateur est responsable de la confidentialité de ses identifiants et de toute activité effectuée depuis son compte.</p>

            <h2>3. Offres et abonnement</h2>
            <p>La Plateforme propose une offre d'essai gratuite ainsi qu'une ou plusieurs offres payantes détaillées sur la <a href="{{ route('pricing') }}" class="font-semibold text-brand-700 hover:text-brand-800">page Tarifs</a>. Le paiement des offres payantes s'effectue par les moyens indiqués sur la Plateforme (dont Mobile Money). Sauf mention contraire, l'abonnement est mensuel et son renouvellement suit les modalités affichées au moment de la souscription.</p>

            <h2>4. Utilisation autorisée</h2>
            <p>L'utilisateur s'engage à utiliser la Plateforme conformément à sa destination et à la réglementation en vigueur, notamment en matière comptable et fiscale. Il s'engage à ne pas transmettre de documents falsifiés, à ne pas tenter de contourner les mesures de sécurité et à ne pas porter atteinte au bon fonctionnement de la Plateforme.</p>

            <h2>5. Données comptables et intégration</h2>
            <p>Les écritures et documents saisis par l'utilisateur (factures, mouvements de stock, paiements) sont synchronisés avec le système de gestion comptable de SITIAME CAPITAL afin de produire les rapports et indicateurs financiers proposés par la Plateforme. L'utilisateur reste responsable de l'exactitude des informations qu'il saisit ou transmet.</p>

            <h2>6. Disponibilité et responsabilité</h2>
            <p>SITIAME CAPITAL met en œuvre les moyens raisonnables pour assurer la disponibilité et la sécurité de la Plateforme, sans garantie d'absence totale d'interruption. SITIAME CAPITAL ne saurait être tenue responsable des dommages indirects résultant de l'utilisation de la Plateforme ou d'une interruption de service.</p>

            <h2>7. Résiliation</h2>
            <p>L'utilisateur peut cesser d'utiliser la Plateforme à tout moment. SITIAME CAPITAL se réserve le droit de suspendre ou résilier un compte en cas de manquement grave aux présentes CGU, après notification lorsque cela est possible.</p>

            <h2>8. Modification des CGU</h2>
            <p>SITIAME CAPITAL peut modifier les présentes CGU à tout moment. Les utilisateurs seront informés de toute modification substantielle. La poursuite de l'utilisation de la Plateforme après modification vaut acceptation des nouvelles conditions.</p>

            <h2>9. Contact</h2>
            <p>Pour toute question relative aux présentes CGU, l'utilisateur peut contacter SITIAME CAPITAL à l'adresse <a href="mailto:contact@sitiame-capital.com" class="font-semibold text-brand-700 hover:text-brand-800">contact@sitiame-capital.com</a>.</p>

            <p class="mt-10 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Ce document sera complété avec les informations juridiques précises de la société (forme juridique, capital, immatriculation) dans les <a href="{{ route('legal.mentions-legales') }}" class="font-semibold underline">Mentions légales</a>.</p>
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
