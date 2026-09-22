<?php

namespace App\Http\Controllers;

use App\Jobs\ProvisionErpNextCompanyForPme;
use App\Models\PlanComptableAccount;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Inscription d'une nouvelle PME déclenchée depuis le desk ERPNext (page
 * "Inscrire une PME" côté Organisation), pas par la PME elle-même. Sens
 * inverse des autres webhooks erpnext/* : c'est ERPNext qui appelle PME360
 * ici, avec le même jeton partagé que celui vérifié dans l'autre sens.
 */
class ErpNextPmeRegistrationWebhookController extends Controller
{
    public const DEFAULT_PASSWORD = 'SITIAME2026!';

    public function handle(Request $request): JsonResponse
    {
        $expectedToken = trim((string) config('services.erpnext.webhook_token', ''));
        $providedToken = (string) $request->header('X-PME360-Webhook-Token', '');

        if ($expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
            'company_name' => ['required', 'string', 'max:255'],
            'company_tax_id' => ['nullable', 'string', 'max:255'],
            'rccm' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
        ]);

        $plainPassword = empty($validated['password']) ? self::DEFAULT_PASSWORD : $validated['password'];
        unset($validated['password']);

        $user = DB::transaction(function () use ($validated, $plainPassword) {
            $newUser = User::create([
                ...$validated,
                'password' => Hash::make($plainPassword),
                'must_change_password' => true,
                'role_key' => 'manager',
                'kyc_status' => 'submitted',
                'kyc_submitted_at' => now(),
                // terms_accepted_at/ip volontairement laissés vides : ce compte est
                // créé depuis ERPNext par un admin, pas via un consentement explicite
                // du client sur le formulaire d'inscription (voir F-34).
            ]);

            PlanComptableAccount::seedDefaultsFor($newUser->id);

            return $newUser;
        });

        ProvisionErpNextCompanyForPme::dispatch($user);

        return response()->json([
            'status' => 'ok',
            'user_id' => $user->id,
            'email' => $user->email,
            'password' => $plainPassword,
        ], 201);
    }
}
