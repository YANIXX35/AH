<?php

namespace App\Domain\Inventory;

use App\Jobs\SyncStockMovementToErpNext;
use App\Models\StockMovement;
use App\Models\StockMovementErpNextSync;
use App\Models\StockProduct;
use App\Services\ErpNextClient;
use App\Services\TreasuryAudit;
use Illuminate\Support\Facades\DB;

/**
 * Module "Extensions" du cadrage produit : optionnel, activable par segment
 * (négoce/distribution), sans dépendance sur le cœur Trésorerie/Facturation.
 * Valorisation au CUMP (coût unitaire moyen pondéré), méthode standard OHADA
 * en repli si ERPNext est indisponible — sinon la quantité/valorisation
 * viennent directement d'ERPNext, qui fait foi (voir recordMovement()).
 */
class StockService
{
    public function __construct(private readonly ErpNextClient $erpNext)
    {
    }


    /**
     * @param  array{sku?: ?string, name: string, unit?: string, sale_price?: float, reorder_threshold?: ?float}  $data
     */
    public function createProduct(int $workspaceUserId, ?int $actorUserId, array $data): StockProduct
    {
        return StockProduct::create([
            'user_id' => $workspaceUserId,
            'actor_user_id' => $actorUserId,
            'sku' => $data['sku'] ?? null,
            'name' => $data['name'],
            'unit' => $data['unit'] ?? 'unité',
            'quantity_on_hand' => 0,
            'average_cost' => 0,
            'sale_price' => $data['sale_price'] ?? 0,
            'reorder_threshold' => $data['reorder_threshold'] ?? null,
            'is_active' => true,
        ]);
    }

    /**
     * Le nom, le SKU, le prix de vente et le seuil de réapprovisionnement restent
     * éditables sans restriction. L'unité de mesure, en revanche, est figée dès
     * qu'un mouvement de stock existe : la changer rétroactivement rendrait les
     * quantités déjà enregistrées (et le CUMP qui en découle) impossibles à
     * interpréter correctement. La quantité en stock et le CUMP eux-mêmes ne
     * sont jamais modifiables ici — seuls les mouvements (recordMovement) les
     * font évoluer.
     *
     * @param  array{sku?: ?string, name: string, unit?: string, sale_price?: float, reorder_threshold?: ?float}  $data
     */
    public function updateProduct(StockProduct $product, array $data, int $actorUserId): StockProduct
    {
        $hasMovements = $product->movements()->exists();

        $updateData = [
            'sku' => $data['sku'] ?? null,
            'name' => $data['name'],
            'sale_price' => $data['sale_price'] ?? 0,
            'reorder_threshold' => $data['reorder_threshold'] ?? null,
        ];

        if (! $hasMovements) {
            $updateData['unit'] = $data['unit'] ?? $product->unit;
        }

        $product->update($updateData);
        $changes = $product->getChanges();

        TreasuryAudit::log($product->user_id, 'stock.product.updated', $product, [
            'actor_user_id' => $actorUserId,
            'has_movements' => $hasMovements,
            'changed_fields' => array_keys($changes),
        ]);

        return $product->fresh();
    }

    /**
     * Supprime le produit si son historique est vide, sinon l'archive
     * (is_active=false) pour préserver la piste d'audit du stock déjà mouvementé
     * — supprimer physiquement casserait la valorisation CUMP déjà comptabilisée
     * et le lien financier avec les mouvements passés (movements.product_id est
     * en cascadeOnDelete, donc un vrai delete effacerait aussi tout l'historique).
     *
     * @return string 'deleted' | 'archived'
     */
    public function deleteProduct(StockProduct $product, int $actorUserId): string
    {
        $hasMovements = $product->movements()->exists();

        if ($hasMovements) {
            $product->update(['is_active' => false]);
            TreasuryAudit::log($product->user_id, 'stock.product.archived', $product, [
                'actor_user_id' => $actorUserId,
            ]);

            return 'archived';
        }

        TreasuryAudit::log($product->user_id, 'stock.product.deleted', $product, [
            'actor_user_id' => $actorUserId,
            'name' => $product->name,
            'sku' => $product->sku,
        ]);
        $product->delete();

        return 'deleted';
    }

    /**
     * Tente d'enregistrer le mouvement sur ERPNext et d'en lire le résultat
     * faisant foi. Retourne null si ERPNext est indisponible, la PME non
     * provisionnée, ou en cas d'erreur — jamais d'exception : le mouvement
     * local ne doit jamais être bloqué par une panne ERPNext.
     *
     * @return array{actual_qty: float, valuation_rate: float}|null
     */
    private function tryRecordMovementOnErpNext(
        StockProduct $product,
        string $type,
        float $delta,
        ?float $unitCost
    ): ?array {
        if (! $this->erpNext->enabled()) {
            return null;
        }

        $pme = $product->user;

        if (empty($pme) || empty($pme->erpnext_company_name) || empty($pme->erpnext_warehouse)) {
            return null;
        }

        try {
            $itemCode = $this->erpNext->findOrCreateItem($product->name);

            if ($type === 'ajustement') {
                $newAbsoluteQty = round(((float) $product->quantity_on_hand) + $delta, 2);
                $newAbsoluteQty = max($newAbsoluteQty, 0);
                $this->erpNext->adjustStockForPme($pme, $product->name, $newAbsoluteQty);
            } else {
                $direction = $delta > 0 ? 'in' : 'out';
                $this->erpNext->createStockMovementForPme(
                    $pme,
                    $product->name,
                    abs($delta),
                    (float) ($unitCost ?? 0),
                    $direction
                );
            }

            return $this->erpNext->getBinForItem($pme, $itemCode);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  string  $type  'entree' | 'sortie' | 'ajustement'
     * @param  float  $quantity  Toujours positive pour entree/sortie ; signée pour ajustement (négatif = correction à la baisse)
     */
    public function recordMovement(
        StockProduct $product,
        string $type,
        float $quantity,
        ?float $unitCost,
        \DateTimeInterface $date,
        ?string $reason,
        ?string $notes,
        int $actorUserId
    ): StockMovement {
        if (! in_array($type, ['entree', 'sortie', 'ajustement'], true)) {
            throw new \InvalidArgumentException('Type de mouvement invalide.');
        }

        $delta = match ($type) {
            'entree' => abs($quantity),
            'sortie' => -abs($quantity),
            'ajustement' => $quantity,
        };

        if (abs($delta) < 0.001) {
            throw new \InvalidArgumentException('La quantité ne peut pas être nulle.');
        }

        $erpNextResult = $this->tryRecordMovementOnErpNext($product, $type, $delta, $unitCost);

        $movement = DB::transaction(function () use ($product, $type, $delta, $unitCost, $date, $reason, $notes, $actorUserId, $erpNextResult) {
            $locked = StockProduct::where('id', $product->id)->lockForUpdate()->firstOrFail();

            $currentQty = (float) $locked->quantity_on_hand;
            $currentAvg = (float) $locked->average_cost;

            $newQty = round($currentQty + $delta, 2);
            if ($newQty < -0.001) {
                throw new \InvalidArgumentException(sprintf(
                    'Stock insuffisant : %.2f disponible(s), mouvement de %.2f demandé.',
                    $currentQty,
                    $delta
                ));
            }
            $newQty = max($newQty, 0);

            $storedUnitCost = $unitCost;

            if ($erpNextResult !== null) {
                $newQty = $erpNextResult['actual_qty'];
                $newAvg = $erpNextResult['valuation_rate'];
                $storedUnitCost = $storedUnitCost ?? $currentAvg;
            } elseif ($delta > 0) {
                if ($unitCost !== null) {
                    $newAvg = $newQty > 0
                        ? round((($currentQty * $currentAvg) + ($delta * $unitCost)) / $newQty, 2)
                        : 0.0;
                } else {
                    $newAvg = $currentAvg;
                }
            } else {
                $storedUnitCost = $unitCost ?? $currentAvg;
                $newAvg = $currentAvg;
            }

            $movement = StockMovement::create([
                'product_id' => $product->id,
                'user_id' => $product->user_id,
                'actor_user_id' => $actorUserId,
                'type' => $type,
                'quantity' => $delta,
                'unit_cost' => $storedUnitCost,
                'quantity_after' => $newQty,
                'average_cost_after' => $newAvg,
                'movement_date' => $date->format('Y-m-d'),
                'reason' => $reason,
                'notes' => $notes,
            ]);

            $locked->update([
                'quantity_on_hand' => $newQty,
                'average_cost' => $newAvg,
            ]);

            TreasuryAudit::log($product->user_id, 'stock.movement.recorded', $movement, [
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $delta,
                'quantity_after' => $newQty,
                'erpnext_engine' => $erpNextResult !== null,
            ]);

            return $movement;
        });

        if ($erpNextResult !== null) {
            StockMovementErpNextSync::updateOrCreate(
                ['stock_movement_id' => $movement->id],
                ['status' => 'synced', 'last_synced_at' => now()]
            );
        } else {
            SyncStockMovementToErpNext::dispatch($movement);
        }

        return $movement;
    }
}
