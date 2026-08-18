<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    @include('site.public-head', [
        'title' => 'Vérification de document | SITIAME CAPITAL',
        'description' => 'Vérifiez l\'authenticité d\'un document comptable généré par SITIAME CAPITAL.',
    ])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased flex items-center justify-center px-4 py-12">

    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-slate-700">
                <img src="{{ asset('images/sitiam.png') }}" alt="SITIAME CAPITAL" class="h-8 w-auto">
                SITIAME CAPITAL
            </a>
        </div>

        @if($verification)
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-emerald-600 px-6 py-5 text-white text-center">
                    <i class="fa-solid fa-circle-check text-2xl mb-2"></i>
                    <p class="text-sm font-semibold uppercase tracking-wide">Document authentique</p>
                </div>
                <div class="px-6 py-6 space-y-4">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400">Entreprise</p>
                        <p class="text-base font-semibold text-slate-900">{{ $verification->company_name }}</p>
                        @if($verification->company_sigle)
                            <p class="text-sm text-slate-500">{{ $verification->company_sigle }}</p>
                        @endif
                    </div>

                    @if($verification->company_tax_id)
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-400">N° d'identification fiscale</p>
                            <p class="text-sm text-slate-700">{{ $verification->company_tax_id }}</p>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-400">Type de document</p>
                            <p class="text-sm text-slate-700">{{ $verification->type === 'liasse' ? 'Liasse fiscale BCEAO' : 'Bilan' }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-400">Exercice</p>
                            <p class="text-sm text-slate-700">{{ $verification->exercise_year }}</p>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-4 grid grid-cols-1 gap-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-500">Total Actif</span>
                            <span class="text-sm font-semibold text-slate-900">{{ number_format($verification->total_actif ?? 0, 0, ',', ' ') }} FCFA</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-500">Total Passif</span>
                            <span class="text-sm font-semibold text-slate-900">{{ number_format($verification->total_passif ?? 0, 0, ',', ' ') }} FCFA</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-500">Résultat net</span>
                            <span class="text-sm font-semibold {{ ($verification->resultat_net ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ number_format($verification->resultat_net ?? 0, 0, ',', ' ') }} FCFA</span>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-4 flex items-center justify-between text-xs text-slate-400">
                        <span>Généré le {{ $verification->generated_at->format('d/m/Y à H:i') }}</span>
                        <span class="font-mono">{{ $verification->reference }}</span>
                    </div>
                </div>
            </div>
            <p class="text-center text-xs text-slate-400 mt-4">Ces données sont un résumé de vérification et ne remplacent pas le document original.</p>
        @else
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-red-600 px-6 py-5 text-white text-center">
                    <i class="fa-solid fa-triangle-exclamation text-2xl mb-2"></i>
                    <p class="text-sm font-semibold uppercase tracking-wide">Référence introuvable</p>
                </div>
                <div class="px-6 py-6">
                    <p class="text-sm text-slate-600">Aucun document ne correspond à la référence <span class="font-mono">{{ $reference }}</span>. Le document a peut-être été régénéré depuis, ou la référence est invalide.</p>
                </div>
            </div>
        @endif
    </div>

</body>
</html>
