<?php

namespace App\Http\Controllers;

use App\Models\AccountingEntry;
use App\Models\PlanComptableAccount;
use App\Models\User;
use App\Services\ErpNextClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ErpNextAccountingEntryWebhookController extends Controller
{
    public function handle(Request $request, ErpNextClient $erpNext): JsonResponse
    {
        $expectedToken = trim((string) config('services.erpnext.webhook_token', ''));
        $providedToken = (string) $request->header('X-PME360-Webhook-Token', '');

        if ($expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            abort(403);
        }

        $doctype = (string) $request->input('doctype', '');
        $docname = (string) $request->input('name', '');
        $company = (string) $request->input('company', '');

        if ($doctype !== 'Journal Entry' || $docname === '' || $company === '') {
            return response()->json(['status' => 'ignored', 'reason' => 'payload incomplet ou doctype non géré'], 200);
        }

        $pme = User::where('erpnext_company_name', $company)->first();

        if (! $pme) {
            Log::warning('Webhook ERPNext Accounting reçu pour une company sans PME locale correspondante.', [
                'company' => $company,
                'name' => $docname,
            ]);

            return response()->json(['status' => 'ignored', 'reason' => 'PME introuvable'], 200);
        }

        if (AccountingEntry::where('document_type', 'ecriture_erpnext')->where('document_reference', $docname)->exists()) {
            return response()->json(['status' => 'ignored', 'reason' => 'déjà traité'], 200);
        }

        try {
            $document = $erpNext->getDocument('Journal Entry', $docname);
        } catch (\Throwable $exception) {
            Log::warning('Webhook ERPNext Accounting: échec de relecture du document.', [
                'name' => $docname,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['status' => 'error'], 200);
        }

        if (Str::startsWith((string) ($document['user_remark'] ?? ''), 'PME360_SYNC')) {
            return response()->json(['status' => 'ignored', 'reason' => 'créée par PME360 elle-même'], 200);
        }

        $lines = (array) ($document['accounts'] ?? []);

        if (count($lines) !== 2) {
            return response()->json(['status' => 'ignored', 'reason' => 'pas exactement 2 lignes'], 200);
        }

        $debitLine = null;
        $creditLine = null;

        foreach ($lines as $line) {
            $debit = (float) ($line['debit_in_account_currency'] ?? 0);
            $credit = (float) ($line['credit_in_account_currency'] ?? 0);

            if ($debit > 0 && $credit == 0) {
                $debitLine = $line;
            } elseif ($credit > 0 && $debit == 0) {
                $creditLine = $line;
            }
        }

        if ($debitLine === null || $creditLine === null) {
            return response()->json(['status' => 'ignored', 'reason' => 'lignes non conformes (pas un débit/crédit propre)'], 200);
        }

        $amount = (float) $debitLine['debit_in_account_currency'];

        if (abs($amount - (float) $creditLine['credit_in_account_currency']) > 0.01) {
            return response()->json(['status' => 'ignored', 'reason' => 'débit et crédit ne correspondent pas'], 200);
        }

        $debitAccount = $this->resolveLocalAccount($pme, (string) $debitLine['account']);
        $creditAccount = $this->resolveLocalAccount($pme, (string) $creditLine['account']);

        if ($debitAccount === null || $creditAccount === null) {
            Log::warning('Webhook ERPNext Accounting: compte sans correspondance locale.', [
                'name' => $docname,
                'debit_account' => $debitLine['account'],
                'credit_account' => $creditLine['account'],
            ]);

            return response()->json(['status' => 'ignored', 'reason' => 'compte non trouvé dans le plan comptable local'], 200);
        }

        AccountingEntry::create([
            'user_id' => $pme->id,
            'actor_user_id' => $pme->id,
            'date' => (string) ($document['posting_date'] ?? now()->toDateString()),
            'document_type' => 'ecriture_erpnext',
            'document_reference' => $docname,
            'description' => (string) ($document['user_remark'] ?? ('Écriture ERPNext '.$docname)),
            'debit_account' => $debitAccount,
            'credit_account' => $creditAccount,
            'amount' => $amount,
        ]);

        return response()->json(['status' => 'ok'], 200);
    }

    private function resolveLocalAccount(User $pme, string $erpNextAccountName): ?string
    {
        $code = Str::before($erpNextAccountName, '-');

        if (PlanComptableAccount::where('user_id', $pme->id)->where('numero_compte', $code)->exists()) {
            return $code;
        }

        // Le plan comptable local numérote certains comptes feuilles sur 7
        // chiffres (ex: 6011000) là où le plan importé sur ERPNext s'arrête
        // à 4 (6011) — écart de granularité constaté empiriquement, pas un
        // compte différent. On tente donc aussi le code local équivalent.
        if (strlen($code) === 4) {
            $paddedCode = $code.'000';

            if (PlanComptableAccount::where('user_id', $pme->id)->where('numero_compte', $paddedCode)->exists()) {
                return $paddedCode;
            }
        }

        return null;
    }
}
