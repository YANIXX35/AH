<?php

namespace App\Domain\Inventory;

use App\Models\StockMovement;
use App\Models\StockProduct;
use App\Services\TreasuryAudit;
use Illuminate\Support\Facades\DB;

/**
 * Module "Extensions" du cadrage produit : optionnel, activable par segment
 * (négoce/distribution), sans dépendance sur le cœur Trésorerie/Facturation.
 * Valorisation au CUMP (coût unitaire moyen pondéré), méthode standard OHADA.
 */
class StockService
{
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

        return DB::transaction(function () use ($product, $type, $quantity, $unitCost, $date, $reason, $notes, $actorUserId) {
            $locked = StockProduct::where('id', $product->id)->lockForUpdate()->firstOrFail();

            $currentQty = (float) $locked->quantity_on_hand;
            $currentAvg = (float) $locked->average_cost;

            $delta = match ($type) {
                'entree' => abs($quantity),
                'sortie' => -abs($quantity),
                'ajustement' => $quantity,
            };

            if (abs($delta) < 0.001) {
                throw new \InvalidArgumentException('La quantité ne peut pas être nulle.');
            }

            $newQty = round($currentQty + $delta, 2);
            if ($newQty < -0.001) {
                throw new \InvalidArgumentException(sprintf(
                    'Stock insuffisant : %.2f disponible(s), mouvement de %.2f demandé.',
                    $currentQty,
                    $delta
                ));
            }
            $newQty = max($newQty, 0);

            if ($delta > 0) {
                if ($unitCost !== null) {
                    $newAvg = $newQty > 0
                        ? round((($currentQty * $currentAvg) + ($delta * $unitCost)) / $newQty, 2)
                        : 0.0;
                } else {
                    $newAvg = $currentAvg;
                }
            } else {
                $unitCost = $unitCost ?? $currentAvg;
                $newAvg = $currentAvg;
            }

            $movement = StockMovement::create([
                'product_id' => $product->id,
                'user_id' => $product->user_id,
                'actor_user_id' => $actorUserId,
                'type' => $type,
                'quantity' => $delta,
                'unit_cost' => $unitCost,
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
            ]);

            return $movement;
        });
    }
}
