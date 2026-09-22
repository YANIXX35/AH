<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Reçoit la confirmation d'un paiement d'abonnement traité côté ERPNext
 * (module "Abonnement" / doctype Subscription Payment, paiement CinetPay
 * déjà re-vérifié par ERPNext via l'API de statut avant cet appel). Même
 * sens et même jeton partagé que register-pme/financing-dossier : ERPNext
 * appelle PME360, jamais l'inverse.
 */
class ErpNextSubscriptionPaidWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $expectedToken = trim((string) config('services.erpnext.webhook_token', ''));
        $providedToken = (string) $request->header('X-PME360-Webhook-Token', '');

        if ($expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            abort(403);
        }

        $validated = $request->validate([
            'company' => ['required', 'string', 'max:255'],
            'duration_months' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
        ]);

        $pme = User::where('erpnext_company_name', $validated['company'])->first();
        if (! $pme) {
            return response()->json(['status' => 'ignored', 'reason' => 'PME introuvable'], 200);
        }

        $paidAt = Carbon::parse($validated['paid_at']);
        $currentExpiry = $pme->premium_ends_at;
        $baseDate = ($currentExpiry !== null && $currentExpiry->isFuture()) ? $currentExpiry : $paidAt;

        $pme->is_premium = true;
        $pme->premium_status = 'active';
        $pme->premium_ends_at = $baseDate->copy()->addMonths($validated['duration_months']);
        $pme->save();

        return response()->json(['status' => 'ok'], 200);
    }
}
