<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\Inventory\ProductStock;
use App\Models\Supplier\PurchaseOrder;
use App\Models\Supplier\PurchaseOrderItem;
use App\Models\Supplier\SupplierProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PROJ-32: Generate purchase order suggestions based on stock levels.
 *
 * Logic:
 *   1. Find all products where current available stock < reorder_point
 *   2. Subtract incoming quantities from open POs
 *   3. For each under-stocked product, find the primary supplier
 *   4. Calculate suggested quantity (reorder_point - net available)
 *   5. Round up to pack size, enforce min_order_qty
 *   6. Group by supplier for easy PO creation
 */
class BestellvorschlagService
{
    /**
     * Get purchase proposals grouped by supplier.
     *
     * @param  int|null  $warehouseId  Filter to specific warehouse (null = all)
     * @param  int|null  $companyId
     * @return Collection<int, array{
     *     supplier_id: int,
     *     supplier_name: string,
     *     items: list<array{
     *         product_id: int,
     *         produktname: string,
     *         artikelnummer: string,
     *         warehouse_id: int,
     *         warehouse_name: string,
     *         current_stock: float,
     *         reserved: float,
     *         available: float,
     *         incoming: float,
     *         reorder_point: float,
     *         shortage: float,
     *         suggested_qty: float,
     *         unit_purchase_milli: int,
     *         supplier_sku: string|null,
     *     }>
     * }>
     */
    public function getProposals(?int $warehouseId = null, ?int $companyId = null): Collection
    {
        // 1. Products below reorder point
        $stockQuery = ProductStock::with(['product', 'warehouse'])
            ->where('reorder_point', '>', 0);

        if ($warehouseId) {
            $stockQuery->where('warehouse_id', $warehouseId);
        }

        $stocks = $stockQuery->get();

        if ($stocks->isEmpty()) {
            return collect();
        }

        // 2. Get incoming quantities from open POs
        $productIds = $stocks->pluck('product_id')->unique()->values();
        $incoming   = $this->getIncomingQuantities($productIds, $warehouseId);

        // 3. Get supplier products (primary/cheapest supplier per product)
        $supplierProducts = SupplierProduct::whereIn('product_id', $productIds)
            ->where('active', true)
            ->with('supplier')
            ->get()
            ->groupBy('product_id');

        $proposals = [];

        foreach ($stocks as $stock) {
            $available = $stock->quantity - $stock->reserved_quantity;
            $incomingQty = $incoming[$stock->product_id] ?? 0;
            $net = $available + $incomingQty;

            if ($net >= $stock->reorder_point) {
                continue; // Enough stock (including incoming)
            }

            $shortage = $stock->reorder_point - $net;

            // Find primary supplier for this product
            $sps = $supplierProducts->get($stock->product_id);
            if (! $sps || $sps->isEmpty()) {
                continue; // No supplier mapped
            }

            /** @var SupplierProduct $primarySp */
            $primarySp = $sps->first(); // First active supplier

            $suggestedQty = $primarySp->suggestQty($shortage);

            $supplierId = $primarySp->supplier_id;
            if (! isset($proposals[$supplierId])) {
                $proposals[$supplierId] = [
                    'supplier_id'   => $supplierId,
                    'supplier_name' => $primarySp->supplier->name ?? "Lieferant #{$supplierId}",
                    'items'         => [],
                ];
            }

            $proposals[$supplierId]['items'][] = [
                'product_id'          => $stock->product_id,
                'produktname'         => $stock->product->produktname ?? '',
                'artikelnummer'       => $stock->product->artikelnummer ?? '',
                'warehouse_id'        => $stock->warehouse_id,
                'warehouse_name'      => $stock->warehouse->name ?? '',
                'current_stock'       => $stock->quantity,
                'reserved'            => $stock->reserved_quantity,
                'available'           => $available,
                'incoming'            => $incomingQty,
                'reorder_point'       => $stock->reorder_point,
                'shortage'            => $shortage,
                'suggested_qty'       => $suggestedQty,
                'unit_purchase_milli' => $primarySp->purchase_price_milli,
                'supplier_sku'        => $primarySp->supplier_sku,
            ];
        }

        return collect(array_values($proposals))->sortBy('supplier_name')->values();
    }

    /**
     * Get sum of incoming (ordered but not yet received) quantities per product.
     *
     * @param  Collection|array  $productIds
     * @return array<int, float>  product_id => incoming qty
     */
    private function getIncomingQuantities($productIds, ?int $warehouseId = null): array
    {
        $query = PurchaseOrderItem::query()
            ->select('purchase_order_items.product_id', DB::raw('SUM(purchase_order_items.qty - COALESCE(purchase_order_items.received_qty, 0)) as pending'))
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->whereIn('purchase_orders.status', PurchaseOrder::OPEN_STATUSES)
            ->whereIn('purchase_order_items.product_id', $productIds)
            ->groupBy('purchase_order_items.product_id');

        if ($warehouseId) {
            $query->where('purchase_orders.warehouse_id', $warehouseId);
        }

        return $query->pluck('pending', 'product_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();
    }
}
