<?php

namespace App\Http\Controllers;

use App\Domain\Invoicing\InvoiceService;
use App\Models\InvoiceErpNextSync;
use App\Models\User;
use App\Services\ErpNextClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ErpNextInvoicingWebhookController extends Controller
{
    public function handle(Request $request, ErpNextClient $erpNext, InvoiceService $invoiceService): JsonResponse
    {
        $expectedToken = trim((string) config('services.erpnext.webhook_token', ''));
        $providedToken = (string) $request->header('X-PME360-Webhook-Token', '');

        if ($expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            abort(403);
        }

        $doctype = (string) $request->input('doctype', '');
        $docname = (string) $request->input('name', '');
        $company = (string) $request->input('company', '');

        if ($doctype === '' || $docname === '' || $company === '') {
            return response()->json(['status' => 'ignored', 'reason' => 'payload incomplet'], 200);
        }

        $pme = User::where('erpnext_company_name', $company)->first();

        if (! $pme) {
            Log::warning('Webhook ERPNext Invoicing reçu pour une company sans PME locale correspondante.', [
                'company' => $company,
                'doctype' => $doctype,
                'name' => $docname,
            ]);

            return response()->json(['status' => 'ignored', 'reason' => 'PME introuvable'], 200);
        }

        try {
            $document = $erpNext->getDocument($doctype, $docname);
        } catch (\Throwable $exception) {
            Log::warning('Webhook ERPNext Invoicing: échec de relecture du document.', [
                'doctype' => $doctype,
                'name' => $docname,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['status' => 'error'], 200);
        }

        try {
            if ($doctype === 'Sales Invoice' && (int) ($document['docstatus'] ?? 0) === 2) {
                $this->handleCancellation($docname);
            } elseif ($doctype === 'Sales Invoice') {
                $this->handleCreation($pme, $docname, $document, $invoiceService);
            } elseif ($doctype === 'Payment Entry') {
                $this->handlePayment($docname, $document, $invoiceService);
            } else {
                return response()->json(['status' => 'ignored', 'reason' => 'doctype non géré'], 200);
            }
        } catch (\Throwable $exception) {
            Log::warning('Webhook ERPNext Invoicing: échec de traitement.', [
                'doctype' => $doctype,
                'name' => $docname,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['status' => 'error'], 200);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function handleCreation(User $pme, string $docname, array $document, InvoiceService $invoiceService): void
    {
        if (InvoiceErpNextSync::where('erpnext_invoice_name', $docname)->exists()) {
            return;
        }

        $items = [];
        foreach ((array) ($document['items'] ?? []) as $line) {
            $items[] = [
                'description' => (string) ($line['item_name'] ?? $line['item_code'] ?? 'Article'),
                'quantity' => (float) ($line['qty'] ?? 0),
                'unit_price' => (float) ($line['rate'] ?? 0),
            ];
        }

        if (empty($items)) {
            return;
        }

        $taxRate = (float) ($document['taxes'][0]['rate'] ?? 0);

        $invoice = $invoiceService->createInvoice(
            $pme->id,
            $pme->id,
            (string) ($document['customer_name'] ?? $document['customer'] ?? 'Client ERPNext'),
            null,
            null,
            null,
            Carbon::parse((string) ($document['posting_date'] ?? now()->toDateString())),
            Carbon::parse((string) ($document['due_date'] ?? now()->toDateString())),
            $items,
            $taxRate,
            null,
            (string) ($document['currency'] ?? 'XOF'),
            true
        );

        InvoiceErpNextSync::updateOrCreate(
            ['invoice_id' => $invoice->id],
            [
                'status' => 'synced',
                'erpnext_invoice_name' => $docname,
                'erpnext_customer_name' => (string) ($document['customer'] ?? ''),
                'last_synced_at' => now(),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function handlePayment(string $docname, array $document, InvoiceService $invoiceService): void
    {
        foreach ((array) ($document['references'] ?? []) as $reference) {
            if (($reference['reference_doctype'] ?? '') !== 'Sales Invoice') {
                continue;
            }

            $sync = InvoiceErpNextSync::where('erpnext_invoice_name', (string) ($reference['reference_name'] ?? ''))->first();

            if (! $sync || ! $sync->invoice) {
                continue;
            }

            $invoiceService->recordPayment(
                $sync->invoice,
                [
                    'amount' => (float) ($reference['allocated_amount'] ?? 0),
                    'paid_at' => Carbon::parse((string) ($document['posting_date'] ?? now()->toDateString())),
                    'method' => (string) ($document['mode_of_payment'] ?? null) ?: null,
                    'reference' => $docname,
                    'treasury_account_code' => null,
                    'treasury_transaction_id' => null,
                    'notes' => null,
                ],
                $sync->invoice->user_id,
                true
            );
        }
    }

    private function handleCancellation(string $docname): void
    {
        $sync = InvoiceErpNextSync::where('erpnext_invoice_name', $docname)->first();

        if (! $sync || ! $sync->invoice) {
            return;
        }

        app(InvoiceService::class)->cancelInvoice(
            $sync->invoice,
            'Annulée depuis ERPNext ('.$docname.')',
            $sync->invoice->user_id,
            true
        );
    }
}
