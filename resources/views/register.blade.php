<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inscription Entreprise</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-sm">
                        <i class="fas fa-building text-white text-3xl"></i>
                    </div>
                    <h2 class="text-4xl font-bold">Sitiame Capitale</h2>
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

                <!-- Form -->
                <form method="POST" action="{{ route('register.post') }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    
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
                        <input id="company_logo" name="company_logo" type="file" accept="image/*" capture="environment" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 text-slate-900 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition" />
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

                    <!-- City & Phone -->
                    <div class="grid grid-cols-2 gap-4">
                        <input id="city" name="city" type="text" placeholder="Ville" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition" />
                        <input id="phone" name="phone" type="tel" placeholder="Téléphone" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition" />
                    </div>

                    <!-- Contact Person Name -->
                    <div>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required placeholder="Nom du responsable" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition" />
                    </div>

                    <!-- Email -->
                    <div>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required placeholder="Email professionnel" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition" />
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

                    <!-- Password Fields -->
                    <div class="grid grid-cols-2 gap-4">
                        <input id="password" name="password" type="password" required placeholder="Mot de passe" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition" />
                        <input id="password_confirmation" name="password_confirmation" type="password" required placeholder="Confirmer" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition" />
                    </div>

                    <!-- Terms Checkbox -->
                    <label class="flex items-start gap-3 text-sm text-slate-600">
                        <input type="checkbox" required class="mt-1 rounded border-orange-300 text-orange-500 focus:ring-orange-500" />
                        <span>J'accepte les <a href="#" class="font-semibold text-orange-600 hover:text-orange-700">conditions d'utilisation</a> et la <a href="#" class="font-semibold text-orange-600 hover:text-orange-700">politique de confidentialité</a></span>
                    </label>

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

                    <!-- Submit Button -->
                    <button type="submit" class="w-full rounded-xl bg-orange-500 px-4 py-3 font-semibold text-white shadow-md hover:bg-orange-600 transition">
                        Créer mon compte entreprise
                    </button>
                </form>

                <!-- Divider -->
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-orange-200"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="bg-white px-2 text-slate-600">Ou</span>
                    </div>
                </div>

                <!-- Google Sign In -->
                <button type="button" class="w-full flex items-center justify-center gap-2 rounded-xl border border-orange-300 bg-white px-4 py-3 font-semibold text-slate-900 hover:bg-orange-50 transition">
                    <svg class="h-5 w-5" viewBox="0 0 24 24">
                        <path fill="#EA4335" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#4A90E2" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#FBBC05" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    <span>Continuer avec Google</span>
                </button>

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
</body>
</html>
