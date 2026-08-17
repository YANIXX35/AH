<?php

namespace App\Domain\Treasury\MobileMoney;

use App\Models\MobileMoneyStatementImport;
use App\Models\MobileMoneyTransaction;
use App\Models\TreasuryTransaction;
use App\Services\TreasuryAudit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class MobileMoneyReconciliationService
{
    /**
     * Fenêtre de tolérance (jours) pour rapprocher une transaction Mobile Money
     * avec une transaction de trésorerie déjà saisie manuellement.
     */
    private const MATCH_WINDOW_DAYS = 3;

    public function __construct(private readonly MobileMoneyConnectorResolver $connectorResolver) {}

    public function importStatement(
        UploadedFile $file,
        string $operator,
        int $workspaceUserId,
        ?int $actorUserId,
        ?string $treasuryAccountCode,
        string $consentIp
    ): MobileMoneyStatementImport {
        $storedPath = $file->store('mobile-money-statements', 'public');

        $import = MobileMoneyStatementImport::create([
            'user_id' => $workspaceUserId,
            'actor_user_id' => $actorUserId,
            'operator' => $operator,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'treasury_account_code' => $treasuryAccountCode,
            'consent_given_at' => now(),
            'consent_ip' => $consentIp,
            'status' => 'processing',
        ]);

        try {
            $rows = $this->connectorResolver->resolve($operator)->parse(
                Storage::disk('public')->path($storedPath),
                $operator
            );

            $imported = 0;
            $duplicates = 0;

            foreach ($rows as $row) {
                if ($this->isDuplicate($workspaceUserId, $operator, $row)) {
                    $duplicates++;

                    continue;
                }

                MobileMoneyTransaction::create([
                    'statement_import_id' => $import->id,
                    'user_id' => $workspaceUserId,
                    'operator' => $operator,
                    'external_reference' => $row->externalReference,
                    'occurred_at' => $row->occurredAt->format('Y-m-d'),
                    'amount' => $row->amount,
                    'direction' => $row->direction,
                    'counterparty_name' => $row->counterpartyName,
                    'counterparty_number' => $row->counterpartyNumber,
                    'raw_line' => $row->rawLine,
                    'status' => 'pending',
                ]);
                $imported++;
            }

            $import->update([
                'rows_total' => count($rows),
                'rows_imported' => $imported,
                'rows_duplicate' => $duplicates,
                'status' => 'completed',
            ]);

            $matched = $this->autoReconcile($import);
            $import->update(['rows_matched' => $matched]);

            TreasuryAudit::log($workspaceUserId, 'treasury.mobile_money.import', $import, [
                'operator' => $operator,
                'rows_total' => count($rows),
                'rows_imported' => $imported,
                'rows_duplicate' => $duplicates,
                'rows_matched' => $matched,
            ]);
        } catch (\Throwable $e) {
            $import->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            throw $e;
        }

        return $import->fresh();
    }

    /**
     * Tente un rapprochement automatique pour chaque ligne en attente de l'import :
     * un seul candidat de trésorerie correspondant (montant + sens + fenêtre de
     * dates) => rapprochement automatique. Zéro ou plusieurs candidats => laissé
     * en attente pour une action manuelle (créer ou choisir dans la revue).
     */
    public function autoReconcile(MobileMoneyStatementImport $import): int
    {
        $matched = 0;

        foreach ($import->transactions()->pending()->get() as $transaction) {
            $candidates = $this->findCandidates($transaction);

            if ($candidates->count() === 1) {
                $this->linkToTreasuryTransaction($transaction, $candidates->first());
                $matched++;
            }
        }

        return $matched;
    }

    /**
     * @return Collection<int, TreasuryTransaction>
     */
    public function findCandidates(MobileMoneyTransaction $transaction)
    {
        $type = $transaction->direction === 'in' ? 'encaissement' : 'decaissement';

        return TreasuryTransaction::query()
            ->where('user_id', $transaction->user_id)
            ->where('type', $type)
            ->where('amount', $transaction->amount)
            ->whereBetween('transaction_date', [
                $transaction->occurred_at->copy()->subDays(self::MATCH_WINDOW_DAYS),
                $transaction->occurred_at->copy()->addDays(self::MATCH_WINDOW_DAYS),
            ])
            ->whereDoesntHave('mobileMoneyTransaction')
            ->get();
    }

    public function linkToTreasuryTransaction(MobileMoneyTransaction $transaction, TreasuryTransaction $treasuryTransaction): void
    {
        $transaction->update([
            'treasury_transaction_id' => $treasuryTransaction->id,
            'status' => 'matched',
            'matched_at' => now(),
        ]);
    }

    /**
     * Aucune transaction de trésorerie ne correspond : ce mouvement Mobile Money
     * n'a jamais été saisi dans PME360. On la crée directement, ce qui est
     * précisément le gain de la réconciliation lecture seule.
     */
    public function createTreasuryTransactionFromMobileMoney(MobileMoneyTransaction $transaction, int $actorUserId): TreasuryTransaction
    {
        $treasuryTransaction = TreasuryTransaction::create([
            'user_id' => $transaction->user_id,
            'actor_user_id' => $actorUserId,
            'type' => $transaction->direction === 'in' ? 'encaissement' : 'decaissement',
            'transaction_type' => $transaction->direction === 'in' ? 'Paiement client' : 'Paiement fournisseur',
            'payment_module' => 'mobile_money_reconciliation',
            'payment_provider' => ucfirst(str_replace('_', ' ', $transaction->operator)),
            'mobile_method' => $transaction->operator,
            'mobile_number' => $transaction->counterparty_number,
            'amount' => $transaction->amount,
            'description' => trim(sprintf(
                'Mobile Money %s%s',
                $transaction->operator,
                $transaction->counterparty_name ? ' — '.$transaction->counterparty_name : ''
            )),
            'transaction_date' => $transaction->occurred_at,
            // Mobile Money : encaissement/décaissement réel et immédiat (PRD 4.4) —
            // aucun délai bancaire, la date de valeur est toujours la date de l'opération.
            'value_date' => $transaction->occurred_at,
            'reference' => $transaction->external_reference,
            'status' => 'effectue',
        ]);

        $this->linkToTreasuryTransaction($transaction, $treasuryTransaction);
        $transaction->update(['status' => 'created']);

        TreasuryAudit::log($transaction->user_id, 'treasury.mobile_money.transaction.created', $treasuryTransaction, [
            'mobile_money_transaction_id' => $transaction->id,
            'operator' => $transaction->operator,
        ]);

        return $treasuryTransaction;
    }

    public function ignore(MobileMoneyTransaction $transaction): void
    {
        $transaction->update(['status' => 'ignored']);
    }

    /**
     * Droit à l'effacement (protection des données) : efface les données à caractère
     * personnel des correspondants (nom, numéro, ligne brute du relevé) tout en
     * conservant montant/date/statut/liens comptables — nécessaires à la piste
     * d'audit financière et non couverts par cette demande d'effacement.
     */
    public function purgePersonalData(MobileMoneyStatementImport $import): int
    {
        $count = $import->transactions()->update([
            'counterparty_name' => null,
            'counterparty_number' => null,
            'raw_line' => null,
        ]);

        $import->update(['personal_data_purged_at' => now()]);

        TreasuryAudit::log($import->user_id, 'treasury.mobile_money.personal_data_purged', $import, [
            'transactions_affected' => $count,
        ]);

        return $count;
    }

    private function isDuplicate(int $userId, string $operator, NormalizedMobileMoneyTransaction $row): bool
    {
        return MobileMoneyTransaction::query()
            ->where('user_id', $userId)
            ->where('operator', $operator)
            ->where('amount', $row->amount)
            ->where('occurred_at', $row->occurredAt->format('Y-m-d'))
            ->when($row->externalReference !== null, fn ($q) => $q->where('external_reference', $row->externalReference))
            ->exists();
    }
}
