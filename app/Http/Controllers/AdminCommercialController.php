<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminCommercialController extends Controller
{
    public function index(Request $request): View
    {
        // Récupérer tous les commerciaux
        $commercials = User::query()
            ->where('role_key', 'commercial')
            ->with(['createdClients' => function ($query) {
                $query->latest();
            }])
            ->get();

        // Récupérer tous les clients parrainés par des commerciaux
        $referredClients = User::query()
            ->whereNotNull('created_by_user_id')
            ->with('creator')
            ->latest()
            ->get();

        $totalCommercials = $commercials->count();
        $totalClientsReferred = $referredClients->count();

        $activeTrials = $referredClients->filter(function ($client) {
            return $client->is_premium && $client->premium_ends_at && $client->premium_ends_at->isFuture();
        })->count();

        $expiredTrials = $referredClients->filter(function ($client) {
            return ! $client->is_premium || ($client->premium_ends_at && $client->premium_ends_at->isPast());
        })->count();

        return view('admin.commerciale', compact(
            'commercials',
            'referredClients',
            'totalCommercials',
            'totalClientsReferred',
            'activeTrials',
            'expiredTrials'
        ));
    }
}
