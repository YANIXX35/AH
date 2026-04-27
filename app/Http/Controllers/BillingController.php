<?php

namespace App\Http\Controllers;

use App\Models\BillingInvoice;
use App\Models\BillingSubscription;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $invoices = BillingInvoice::query()
            ->where('user_id', $user->id)
            ->latest('issued_at')
            ->paginate(20);
        $overdueInvoice = BillingInvoice::query()
            ->where('user_id', $user->id)
            ->where('status', 'overdue')
            ->latest('due_at')
            ->first();
        $unpaidCount = BillingInvoice::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['issued', 'overdue'])
            ->count();

        $subscription = BillingSubscription::query()
            ->with(['plan', 'addons.addon'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'past_due', 'suspended'])
            ->latest('id')
            ->first();

        return view('billing.index', [
            'invoices' => $invoices,
            'subscription' => $subscription,
            'overdueInvoice' => $overdueInvoice,
            'unpaidCount' => $unpaidCount,
        ]);
    }

    public function downloadInvoicePdf(Request $request, BillingInvoice $invoice, BillingService $billingService): StreamedResponse
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
        if (! $invoice->pdf_path || ! Storage::disk('public')->exists($invoice->pdf_path)) {
            $billingService->renderInvoicePdf($invoice);
            $invoice->refresh();
        }

        return Storage::disk('public')->download($invoice->pdf_path, $invoice->invoice_number.'.pdf');
    }

    public function viewInvoicePdf(Request $request, BillingInvoice $invoice, BillingService $billingService): View
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
        if (! $invoice->pdf_path || ! Storage::disk('public')->exists($invoice->pdf_path)) {
            $billingService->renderInvoicePdf($invoice);
            $invoice->refresh();
        }

        $disk = Storage::disk('public');
        $storedPath = (string) $invoice->pdf_path;
        $documentName = $invoice->invoice_number.'.pdf';
        $mimeType = (string) ($disk->mimeType($storedPath) ?? 'application/pdf');

        return view('accounting.document-viewer', [
            'documentName' => $documentName,
            'documentTypeLabel' => 'Facture client',
            'previewType' => 'pdf',
            'previewUrl' => route('billing.invoices.pdf.stream', $invoice),
            'backUrl' => route('billing.invoices'),
            'mimeType' => $mimeType,
            'fileExtension' => 'PDF',
            'fileSizeLabel' => $this->formatFileSize((int) $disk->size($storedPath)),
            'textPreview' => null,
            'pdfDataBase64' => base64_encode((string) $disk->get($storedPath)),
        ]);
    }

    public function streamInvoicePdf(Request $request, BillingInvoice $invoice, BillingService $billingService): StreamedResponse
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
        if (! $invoice->pdf_path || ! Storage::disk('public')->exists($invoice->pdf_path)) {
            $billingService->renderInvoicePdf($invoice);
            $invoice->refresh();
        }

        $absolutePath = Storage::disk('public')->path($invoice->pdf_path);

        return response()->file($absolutePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$invoice->invoice_number.'.pdf"',
        ]);
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 2, ',', ' ').' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1, ',', ' ').' KB';
        }

        return $bytes.' octets';
    }
}
