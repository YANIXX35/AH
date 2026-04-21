<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @if(config('services.recaptcha.site_key'))
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif
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
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-sm">
                        <i class="fas fa-cube text-white text-3xl"></i>
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
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="rounded border-orange-300 text-orange-500 focus:ring-orange-500" />
                            <span>Se souvenir de moi</span>
                        </label>
                        <a href="{{ route('password.request') }}" class="text-sm font-medium text-orange-600 hover:text-orange-700 transition">Mot de passe oublié ?</a>
                    </div>

                    @if(config('services.recaptcha.site_key'))
                        <div>
                            <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
                        </div>
                    @endif

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
