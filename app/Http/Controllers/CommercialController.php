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

    public function showcase(): View
    {
        return view('commercial.showcase');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_sigle' => ['nullable', 'string', 'max:255'],
            'company_tax_id' => ['nullable', 'string', 'max:255'],
            'company_logo' => ['nullable', 'image', 'max:5120'],
            'sector' => ['nullable', 'string', 'max:255'],
            'rccm' => ['nullable', 'string', 'max:255'],
            'trade_register' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'license_key' => ['nullable', 'string', 'max:64'],
        ]);

        if ($request->hasFile('company_logo')) {
            $validated['company_logo'] = $request->file('company_logo')->store('company-logos', 'public');
        }

        if ($request->hasFile('trade_register')) {
            $validated['trade_register_file'] = $request->file('trade_register')->store('trade-registers', 'public');
        }

        unset($validated['trade_register'], $validated['license_key']);

        $commercial = $request->user();

        DB::transaction(function () use ($validated, $commercial, $request) {
            $user = User::create([
                ...$validated,
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
            'phone' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_sigle' => ['nullable', 'string', 'max:255'],
            'company_tax_id' => ['nullable', 'string', 'max:255'],
            'company_logo' => ['nullable', 'image', 'max:5120'],
            'sector' => ['nullable', 'string', 'max:255'],
            'rccm' => ['nullable', 'string', 'max:255'],
            'trade_register' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        if ($request->hasFile('company_logo')) {
            $validated['company_logo'] = $request->file('company_logo')->store('company-logos', 'public');
        }

        if ($request->hasFile('trade_register')) {
            $validated['trade_register_file'] = $request->file('trade_register')->store('trade-registers', 'public');
        }

        unset($validated['trade_register']);

        if (!empty($validated['password'])) {
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
}
