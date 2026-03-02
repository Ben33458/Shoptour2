<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Orders\Order;
use App\Models\Supplier\PurchaseOrder;
use App\Models\Supplier\SupplierProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Analyse current stock levels and suggest purchase quantities per supplier.
 *
 * Logic:
 *   For each product_stock row in the given warehouse with reorder_point > 0:
 *     available = quantity - reserved_quantity
 *     incoming  = open PO items not yet received (status != received/cancelled)
 *     net       = available + incoming
 *     demand    = expected demand over the next --days days (simplified: 0 for now)
 *
 *     If net < reorder_point:
 *       needed = reorder_point - net
 *       find the cheapest/first active supplier_product row for this product
 *       round needed up to pack_size, enforce min_order_qty
 *
 *   Output is grouped by supplier.
 *
 * Usage:
 *   php artisan kolabri:supplier:suggest --warehouse_id=1 --days=14
 */
class SupplierSuggestCommand extends Command
{
    protected $signature = 'kolabri:supplier:suggest
                            {--warehouse_id= : ID of the warehouse to analyse (required)}
                            {--days=14       : Planning horizon in days (default 14)}';

    protected $description = 'Suggest purchase quantities grouped by supplier based on stock levels';

    public function handle(): int
    {
        $warehouseId = (int) $this->option('warehouse_id');

        if (! $warehouseId) {
            $this->error('--warehouse_id is required.');
            return self::FAILURE;
        }

        $warehouse = Warehouse::find($warehouseId);
        if (! $warehouse) {
            $this->error("Warehouse #{$warehouseId} not found.");
            return self::FAILURE;
        }

        $days = max(1, (int) $this->option('days'));

        $this->info("Analysing warehouse: {$warehouse->name}  (horizon: {$days} days)");
        $this->newLine();

        // ── Step 1: Find all products below reorder point ─────────────────────
        /** @var Collection<int, ProductStock> $stocks */
        $stocks = ProductStock::where('warehouse_id', $warehouseId)
            ->where('reorder_point', '>', 0)
            ->with('product')
            ->get();

        if ($stocks->isEmpty()) {
            $this->line('No products with reorder_point configured for this warehouse.');
            return self::SUCCESS;
        }

        // ── Step 2: Calculate incoming stock from open POs ────────────────────
        $incomingByProduct = $this->getIncomingQtyByProduct($warehouseId);

        // ── Step 3: Backorder demand (orders in pending/confirmed status) ─────
        $backorderByProduct = $this->getBackorderDemand($warehouseId);

        // ── Step 4: Build suggestion list ─────────────────────────────────────
        /** @var array<int, array{supplier_name: string, suggestions: list<array>}> $bySupplier */
        $bySupplier = [];

        foreach ($stocks as $stock) {
            $productId  = $stock->product_id;
            $available  = $stock->quantity - $stock->reserved_quantity;
            $incoming   = $incomingByProduct[$productId] ?? 0.0;
            $backorder  = $backorderByProduct[$productId] ?? 0.0;
            $net        = $available + $incoming - $backorder;

            if ($net >= $stock->reorder_point) {
                continue; // no replenishment needed
            }

            $needed = $stock->reorder_point - $net;

            // Find cheapest active supplier product
            /** @var SupplierProduct|null $sp */
            $sp = SupplierProduct::where('product_id', $productId)
                ->where('active', true)
                ->with('supplier')
                ->orderBy('purchase_price_milli')
                ->first();

            if (! $sp) {
                $this->warn("  No active supplier for product #{$productId} ({$stock->product->artikelnummer}) — skipped.");
                continue;
            }

            $suggestedQty = $sp->suggestQty($needed);

            $supplierId = $sp->supplier_id;
            if (! isset($bySupplier[$supplierId])) {
                $bySupplier[$supplierId] = [
                    'supplier_name' => $sp->supplier->name,
                    'suggestions'   => [],
                ];
            }

            $bySupplier[$supplierId]['suggestions'][] = [
                'product_id'    => $productId,
                'artikelnummer' => $stock->product->artikelnummer,
                'produktname'   => $stock->product->produktname,
                'available'     => $available,
                'incoming'      => $incoming,
                'backorder'     => $backorder,
                'net'           => $net,
                'reorder_point' => $stock->reorder_point,
                'needed'        => $needed,
                'suggested_qty' => $suggestedQty,
                'pack_size'     => $sp->pack_size,
                'lead_time'     => $sp->lead_time_days,
                'unit_price'    => $sp->purchase_price_milli,
                'line_total'    => $suggestedQty * $sp->purchase_price_milli,
                'supplier_sku'  => $sp->supplier_sku,
            ];
        }

        if (empty($bySupplier)) {
            $this->info('✓ All products are above their reorder points — no action needed.');
            return self::SUCCESS;
        }

        // ── Step 5: Output ────────────────────────────────────────────────────
        foreach ($bySupplier as $supplierId => $group) {
            $this->line("┌─ Supplier: <fg=yellow>{$group['supplier_name']}</> (ID #{$supplierId})");

            $headers = ['Artikelnr.', 'Name', 'On-hand', 'Incoming', 'Net', 'Reorder pt.', 'Suggest', 'Pack', 'Lead'];
            $rows    = array_map(function (array $s): array {
                return [
                    $s['artikelnummer'],
                    mb_substr($s['produktname'], 0, 30),
                    number_format($s['available'], 1),
                    number_format($s['incoming'], 1),
                    number_format($s['net'], 1),
                    number_format($s['reorder_point'], 1),
                    '<fg=green>' . number_format($s['suggested_qty'], 1) . '</>',
                    number_format($s['pack_size'], 1),
                    $s['lead_time'] . 'd',
                ];
            }, $group['suggestions']);

            $this->table($headers, $rows);
            $this->newLine();
        }

        $this->line('<fg=cyan>Tip: run `php artisan kolabri:po:create --supplier_id=X --warehouse_id=Y` to create a draft PO.</>');

        return self::SUCCESS;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Sum of ordered-but-not-yet-received quantities per product, for the
     * given warehouse, from open purchase orders.
     *
     * @return array<int, float>  product_id → qty
     */
    private function getIncomingQtyByProduct(int $warehouseId): array
    {
        $rows = DB::table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->where('po.warehouse_id', $warehouseId)
            ->whereNotIn('po.status', [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CANCELLED])
            ->select('poi.product_id', DB::raw('SUM(poi.qty - COALESCE(poi.received_qty, 0)) as incoming'))
            ->groupBy('poi.product_id')
            ->get();

        return $rows->pluck('incoming', 'product_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();
    }

    /**
     * Sum of backorder quantities for open orders in the given warehouse.
     * Simplified: sums qty from order_items where the parent order is pending/confirmed.
     *
     * @return array<int, float>  product_id → qty
     */
    private function getBackorderDemand(int $warehouseId): array
    {
        $rows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->where('o.warehouse_id', $warehouseId)
            ->whereIn('o.status', [Order::STATUS_PENDING, Order::STATUS_CONFIRMED])
            ->where('oi.is_backorder', true)
            ->select('oi.product_id', DB::raw('SUM(oi.qty) as backorder_qty'))
            ->groupBy('oi.product_id')
            ->get();

        return $rows->pluck('backorder_qty', 'product_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();
    }
}
