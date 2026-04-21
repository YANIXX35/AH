<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    @include('site.public-head', [
        'title' => 'Documentation | SITIAME CAPITAL',
        'description' => 'Documentation de la plateforme SITIAME CAPITAL.',
    ])
</head>
<body class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50 font-sans text-slate-800 antialiased">
    @include('site.public-header')

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-8 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-brand-950 px-6 py-10 text-white shadow-soft-lg sm:px-10">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-300">Documentation</p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Guide complet de la plateforme</h1>
            <p class="mt-4 max-w-3xl text-base leading-relaxed text-slate-300 sm:text-lg">
                Quick start, prérequis et modules déjà livrés : authentification, OTP, e-mails SMTP, FedaPay sandbox, Premium et administration des paiements.
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[280px,1fr]">
            <aside class="h-max rounded-2xl border border-slate-100 bg-white p-5 shadow-soft lg:sticky lg:top-24">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Sommaire</p>
                <nav class="space-y-2 text-sm">
                    <a href="#quick-start" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Quick start</a>
                    <a href="#prerequis" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Prérequis</a>
                    <a href="#installation" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Installation</a>
                    <a href="#usage" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Usage</a>
                    <a href="#auth" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Authentification</a>
                    <a href="#paiement" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Paiement FedaPay</a>
                    <a href="#premium" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Premium & Comptabilité</a>
                    <a href="#admin" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">Administration</a>
                    <a href="#faq" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">FAQ / Dépannage</a>
                </nav>
            </aside>

            <div class="space-y-6">
                <section id="quick-start" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft">
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Quick start</h2>
                    <p class="mt-3 text-sm text-slate-600">
                        Démarrez rapidement la plateforme en configurant l'environnement, la base de données et les services externes (SMTP, reCAPTCHA, FedaPay sandbox).
                    </p>
                </section>

                <section id="prerequis" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft">
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Prérequis</h2>
                    <ul class="mt-4 space-y-2 text-sm text-slate-700">
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>PHP 8.2+ et Composer</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>MySQL/MariaDB</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Node.js (optionnel pour assets)</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Compte Gmail SMTP (app password)</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Clés Google reCAPTCHA</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Clé API FedaPay Sandbox</li>
                    </ul>
                </section>

                <section id="installation" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft">
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Installation</h2>
                    <div class="mt-4 rounded-xl bg-slate-900 p-4 text-sm text-slate-100">
                        <pre class="overflow-x-auto"><code>composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan serve</code></pre>
                    </div>
                    <p class="mt-3 text-sm text-slate-600">
                        Complétez ensuite les variables dans <code>.env</code> : SMTP, reCAPTCHA et FedaPay sandbox.
                    </p>
                </section>

                <section id="usage" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft">
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Usage</h2>
                    <ul class="mt-4 space-y-2 text-sm text-slate-700">
                        <li><i class="fa fa-arrow-right mr-2 text-brand-600"></i><strong>Public:</strong> accueil, page tarifs, documentation.</li>
                        <li><i class="fa fa-arrow-right mr-2 text-brand-600"></i><strong>Invité:</strong> inscription, connexion, mot de passe oublié OTP.</li>
                        <li><i class="fa fa-arrow-right mr-2 text-brand-600"></i><strong>Connecté:</strong> dashboard, profil, paiements sandbox, modules métiers.</li>
                        <li><i class="fa fa-arrow-right mr-2 text-brand-600"></i><strong>Admin:</strong> gestion complète des paiements et activation/désactivation Premium.</li>
                    </ul>
                </section>

                <section id="auth" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft">
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Authentification & sécurité</h2>
                    <ul class="mt-4 space-y-2 text-sm text-slate-700">
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Connexion protégée par reCAPTCHA.</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Réinitialisation mot de passe via OTP (6 chiffres, expiration, limite de tentatives).</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Envoi d'e-mails (création compte, OTP, confirmation reset).</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Journalisation des événements d'authentification.</li>
                    </ul>
                </section>

                <section id="paiement" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft">
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Paiement FedaPay sandbox</h2>
                    <ul class="mt-4 space-y-2 text-sm text-slate-700">
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Page dédiée de test paiement (<code>/payments/sandbox</code>).</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Validation pays/correspondant/montant/MSISDN.</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Enregistrement complet des transactions (payload, statut, motif d'échec).</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Historique récent visible sur le profil utilisateur.</li>
                    </ul>
                </section>

                <section id="premium" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft">
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Premium & accès Comptabilité</h2>
                    <ul class="mt-4 space-y-2 text-sm text-slate-700">
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Activation Premium automatique 30 jours après paiement sandbox validé.</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Middleware dédié pour bloquer la comptabilité en mode Gratuit.</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Passage manuel Gratuit/Premium possible depuis l'administration.</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Historique d'abonnement conservé.</li>
                    </ul>
                </section>

                <section id="admin" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft">
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Administration des paiements</h2>
                    <ul class="mt-4 space-y-2 text-sm text-slate-700">
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Vue centralisée des transactions et KPI.</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Filtres par statut, pays, correspondant, utilisateur, période et recherche.</li>
                        <li><i class="fa fa-check mr-2 text-brand-600"></i>Actions admin : activer Premium (30 jours) ou repasser en Gratuit.</li>
                    </ul>
                </section>

                <section id="faq" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-soft">
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">FAQ & dépannage</h2>
                    <div class="mt-4 space-y-4 text-sm text-slate-700">
                        <article>
                            <h3 class="font-semibold text-slate-900">Je ne reçois pas l'OTP par e-mail</h3>
                            <p class="mt-1">Vérifiez la configuration SMTP Gmail (host, port, username, app password, scheme).</p>
                        </article>
                        <article>
                            <h3 class="font-semibold text-slate-900">Le login bloque sur reCAPTCHA</h3>
                            <p class="mt-1">Ajoutez votre domaine (localhost / ngrok) dans la console reCAPTCHA.</p>
                        </article>
                        <article>
                            <h3 class="font-semibold text-slate-900">Paiement sandbox rejeté</h3>
                            <p class="mt-1">Contrôlez le code pays ISO3, le correspondant et le format MSISDN attendu.</p>
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
                <p class="mt-4 text-sm leading-relaxed text-slate-400">Documentation fonctionnelle et technique.</p>
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
