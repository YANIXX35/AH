<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesClientWorkspace;
use App\Models\AccountingDocument;
use App\Models\AccountingEntry;
use App\Models\TreasuryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

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

        $existingData = (array) $document->extracted_data;
        $existingData['document_type'] = $document->document_type;
        $feedback = $this->buildOcrFeedback($existingData, $validated);

        $document->update([
            'document_type' => $validated['document_type'],
            'status' => 'validated',
            'actor_user_id' => Auth::id(),
            'extracted_data' => array_merge($existingData, [
                'partner' => $validated['partner'],
                'invoice_date' => $validated['invoice_date'],
                'invoice_number' => $validated['invoice_number'],
                'amount_ht' => $validated['amount_ht'] ?? 0,
                'amount_ttc' => $validated['amount_ttc'],
                'tva' => $validated['tva'] ?? 0,
                'currency' => $validated['currency'],
                'debit_account' => $validated['debit_account'],
                'credit_account' => $validated['credit_account'],
                'ocr_feedback' => $feedback,
            ]),
            'confidence' => 100.00,
        ]);

        $this->createEntryFromDocument($document);

        return redirect()->route('accounting.documents')->with('status', 'Document validé et écriture générée.');
    }

    private function createEntryFromDocument(AccountingDocument $document): void
    {
        $data = $document->extracted_data;
        $type = $document->document_type;
        $amount = (float) ($data['amount_ttc'] ?? 0);

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
                'description' => '[OCR] '.($data['partner'] ?? 'Document').' - '.($data['invoice_number'] ?? 'Sans référence'),
                'debit_account' => $accounts['debit'],
                'credit_account' => $accounts['credit'],
                'amount' => $amount,
            ]
        );

        $this->syncTreasuryMovementFromDocument($document, $data, $amount);
    }

    private function syncTreasuryMovementFromDocument(AccountingDocument $document, array $data, float $amount): void
    {
        $debitAccount = trim((string) ($data['debit_account'] ?? ''));
        $creditAccount = trim((string) ($data['credit_account'] ?? ''));
        $treasuryReference = 'DOC-BANK-'.$document->id;

        $existingMovement = TreasuryTransaction::query()
            ->where('user_id', $this->workspaceUserId())
            ->where('payment_module', 'accounting_document')
            ->where('bank_reference', $treasuryReference)
            ->first();

        $movementType = null;
        if ($this->isClassFiveAccount($debitAccount) && ! $this->isClassFiveAccount($creditAccount)) {
            $movementType = 'encaissement';
        } elseif ($this->isClassFiveAccount($creditAccount) && ! $this->isClassFiveAccount($debitAccount)) {
            $movementType = 'decaissement';
        }

        if ($movementType === null || $amount <= 0) {
            if ($existingMovement) {
                $existingMovement->delete();
            }

            return;
        }

        $invoiceDate = $data['invoice_date'] ?? null;
        try {
            $transactionDate = $invoiceDate ? Carbon::parse((string) $invoiceDate)->toDateString() : now()->toDateString();
        } catch (\Throwable) {
            $transactionDate = now()->toDateString();
        }

        $partner = trim((string) ($data['partner'] ?? 'Document'));
        $invoiceNumber = trim((string) ($data['invoice_number'] ?? ''));
        $description = '[DOC OCR] '.$partner;
        if ($invoiceNumber !== '') {
            $description .= ' - '.$invoiceNumber;
        }

        TreasuryTransaction::updateOrCreate(
            [
                'user_id' => $this->workspaceUserId(),
                'payment_module' => 'accounting_document',
                'bank_reference' => $treasuryReference,
            ],
            [
                'actor_user_id' => Auth::id(),
                'type' => $movementType,
                'transaction_type' => 'mouvement_bancaire',
                'payment_provider' => 'OCR Document',
                'amount' => $amount,
                'description' => $description,
                'transaction_date' => $transactionDate,
                'reference' => $invoiceNumber !== '' ? $invoiceNumber : $treasuryReference,
                'bank_account' => '512 Banque',
                'status' => 'effectue',
                'notes' => 'Généré automatiquement depuis la validation OCR du document #'.$document->id,
            ]
        );
    }

    private function isClassFiveAccount(?string $account): bool
    {
        $normalized = ltrim(trim((string) $account), '0');

        return $normalized !== '' && str_starts_with($normalized, '5');
    }

    private function authorizeDocument(AccountingDocument $document): void
    {
        if (! $this->workspaceOwnsDataUserId((int) $document->user_id)) {
            abort(403);
        }
    }

    /**
     * Construit un journal des corrections manuelles pour améliorer l'OCR.
     */
    private function buildOcrFeedback(array $existingData, array $validated): array
    {
        $mapping = [
            'partner' => 'partner',
            'invoice_date' => 'invoice_date',
            'invoice_number' => 'invoice_number',
            'amount_ht' => 'amount_ht',
            'amount_ttc' => 'amount_ttc',
            'tva' => 'tva',
            'currency' => 'currency',
            'debit_account' => 'debit_account',
            'credit_account' => 'credit_account',
            'document_type' => 'document_type',
        ];

        $changes = [];
        foreach ($mapping as $existingKey => $validatedKey) {
            $before = $existingKey === 'document_type'
                ? (string) ($existingData['document_type'] ?? '')
                : (string) ($existingData[$existingKey] ?? '');
            $after = (string) ($validated[$validatedKey] ?? '');

            if (trim($before) !== trim($after)) {
                $changes[] = [
                    'field' => $validatedKey,
                    'ocr_value' => $before,
                    'validated_value' => $after,
                ];
            }
        }

        return [
            'validated_at' => now()->toDateTimeString(),
            'validated_by_user_id' => Auth::id(),
            'changes_count' => count($changes),
            'changes' => $changes,
        ];
    }
}
