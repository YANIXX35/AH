<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inscription Entreprise</title>
    <link rel="icon" type="image/png" href="{{ asset('images/sitiam.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/sitiam.png') }}">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#0f172a">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-white">
    <div class="flex min-h-screen">
        <!-- Left Side - Branding -->
        <div class="hidden w-2/5 overflow-hidden lg:flex flex-col justify-between relative">
            <img src="https://images.unsplash.com/photo-1521737711867-e3b97375f902?w=800&h=1000&fit=crop" alt="" class="absolute inset-0 h-full w-full object-cover" />
            <div class="absolute inset-0 bg-black/40"></div>
            <div class="relative space-y-8 text-white p-12">
                <a href="/" class="inline-flex items-center gap-3 text-white hover:opacity-80 transition">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </div>
                </a>
                <div class="space-y-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-sm p-2">
                        <img src="{{ asset('images/sitiam.png') }}" alt="Logo Sitiame Capital" class="h-full w-auto">
                    </div>
                    <h2 class="text-4xl font-bold">Sitiame Capital</h2>
                    <p class="text-white/90 text-lg leading-relaxed">Enregistrez votre entreprise et gérez votre présence digitale</p>
                </div>
            </div>
            <div class="relative space-y-4 text-sm text-white/80 p-12">
                <div class="flex items-start gap-3">
                    <i class="fas fa-check-circle mt-1 text-white/60"></i>
                    <span>Inscription rapide et sécurisée</span>
                </div>
                <div class="flex items-start gap-3">
                    <i class="fas fa-check-circle mt-1 text-white/60"></i>
                    <span>Gestion centralisée de votre entreprise</span>
                </div>
                <div class="flex items-start gap-3">
                    <i class="fas fa-check-circle mt-1 text-white/60"></i>
                    <span>Accès à tous les outils professionnels</span>
                </div>
            </div>
        </div>

        <!-- Right Side - Form -->
        <div class="flex w-full lg:w-3/5 items-center justify-center px-6 py-8 sm:px-12 overflow-y-auto">
            <div class="w-full max-w-md space-y-6">
                <!-- Header -->
                <div class="text-center lg:text-left">
                    <p class="text-sm font-medium text-orange-600 uppercase tracking-[0.3em]">Créer un compte entreprise</p>
                    <h1 class="mt-4 text-4xl font-bold text-slate-900">Bienvenue</h1>
                </div>

                <div class="rounded-xl border border-orange-200 bg-orange-50 px-4 py-3">
                    <div class="mb-2 flex items-center justify-between text-xs font-semibold uppercase tracking-[0.2em] text-orange-700">
                        <span>Etape <span id="register-step-number">1</span>/2</span>
                        <span id="register-step-label">Compte</span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-orange-100">
                        <div id="register-step-progress" class="h-full w-1/2 rounded-full bg-orange-500 transition-all duration-300"></div>
                    </div>
                </div>

                <!-- Form -->
                <form id="register-form" method="POST" action="{{ route('register.post') }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    <div id="register-step-1" class="space-y-5">
                        <div>
                            <input id="name" name="name" type="text" value="{{ old('name') }}" placeholder="Nom du responsable *" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition" />
                        </div>
                        <div>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="Email professionnel *" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition" />
                        </div>
                        <div>
                            <input id="phone" name="phone" type="tel" placeholder="Téléphone" value="{{ old('phone') }}" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <input id="password" name="password" type="password" placeholder="Mot de passe *" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition" />
                            <input id="password_confirmation" name="password_confirmation" type="password" placeholder="Confirmer *" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition" />
                        </div>
                        <div class="flex justify-end">
                            <button id="register-next-step" type="button" class="rounded-xl bg-orange-500 px-4 py-3 font-semibold text-white shadow-md hover:bg-orange-600 transition">
                                Continuer (étape 2)
                            </button>
                        </div>
                    </div>

                    <div id="register-step-2" class="hidden space-y-5">
                        <!-- Company Name -->
                        <div>
                            <input id="company_name" name="company_name" type="text" placeholder="Nom de l'entreprise" value="{{ old('company_name') }}" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition" />
                        </div>

                        <!-- Company Sigle -->
                        <div>
                            <input id="company_sigle" name="company_sigle" type="text" placeholder="Sigle usuel" value="{{ old('company_sigle') }}" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition" />
                        </div>

                        <!-- Company Tax ID -->
                        <div>
                            <input id="company_tax_id" name="company_tax_id" type="text" placeholder="N° d'identification fiscale (NIF)" value="{{ old('company_tax_id') }}" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition @error('company_tax_id') border-red-500 @enderror" />
                            <p class="mt-1 text-xs text-slate-500">Si vous utilisez une <strong>clé de licence</strong>, le NIF doit être identique à celui de votre entreprise (une licence = une seule entreprise).</p>
                            @error('company_tax_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <!-- Company Logo -->
                        <div>
                            <label for="company_logo" class="sr-only">Logo de l'entreprise</label>
                            <input id="company_logo" name="company_logo" type="file" accept="image/*,.pdf,.doc,.docx" capture="environment" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 text-slate-900 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition" />
                        </div>

                        <!-- Industry Sector -->
                        <div>
                            <select id="sector" name="sector" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 text-slate-900 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition">
                                <option selected disabled>Secteur d'activité</option>
                                <option>Technologies & IT</option>
                                <option>Commerce & Retail</option>
                                <option>Services</option>
                                <option>Industrie</option>
                                <option>Santé</option>
                                <option>Education</option>
                                <option>Autres</option>
                            </select>
                        </div>

                        <!-- Registration Number -->
                        <div>
                            <input id="rccm" name="rccm" type="text" placeholder="Numéro RCCM / SIRET" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition" />
                        </div>

                        <!-- Trade Register File Upload -->
                        <div>
                            <label for="trade_register" class="mb-2 block text-sm font-medium text-slate-700">
                                <i class="fas fa-file-pdf text-orange-600 mr-2"></i>
                                Registre de commerce (optionnel)
                            </label>
                            <input id="trade_register" name="trade_register" type="file" accept=".pdf,.jpg,.jpeg,.png,image/*" capture="environment" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 text-slate-900 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-orange-100 file:text-orange-700 hover:file:bg-orange-200 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition" />
                            <p class="mt-1 text-xs text-slate-500">PDF ou photo (max. 5 Mo). Sur mobile : vous pouvez scanner le document avec l’appareil photo.</p>
                        </div>

                        <!-- Company Address -->
                        <div>
                            <input id="address" name="address" type="text" placeholder="Adresse complète" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition" />
                        </div>

                        <!-- City -->
                        <div>
                            <input id="city" name="city" type="text" placeholder="Ville" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition" />
                        </div>

                        <!-- Clé de licence (membre d’une équipe déjà sous contrat) -->
                        <div>
                            <label for="license_key" class="sr-only">Clé de licence entreprise</label>
                            <input id="license_key" name="license_key" type="text" value="{{ old('license_key') }}" autocomplete="off" placeholder="Clé de licence (optionnel — 2ᵉ, 3ᵉ utilisateur…)" class="w-full rounded-xl border border-orange-200 bg-orange-50/50 px-4 py-3 text-slate-900 placeholder:text-slate-500 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition" />
                            <p class="mt-1 text-xs text-slate-500">Fournie par l’administrateur ; la clé ne peut servir qu’à une entreprise (même NIF), jusqu’à {{ config('licensing.enterprise_max_users_per_license') }} comptes.</p>
                            @error('license_key')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Terms Checkbox -->
                        <label class="flex items-start gap-3 text-sm text-slate-600">
                            <input id="register-terms" type="checkbox" required class="mt-1 rounded border-orange-300 text-orange-500 focus:ring-orange-500" />
                            <span>J'accepte les <a href="#" class="font-semibold text-orange-600 hover:text-orange-700">conditions d'utilisation</a> et la <a href="#" class="font-semibold text-orange-600 hover:text-orange-700">politique de confidentialité</a></span>
                        </label>

                        <div class="flex items-center justify-between gap-3">
                            <button id="register-prev-step" type="button" class="rounded-xl border border-orange-300 px-4 py-3 font-semibold text-orange-700 hover:bg-orange-50 transition">
                                Retour
                            </button>
                            <button type="submit" class="rounded-xl bg-orange-500 px-4 py-3 font-semibold text-white shadow-md hover:bg-orange-600 transition">
                                Créer mon compte entreprise
                            </button>
                        </div>
                    </div>

                    <!-- Error Messages -->
                    @if ($errors->any())
                        <div class="rounded-xl border border-red-200 bg-red-50 p-4">
                            <ul class="space-y-2 text-sm text-red-700">
                                @foreach ($errors->all() as $error)
                                    <li class="flex items-start gap-2">
                                        <i class="fas fa-exclamation-circle mt-0.5 shrink-0"></i>
                                        <span>{{ $error }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                </form>

                <!-- Login Link -->
                <div class="text-center">
                    <p class="text-slate-600">
                        Vous avez un compte ?
                        <a href="{{ route('login') }}" class="font-semibold text-orange-600 hover:text-orange-700 transition">Se connecter</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function () {
            const step1 = document.getElementById('register-step-1');
            const step2 = document.getElementById('register-step-2');
            const nextBtn = document.getElementById('register-next-step');
            const prevBtn = document.getElementById('register-prev-step');
            const stepNumber = document.getElementById('register-step-number');
            const stepLabel = document.getElementById('register-step-label');
            const stepProgress = document.getElementById('register-step-progress');
            const form = document.getElementById('register-form');

            if (!step1 || !step2 || !nextBtn || !prevBtn || !stepNumber || !stepLabel || !stepProgress || !form) {
                return;
            }

            function setStep(step) {
                const isStep1 = step === 1;
                step1.classList.toggle('hidden', !isStep1);
                step2.classList.toggle('hidden', isStep1);
                stepNumber.textContent = String(step);
                stepLabel.textContent = isStep1 ? 'Compte' : 'Entreprise';
                stepProgress.classList.toggle('w-1/2', isStep1);
                stepProgress.classList.toggle('w-full', !isStep1);
            }

            function validateStep1() {
                const name = document.getElementById('name');
                const email = document.getElementById('email');
                const password = document.getElementById('password');
                const passwordConfirmation = document.getElementById('password_confirmation');

                if (!name || !email || !password || !passwordConfirmation) {
                    return false;
                }

                if (!name.value.trim()) {
                    name.focus();
                    alert('Le nom du responsable est obligatoire.');
                    return false;
                }
                if (!email.value.trim()) {
                    email.focus();
                    alert('L’email professionnel est obligatoire.');
                    return false;
                }
                if (!password.value) {
                    password.focus();
                    alert('Le mot de passe est obligatoire.');
                    return false;
                }
                if (password.value.length < 8) {
                    password.focus();
                    alert('Le mot de passe doit contenir au moins 8 caractères.');
                    return false;
                }
                if (password.value !== passwordConfirmation.value) {
                    passwordConfirmation.focus();
                    alert('La confirmation du mot de passe ne correspond pas.');
                    return false;
                }

                return true;
            }

            nextBtn.addEventListener('click', function () {
                if (!validateStep1()) {
                    return;
                }
                setStep(2);
            });

            prevBtn.addEventListener('click', function () {
                setStep(1);
            });

            form.addEventListener('submit', function (event) {
                if (!validateStep1()) {
                    event.preventDefault();
                    setStep(1);
                    return;
                }
                const terms = document.getElementById('register-terms');
                if (terms && !terms.checked) {
                    event.preventDefault();
                    setStep(2);
                    terms.focus();
                    alert('Veuillez accepter les conditions d’utilisation.');
                }
            });

            const hasErrors = {{ $errors->any() ? 'true' : 'false' }};
            if (hasErrors) {
                const step2Fields = ['company_name', 'company_sigle', 'company_tax_id', 'license_key', 'sector', 'rccm', 'address', 'city'];
                const hasStep2Data = step2Fields.some(function (field) {
                    const el = document.getElementById(field);
                    return el && String(el.value || '').trim() !== '';
                });
                setStep(hasStep2Data ? 2 : 1);
            } else {
                setStep(1);
            }
        })();

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/service-worker.js');
            });
        }
    </script>

    @include('partials.posthog')
</body>
</html>
