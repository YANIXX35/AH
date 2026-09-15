<?php

namespace App\Http\Controllers;

use App\Models\SportEvent;
use App\Models\User;
use App\Services\ErpNextClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ErpNextSportEventWebhookController extends Controller
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

        if ($doctype !== 'Event' || $docname === '') {
            return response()->json(['status' => 'ignored', 'reason' => 'payload incomplet ou doctype non géré'], 200);
        }

        try {
            $document = $erpNext->getDocument('Event', $docname);
        } catch (\Throwable $exception) {
            Log::warning('Webhook ERPNext Sport Event: échec de relecture du document.', [
                'name' => $docname,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['status' => 'error'], 200);
        }

        if (($document['reference_doctype'] ?? '') !== 'Company') {
            return response()->json(['status' => 'ignored', 'reason' => 'événement non rattaché à une Company'], 200);
        }

        $company = (string) ($document['reference_docname'] ?? '');
        $pme = User::where('erpnext_company_name', $company)->first();

        if (! $pme) {
            Log::warning('Webhook ERPNext Sport Event reçu pour une company sans PME locale correspondante.', [
                'company' => $company,
                'name' => $docname,
            ]);

            return response()->json(['status' => 'ignored', 'reason' => 'PME introuvable'], 200);
        }

        SportEvent::updateOrCreate(
            ['erpnext_event_name' => $docname],
            [
                'user_id' => $pme->id,
                'subject' => (string) ($document['subject'] ?? 'Événement'),
                'starts_on' => (string) ($document['starts_on'] ?? now()->toDateTimeString()),
                'description' => $document['description'] ?? null,
            ]
        );

        return response()->json(['status' => 'ok'], 200);
    }
}
