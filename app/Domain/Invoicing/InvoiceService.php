<?php

namespace App\Domain\Invoicing;

use App\Models\AccountingEntry;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceNumberCounter;
use App\Models\InvoicePayment;
use App\Services\TreasuryAudit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Ferme la boucle "Facturation -> Créances -> Rapprochement paiement" décrite
 * dans le cadrage produit : une facture émise devient une créance (écriture
 * 411/701 OHADA), un encaissement contre cette facture solde la créance
 * (écriture 411/compte de trésorerie).
 */
class InvoiceService
{
    public function createInvoice(
        int $workspaceUserId,
        ?int $actorUserId,
        string $clientName,
        ?string $clientContact,
        ?string $clientAddress,
        ?string $clientTaxId,
        \DateTimeInterface $issueDate,
        \DateTimeInterface $dueDate,
        array $items,
        float $taxRate = 0,
        ?string $notes = null,
        string $currency = 'XOF'
    ): Invoice {
        return DB::transaction(function () use (
            $workspaceUserId, $actorUserId, $clientName, $clientContact, $clientAddress,
            $clientTaxId, $issueDate, $dueDate, $items, $taxRate, $notes, $currency
        ) {
            $invoiceNumber = $this->allocateInvoiceNumber($workspaceUserId, $issueDate);

            $subtotal = 0.0;
            foreach ($items as $item) {
                $subtotal += round(((float) $item['quantity']) * ((float) $item['unit_price']), 2);
            }
            $taxAmount = round($subtotal * $taxRate / 100, 2);
            $totalAmount = round($subtotal + $taxAmount, 2);

            $invoice = Invoice::create([
                'user_id' => $workspaceUserId,
                'actor_user_id' => $actorUserId,
                'invoice_number' => $invoiceNumber,
                'issue_date' => $issueDate->format('Y-m-d'),
                'due_date' => $dueDate->format('Y-m-d'),
                'client_name' => $clientName,
                'client_contact' => $clientContact,
                'client_address' => $clientAddress,
                'client_tax_id' => $clientTaxId,
                'currency' => $currency,
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'amount_paid' => 0,
                'status' => 'unpaid',
                'notes' => $notes,
            ]);

            foreach (array_values($items) as $position => $item) {
                $lineTotal = round(((float) $item['quantity']) * ((float) $item['unit_price']), 2);
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'position' => $position,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $lineTotal,
                ]);
            }

            $this->createSaleAccountingEntries($invoice, $actorUserId);

            TreasuryAudit::log($workspaceUserId, 'invoicing.invoice.created', $invoice, [
                'invoice_number' => $invoice->invoice_number,
                'total_amount' => $totalAmount,
            ]);

            return $invoice->fresh('items');
        });
    }

    /**
     * @param  array{amount: float, paid_at: \DateTimeInterface, treasury_transaction_id: ?int, method: ?string, reference: ?string, treasury_account_code: ?string, notes: ?string}  $data
     */
    public function recordPayment(Invoice $invoice, array $data, int $actorUserId): InvoicePayment
    {
        return DB::transaction(function () use ($invoice, $data, $actorUserId) {
            $amount = round((float) $data['amount'], 2);
            $balanceDue = $invoice->balanceDue();

            if ($amount <= 0) {
                throw new \InvalidArgumentException('Le montant encaissé doit être positif.');
            }
            if ($amount > $balanceDue + 0.01) {
                throw new \InvalidArgumentException(sprintf('Le montant dépasse le solde dû (%.2f).', $balanceDue));
            }

            $accountingEntry = null;
            if (! empty($data['treasury_account_code'])) {
                $accountingEntry = AccountingEntry::create([
                    'user_id' => $invoice->user_id,
                    'actor_user_id' => $actorUserId,
                    'date' => $data['paid_at']->format('Y-m-d'),
                    'document_type' => 'encaissement_facture',
                    'document_reference' => $invoice->invoice_number,
                    'description' => 'Encaissement facture '.$invoice->invoice_number.' — '.$invoice->client_name,
                    'debit_account' => $data['treasury_account_code'],
                    'credit_account' => '411',
                    'amount' => $amount,
                ]);
            }

            $payment = InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'actor_user_id' => $actorUserId,
                'treasury_transaction_id' => $data['treasury_transaction_id'] ?? null,
                'accounting_entry_id' => $accountingEntry?->id,
                'amount' => $amount,
                'paid_at' => $data['paid_at']->format('Y-m-d'),
                'method' => $data['method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $newAmountPaid = round((float) $invoice->amount_paid + $amount, 2);
            $status = $newAmountPaid >= (float) $invoice->total_amount - 0.01
                ? 'paid'
                : ($newAmountPaid > 0 ? 'partially_paid' : 'unpaid');

            $invoice->update([
                'amount_paid' => $newAmountPaid,
                'status' => $status,
            ]);

            TreasuryAudit::log($invoice->user_id, 'invoicing.invoice.payment_recorded', $invoice, [
                'invoice_number' => $invoice->invoice_number,
                'amount' => $amount,
                'new_status' => $status,
            ]);

            return $payment;
        });
    }

    /**
     * Corrige une facture pas encore réglée (aucun encaissement enregistré, pas annulée).
     * La date d'émission n'est jamais modifiable : le numéro de facture a déjà été alloué
     * pour son année, la changer casserait la cohérence numéro/année. Les lignes sont
     * remplacées et les écritures comptables de vente régénérées pour rester exactes.
     *
     * @param  array{client_name: string, client_contact: ?string, client_address: ?string, client_tax_id: ?string, due_date: \DateTimeInterface, items: array, tax_rate?: float, notes: ?string}  $data
     */
    public function updateInvoice(Invoice $invoice, array $data, int $actorUserId): Invoice
    {
        if ((float) $invoice->amount_paid > 0 || $invoice->status === 'cancelled') {
            throw new \InvalidArgumentException(
                'Facture déjà réglée (partiellement ou totalement) ou annulée : impossible de la modifier.'
            );
        }

        return DB::transaction(function () use ($invoice, $data, $actorUserId) {
            $before = $invoice->only(['client_name', 'due_date', 'tax_rate', 'total_amount']);

            $subtotal = 0.0;
            foreach ($data['items'] as $item) {
                $subtotal += round(((float) $item['quantity']) * ((float) $item['unit_price']), 2);
            }
            $taxRate = (float) ($data['tax_rate'] ?? 0);
            $taxAmount = round($subtotal * $taxRate / 100, 2);
            $totalAmount = round($subtotal + $taxAmount, 2);

            $invoice->update([
                'client_name' => $data['client_name'],
                'client_contact' => $data['client_contact'] ?? null,
                'client_address' => $data['client_address'] ?? null,
                'client_tax_id' => $data['client_tax_id'] ?? null,
                'due_date' => $data['due_date']->format('Y-m-d'),
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'notes' => $data['notes'] ?? null,
            ]);

            $invoice->items()->delete();
            foreach (array_values($data['items']) as $position => $item) {
                $lineTotal = round(((float) $item['quantity']) * ((float) $item['unit_price']), 2);
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'position' => $position,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $lineTotal,
                ]);
            }

            AccountingEntry::query()
                ->where('user_id', $invoice->user_id)
                ->where('document_type', 'facture_vente')
                ->where('document_reference', $invoice->invoice_number)
                ->delete();
            $this->createSaleAccountingEntries($invoice->fresh(), $actorUserId);

            TreasuryAudit::log($invoice->user_id, 'invoicing.invoice.updated', $invoice, [
                'invoice_number' => $invoice->invoice_number,
                'actor_user_id' => $actorUserId,
                'before' => $before,
                'after' => $invoice->fresh()->only(['client_name', 'due_date', 'tax_rate', 'total_amount']),
            ]);

            return $invoice->fresh('items');
        });
    }

    public function cancelInvoice(Invoice $invoice, string $reason, int $actorUserId): void
    {
        if ($invoice->status === 'paid' || (float) $invoice->amount_paid > 0) {
            throw new \InvalidArgumentException('Facture déjà réglée (partiellement ou totalement) : impossible d\'annuler, établir un avoir.');
        }

        $invoice->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_reason' => $reason,
        ]);

        TreasuryAudit::log($invoice->user_id, 'invoicing.invoice.cancelled', $invoice, [
            'invoice_number' => $invoice->invoice_number,
            'reason' => $reason,
            'actor_user_id' => $actorUserId,
        ]);
    }

    public function deleteInvoice(Invoice $invoice, int $actorUserId): void
    {
        if ((float) $invoice->amount_paid > 0 || $invoice->status === 'cancelled') {
            throw new \InvalidArgumentException(
                'Facture déjà réglée (partiellement ou totalement) ou annulée : impossible de la supprimer.'
            );
        }

        TreasuryAudit::log($invoice->user_id, 'invoicing.invoice.deleted', $invoice, [
            'invoice_number' => $invoice->invoice_number,
            'actor_user_id' => $actorUserId,
            'before' => $invoice->only(['invoice_number', 'client_name', 'total_amount', 'status', 'issue_date']),
        ]);

        $invoice->delete();
    }

    private function createSaleAccountingEntries(Invoice $invoice, ?int $actorUserId): void
    {
        AccountingEntry::create([
            'user_id' => $invoice->user_id,
            'actor_user_id' => $actorUserId,
            'date' => $invoice->issue_date->format('Y-m-d'),
            'document_type' => 'facture_vente',
            'document_reference' => $invoice->invoice_number,
            'description' => 'Facture '.$invoice->invoice_number.' — '.$invoice->client_name,
            'debit_account' => '411',
            'credit_account' => '701',
            'amount' => $invoice->subtotal,
        ]);

        if ((float) $invoice->tax_amount > 0) {
            AccountingEntry::create([
                'user_id' => $invoice->user_id,
                'actor_user_id' => $actorUserId,
                'date' => $invoice->issue_date->format('Y-m-d'),
                'document_type' => 'facture_vente',
                'document_reference' => $invoice->invoice_number,
                'description' => 'TVA facture '.$invoice->invoice_number,
                'debit_account' => '411',
                'credit_account' => '4431',
                'amount' => $invoice->tax_amount,
            ]);
        }
    }

    /**
     * Allocation atomique et séquentielle (sans trou) du numéro de facture par
     * PME et par année, condition de conformité e-invoicing UEMOA. Verrouillage
     * de ligne (lockForUpdate) pour éviter deux numéros identiques en cas de
     * requêtes concurrentes.
     */
    private function allocateInvoiceNumber(int $userId, \DateTimeInterface $issueDate): string
    {
        $year = (int) $issueDate->format('Y');

        try {
            InvoiceNumberCounter::firstOrCreate(
                ['user_id' => $userId, 'year' => $year],
                ['last_number' => 0]
            );
        } catch (QueryException) {
            // Créée entre-temps par une requête concurrente : rien à faire, la lecture verrouillée ci-dessous la trouvera.
        }

        $counter = InvoiceNumberCounter::where('user_id', $userId)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        $next = $counter->last_number + 1;
        $counter->update(['last_number' => $next]);

        return sprintf('FA-%d-%06d', $year, $next);
    }
}
