<?php

namespace App\Http\Controllers;

use App\Domain\Invoicing\InvoiceService;
use App\Http\Controllers\Concerns\UsesClientWorkspace;
use App\Models\Invoice;
use App\Models\PlanComptableAccount;
use App\Models\TreasuryTransaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    use UsesClientWorkspace;

    public function __construct(private readonly InvoiceService $invoiceService) {}

    public function index(Request $request): View
    {
        $userIds = $this->workspaceDataUserIds();

        $invoices = Invoice::whereIn('user_id', $userIds)
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $totals = [
            'unpaid' => Invoice::whereIn('user_id', $userIds)->where('status', 'unpaid')->sum('total_amount'),
            'partially_paid' => Invoice::whereIn('user_id', $userIds)->where('status', 'partially_paid')->sum('total_amount'),
            'overdue' => Invoice::whereIn('user_id', $userIds)->whereIn('status', ['unpaid', 'partially_paid'])->where('due_date', '<', now())->count(),
        ];

        return view('invoicing.index', [
            'invoices' => $invoices,
            'totals' => $totals,
            'currentStatus' => $request->query('status'),
        ]);
    }

    public function create(): View
    {
        return view('invoicing.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'client_contact' => ['nullable', 'string', 'max:255'],
            'client_address' => ['nullable', 'string', 'max:255'],
            'client_tax_id' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $invoice = $this->invoiceService->createInvoice(
            $this->workspaceUserId(),
            auth()->id(),
            $validated['client_name'],
            $validated['client_contact'] ?? null,
            $validated['client_address'] ?? null,
            $validated['client_tax_id'] ?? null,
            Carbon::parse($validated['issue_date']),
            Carbon::parse($validated['due_date']),
            $validated['items'],
            (float) ($validated['tax_rate'] ?? 0),
            $validated['notes'] ?? null
        );

        return redirect()->route('invoicing.show', $invoice)
            ->with('success', 'Facture '.$invoice->invoice_number.' créée.');
    }

    public function show(Invoice $invoice): View
    {
        $this->authorizeInvoice($invoice);
        $invoice->load(['items', 'payments.treasuryTransaction']);

        $treasuryAccounts = PlanComptableAccount::where('user_id', $this->workspaceUserId())
            ->where('prefix', 'like', '5%')
            ->orderBy('prefix')
            ->get();

        $unlinkedTreasuryTransactions = TreasuryTransaction::whereIn('user_id', $this->workspaceDataUserIds())
            ->where('type', 'encaissement')
            ->where('status', 'effectue')
            ->whereNotIn('id', $invoice->payments()->whereNotNull('treasury_transaction_id')->pluck('treasury_transaction_id'))
            ->orderByDesc('transaction_date')
            ->limit(30)
            ->get();

        return view('invoicing.show', [
            'invoice' => $invoice,
            'treasuryAccounts' => $treasuryAccounts,
            'unlinkedTreasuryTransactions' => $unlinkedTreasuryTransactions,
        ]);
    }

    public function storePayment(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['required', 'date'],
            'method' => ['nullable', 'string', 'max:60'],
            'reference' => ['nullable', 'string', 'max:120'],
            'treasury_account_code' => ['nullable', 'string', 'max:20'],
            'treasury_transaction_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->invoiceService->recordPayment($invoice, [
                'amount' => $validated['amount'],
                'paid_at' => Carbon::parse($validated['paid_at']),
                'method' => $validated['method'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'treasury_account_code' => $validated['treasury_account_code'] ?? null,
                'treasury_transaction_id' => $validated['treasury_transaction_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ], auth()->id());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('success', 'Encaissement enregistré.');
    }

    public function edit(Invoice $invoice): View
    {
        $this->authorizeInvoice($invoice);
        abort_unless((float) $invoice->amount_paid <= 0 && $invoice->status !== 'cancelled', 403, 'Facture déjà réglée ou annulée : non modifiable.');
        $invoice->load('items');

        return view('invoicing.edit', ['invoice' => $invoice]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);

        $validated = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'client_contact' => ['nullable', 'string', 'max:255'],
            'client_address' => ['nullable', 'string', 'max:255'],
            'client_tax_id' => ['nullable', 'string', 'max:100'],
            'due_date' => ['required', 'date'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $this->invoiceService->updateInvoice($invoice, [
                'client_name' => $validated['client_name'],
                'client_contact' => $validated['client_contact'] ?? null,
                'client_address' => $validated['client_address'] ?? null,
                'client_tax_id' => $validated['client_tax_id'] ?? null,
                'due_date' => Carbon::parse($validated['due_date']),
                'items' => $validated['items'],
                'tax_rate' => (float) ($validated['tax_rate'] ?? 0),
                'notes' => $validated['notes'] ?? null,
            ], auth()->id());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        }

        return redirect()->route('invoicing.show', $invoice)->with('success', 'Facture mise à jour.');
    }

    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->invoiceService->cancelInvoice($invoice, $validated['reason'], auth()->id());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return back()->with('success', 'Facture annulée.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);

        try {
            $this->invoiceService->deleteInvoice($invoice, auth()->id());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('invoicing.index')->with('success', 'Facture supprimée.');
    }

    public function downloadPdf(Invoice $invoice): StreamedResponse
    {
        $this->authorizeInvoice($invoice);
        $invoice->load(['items', 'user']);

        $pdf = Pdf::loadView('invoicing.pdf', ['invoice' => $invoice]);
        $path = 'invoices/'.$invoice->invoice_number.'.pdf';
        Storage::disk('public')->put($path, $pdf->output());
        $invoice->update(['pdf_path' => $path]);

        return Storage::disk('public')->download($path, $invoice->invoice_number.'.pdf');
    }

    private function authorizeInvoice(Invoice $invoice): void
    {
        abort_unless(in_array($invoice->user_id, $this->workspaceDataUserIds(), true), 403);
    }
}
