<?php

namespace App\Http\Controllers;

use App\Models\AccountingDocument;
use App\Models\AccountingEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Concerns\UsesClientWorkspace;

class AccountingDocumentController extends Controller
{
    use UsesClientWorkspace;

    public function showValidation(AccountingDocument $document)
    {
        $this->authorizeDocument($document);

        return view('accounting.validate-document', compact('document'));
    }

    public function storeValidation(Request $request, AccountingDocument $document)
    {
        $this->authorizeDocument($document);

        $validated = $request->validate([
            'partner' => ['required', 'string', 'max:255'],
            'invoice_date' => ['required', 'date'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'amount_ht' => ['nullable', 'numeric', 'min:0'],
            'amount_ttc' => ['required', 'numeric', 'min:0'],
            'tva' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'document_type' => ['required', 'string', 'max:255'],
            'debit_account' => ['required', 'string', 'max:255'],
            'credit_account' => ['required', 'string', 'max:255'],
        ]);

        $document->update([
            'document_type' => $validated['document_type'],
            'status' => 'validated',
            'actor_user_id' => Auth::id(),
            'extracted_data' => [
                'partner' => $validated['partner'],
                'invoice_date' => $validated['invoice_date'],
                'invoice_number' => $validated['invoice_number'],
                'amount_ht' => $validated['amount_ht'] ?? 0,
                'amount_ttc' => $validated['amount_ttc'],
                'tva' => $validated['tva'] ?? 0,
                'currency' => $validated['currency'],
                'debit_account' => $validated['debit_account'],
                'credit_account' => $validated['credit_account'],
            ],
            'confidence' => 100.00,
        ]);

        $this->createEntryFromDocument($document);

        return redirect()->route('accounting.documents')->with('status', 'Document validé et écriture générée.');
    }

    private function createEntryFromDocument(AccountingDocument $document): void
    {
        $data = $document->extracted_data;
        $type = $document->document_type;
        $amount = $data['amount_ttc'] ?? 0;

        $accountMap = [
            'Achat' => ['debit' => '607 Achats de marchandises', 'credit' => '401 Fournisseurs'],
            'Vente' => ['debit' => '411 Clients', 'credit' => '701 Ventes de marchandises'],
            'Reçu' => ['debit' => '512 Banque', 'credit' => '411 Clients'],
            'Justificatif' => ['debit' => '627 Services bancaires', 'credit' => '512 Banque'],
        ];

        $accounts = [
            'debit' => $data['debit_account'] ?? $accountMap[$type]['debit'] ?? '471 Compte transitoire',
            'credit' => $data['credit_account'] ?? $accountMap[$type]['credit'] ?? "472 Compte d'attente",
        ];

        AccountingEntry::updateOrCreate(
            [
                'document_id' => $document->id,
            ],
            [
                'user_id' => $this->workspaceUserId(),
                'actor_user_id' => Auth::id(),
                'date' => $data['invoice_date'] ?? now()->toDateString(),
                'document_type' => $type,
                'document_reference' => $data['invoice_number'] ?? null,
                'description' => '[OCR] ' . ($data['partner'] ?? 'Document') . ' - ' . ($data['invoice_number'] ?? 'Sans référence'),
                'debit_account' => $accounts['debit'],
                'credit_account' => $accounts['credit'],
                'amount' => $amount,
            ]
        );
    }

    private function authorizeDocument(AccountingDocument $document): void
    {
        if (! $this->workspaceOwnsDataUserId((int) $document->user_id)) {
            abort(403);
        }
    }
}
