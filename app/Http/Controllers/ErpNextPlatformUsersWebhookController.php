<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Liste en lecture seule toutes les PME provisionnées (celles avec une
 * société ERPNext), pour la page admin "Utilisateurs de la plateforme"
 * côté ERPNext -- même sens et même jeton partagé que
 * ErpNextFinancingDossierWebhookController : ERPNext appelle PME360.
 */
class ErpNextPlatformUsersWebhookController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $expectedToken = trim((string) config('services.erpnext.webhook_token', ''));
        $providedToken = (string) $request->header('X-PME360-Webhook-Token', '');

        if ($expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            abort(403);
        }

        $pmes = User::whereNotNull('erpnext_company_name')->orderBy('created_at')->get();

        $lastLogins = UserLoginLog::query()
            ->whereIn('user_id', $pmes->pluck('id'))
            ->where('event', 'login')
            ->selectRaw('user_id, MAX(created_at) as last_login_at')
            ->groupBy('user_id')
            ->pluck('last_login_at', 'user_id');

        $users = $pmes->map(fn (User $pme) => [
            'email' => $pme->email,
            'name' => $pme->name,
            'company_name' => $pme->company_name,
            'erpnext_company_name' => $pme->erpnext_company_name,
            'is_premium' => (bool) $pme->is_premium,
            'premium_status' => $pme->premium_status,
            'premium_trial_ends_at' => optional($pme->premium_trial_ends_at)->toIso8601String(),
            'premium_ends_at' => optional($pme->premium_ends_at)->toIso8601String(),
            'registered_at' => $pme->created_at->toIso8601String(),
            'last_login_at' => optional($lastLogins->get($pme->id))
                ? \Illuminate\Support\Carbon::parse($lastLogins->get($pme->id))->toIso8601String()
                : null,
        ])->values();

        return response()->json(['users' => $users], 200);
    }
}
