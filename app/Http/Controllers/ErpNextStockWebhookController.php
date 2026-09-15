<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use App\Models\StockProduct;
use App\Models\User;
use App\Services\ErpNextClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ErpNextStockWebhookController extends Controller
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

        if ($doctype === '' || $docname === '' || $company === '') {
            return response()->json(['status' => 'ignored', 'reason' => 'payload incomplet'], 200);
        }

        $pme = User::where('erpnext_company_name', $company)->first();

        if (! $pme) {
            Log::warning('Webhook ERPNext Stock reçu pour une company sans PME locale correspondante.', [
                'company' => $company,
                'doctype' => $doctype,
                'name' => $docname,
            ]);

            return response()->json(['status' => 'ignored', 'reason' => 'PME introuvable'], 200);
        }

        try {
            $document = $erpNext->getDocument($doctype, $docname);
        } catch (\Throwable $exception) {
            Log::warning('Webhook ERPNext Stock: échec de relecture du document.', [
                'doctype' => $doctype,
                'name' => $docname,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['status' => 'error'], 200);
        }

        $movementType = match (true) {
            $doctype === 'Stock Reconciliation' => 'ajustement',
            ($document['stock_entry_type'] ?? '') === 'Material Receipt' => 'entree',
            ($document['stock_entry_type'] ?? '') === 'Material Issue' => 'sortie',
            default => null,
        };

        if ($movementType === null) {
            return response()->json(['status' => 'ignored', 'reason' => 'type de document non géré'], 200);
        }

        foreach ((array) ($document['items'] ?? []) as $item) {
            $itemCode = (string) ($item['item_code'] ?? '');
            $itemName = (string) ($item['item_name'] ?? $itemCode);

            if ($itemCode === '') {
                continue;
            }

            $product = StockProduct::firstOrCreate(
                ['user_id' => $pme->id, 'name' => $itemName],
                ['unit' => 'unité', 'quantity_on_hand' => 0, 'average_cost' => 0, 'sale_price' => 0, 'is_active' => true]
            );

            $bin = $erpNext->getBinForItem($pme, $itemCode);

            if ($bin === null) {
                continue;
            }

            $previousQty = (float) $product->quantity_on_hand;

            StockMovement::create([
                'product_id' => $product->id,
                'user_id' => $pme->id,
                'actor_user_id' => $pme->id,
                'type' => $movementType,
                'quantity' => round($bin['actual_qty'] - $previousQty, 2),
                'unit_cost' => $bin['valuation_rate'],
                'quantity_after' => $bin['actual_qty'],
                'average_cost_after' => $bin['valuation_rate'],
                'movement_date' => now()->toDateString(),
                'reason' => 'Créé depuis ERPNext ('.$docname.')',
            ]);

            $product->update([
                'quantity_on_hand' => $bin['actual_qty'],
                'average_cost' => $bin['valuation_rate'],
            ]);
        }

        return response()->json(['status' => 'ok'], 200);
    }
}
