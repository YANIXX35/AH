<?php

namespace App\Http\Controllers;

use App\Models\CommercialDocument;
use App\Models\CommercialFeedback;
use App\Models\Prospect;
use App\Models\User;
use App\Services\CommercialCommissionService;
use App\Services\CompanyDocumentParserService;
use App\Services\UserPremiumService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommercialController extends Controller
{
    public function __construct(
        private readonly UserPremiumService $userPremium,
        private readonly CommercialCommissionService $commission
    ) {}

    public function index(Request $request): View
    {
        $commercial = $request->user();
        if (! $commercial) {
            abort(403);
        }

        try {
            $clients = ($commercial->is_platform_admin ?? false)
                ? User::query()->clients()->latest()->get()
                : $commercial->createdClients()->latest()->get();
        } catch (\Throwable $e) {
            $clients = collect();
        }

        try {
            $prospects = Prospect::query()
                ->when(! ($commercial->is_platform_admin ?? false), fn ($q) => $q->where('commercial_user_id', $commercial->id))
                ->latest()
                ->get();
        } catch (\Throwable $e) {
            $prospects = collect();
        }

        $totalClients = $clients->count();

        // Clients encore dans leur période d'essai d'1 mois
        $activeTrials = $clients->filter(function ($client) {
            if (! $client) {
                return false;
            }
            // Si un premium_trial_ends_at explicite est défini, on s'y réfère
            if (! empty($client->premium_trial_ends_at)) {
                try {
                    $trialEndsAt = $client->premium_trial_ends_at instanceof Carbon
                        ? $client->premium_trial_ends_at
                        : Carbon::parse($client->premium_trial_ends_at);

                    return $trialEndsAt->isFuture();
                } catch (\Throwable $e) {
                    return false;
                }
            }
            // Sinon, on considère que l'essai est le premier mois depuis la création du compte
            try {
                $createdAt = $client->created_at instanceof Carbon
                    ? $client->created_at
                    : Carbon::parse($client->created_at);

                return $createdAt->isAfter(now()->subMonth());
            } catch (\Throwable $e) {
                return false;
            }
        })->count();

        // Clients dont la période d'essai est terminée
        $portfolioTrialExpired = max(0, $totalClients - $activeTrials);

        // Clients convertis : essai terminé + premium actif
        $portfolioConverted = $clients->filter(function ($client) {
            if (! $client) {
                return false;
            }
            if (! ($client->is_premium ?? false)) {
                return false;
            }
            // Vérifier que l'essai est bien terminé (compte > 1 mois ou trial_ends_at passé)
            $trialExpired = false;
            if (! empty($client->premium_trial_ends_at)) {
                try {
                    $trialEndsAt = $client->premium_trial_ends_at instanceof Carbon
                        ? $client->premium_trial_ends_at
                        : Carbon::parse($client->premium_trial_ends_at);
                    $trialExpired = $trialEndsAt->isPast();
                } catch (\Throwable $e) {
                }
            } else {
                try {
                    $createdAt = $client->created_at instanceof Carbon
                        ? $client->created_at
                        : Carbon::parse($client->created_at);
                    $trialExpired = $createdAt->isBefore(now()->subMonth());
                } catch (\Throwable $e) {
                }
            }

            return $trialExpired;
        })->count();

        // Clients partis : essai terminé + pas premium
        $portfolioChurned = max(0, $portfolioTrialExpired - $portfolioConverted);

        // Taux de conversion (pourcentage)
        $conversionRate = $portfolioTrialExpired > 0
            ? round(($portfolioConverted / $portfolioTrialExpired) * 100)
            : 0;

        $expiredTrials = $portfolioTrialExpired; // alias pour compat

        $totalProspects = $prospects->count();
        $newProspects = $prospects->where('status', 'nouveau')->count();
        $qualifiedProspects = $prospects->where('status', 'qualifie')->count();
        $convertedProspects = $prospects->where('status', 'client')->count();

        // Évolution cumulée du portefeuille sur les 6 derniers mois
        $clientsByMonth = $clients->groupBy(function ($client) {
            try {
                $createdAt = $client->created_at instanceof Carbon
                    ? $client->created_at
                    : Carbon::parse($client->created_at);

                return $createdAt->format('Y-m');
            } catch (\Throwable $e) {
                return 'inconnu';
            }
        });
        $growthWindowStart = now()->subMonths(5)->startOfMonth();
        $cumulative = $clients->filter(function ($client) use ($growthWindowStart) {
            try {
                $createdAt = $client->created_at instanceof Carbon
                    ? $client->created_at
                    : Carbon::parse($client->created_at);

                return $createdAt->lt($growthWindowStart);
            } catch (\Throwable $e) {
                return false;
            }
        })->count();
        $growthLabels = [];
        $growthData = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = now()->subMonths($i);
            $key = $monthDate->format('Y-m');
            $cumulative += $clientsByMonth->get($key, collect())->count();
            $growthLabels[] = $monthDate->format('m/Y');
            $growthData[] = $cumulative;
        }

        // Recent activity feed
        $recentClients = $clients->sortByDesc('created_at')->take(5);
        $recentProspects = $prospects->sortByDesc('created_at')->take(5);
        $recentActivities = $recentClients->map(function ($c) {
            return [
                'type' => 'client',
                'name' => $c->company_name ?: ($c->name ?? 'Client'),
                'company_name' => $c->company_name,
                'date' => $c->created_at,
            ];
        })->merge($recentProspects->map(function ($p) {
            return [
                'type' => 'prospect',
                'name' => $p->company_name ?: ($p->name ?? 'Prospect'),
                'company_name' => $p->company_name,
                'date' => $p->created_at,
            ];
        }))->sortByDesc('date')->take(10);

        return view('commercial.dashboard', compact(
            'clients',
            'prospects',
            'totalClients',
            'activeTrials',
            'expiredTrials',
            'portfolioTrialExpired',
            'portfolioConverted',
            'portfolioChurned',
            'growthLabels',
            'growthData',
            'conversionRate',
            'totalProspects',
            'newProspects',
            'qualifiedProspects',
            'convertedProspects',
            'recentActivities'
        ));
    }

    /**
     * Solde du commercial : commission d'ajout de client (10 000 F pour chacun des 3
     * premiers clients ajoutés à vie, 7 500 F pour chaque client suivant) + 1 500 F
     * pour chaque renouvellement d'abonnement réellement payé (CinetPay) par un client
     * apporté par ce commercial, après la fin de son essai gratuit.
     */
    public function balance(Request $request): View
    {
        $commercial = $request->user();
        if (! $commercial) {
            abort(403);
        }

        $balance = $this->commission->calculateBalance($commercial);

        return view('commercial.balance', [
            'rows' => $balance['rows'],
            'totalBalance' => $balance['totalBalance'],
            'totalSignupEarnings' => $balance['totalSignupEarnings'],
            'totalRenewalEarnings' => $balance['totalRenewalEarnings'],
            'totalClients' => $balance['totalClients'],
            'tier1Slots' => CommercialCommissionService::TIER1_SLOTS,
            'signupBonusTier1' => CommercialCommissionService::SIGNUP_BONUS_TIER1,
            'signupBonusTier2' => CommercialCommissionService::SIGNUP_BONUS_TIER2,
            'renewalBonus' => CommercialCommissionService::RENEWAL_BONUS,
        ]);
    }

    public function portefeuille(Request $request): View
    {
        $commercial = $request->user();
        if (! $commercial) {
            abort(403);
        }

        $search = $request->query('search');

        try {
            $query = ($commercial->is_platform_admin ?? false)
                ? User::query()->clients()
                : $commercial->createdClients();

            if (! empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            $clients = $query->latest()->get();
        } catch (\Throwable $e) {
            $clients = collect();
        }

        // For KPI stats, let's load all clients (unfiltered by search) to calculate KPIs accurately
        try {
            $allClients = ($commercial->is_platform_admin ?? false)
                ? User::query()->clients()->get()
                : $commercial->createdClients()->get();
        } catch (\Throwable $e) {
            $allClients = collect();
        }

        $totalClients = $allClients->count();

        // Clients encore dans leur période d'essai d'1 mois
        $activeTrials = $allClients->filter(function ($client) {
            if (! $client) {
                return false;
            }
            if (! empty($client->premium_trial_ends_at)) {
                try {
                    $trialEndsAt = $client->premium_trial_ends_at instanceof Carbon
                        ? $client->premium_trial_ends_at
                        : Carbon::parse($client->premium_trial_ends_at);

                    return $trialEndsAt->isFuture();
                } catch (\Throwable $e) {
                    return false;
                }
            }
            try {
                $createdAt = $client->created_at instanceof Carbon
                    ? $client->created_at
                    : Carbon::parse($client->created_at);

                return $createdAt->isAfter(now()->subMonth());
            } catch (\Throwable $e) {
                return false;
            }
        })->count();

        // Clients dont la période d'essai est terminée
        $portfolioTrialExpired = max(0, $totalClients - $activeTrials);

        // Clients convertis : essai terminé + premium actif
        $portfolioConverted = $allClients->filter(function ($client) {
            if (! $client) {
                return false;
            }
            if (! ($client->is_premium ?? false)) {
                return false;
            }
            $trialExpired = false;
            if (! empty($client->premium_trial_ends_at)) {
                try {
                    $trialEndsAt = $client->premium_trial_ends_at instanceof Carbon
                        ? $client->premium_trial_ends_at
                        : Carbon::parse($client->premium_trial_ends_at);
                    $trialExpired = $trialEndsAt->isPast();
                } catch (\Throwable $e) {
                }
            } else {
                try {
                    $createdAt = $client->created_at instanceof Carbon
                        ? $client->created_at
                        : Carbon::parse($client->created_at);
                    $trialExpired = $createdAt->isBefore(now()->subMonth());
                } catch (\Throwable $e) {
                }
            }

            return $trialExpired;
        })->count();

        // Clients partis : essai terminé + pas premium
        $portfolioChurned = max(0, $portfolioTrialExpired - $portfolioConverted);

        // Taux de conversion (pourcentage)
        $conversionRate = $portfolioTrialExpired > 0
            ? round(($portfolioConverted / $portfolioTrialExpired) * 100)
            : 0;

        $totalProspects = Prospect::query()
            ->when(! ($commercial->is_platform_admin ?? false), fn ($q) => $q->where('commercial_user_id', $commercial->id))
            ->count();

        return view('commercial.portefeuille', compact(
            'clients',
            'totalClients',
            'activeTrials',
            'portfolioTrialExpired',
            'portfolioConverted',
            'portfolioChurned',
            'conversionRate',
            'totalProspects',
            'search'
        ));
    }

    public function showcase(): View
    {
        return view('commercial.showcase');
    }

    /**
     * Enregistre l'avis de satisfaction du commercial sur l'accompagnement
     * SITIAME (widget "Votre avis compte" du Kit Marketing). Auparavant un
     * simple alert() JS sans aucune sauvegarde côté serveur.
     */
    public function storeFeedback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'satisfaction_label' => ['required', 'string', Rule::in([
                'Très satisfait', 'Satisfait', 'Moyen', 'Peu satisfait',
            ])],
        ]);

        CommercialFeedback::create([
            'user_id' => $request->user()->id,
            'rating' => $validated['rating'],
            'satisfaction_label' => $validated['satisfaction_label'],
        ]);

        return response()->json(['ok' => true, 'message' => 'Merci pour votre évaluation ! Notre équipe en prend note.']);
    }

    public function guides(): View
    {
        return view('commercial.guides');
    }

    /**
     * Sert les PDF réels des guides d'expertise depuis storage/app/public/guides/.
     * Aucun contenu n'est généré ici : si le fichier n'a pas encore été déposé par
     * un administrateur, on le dit clairement au lieu de rediriger silencieusement
     * vers une autre page comme le faisait l'ancien bouton.
     */
    public function downloadGuide(string $slug): RedirectResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $guides = [
            'bilan-syscohada' => ['file' => 'guide-1-bilan-syscohada.pdf', 'label' => 'Guide 1 : Réussir son Bilan SYSCOHADA Révisé'],
            'tresorerie-mobile-money' => ['file' => 'guide-2-tresorerie-mobile-money.pdf', 'label' => 'Guide 2 : Trésorerie & Mobile Money'],
            'investor-readiness' => ['file' => 'guide-3-investor-readiness.pdf', 'label' => 'Guide 3 : Investor Readiness & Levée de Fonds'],
        ];

        if (! isset($guides[$slug])) {
            abort(404);
        }

        $relativePath = 'guides/'.$guides[$slug]['file'];

        if (! Storage::disk('public')->exists($relativePath)) {
            return redirect()
                ->route('commercial.guides')
                ->with('error', '« '.$guides[$slug]['label'].' » n\'est pas encore disponible. Contactez un administrateur pour le mettre en ligne.');
        }

        return response()->download(
            Storage::disk('public')->path($relativePath),
            $guides[$slug]['file']
        );
    }

    public function club(): View
    {
        return view('commercial.club');
    }

    public function importFile(Request $request): View
    {
        $user = $request->user();
        try {
            $savedDocuments = CommercialDocument::query()
                ->when(! ($user->is_platform_admin ?? false), fn ($q) => $q->where('user_id', $user->id))
                ->latest()
                ->get();
        } catch (\Throwable $e) {
            $savedDocuments = collect();
        }

        return view('commercial.import', compact('savedDocuments'));
    }

    public function storeDocument(Request $request): RedirectResponse
    {
        $request->validate([
            'document_file' => ['required', 'file', 'max:20480'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $file = $request->file('document_file');

        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getClientMimeType() ?: $file->getClientOriginalExtension();
        $fileSize = $file->getSize();

        $path = $file->store('commercial_documents', 'public');

        CommercialDocument::create([
            'user_id' => $user->id,
            'original_name' => $originalName,
            'file_path' => $path,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'notes' => $request->input('notes'),
        ]);

        return redirect()->route('commercial.import')->with('status', 'Fichier "'.$originalName.'" enregistré avec succès dans votre espace et en base de données !');
    }

    public function downloadDocument(Request $request, $id)
    {
        $user = $request->user();
        $document = CommercialDocument::findOrFail($id);

        if (! ($user->is_platform_admin ?? false) && $document->user_id !== $user->id) {
            abort(403, 'Accès non autorisé à ce document.');
        }

        if (! Storage::disk('public')->exists($document->file_path)) {
            return back()->withErrors(['document' => 'Le fichier physique n\'existe pas sur le serveur.']);
        }

        return Storage::disk('public')->download($document->file_path, $document->original_name);
    }

    public function destroyDocument(Request $request, $id): RedirectResponse
    {
        $user = $request->user();
        $document = CommercialDocument::findOrFail($id);

        if (! ($user->is_platform_admin ?? false) && $document->user_id !== $user->id) {
            abort(403, 'Accès non autorisé à supprimer ce document.');
        }

        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return redirect()->route('commercial.import')->with('status', 'Le fichier a été supprimé de votre espace et de la base de données.');
    }

    public function prospects(Request $request): View
    {
        $commercial = $request->user();
        try {
            $prospects = Prospect::query()
                ->when(! $commercial->is_platform_admin, fn ($q) => $q->where('commercial_user_id', $commercial->id))
                ->latest()
                ->get();
        } catch (\Throwable $e) {
            $prospects = collect();
        }

        return view('commercial.prospects', compact('prospects'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_sigle' => ['nullable', 'string', 'max:255'],
            'company_tax_id' => ['nullable', 'string', 'max:255'],
            'company_logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:5120'],
            'sector' => ['nullable', 'string', 'max:255'],
            'rccm' => ['nullable', 'string', 'max:255'],
            'trade_register' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'license_key' => ['nullable', 'string', 'max:64'],
        ]);

        // Nom et e-mail sont désormais obligatoires (validés ci-dessus) : plus de
        // génération silencieuse de "client_<uniqid>@sitiame-capital.com" ni de
        // "Client sans nom". Seul le mot de passe garde une valeur par défaut
        // documentée, à communiquer explicitement au client.
        $plainPassword = empty($validated['password']) ? 'Sitiame2026' : $validated['password'];

        if ($request->hasFile('company_logo')) {
            $validated['company_logo'] = $request->file('company_logo')->store('company-logos', 'public');
        }

        if ($request->hasFile('trade_register')) {
            $validated['trade_register_file'] = $request->file('trade_register')->store('trade-registers', 'public');
        }

        unset($validated['trade_register'], $validated['license_key']);

        $commercial = $request->user();

        DB::transaction(function () use ($validated, $commercial, $request, $plainPassword) {
            $user = User::create([
                ...$validated,
                'password' => Hash::make($plainPassword),
                'must_change_password' => true,
                'role_key' => 'manager',
                'created_by_user_id' => $commercial->id,
                'kyc_status' => 'submitted',
                'kyc_submitted_at' => now(),
            ]);

            // Activer 30 jours (1 mois) d'essai premium gratuit
            $this->userPremium->activateForDays(
                $user,
                30,
                'commercial_referral',
                "Essai gratuit 1 mois accordé via parrainage commercial (par {$commercial->name}).",
                $commercial,
                ['commercial_id' => $commercial->id],
                $request
            );
        });

        return back()->with('status', "Client enregistré avec succès. Son compte a été créé avec un mois d’essai gratuit. Identifiants de connexion — E-mail : {$validated['email']} / Mot de passe : {$plainPassword}");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $commercial = $request->user();
        if ($user->created_by_user_id !== $commercial->id) {
            abort(403, 'Vous n’êtes pas autorisé à modifier ce client.');
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_sigle' => ['nullable', 'string', 'max:255'],
            'company_tax_id' => ['nullable', 'string', 'max:255'],
            'company_logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:5120'],
            'sector' => ['nullable', 'string', 'max:255'],
            'rccm' => ['nullable', 'string', 'max:255'],
            'trade_register' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $validated = array_filter($validated, fn ($value) => $value !== null && $value !== '');

        if ($request->hasFile('company_logo')) {
            $validated['company_logo'] = $request->file('company_logo')->store('company-logos', 'public');
        }

        if ($request->hasFile('trade_register')) {
            $validated['trade_register_file'] = $request->file('trade_register')->store('trade-registers', 'public');
        }

        unset($validated['trade_register']);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return back()->with('status', 'Informations du client mises à jour avec succès.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $commercial = $request->user();
        if ($user->created_by_user_id !== $commercial->id) {
            abort(403, 'Vous n’êtes pas autorisé à supprimer ce client.');
        }

        $user->delete();

        return back()->with('status', 'Client supprimé avec succès.');
    }

    public function storeProspect(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'need_type' => ['required', 'string', Rule::in(['diagnostic', 'syscohada', 'tresorerie', 'levee_fonds', 'ma'])],
            'status' => ['nullable', 'string', Rule::in(['nouveau', 'contacte', 'qualifie', 'client', 'sans_suite'])],
            'notes' => ['nullable', 'string'],
        ]);

        Prospect::create([
            ...$validated,
            'commercial_user_id' => $request->user()->id,
            'status' => $validated['status'] ?? 'nouveau',
        ]);

        return back()->with('status', 'Prospect / Lead qualifié enregistré avec succès.');
    }

    public function updateProspectStatus(Request $request, Prospect $prospect): RedirectResponse
    {
        $commercial = $request->user();
        if (! $commercial->is_platform_admin && $prospect->commercial_user_id !== $commercial->id) {
            abort(403, 'Vous n’êtes pas autorisé à modifier ce prospect.');
        }

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['nouveau', 'contacte', 'qualifie', 'client', 'sans_suite'])],
            'notes' => ['nullable', 'string'],
        ]);

        $prospect->update($validated);

        return back()->with('status', 'Statut du prospect mis à jour avec succès.');
    }

    public function destroyProspect(Request $request, Prospect $prospect): RedirectResponse
    {
        $commercial = $request->user();
        if (! $commercial->is_platform_admin && $prospect->commercial_user_id !== $commercial->id) {
            abort(403, 'Vous n’êtes pas autorisé à supprimer ce prospect.');
        }

        $prospect->delete();

        return back()->with('status', 'Prospect supprimé avec succès.');
    }

    public function parseCompanyDocument(Request $request, CompanyDocumentParserService $parser): JsonResponse
    {
        $request->validate([
            'document' => ['required', 'file', 'max:10240', 'mimes:docx,doc,xlsx,xls,csv,pdf,txt'],
        ]);

        $file = $request->file('document');
        $extracted = $parser->parse($file);

        return response()->json([
            'ok' => true,
            'filename' => $file->getClientOriginalName(),
            'fields_found_count' => count($extracted),
            'extracted' => $extracted,
            'message' => count($extracted) > 0
                ? count($extracted).' champ(s) identifié(s) et pré-rempli(s) automatiquement.'
                : 'Aucun champ reconnu automatiquement. Vous pouvez remplir les informations manuellement.',
        ]);
    }
}
