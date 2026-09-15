<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesClientWorkspace;
use App\Models\Invoice;
use App\Support\Export\TabularDocumentExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    use UsesClientWorkspace;

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

    public function show(Invoice $invoice): View
    {
        $this->authorizeInvoice($invoice);
        $invoice->load(['items', 'payments.treasuryTransaction']);

        return view('invoicing.show', ['invoice' => $invoice]);
    }

    public function downloadPdf(Invoice $invoice): StreamedResponse
    {
        $this->authorizeInvoice($invoice);
        $invoice->load(['items', 'user']);

        $pdf = Pdf::loadView('invoicing.pdf', [
            'invoice' => $invoice,
            'companyLogo' => \App\Support\CompanyLogo::toDataUri($invoice->user->company_logo),
        ]);
        $path = 'invoices/'.$invoice->invoice_number.'.pdf';
        Storage::disk('public')->put($path, $pdf->output());
        $invoice->update(['pdf_path' => $path]);

        return Storage::disk('public')->download($path, $invoice->invoice_number.'.pdf');
    }

    public function export(Invoice $invoice, string $format, TabularDocumentExporter $exporter)
    {
        $this->authorizeInvoice($invoice);

        if ($format === 'pdf') {
            return $this->downloadPdf($invoice);
        }

        $invoice->load(['items', 'user']);
        $filename = 'facture-'.$invoice->invoice_number;
        $title = 'Facture '.$invoice->invoice_number.' — '.$invoice->client_name;
        $logoPath = \App\Support\CompanyLogo::absolutePath($invoice->user->company_logo);

        $summary = [
            ['Émetteur', $invoice->user->company_name ?? $invoice->user->name ?? '-'],
            ['Client', $invoice->client_name],
            ['Date d\'émission', $invoice->issue_date->format('d/m/Y')],
            ['Échéance', $invoice->due_date->format('d/m/Y')],
            ['Statut', strtoupper((string) $invoice->status)],
            ['Devise', $invoice->currency],
            ['Sous-total', number_format((float) $invoice->subtotal, 0, ',', ' ').' '.$invoice->currency],
            ['TVA ('.$invoice->tax_rate.'%)', number_format((float) $invoice->tax_amount, 0, ',', ' ').' '.$invoice->currency],
            ['Total TTC', number_format((float) $invoice->total_amount, 0, ',', ' ').' '.$invoice->currency],
            ['Montant payé', number_format((float) $invoice->amount_paid, 0, ',', ' ').' '.$invoice->currency],
            ['Solde dû', number_format($invoice->balanceDue(), 0, ',', ' ').' '.$invoice->currency],
        ];

        $headers = ['Libellé', 'Quantité', 'Prix unitaire', 'Total'];
        $rows = $invoice->items->map(fn ($item) => [
            $item->description,
            $item->quantity,
            number_format((float) $item->unit_price, 0, ',', ' ').' '.$invoice->currency,
            number_format((float) $item->line_total, 0, ',', ' ').' '.$invoice->currency,
        ])->all();

        return match ($format) {
            'csv' => $exporter->csv($filename, $title, $summary, $headers, $rows),
            'xlsx' => $exporter->excel($filename, $title, $summary, $headers, $rows, $logoPath),
            'docx' => $exporter->word($filename, $title, $summary, $headers, $rows, $logoPath),
            default => abort(404),
        };
    }

    private function authorizeInvoice(Invoice $invoice): void
    {
        abort_unless(in_array($invoice->user_id, $this->workspaceDataUserIds(), true), 403);
    }
}
