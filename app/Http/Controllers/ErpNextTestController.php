<?php

namespace App\Http\Controllers;

use App\Exceptions\ErpNextApiException;
use App\Models\ErpNextTestInvoice;
use App\Models\User;
use App\Services\ErpNextClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ErpNextTestController extends Controller
{
    public function index(): View
    {
        $invoices = ErpNextTestInvoice::with('user')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.erpnext-test.index', compact('invoices'));
    }

    public function create(): View
    {
        $pmes = User::clients()->orderBy('company_name')->get();

        return view('admin.erpnext-test.create', compact('pmes'));
    }

    public function store(Request $request, ErpNextClient $erpNext): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $pme = User::findOrFail($validated['user_id']);

        $invoice = ErpNextTestInvoice::create([
            'user_id' => $pme->id,
            'created_by_user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        foreach ($validated['lines'] as $line) {
            $invoice->items()->create([
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'amount' => $line['quantity'] * $line['unit_price'],
            ]);
        }

        try {
            $customerName = $erpNext->findOrCreateCustomer($pme);
            $response = $erpNext->createSalesInvoice($pme, $customerName, $validated['lines']);

            $invoice->update([
                'status' => 'synced',
                'erpnext_invoice_name' => $response['name'] ?? null,
                'total_before_tax' => $response['total'] ?? null,
                'total_taxes' => $response['total_taxes_and_charges'] ?? null,
                'grand_total' => $response['grand_total'] ?? null,
                'outstanding_amount' => $response['outstanding_amount'] ?? null,
                'raw_response' => $response,
            ]);
        } catch (ErpNextApiException|\Throwable $exception) {
            $invoice->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('admin.erpnext-test.show', $invoice);
    }

    public function show(ErpNextTestInvoice $erpNextTestInvoice): View
    {
        $erpNextTestInvoice->load(['user', 'createdBy', 'items']);

        return view('admin.erpnext-test.show', ['invoice' => $erpNextTestInvoice]);
    }
}
