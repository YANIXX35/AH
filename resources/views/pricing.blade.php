<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    @include('site.public-head', [
        'title' => 'SITIAME CAPITAL - Tarifs',
        'description' => 'Tarifs et formules SITIAME CAPITAL.',
    ])
</head>
<body class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50 font-sans text-slate-800 antialiased">
    @include('site.public-header')

    <main>
        <section class="relative overflow-hidden bg-slate-950 py-20 text-white">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-brand-950/80 to-slate-900" aria-hidden="true"></div>
            <div class="relative mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-brand-300/90 sm:text-sm">Plans & Pricing</p>
                <h1 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl lg:text-5xl">Tarifs alignés sur la plateforme</h1>
                <p class="mx-auto mt-5 max-w-3xl text-base leading-relaxed text-slate-300 sm:text-lg">Authentification renforcée, paiement FedaPay sandbox, Premium et accès Comptabilité — des offres calées sur ce qui est déjà en production.</p>
            </div>
        </section>

        <section class="mx-auto -mt-10 max-w-7xl px-4 pb-14 sm:px-6 lg:px-8">
            @error('payment_redirect')
                <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    {{ $message }}
                </div>
            @enderror
            <div class="grid gap-6 lg:grid-cols-2">
                <article class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
                    <h2 class="text-xl font-bold text-slate-900">Gratuit (période d'essai)</h2>
                    <p class="mt-2 text-sm text-slate-500">Découvrir l'application et ses flux principaux.</p>
                    <p class="mt-6 text-4xl font-extrabold text-emerald-600">0 <span class="text-lg font-semibold text-slate-500">FCFA</span></p>
                    <ul class="mt-6 space-y-3 text-sm text-slate-600">
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Connexion sécurisée</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Mot de passe oublié avec OTP par e-mail</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Tableau de bord et profil</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>1 compte utilisateur</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Test paiement FedaPay sandbox</li>
                        <li><i class="fa fa-times mr-2 text-rose-500"></i>Comptabilité Premium verrouillée</li>
                    </ul>
                    <a href="{{ route('login') }}" class="mt-7 inline-block w-full rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-center text-sm font-semibold text-emerald-700 hover:bg-emerald-100">Activer l'offre gratuite</a>
                </article>

                <article class="relative rounded-2xl border-2 border-brand-600 bg-white p-7 shadow-soft-lg ring-1 ring-brand-600/10">
                    <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-brand-600 px-4 py-1 text-xs font-semibold uppercase tracking-wider text-white shadow-md">Le plus populaire</span>
                    <h2 class="text-xl font-bold text-slate-900">Enterprise Premium</h2>
                    <p class="mt-2 text-sm text-slate-500">Le plan principal pour une utilisation métier complète.</p>
                    <p class="mt-6 text-4xl font-extrabold text-brand-800">15 000 <span class="text-lg font-semibold text-slate-500">FCFA / mois</span></p>
                    <ul class="mt-6 space-y-3 text-sm text-slate-600">
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Activation Premium automatique 30 jours après paiement validé</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Accès au module Comptabilité (routes protégées Premium)</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Historique des paiements sandbox sur le profil</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Suivi des statuts d'abonnement (active/free)</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Journalisation des événements d'authentification</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Support standard</li>
                    </ul>
                    <a href="{{ auth()->check() ? route('payments.redirect') : route('login') }}" class="mt-7 inline-block w-full rounded-lg bg-brand-700 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-brand-800">Passer en Enterprise Premium</a>
                </article>
            </div>
        </section>

        <section class="bg-white py-14">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h3 class="text-center text-3xl font-bold text-slate-900">Comparatif des 2 offres</h3>
                <div class="mt-8 overflow-x-auto rounded-2xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Fonctionnalités</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Gratuit (période d'essai)</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Enterprise Premium</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr><td class="px-4 py-3">Connexion sécurisée</td><td class="px-4 py-3">Oui</td><td class="px-4 py-3">Oui</td></tr>
                            <tr><td class="px-4 py-3">Réinitialisation mot de passe OTP (e-mail)</td><td class="px-4 py-3">Oui</td><td class="px-4 py-3">Oui</td></tr>
                            <tr><td class="px-4 py-3">Paiement FedaPay sandbox</td><td class="px-4 py-3">Oui</td><td class="px-4 py-3">Oui</td></tr>
                            <tr><td class="px-4 py-3">Activation Premium 30 jours</td><td class="px-4 py-3">Après paiement</td><td class="px-4 py-3">Oui</td></tr>
                            <tr><td class="px-4 py-3">Accès module Comptabilité</td><td class="px-4 py-3">Non</td><td class="px-4 py-3">Oui</td></tr>
                            <tr><td class="px-4 py-3">Gestion admin des paiements</td><td class="px-4 py-3">Back-office</td><td class="px-4 py-3">Back-office</td></tr>
                            <tr><td class="px-4 py-3">Support</td><td class="px-4 py-3">E-mail</td><td class="px-4 py-3">Standard</td></tr>
                            <tr><td class="px-4 py-3">Tarification</td><td class="px-4 py-3">0 FCFA (période d'essai)</td><td class="px-4 py-3">15 000 FCFA / mois</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="py-14">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                    <h3 class="text-2xl font-bold text-slate-900">Stratégie derrière ces 2 offres</h3>
                    <ul class="mt-5 space-y-3 text-sm text-slate-700">
                        <li><i class="fa fa-arrow-right mr-2 text-brand-600"></i>Gratuit (période d'essai): entrée sans friction avec les modules réellement actifs.</li>
                        <li><i class="fa fa-arrow-right mr-2 text-brand-600"></i>Enterprise Premium: déverrouille la vraie valeur métier (Comptabilité + Premium).</li>
                    </ul>
                    <div class="mt-6 rounded-xl bg-amber-50 p-4 text-sm text-amber-800">
                        <strong>Piège à éviter:</strong> un gratuit trop généreux réduit la conversion; un plan payant sans valeur évidente ne convertit pas.
                    </div>
                </div>
            </div>
        </section>

        <section class="py-14">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h3 class="text-center text-3xl font-bold text-slate-900">Questions fréquentes</h3>
                <div class="mx-auto mt-8 grid max-w-4xl gap-4">
                    <article class="rounded-xl border border-slate-200 bg-white p-5">
                        <h4 class="font-semibold text-slate-900">Quel est le tarif ?</h4>
                        <p class="mt-2 text-sm text-slate-600">L'offre Enterprise Premium est à 15 000 FCFA / mois. L'offre Gratuit (période d'essai) reste à 0 FCFA.</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-5">
                        <h4 class="font-semibold text-slate-900">Comment passer en Premium ?</h4>
                        <p class="mt-2 text-sm text-slate-600">Un paiement FedaPay sandbox validé active automatiquement Premium pendant 30 jours sur le compte entreprise.</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-5">
                        <h4 class="font-semibold text-slate-900">L'offre gratuite peut-elle être réactivée ?</h4>
                        <p class="mt-2 text-sm text-slate-600">Oui. Depuis l'administration des paiements, un compte peut repasser en mode Gratuit (période d'essai) manuellement.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="bg-gradient-to-br from-slate-900 via-slate-900 to-brand-950 py-16 text-white">
            <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
                <h3 class="text-2xl font-semibold tracking-tight sm:text-3xl">Passez à la formule Enterprise Premium</h3>
                <p class="mx-auto mt-4 max-w-2xl text-slate-300">Pour exploiter pleinement la plateforme, Enterprise Premium est le choix adapté.</p>
                <a href="{{ auth()->check() ? route('payments.redirect') : route('login') }}" class="mt-8 inline-flex rounded-xl bg-white px-8 py-3.5 text-sm font-semibold text-brand-900 shadow-lg shadow-brand-950/30 transition hover:bg-brand-50">Aller au paiement Enterprise Premium</a>
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
                    <li><a href="{{ route('about-us') }}" class="text-slate-400 transition hover:text-white">À propos</a></li>
                    <li><a href="{{ route('about-us') }}" class="text-slate-400 transition hover:text-white">Nos services</a></li>
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
