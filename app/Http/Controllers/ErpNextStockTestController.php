<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ErpNextClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ErpNextStockTestController extends Controller
{
    public function index(): View
    {
        $pmes = User::whereNotNull('erpnext_company_name')->orderBy('company_name')->get();

        return view('admin.erpnext-stock-test.index', compact('pmes'));
    }

    public function show(Request $request, ErpNextClient $erpNext): View
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $pme = User::findOrFail($validated['user_id']);

        $stockLevels = null;
        $stockLevelsError = null;

        if (empty($pme->erpnext_company_name) || empty($pme->erpnext_warehouse)) {
            $stockLevelsError = 'Cette PME n\'a pas de société/entrepôt ERPNext provisionné.';
        } else {
            try {
                $stockLevels = $erpNext->getStockLevelsForCompany($pme);
            } catch (\Throwable $e) {
                $stockLevelsError = $e->getMessage();
            }
        }

        return view('admin.erpnext-stock-test.show', compact('pme', 'stockLevels', 'stockLevelsError'));
    }

    public function createMovement(Request $request): View
    {
        $pmes = User::whereNotNull('erpnext_company_name')->orderBy('company_name')->get();
        $selectedUserId = $request->query('user_id');

        return view('admin.erpnext-stock-test.create-movement', [
            'pmes' => $pmes,
            'selectedUserId' => $selectedUserId,
        ]);
    }

    public function storeMovement(Request $request, ErpNextClient $erpNext): View
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'item_description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_rate' => ['required_if:movement_type,in,out', 'nullable', 'numeric', 'min:0'],
            'movement_type' => ['required', 'in:in,out,ajustement'],
        ]);

        $pme = User::findOrFail($validated['user_id']);

        $result = null;
        $error = null;

        try {
            if ($validated['movement_type'] === 'ajustement') {
                $result = $erpNext->adjustStockForPme(
                    $pme,
                    $validated['item_description'],
                    (float) $validated['quantity']
                );
            } else {
                $result = $erpNext->createStockMovementForPme(
                    $pme,
                    $validated['item_description'],
                    (float) $validated['quantity'],
                    (float) ($validated['unit_rate'] ?? 0),
                    $validated['movement_type']
                );
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('admin.erpnext-stock-test.create-movement', [
            'pmes' => User::whereNotNull('erpnext_company_name')->orderBy('company_name')->get(),
            'selectedUserId' => $validated['user_id'],
            'result' => $result,
            'error' => $error,
        ]);
    }
}
