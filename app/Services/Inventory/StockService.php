<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Catalog\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Core inventory service for Kolabri Getränke.
 *
 * Responsibilities:
 *   - Increase / decrease stock for a product in a warehouse.
 *   - Write an audit-safe journal entry (stock_movements) for every change.
 *   - Upsert the stock snapshot (product_stocks) atomically inside a transaction.
 *   - Explode bundle products into their leaf components on decrease.
 *
 * Negative stock is intentionally allowed — the system supports backorders.
 *
 * All mutations run inside a DB::transaction(). If any step fails the
 * entire operation is rolled back, keeping journal and snapshot consistent.
 *
 * Bundle explosion:
 *   When decreaseStock() is called on a bundle product (is_bundle = true)
 *   the bundle itself is NOT touched. Instead, Product::resolveBundleComponentsRecursive()
 *   flattens the component tree and decreaseStock() is applied to each leaf
 *   product with movement_type = bundle_explosion.
 *   Infinite-loop protection is handled by the recursive method itself
 *   (it maintains a $visited set and logs a warning on circular references).
 */
class StockService
{
    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Add stock for a product in a warehouse.
     *
     * Creates the product_stocks row if it does not yet exist.
     * Always writes a stock_movements journal entry.
     *
     * @param  Product     $product
     * @param  Warehouse   $warehouse
     * @param  float       $qty            Positive quantity to add (must be > 0)
     * @param  string      $movementType   One of StockMovement::TYPE_* constants
     * @param  string|null $referenceType  Optional: 'order', 'supplier_order', 'manual', …
     * @param  int|null    $referenceId    Optional: PK of the referencing document
     * @param  string|null $note           Optional free-text note
     * @param  int|null    $createdByUserId Optional: auditing user id
     * @throws \InvalidArgumentException   when $qty <= 0
     */
    public function increaseStock(
        Product  $product,
        Warehouse $warehouse,
        float    $qty,
        string   $movementType,
        ?string  $referenceType   = null,
        ?int     $referenceId     = null,
        ?string  $note            = null,
        ?int     $createdByUserId = null,
    ): void {
        if ($qty <= 0) {
            throw new \InvalidArgumentException(
                "increaseStock requires a positive quantity, got {$qty}."
            );
        }

        DB::transaction(function () use (
            $product, $warehouse, $qty, $movementType,
            $referenceType, $referenceId, $note, $createdByUserId
        ): void {
            $this->applyDelta(
                product:         $product,
                warehouse:       $warehouse,
                delta:           $qty,         // positive = in
                movementType:    $movementType,
                referenceType:   $referenceType,
                referenceId:     $referenceId,
                note:            $note,
                createdByUserId: $createdByUserId,
            );
        });
    }

    /**
     * Remove stock for a product in a warehouse.
     *
     * If the product is a BUNDLE (is_bundle = true):
     *   - The bundle itself is NOT touched.
     *   - Each leaf component is decreased proportionally.
     *   - Each component movement is written with movement_type = bundle_explosion.
     *   - The originally requested $movementType is ignored for child records
     *     (bundle_explosion is the canonical type for these entries).
     *
     * Negative stock is allowed — the caller must decide whether to
     * reject orders that would exceed reserve limits.
     *
     * Creates the product_stocks row if it does not yet exist.
     * Always writes stock_movements journal entries.
     *
     * @param  Product     $product
     * @param  Warehouse   $warehouse
     * @param  float       $qty            Positive quantity to remove (must be > 0)
     * @param  string      $movementType   One of StockMovement::TYPE_* constants
     * @param  string|null $referenceType  Optional: 'order', 'supplier_order', 'manual', …
     * @param  int|null    $referenceId    Optional: PK of the referencing document
     * @param  string|null $note           Optional free-text note
     * @param  int|null    $createdByUserId Optional: auditing user id
     * @throws \InvalidArgumentException   when $qty <= 0
     */
    public function decreaseStock(
        Product  $product,
        Warehouse $warehouse,
        float    $qty,
        string   $movementType,
        ?string  $referenceType   = null,
        ?int     $referenceId     = null,
        ?string  $note            = null,
        ?int     $createdByUserId = null,
    ): void {
        if ($qty <= 0) {
            throw new \InvalidArgumentException(
                "decreaseStock requires a positive quantity, got {$qty}."
            );
        }

        DB::transaction(function () use (
            $product, $warehouse, $qty, $movementType,
            $referenceType, $referenceId, $note, $createdByUserId
        ): void {
            if ($product->is_bundle) {
                $this->explodeBundle(
                    bundle:          $product,
                    warehouse:       $warehouse,
                    qty:             $qty,
                    referenceType:   $referenceType,
                    referenceId:     $referenceId,
                    note:            $note,
                    createdByUserId: $createdByUserId,
                );

                return;
            }

            $this->applyDelta(
                product:         $product,
                warehouse:       $warehouse,
                delta:           -$qty,         // negative = out
                movementType:    $movementType,
                referenceType:   $referenceType,
                referenceId:     $referenceId,
                note:            $note,
                createdByUserId: $createdByUserId,
            );
        });
    }

    /**
     * Return the quantity immediately available for new orders.
     *
     *   available = quantity - reserved_quantity
     *
     * Returns 0.0 when no stock row exists yet for this product/warehouse.
     *
     * @param  Product   $product
     * @param  Warehouse $warehouse
     * @return float
     */
    public function getAvailableQuantity(Product $product, Warehouse $warehouse): float
    {
        $stock = ProductStock::query()
            ->where('product_id',   $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        if ($stock === null) {
            return 0.0;
        }

        return $stock->availableQuantity();
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * Explode a bundle into its leaf components and decrease each one.
     *
     * resolveBundleComponentsRecursive() returns an array keyed by product_id:
     *   [ product_id => ['product' => Product, 'qty' => int] ]
     *
     * Each component quantity is multiplied by the number of bundles being sold.
     *
     * @param  Product     $bundle
     * @param  Warehouse   $warehouse
     * @param  float       $qty            Number of bundle units being sold
     * @param  string|null $referenceType
     * @param  int|null    $referenceId
     * @param  string|null $note
     * @param  int|null    $createdByUserId
     */
    private function explodeBundle(
        Product  $bundle,
        Warehouse $warehouse,
        float    $qty,
        ?string  $referenceType,
        ?int     $referenceId,
        ?string  $note,
        ?int     $createdByUserId,
    ): void {
        $components = $bundle->resolveBundleComponentsRecursive();

        if (empty($components)) {
            // Bundle has no components — nothing to deduct.
            // Log a warning; do NOT silently succeed.
            \Illuminate\Support\Facades\Log::warning(
                'decreaseStock called on bundle with no resolvable components',
                ['product_id' => $bundle->id, 'artikelnummer' => $bundle->artikelnummer]
            );

            return;
        }

        foreach ($components as ['product' => $childProduct, 'qty' => $componentQty]) {
            // Total units of this child that need to be deducted:
            //   bundle_qty  ×  child_qty_per_bundle
            $totalChildQty = $qty * $componentQty;

            $this->applyDelta(
                product:         $childProduct,
                warehouse:       $warehouse,
                delta:           -$totalChildQty,
                movementType:    StockMovement::TYPE_BUNDLE_EXPLOSION,
                referenceType:   $referenceType,
                referenceId:     $referenceId,
                note:            $note ?? "Bundle explosion from bundle #{$bundle->id}",
                createdByUserId: $createdByUserId,
            );
        }
    }

    /**
     * Write a movement journal entry and update the stock snapshot atomically.
     *
     * This is the single choke-point that touches both tables.
     * Must be called inside an existing DB::transaction().
     *
     * Uses a SELECT … FOR UPDATE on the product_stocks row to prevent
     * concurrent writes from racing on the quantity column.
     *
     * @param  Product     $product
     * @param  Warehouse   $warehouse
     * @param  float       $delta          Signed: positive = in, negative = out
     * @param  string      $movementType
     * @param  string|null $referenceType
     * @param  int|null    $referenceId
     * @param  string|null $note
     * @param  int|null    $createdByUserId
     */
    private function applyDelta(
        Product  $product,
        Warehouse $warehouse,
        float    $delta,
        string   $movementType,
        ?string  $referenceType,
        ?int     $referenceId,
        ?string  $note,
        ?int     $createdByUserId,
    ): void {
        // 1. Lock or create the stock snapshot row.
        //    lockForUpdate() prevents concurrent transactions from reading
        //    a stale quantity before we write our updated value.
        $stock = ProductStock::query()
            ->where('product_id',   $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->lockForUpdate()
            ->first();

        if ($stock === null) {
            // First movement for this product/warehouse combination.
            // Start from zero, then apply the delta.
            $stock = new ProductStock([
                'product_id'        => $product->id,
                'warehouse_id'      => $warehouse->id,
                'quantity'          => 0.0,
                'reserved_quantity' => 0.0,
            ]);
        }

        // 2. Apply the delta to the snapshot.
        $stock->quantity = $stock->quantity + $delta;
        $stock->save();

        // 3. Append an immutable journal entry.
        StockMovement::create([
            'product_id'         => $product->id,
            'warehouse_id'       => $warehouse->id,
            'movement_type'      => $movementType,
            'quantity_delta'     => $delta,
            'reference_type'     => $referenceType,
            'reference_id'       => $referenceId,
            'note'               => $note,
            'created_by_user_id' => $createdByUserId,
        ]);
    }
}
