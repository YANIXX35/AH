<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserPremiumService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommercialController extends Controller
{
    public function __construct(
        private readonly UserPremiumService $userPremium
    ) {}

    public function index(Request $request): View
    {
        $commercial = $request->user();
        $clients = $commercial->createdClients()->latest()->get();

        $totalClients = $clients->count();
        
        $activeTrials = $clients->filter(function ($client) {
            return $client->is_premium && $client->premium_ends_at && $client->premium_ends_at->isFuture();
        })->count();

        $expiredTrials = $clients->filter(function ($client) {
            return !$client->is_premium || ($client->premium_ends_at && $client->premium_ends_at->isPast());
        })->count();

        return view('commercial.dashboard', compact('clients', 'totalClients', 'activeTrials', 'expiredTrials'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'company_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $commercial = $request->user();

        DB::transaction(function () use ($validated, $commercial, $request) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'company_name' => $validated['company_name'],
                'password' => Hash::make($validated['password']),
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

        return back()->with('status', 'Client enregistré avec succès. Son compte a été créé avec un mois d’essai gratuit.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $commercial = $request->user();
        if ($user->created_by_user_id !== $commercial->id) {
            abort(403, 'Vous n’êtes pas autorisé à modifier ce client.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'company_name' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'company_name' => $validated['company_name'],
        ];

        if (!empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $user->update($payload);

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
}
