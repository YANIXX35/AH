<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    @include('site.public-head', [
        'title' => 'Documentation | SITIAME CAPITAL',
        'description' => 'Guide client de la plateforme SITIAME CAPITAL.',
    ])
</head>
<body class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50 font-sans text-slate-800 antialiased">
    @include('site.public-header')

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-8 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-brand-950 px-6 py-10 text-white shadow-soft-lg sm:px-10">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-300">Guide client</p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Tout comprendre sur la plateforme en quelques minutes</h1>
            <p class="mt-4 max-w-3xl text-base leading-relaxed text-slate-300 sm:text-lg">
                Cette page vous aide à découvrir les fonctionnalités utiles à votre entreprise :
                création de compte, gestion comptable, trésorerie, facturation, conformité KYC/KYB,
                support et administration.
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[280px,1fr]">
            <aside class="h-max rounded-2xl border border-slate-100 bg-white p-5 shadow-soft lg:sticky lg:top-24">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Sommaire</p>
                <nav class="space-y-2 text-sm">
                    <a href="#demarrage" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Démarrage rapide</a>
                    <a href="#parcours-client" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Parcours entreprise</a>
                    <a href="#modules" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Modules principaux</a>
                    <a href="#kyc" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Conformité KYC/KYB</a>
                    <a href="#roles" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Rôles et accès</a>
                    <a href="#facturation" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Facturation et abonnement</a>
                    <a href="#support" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Support client</a>
                    <a href="#faq" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">FAQ</a>
                </nav>
            </aside>

            <div class="space-y-6">
                <section id="demarrage" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft">
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Démarrage rapide</h2>
                    <ol class="mt-4 space-y-2 text-sm text-slate-700">
                        <li><strong>1)</strong> Créez votre compte entreprise depuis la page d’inscription.</li>
                        <li><strong>2)</strong> Complétez vos informations (entreprise, contact, pièces utiles).</li>
                        <li><strong>3)</strong> Soumettez automatiquement votre dossier KYC/KYB.</li>
                        <li><strong>4)</strong> Accédez à votre espace de pilotage (dashboard, comptabilité, trésorerie).</li>
                        <li><strong>5)</strong> Suivez vos notifications et vos validations administratives.</li>
                    </ol>
                </section>

                <section id="parcours-client" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft">
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Parcours entreprise</h2>
                    <ul class="mt-4 space-y-2 text-sm text-slate-700">
                        <li><i class="fa fa-check mr-2 text-brand-600"></i><strong>Inscription :</strong> création du compte principal de votre entreprise.</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i><strong>Validation :</strong> vérification KYC/KYB par l’administrateur plateforme.</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i><strong>Activation :</strong> accès complet aux modules autorisés.</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i><strong>Exploitation :</strong> suivi quotidien des opérations, rapports, paiements et alertes.</li>
                    </ul>
                </section>

                <section id="modules" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft">
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Modules principaux</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-100 p-4">
                            <h3 class="font-semibold text-slate-900">Comptabilité</h3>
                            <p class="mt-1 text-sm text-slate-600">Saisie des écritures, gestion des pièces, plan comptable, rapports de base.</p>
                        </div>
                        <div class="rounded-xl border border-slate-100 p-4">
                            <h3 class="font-semibold text-slate-900">Trésorerie</h3>
                            <p class="mt-1 text-sm text-slate-600">Encaissements/décaissements, équilibre de trésorerie et prévisions.</p>
                        </div>
                        <div class="rounded-xl border border-slate-100 p-4">
                            <h3 class="font-semibold text-slate-900">Facturation</h3>
                            <p class="mt-1 text-sm text-slate-600">Historique des factures, visualisation PDF, téléchargement, suivi des impayés.</p>
                        </div>
                        <div class="rounded-xl border border-slate-100 p-4">
                            <h3 class="font-semibold text-slate-900">Support</h3>
                            <p class="mt-1 text-sm text-slate-600">Messagerie support en temps réel, statut des tickets et réponses administrateur.</p>
                        </div>
                    </div>
                </section>

                <section id="kyc" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft">
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Conformité KYC/KYB</h2>
                    <ul class="mt-4 space-y-2 text-sm text-slate-700">
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Après inscription, votre dossier est soumis automatiquement pour validation.</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>L’administrateur vérifie vos pièces et valide ou rejette avec motif.</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>En cas de rejet, le dossier peut être resoumis avec les corrections nécessaires.</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Chaque action est tracée dans un journal d’audit.</li>
                    </ul>
                </section>

                <section id="roles" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft">
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Rôles et accès</h2>
                    <ul class="mt-4 space-y-2 text-sm text-slate-700">
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>La plateforme gère des rôles métier (manager, comptable, analyste, lecture seule).</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Les permissions peuvent être activées par module selon le besoin de l’entreprise.</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Les opérations sensibles peuvent être soumises à approbation multi-niveaux.</li>
                    </ul>
                </section>

                <section id="facturation" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft">
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Facturation et abonnement</h2>
                    <ul class="mt-4 space-y-2 text-sm text-slate-700">
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Accès à la liste des factures et au détail de l’abonnement.</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Visualisation et téléchargement des factures PDF.</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Suivi des impayés et relances automatiques selon l’échéance.</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Supervision admin : taux de paiement, risques client, décisions de recouvrement.</li>
                    </ul>
                </section>

                <section id="support" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft">
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Support client</h2>
                    <ul class="mt-4 space-y-2 text-sm text-slate-700">
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Ouverture de ticket support depuis votre espace.</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Suivi en temps réel des échanges avec l’équipe administrative.</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Statuts de message (envoyé, reçu, lu) pour un meilleur suivi.</li>
                    </ul>
                </section>

                <section id="faq" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft">
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">FAQ</h2>
                    <div class="mt-4 space-y-4 text-sm text-slate-700">
                        <article>
                            <h3 class="font-semibold text-slate-900">Combien de temps prend la validation KYC/KYB ?</h3>
                            <p class="mt-1">La validation dépend de la qualité des pièces fournies. Vous suivez le statut directement dans votre profil.</p>
                        </article>
                        <article>
                            <h3 class="font-semibold text-slate-900">Puis-je inviter mon équipe ?</h3>
                            <p class="mt-1">Oui. La gestion d’équipe dépend de votre niveau d’abonnement et des permissions accordées.</p>
                        </article>
                        <article>
                            <h3 class="font-semibold text-slate-900">Comment obtenir mes factures ?</h3>
                            <p class="mt-1">Depuis la rubrique facturation, vous pouvez visualiser chaque facture et la télécharger en PDF.</p>
                        </article>
                        <article>
                            <h3 class="font-semibold text-slate-900">Comment contacter le support ?</h3>
                            <p class="mt-1">Utilisez le module “Aide & support” de votre espace pour créer un ticket et suivre les réponses.</p>
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <footer class="mt-12 border-t border-white/10 bg-slate-950 py-12 text-slate-300">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-3 lg:px-8">
            <div>
                <img src="https://sitiame-capital.com/assets/images/logo.png" alt="Logo Sitiame" class="h-12 w-auto opacity-95 sm:h-14">
                <p class="mt-4 text-sm leading-relaxed text-slate-400">Documentation orientée usage client et accompagnement PME.</p>
            </div>
            <div>
                <h4 class="text-sm font-semibold uppercase tracking-wider text-white">Navigation</h4>
                <ul class="mt-4 space-y-2 text-sm">
                    <li><a href="{{ route('home') }}" class="text-slate-400 transition hover:text-white">Accueil</a></li>
                    <li><a href="{{ route('pricing') }}" class="text-slate-400 transition hover:text-white">Tarifs</a></li>
                    <li><a href="{{ route('documentation') }}" class="text-slate-400 transition hover:text-white">Documentation</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-sm font-semibold uppercase tracking-wider text-white">Contact</h4>
                <ul class="mt-4 space-y-2 text-sm text-slate-400">
                    <li>+225 2724523043</li>
                    <li>+225 0709161381</li>
                    <li>contact@sitiame-capital.com</li>
                </ul>
            </div>
        </div>
        <p class="mt-10 border-t border-white/10 pt-8 text-center text-xs text-slate-500">SITIAME CAPITAL © {{ date('Y') }} · Tous droits réservés</p>
    </footer>
</body>
</html>
