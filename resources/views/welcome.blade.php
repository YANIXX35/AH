<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    @include('site.public-head', [
        'title' => 'SITIAME CAPITAL - Pour votre investissement gagnant',
        'description' => 'SITIAME CAPITAL - Conseils aux PME et investissement en capital.',
    ])
</head>
<body class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50 font-sans text-slate-800 antialiased">
    @include('site.public-header')

    <main>
        <section id="accueil" class="relative overflow-hidden bg-slate-950 text-white">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-brand-950/90 to-slate-900" aria-hidden="true"></div>
            <img src="https://sitiame-capital.com/assets/images/slider/slide1.jpg" alt="" class="absolute inset-0 h-full w-full object-cover opacity-35 mix-blend-overlay">
            <div class="relative mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-24 lg:px-8 lg:py-28">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-brand-300/90 sm:text-sm">Conseil & investissement</p>
                <h1 class="mt-4 max-w-3xl text-3xl font-semibold leading-[1.15] tracking-tight sm:text-4xl lg:text-5xl">Pour votre investissement gagnant</h1>
                <p class="mt-5 max-w-2xl text-base leading-relaxed text-slate-300 sm:text-lg">Expertise en conseils aux PME et en investissement en capital, avec une approche structurée et orientée résultats.</p>
                <div class="mt-10 flex flex-wrap gap-4">
                    <a href="#expertise" class="inline-flex items-center justify-center rounded-xl bg-white px-6 py-3.5 text-sm font-semibold text-brand-900 shadow-lg shadow-brand-900/20 transition hover:bg-brand-50">Voir nos domaines d’expertise</a>
                    <a href="#contact" class="inline-flex items-center justify-center rounded-xl border border-white/25 bg-white/5 px-6 py-3.5 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-white/10">Contactez-nous</a>
                </div>
            </div>
        </section>

        <section class="mx-auto grid max-w-7xl gap-6 px-4 py-14 sm:px-6 lg:grid-cols-3 lg:gap-8 lg:px-8">
            <article class="group rounded-2xl border border-slate-200/80 bg-white p-6 shadow-soft transition duration-300 hover:-translate-y-0.5 hover:border-brand-200/80 hover:shadow-soft-lg">
                <div class="overflow-hidden rounded-xl ring-1 ring-slate-900/5">
                    <img src="https://sitiame-capital.com/assets/images/slider/slide1.jpg" alt="Conseils stratégiques" class="h-40 w-full object-cover transition duration-500 group-hover:scale-[1.02]">
                </div>
                <h2 class="mt-5 text-xl font-semibold tracking-tight text-slate-900">Conseils stratégiques</h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">L'élaboration d'une stratégie efficace est essentielle pour stimuler la croissance, maintenir la compétitivité et répondre aux défis.</p>
            </article>
            <article class="group rounded-2xl border border-slate-200/80 bg-white p-6 shadow-soft transition duration-300 hover:-translate-y-0.5 hover:border-brand-200/80 hover:shadow-soft-lg">
                <div class="overflow-hidden rounded-xl ring-1 ring-slate-900/5">
                    <img src="https://sitiame-capital.com/assets/images/slider/slide2.jpg" alt="Investissements" class="h-40 w-full object-cover transition duration-500 group-hover:scale-[1.02]">
                </div>
                <h2 class="mt-5 text-xl font-semibold tracking-tight text-slate-900">Investissements</h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">A l'aide d'outils et des techniques de pointe pour assurer la réussite de votre projet, en respectant les délais, les budgets et les objectifs fixés.</p>
            </article>
            <article class="group rounded-2xl border border-slate-200/80 bg-white p-6 shadow-soft transition duration-300 hover:-translate-y-0.5 hover:border-brand-200/80 hover:shadow-soft-lg">
                <div class="overflow-hidden rounded-xl ring-1 ring-slate-900/5">
                    <img src="https://sitiame-capital.com/assets/images/slider/slide3.jpg" alt="Levée de fonds" class="h-40 w-full object-cover transition duration-500 group-hover:scale-[1.02]">
                </div>
                <h2 class="mt-5 text-xl font-semibold tracking-tight text-slate-900">Levée de fonds</h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">Identification des besoins de trésorerie, évaluation de la structure de financement, des conditions de levée de fonds et des garanties.</p>
            </article>
        </section>

        <section id="expertise" class="border-y border-slate-100 bg-white py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-3xl text-center">
                    <span class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-600">Expertise</span>
                    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Nos domaines d’expertise</h2>
                    <div class="mx-auto mt-4 h-px w-16 bg-gradient-to-r from-transparent via-brand-400 to-transparent"></div>
                    <p class="mt-6 text-slate-600 leading-relaxed">Sitiame accompagne les entreprises et les acteurs publics sur des enjeux stratégiques et financiers majeurs : croissance, restructuration, fusions-acquisitions, diagnostic financier, modèles économiques, levée de fonds et évaluation de projets.</p>
                </div>
                <div class="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <article class="rounded-2xl border border-slate-100 bg-gradient-to-b from-slate-50/80 to-white p-5 shadow-soft transition hover:shadow-soft-lg">
                        <img src="https://sitiame-capital.com/assets/images/services/investissement.jpg" alt="Investissements" class="h-36 w-full rounded-xl object-cover ring-1 ring-slate-900/5">
                        <h3 class="mt-4 font-semibold text-slate-900">Investissements</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">Critères d'investissement et lecture marché pour viser les meilleurs résultats.</p>
                    </article>
                    <article class="rounded-2xl border border-slate-100 bg-gradient-to-b from-slate-50/80 to-white p-5 shadow-soft transition hover:shadow-soft-lg">
                        <img src="https://sitiame-capital.com/assets/images/services/conseils_strategiques.jpg" alt="Conseils stratégiques" class="h-36 w-full rounded-xl object-cover ring-1 ring-slate-900/5">
                        <h3 class="mt-4 font-semibold text-slate-900">Conseils stratégiques</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">Diagnostic et accompagnement sur les opportunités de développement et cessions.</p>
                    </article>
                    <article class="rounded-2xl border border-slate-100 bg-gradient-to-b from-slate-50/80 to-white p-5 shadow-soft transition hover:shadow-soft-lg">
                        <img src="https://sitiame-capital.com/assets/images/services/fonds.jpg" alt="Levée de fonds" class="h-36 w-full rounded-xl object-cover ring-1 ring-slate-900/5">
                        <h3 class="mt-4 font-semibold text-slate-900">Levée de fonds</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">Structure de financement, conditions et garanties adaptées.</p>
                    </article>
                    <article class="rounded-2xl border border-slate-100 bg-gradient-to-b from-slate-50/80 to-white p-5 shadow-soft transition hover:shadow-soft-lg">
                        <img src="https://sitiame-capital.com/assets/images/services/gestion_projet.jpg" alt="Gestion de projets" class="h-36 w-full rounded-xl object-cover ring-1 ring-slate-900/5">
                        <h3 class="mt-4 font-semibold text-slate-900">Gestion de projets</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">Méthodes et outils pour tenir délais, budgets et objectifs.</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="about" class="mx-auto grid max-w-7xl items-center gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:gap-14 lg:px-8">
            <div>
                <span class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-600">Présentation</span>
                <h3 class="mt-3 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Qui sommes-nous ?</h3>
                <p class="mt-5 leading-relaxed text-slate-600">Sitiame Capital est votre partenaire de confiance en matière de conseils aux PME et d'investissement en capital. Notre équipe accompagne la réalisation de vos objectifs financiers et la dynamique de votre croissance.</p>
                <div class="mt-8 flex items-center gap-4 text-slate-500">
                    <span class="text-sm font-medium text-slate-700">Suivez-nous</span>
                    <span class="flex gap-3 text-xl">
                        <a href="#" class="transition hover:text-brand-600" aria-label="Facebook"><i class="fa fa-facebook-square"></i></a>
                        <a href="#" class="transition hover:text-brand-600" aria-label="Twitter"><i class="fa fa-twitter-square"></i></a>
                        <a href="#" class="transition hover:text-brand-600" aria-label="Instagram"><i class="fa fa-instagram"></i></a>
                        <a href="#" class="transition hover:text-brand-600" aria-label="LinkedIn"><i class="fa fa-linkedin-square"></i></a>
                    </span>
                </div>
            </div>
            <div class="relative">
                <div class="absolute -inset-4 rounded-3xl bg-gradient-to-tr from-brand-100/50 to-slate-100/50 blur-2xl" aria-hidden="true"></div>
                <img src="https://sitiame-capital.com/assets/images/intro-video.jpg" alt="Sitiame Capital" class="relative h-72 w-full rounded-2xl object-cover shadow-soft-lg ring-1 ring-slate-900/5 sm:h-80">
            </div>
        </section>

        <section id="formulaires" class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-slate-900 to-brand-950 py-16 text-white">
            <div class="pointer-events-none absolute inset-0 opacity-30" style="background-image: radial-gradient(circle at 20% 50%, rgba(59,130,246,0.15), transparent 50%);"></div>
            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <span class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-300">Documents</span>
                    <h3 class="mt-3 text-2xl font-semibold tracking-tight sm:text-3xl">Nos formulaires</h3>
                    <p class="mt-3 text-slate-300">Documents administratifs essentiels pour structurer votre dossier.</p>
                </div>
                <div class="mt-10 grid gap-5 md:grid-cols-3">
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-sm transition hover:border-white/20 hover:bg-white/10">
                        <h4 class="font-semibold text-white">Présentation de projet</h4>
                        <p class="mt-2 text-sm leading-relaxed text-slate-300">Décrivez votre projet et vos besoins de financement.</p>
                    </article>
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-sm transition hover:border-white/20 hover:bg-white/10">
                        <h4 class="font-semibold text-white">Adhésion (personne morale)</h4>
                        <p class="mt-2 text-sm leading-relaxed text-slate-300">Dossier pour entreprises et organisations.</p>
                    </article>
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-sm transition hover:border-white/20 hover:bg-white/10">
                        <h4 class="font-semibold text-white">Adhésion (personne physique)</h4>
                        <p class="mt-2 text-sm leading-relaxed text-slate-300">Pour investisseurs et porteurs de projets.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="bg-white py-14">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h3 class="text-center text-2xl font-semibold tracking-tight text-slate-900">Nos partenaires</h3>
                <div class="mx-auto mt-4 h-px w-12 bg-gradient-to-r from-transparent via-brand-300 to-transparent"></div>
                <div class="mt-10 grid gap-6 sm:grid-cols-3">
                    <div class="flex items-center justify-center rounded-2xl border border-slate-100 bg-slate-50/80 py-8 shadow-soft transition hover:shadow-soft-lg"><img src="https://sitiame-capital.com/assets/images/partners/cci.jpg" alt="CCI" class="mx-auto h-14 w-auto opacity-90 sm:h-16"></div>
                    <div class="flex items-center justify-center rounded-2xl border border-slate-100 bg-slate-50/80 py-8 shadow-soft transition hover:shadow-soft-lg"><img src="https://sitiame-capital.com/assets/images/partners/bni.jpg" alt="BNI" class="mx-auto h-14 w-auto opacity-90 sm:h-16"></div>
                    <div class="flex items-center justify-center rounded-2xl border border-slate-100 bg-slate-50/80 py-8 shadow-soft transition hover:shadow-soft-lg"><img src="https://sitiame-capital.com/assets/images/partners/cdci.jpg" alt="CDCI" class="mx-auto h-14 w-auto opacity-90 sm:h-16"></div>
                </div>
            </div>
        </section>

        <section id="tarif" class="bg-slate-50 py-16">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="relative overflow-hidden rounded-3xl border border-brand-100/80 bg-white p-8 shadow-soft-lg sm:p-10">
                    <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-brand-100/40 blur-3xl" aria-hidden="true"></div>
                    <div class="relative">
                        <p class="inline-flex items-center rounded-full bg-brand-50 px-4 py-1.5 text-xs font-semibold uppercase tracking-wider text-brand-800">Tarification</p>
                        <h3 class="mt-4 text-3xl font-semibold tracking-tight text-slate-900">Un investissement maîtrisé</h3>
                        <p class="mt-3 max-w-xl text-slate-600 leading-relaxed">Accompagnement professionnel en conseil stratégique, levée de fonds et investissement. Découvrez nos formules détaillées.</p>
                        <div class="mt-8 flex flex-wrap items-end gap-2 border-l-4 border-brand-500 pl-5">
                            <span class="text-5xl font-semibold tracking-tight text-brand-800">17 580</span>
                            <span class="pb-1 text-lg font-medium text-slate-600">FCFA</span>
                        </div>
                        <div class="mt-8 flex flex-wrap gap-4">
                            <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center rounded-xl bg-brand-700 px-6 py-3 text-sm font-semibold text-white shadow-md transition hover:bg-brand-800">Voir tous les tarifs</a>
                            <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-brand-200 hover:bg-brand-50/50">Commencer maintenant</a>
                            <a href="#contact" class="inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-semibold text-brand-700 underline-offset-4 hover:underline">Demander des informations</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="contact" class="bg-gradient-to-b from-slate-100 to-slate-50 py-16">
            <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:items-start lg:px-8">
                <div class="overflow-hidden rounded-2xl shadow-soft-lg ring-1 ring-slate-900/5">
                    <iframe title="Carte Sitiame Capital" src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d496.52751049721695!2d-3.973826836675762!3d5.383384556810084!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xfc1931274d601d7%3A0xf37047dbf88f29ed!2sImmeuble%20CGK!5e0!3m2!1sfr!2sci!4v1690843095882!5m2!1sfr!2sci" class="h-72 w-full min-h-[18rem] border-0 sm:h-80" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-8 shadow-soft-lg sm:p-10">
                    <span class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-600">Contact</span>
                    <h3 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">Nous contacter</h3>
                    <p class="mt-2 text-slate-600">Notre équipe répond à vos demandes dans les meilleurs délais.</p>
                    <ul class="mt-8 space-y-4 text-slate-700">
                        <li class="flex items-start gap-3"><i class="fa fa-phone mt-0.5 text-brand-600"></i><span>+225 2724523043</span></li>
                        <li class="flex items-start gap-3"><i class="fa fa-phone mt-0.5 text-brand-600"></i><span>+225 0709161381</span></li>
                        <li class="flex items-start gap-3"><i class="fa fa-envelope mt-0.5 text-brand-600"></i><span>contact@sitiame-capital.com</span></li>
                        <li class="flex items-start gap-3"><i class="fa fa-home mt-0.5 text-brand-600"></i><span>Abidjan, Côte d'Ivoire</span></li>
                    </ul>
                    <div class="mt-8 rounded-xl border border-slate-100 bg-slate-50/80 p-5">
                        <p class="text-sm font-semibold text-slate-900">Newsletter</p>
                        <form class="mt-3 flex flex-col gap-3 sm:flex-row" action="#" method="post">
                            @csrf
                            <input type="email" name="email" placeholder="Votre adresse e-mail" class="flex-1 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none ring-brand-500/20 transition focus:border-brand-400 focus:ring-2">
                            <button type="button" class="rounded-xl bg-brand-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800">Souscrire</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-white/10 bg-slate-950 py-12 text-slate-300">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-3 lg:px-8">
            <div>
                <img src="https://sitiame-capital.com/assets/images/logo.png" alt="Logo Sitiame" class="h-12 w-auto opacity-95 sm:h-14">
                <p class="mt-4 text-sm leading-relaxed text-slate-400">Partenaire de confiance pour le conseil aux PME et l'investissement en capital.</p>
            </div>
            <div>
                <h4 class="text-sm font-semibold uppercase tracking-wider text-white">Liens</h4>
                <ul class="mt-4 space-y-2 text-sm">
                    <li><a href="{{ route('about-us') }}" class="text-slate-400 transition hover:text-white">À propos</a></li>
                    <li><a href="#expertise" class="text-slate-400 transition hover:text-white">Nos services</a></li>
                    <li><a href="{{ route('pricing') }}" class="text-slate-400 transition hover:text-white">Tarifs</a></li>
                    <li><a href="{{ route('documentation') }}" class="text-slate-400 transition hover:text-white">Documentation</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-sm font-semibold uppercase tracking-wider text-white">Formulaires</h4>
                <ul class="mt-4 space-y-2 text-sm text-slate-400">
                    <li>Présentation de projet</li>
                    <li>Adhésion personne morale</li>
                    <li>Adhésion personne physique</li>
                </ul>
            </div>
        </div>
        <p class="mt-10 border-t border-white/10 pt-8 text-center text-xs text-slate-500">SITIAME CAPITAL © {{ date('Y') }} · Tous droits réservés</p>
    </footer>
</body>
</html>
