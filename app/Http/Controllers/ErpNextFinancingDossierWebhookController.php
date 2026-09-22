<?php

namespace App\Http\Controllers;

use App\Models\FinancingDossier;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Relaie vers ERPNext les dossiers de financement déjà saisis par les
 * analystes dans PME360 (espace /analyste), pour la page "Financement"
 * d'ERPNext -- même sens que register-pme : ERPNext appelle PME360, pas
 * l'inverse, avec le même jeton partagé.
 */
class ErpNextFinancingDossierWebhookController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $expectedToken = trim((string) config('services.erpnext.webhook_token', ''));
        $providedToken = (string) $request->header('X-PME360-Webhook-Token', '');

        if ($expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            abort(403);
        }

        $erpnextCompany = (string) $request->query('company', '');
        if ($erpnextCompany === '') {
            return response()->json(['status' => 'error', 'message' => 'company manquant'], 422);
        }

        $pme = User::where('erpnext_company_name', $erpnextCompany)->first();
        if (! $pme) {
            return response()->json(['status' => 'ignored', 'reason' => 'PME introuvable'], 200);
        }

        $dossier = FinancingDossier::where('user_id', $pme->id)->latest()->first();
        if (! $dossier) {
            return response()->json(['status' => 'ignored', 'reason' => 'aucun dossier de financement'], 200);
        }

        return response()->json([
            'status' => 'ok',
            'reference' => $dossier->reference,
            'dossier_status' => $dossier->status,
            'financing_type' => $dossier->financing_type,
            'financing_purpose' => $dossier->financing_purpose,
            'amount_requested' => $dossier->amount_requested,
            'currency' => $dossier->currency ?? 'XOF',
            'desired_term_months' => $dossier->desired_term_months,
            'grace_period_months' => $dossier->grace_period_months,
            'repayment_frequency' => $dossier->repayment_frequency,
            'promoter_contribution' => $dossier->promoter_contribution,
            'desired_disbursement_date' => optional($dossier->desired_disbursement_date)->toDateString(),
            'financing_summary' => data_get($dossier->financing_summary_data, 'financing_summary'),
            'repayment_source' => data_get($dossier->financing_summary_data, 'repayment_source'),
        ], 200);
    }
}
