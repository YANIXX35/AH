<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion</title>
    <link rel="icon" type="image/png" href="{{ asset('images/sitiam.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/sitiam.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="min-h-screen bg-white">
    <div class="flex min-h-screen">
        <!-- Left Side - Branding -->
        <div class="hidden w-2/5 overflow-hidden lg:flex flex-col justify-between relative">
            <img src="https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=800&h=1000&fit=crop" alt="" class="absolute inset-0 h-full w-full object-cover" />
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
                    <h2 class="text-4xl font-bold">Sitiame Capitale</h2>
                    <p class="text-white/90 text-lg leading-relaxed">Accédez à votre espace professionnel sécurisé. Une plateforme moderne et performante pour gérer vos activités.</p>
                </div>
            </div>
            <div class="relative space-y-4 text-sm text-white/80 p-12">
                <div class="flex items-start gap-3">
                    <i class="fas fa-check-circle mt-1 text-white/60"></i>
                    <span>Authentification sécurisée</span>
                </div>
                <div class="flex items-start gap-3">
                    <i class="fas fa-check-circle mt-1 text-white/60"></i>
                    <span>Interface moderne et intuitive</span>
                </div>
                <div class="flex items-start gap-3">
                    <i class="fas fa-check-circle mt-1 text-white/60"></i>
                    <span>Support 24/7 disponible</span>
                </div>
            </div>
        </div>

        <!-- Right Side - Form -->
        <div class="flex w-full lg:w-3/5 items-center justify-center px-6 py-12 sm:px-12">
            <div class="w-full max-w-md space-y-8">
                <!-- Header -->
                <div class="text-center lg:text-left">
                    <div class="mb-5 flex justify-center lg:justify-start">
                        <img src="{{ asset('images/sitiam.png') }}" alt="Logo Sitiame Capital" class="h-12 w-auto">
                    </div>
                    <p class="text-sm font-medium text-orange-600 uppercase tracking-[0.3em]">Veuillez entrer vos identifiants</p>
                    <h1 class="mt-4 text-4xl font-bold text-slate-900">Bienvenue</h1>
                </div>

                <!-- Form -->
                <form method="POST" action="{{ route('login.post') }}" class="space-y-6">
                    @csrf

                    @if(session('status'))
                        <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">
                            {{ session('status') }}
                        </div>
                    @endif
                    
                    <!-- Email Field -->
                    <div>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required placeholder="Email ou pseudo" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition" />
                    </div>

                    <!-- Password Field -->
                    <div>
                        <div class="relative">
                            <input id="password" name="password" type="password" required placeholder="Mot de passe" class="w-full rounded-xl border border-orange-300 bg-white px-4 py-3 pr-12 text-slate-900 placeholder:text-slate-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200 transition" />
                            <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 px-4 text-slate-500 hover:text-slate-700" aria-label="Afficher/masquer le mot de passe">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p id="passwordStrengthText" class="mt-1 text-xs text-slate-500">Force du mot de passe: —</p>
                    </div>

                    <!-- Remember & Forgot Password -->
                    <div class="flex items-center justify-between gap-4">
                        <label class="flex items-start gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="mt-0.5 rounded border-orange-300 text-orange-500 focus:ring-orange-500" />
                            <span>
                                <span class="block font-medium text-slate-700">Se souvenir de moi (30 jours)</span>
                                <span class="block text-xs text-amber-700">A utiliser uniquement sur un appareil personnel.</span>
                            </span>
                        </label>
                        <a href="{{ route('password.request') }}" class="text-sm font-medium text-orange-600 hover:text-orange-700 transition">Mot de passe oublié ?</a>
                    </div>

                    <!-- Error Messages -->
                    @if ($errors->login->any())
                        <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                            <ul class="space-y-2 text-sm text-red-700">
                                @foreach ($errors->login->all() as $error)
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
                        Se connecter
                    </button>
                </form>

                <!-- Sign Up Link -->
                <div class="text-center">
                    <p class="text-slate-600">
                        Pas encore de compte ?
                        <a href="{{ route('register') }}" class="font-semibold text-orange-600 hover:text-orange-700 transition">Créer un compte</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <!-- Floating Support Service Client Button -->
    <div class="fixed bottom-4 right-4 z-50">
        <a href="mailto:contact@sitiame-capital.com?subject=Demande%20d'assistance%20-%20Service%20Client%20Sitiame%20Capital&body=Bonjour%20Service%20Client%20Sitiame%20Capital,%0A%0AMon%20message%20:%20" class="group flex items-center gap-2.5 rounded-full bg-gradient-to-r from-orange-500 to-amber-600 p-3 sm:px-5 sm:py-3.5 text-white shadow-xl hover:shadow-2xl hover:scale-105 transition-all duration-200 border border-white/20" title="Contacter le Service Client (contact@sitiame-capital.com)">
            <div class="flex h-8 w-8 sm:h-9 sm:w-9 items-center justify-center rounded-full bg-white/20 backdrop-blur-sm group-hover:bg-white/30 transition">
                <i class="fas fa-envelope text-white text-base sm:text-lg"></i>
            </div>
            <div class="hidden sm:flex flex-col text-left">
                <span class="text-[10px] font-bold uppercase tracking-widest text-orange-100">Service Client</span>
                <span class="text-xs font-extrabold text-white">contact@sitiame-capital.com</span>
            </div>
        </a>
    </div>

    <script>
        (function () {
            const password = document.getElementById('password');
            const toggle = document.getElementById('togglePassword');
            const strength = document.getElementById('passwordStrengthText');
            if (!password || !toggle || !strength) return;

            toggle.addEventListener('click', function () {
                const asText = password.type === 'password';
                password.type = asText ? 'text' : 'password';
                this.innerHTML = asText ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
            });

            const scorePassword = function (value) {
                let score = 0;
                if (value.length >= 8) score++;
                if (/[A-Z]/.test(value)) score++;
                if (/[a-z]/.test(value)) score++;
                if (/[0-9]/.test(value)) score++;
                if (/[^A-Za-z0-9]/.test(value)) score++;
                return score;
            };

            password.addEventListener('input', function () {
                const value = this.value || '';
                const score = scorePassword(value);
                if (value.length === 0) {
                    strength.textContent = 'Force du mot de passe: —';
                    strength.className = 'mt-1 text-xs text-slate-500';
                } else if (score <= 2) {
                    strength.textContent = 'Force du mot de passe: Faible';
                    strength.className = 'mt-1 text-xs text-red-600';
                } else if (score === 3 || score === 4) {
                    strength.textContent = 'Force du mot de passe: Moyenne';
                    strength.className = 'mt-1 text-xs text-amber-600';
                } else {
                    strength.textContent = 'Force du mot de passe: Forte';
                    strength.className = 'mt-1 text-xs text-green-600';
                }
            });
        })();
    </script>
</body>
</html>
