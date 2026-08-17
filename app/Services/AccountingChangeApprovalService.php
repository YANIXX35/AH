<?php

namespace App\Services;

use App\Domain\Invoicing\InvoiceService;
use App\Models\AccountingChangeRequest;
use App\Models\AccountingDocument;
use App\Models\AccountingEntry;
use App\Models\AppNotification;
use App\Models\Invoice;
use App\Models\TreasuryTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Validation comptable des modifications/suppressions sur les données
 * comptables (écritures, documents, factures) : un utilisateur non-comptable
 * (`is_accountant = false`) ne les applique jamais directement — la demande
 * est mise en attente et n'importe quel comptable (`is_accountant = true`,
 * global, non rattaché à une entreprise précise) peut l'approuver ou la
 * refuser. Un comptable qui agit lui-même n'a pas besoin de validation.
 */
class AccountingChangeApprovalService
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function requestChange(
        Model $subject,
        string $action,
        int $workspaceUserId,
        int $requesterId,
        string $label,
        array $payload = []
    ): AccountingChangeRequest {
        $existing = AccountingChangeRequest::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->pending()
            ->first();

        if ($existing) {
            throw new \RuntimeException('Une demande est déjà en attente de validation pour cet élément.');
        }

        $changeRequest = AccountingChangeRequest::create([
            'requester_user_id' => $requesterId,
            'workspace_user_id' => $workspaceUserId,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'subject_label' => $label,
            'payload' => $payload,
            'status' => 'pending',
        ]);

        $this->notifyAccountants($changeRequest);

        return $changeRequest;
    }

    public function approve(AccountingChangeRequest $changeRequest, User $accountant, ?string $note = null): void
    {
        abort_if($changeRequest->status !== 'pending', 409, 'Cette demande a déjà été traitée.');

        $this->apply($changeRequest, $accountant);

        $changeRequest->update([
            'status' => 'approved',
            'reviewed_by_user_id' => $accountant->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        $this->notifyRequester($changeRequest, true);
    }

    public function reject(AccountingChangeRequest $changeRequest, User $accountant, ?string $note = null): void
    {
        abort_if($changeRequest->status !== 'pending', 409, 'Cette demande a déjà été traitée.');

        $changeRequest->update([
            'status' => 'rejected',
            'reviewed_by_user_id' => $accountant->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        $this->notifyRequester($changeRequest, false);
    }

    private function apply(AccountingChangeRequest $changeRequest, User $accountant): void
    {
        match ($changeRequest->subject_type) {
            AccountingEntry::class => $this->applyEntry($changeRequest, $accountant),
            AccountingDocument::class => $this->applyDocument($changeRequest, $accountant),
            Invoice::class => $this->applyInvoice($changeRequest, $accountant),
            default => throw new \RuntimeException('Type de demande inconnu : '.$changeRequest->subject_type),
        };
    }

    private function applyEntry(AccountingChangeRequest $changeRequest, User $accountant): void
    {
        $entry = AccountingEntry::find($changeRequest->subject_id);
        if (! $entry) {
            return;
        }

        if ($changeRequest->action === 'delete') {
            TreasuryAudit::log($entry->user_id, 'accounting.entry.deleted', $entry, [
                'actor_user_id' => $accountant->id,
                'description' => $entry->description,
                'amount' => (float) $entry->amount,
                'document_reference' => $entry->document_reference,
                'approved_change_request_id' => $changeRequest->id,
            ]);

            if ($entry->attachment_path) {
                Storage::disk('public')->delete($entry->attachment_path);
            }
            $entry->delete();

            return;
        }

        $payload = (array) $changeRequest->payload;
        $before = $entry->only(array_keys($payload));
        $entry->update(array_merge($payload, ['actor_user_id' => $accountant->id]));

        TreasuryAudit::log($entry->user_id, 'accounting.entry.updated', $entry, [
            'before' => $before,
            'after' => $entry->only(array_keys($payload)),
            'approved_change_request_id' => $changeRequest->id,
        ]);
    }

    private function applyDocument(AccountingChangeRequest $changeRequest, User $accountant): void
    {
        $document = AccountingDocument::find($changeRequest->subject_id);
        if (! $document) {
            return;
        }

        TreasuryAudit::log($document->user_id, 'accounting.document.deleted', $document, [
            'actor_user_id' => $accountant->id,
            'original_name' => $document->original_name,
            'status' => $document->status,
            'linked_entries_count' => $document->entries()->count(),
            'approved_change_request_id' => $changeRequest->id,
        ]);

        foreach ($document->entries as $entry) {
            if ($entry->attachment_path && $entry->attachment_path !== $document->stored_path) {
                Storage::disk('public')->delete($entry->attachment_path);
            }
            $entry->delete();
        }

        TreasuryTransaction::query()
            ->where('user_id', $document->user_id)
            ->where('payment_module', 'accounting_document')
            ->where('bank_reference', 'DOC-BANK-'.$document->id)
            ->delete();

        if ($document->stored_path) {
            Storage::disk('public')->delete($document->stored_path);
        }

        $document->delete();
    }

    private function applyInvoice(AccountingChangeRequest $changeRequest, User $accountant): void
    {
        $invoice = Invoice::find($changeRequest->subject_id);
        if (! $invoice) {
            return;
        }

        $payload = (array) $changeRequest->payload;

        match ($changeRequest->action) {
            'update' => $this->invoiceService->updateInvoice($invoice, [
                'client_name' => $payload['client_name'],
                'client_contact' => $payload['client_contact'] ?? null,
                'client_address' => $payload['client_address'] ?? null,
                'client_tax_id' => $payload['client_tax_id'] ?? null,
                'due_date' => Carbon::parse($payload['due_date']),
                'items' => $payload['items'],
                'tax_rate' => (float) ($payload['tax_rate'] ?? 0),
                'notes' => $payload['notes'] ?? null,
            ], $accountant->id),
            'cancel' => $this->invoiceService->cancelInvoice(
                $invoice,
                (string) ($payload['reason'] ?? 'Approuvé par le comptable.'),
                $accountant->id
            ),
            'delete' => $this->invoiceService->deleteInvoice($invoice, $accountant->id),
            default => throw new \RuntimeException('Action facture inconnue : '.$changeRequest->action),
        };
    }

    private function notifyAccountants(AccountingChangeRequest $changeRequest): void
    {
        User::query()->where('is_accountant', true)->get()->each(function (User $accountant) use ($changeRequest) {
            AppNotification::create([
                'user_id' => $accountant->id,
                'title' => 'Demande de validation comptable',
                'body' => $this->describeAction($changeRequest).' — en attente de votre validation.',
                'type' => 'accounting_change_request',
                'action_url' => route('accountant.change-requests.index'),
            ]);
        });
    }

    private function notifyRequester(AccountingChangeRequest $changeRequest, bool $approved): void
    {
        AppNotification::create([
            'user_id' => $changeRequest->requester_user_id,
            'title' => $approved ? 'Demande approuvée' : 'Demande refusée',
            'body' => ($approved ? 'Votre demande a été approuvée : ' : 'Votre demande a été refusée : ').$changeRequest->subject_label,
            'type' => 'accounting_change_request',
            'action_url' => route('accounting'),
        ]);
    }

    private function describeAction(AccountingChangeRequest $changeRequest): string
    {
        $verb = match ($changeRequest->action) {
            'update' => 'Modification',
            'delete' => 'Suppression',
            'cancel' => 'Annulation',
            default => 'Action',
        };

        return "{$verb} demandée : {$changeRequest->subject_label}";
    }
}
