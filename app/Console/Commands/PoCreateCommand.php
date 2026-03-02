<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Inventory\Warehouse;
use App\Models\Inventory\ProductStock;
use App\Models\Supplier\PurchaseOrder;
use App\Models\Supplier\PurchaseOrderItem;
use App\Models\Supplier\Supplier;
use App\Models\Supplier\SupplierProduct;
use App\Services\Admin\AuditLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Create a draft purchase order for a specific supplier + warehouse combination.
 *
 * Items are derived from products that are below their reorder point and
 * have an active supplier_product row for the given supplier.
 *
 * Usage:
 *   php artisan kolabri:po:create --supplier_id=1 --warehouse_id=1 --date=2024-06-01
 */
class PoCreateCommand extends Command
{
    protected $signature = 'kolabri:po:create
                            {--supplier_id=  : ID of the supplier (required)}
                            {--warehouse_id= : ID of the destination warehouse (required)}
                            {--date=         : Order date (YYYY-MM-DD, default: today)}';

    protected $description = 'Create a draft purchase order for a supplier based on stock reorder points';

    public function handle(): int
    {
        $supplierId  = (int) $this->option('supplier_id');
        $warehouseId = (int) $this->option('warehouse_id');
        $dateStr     = $this->option('date') ?: now()->toDateString();

        if (! $supplierId || ! $warehouseId) {
            $this->error('Both --supplier_id and --warehouse_id are required.');
            return self::FAILURE;
        }

        $supplier  = Supplier::find($supplierId);
        $warehouse = Warehouse::find($warehouseId);

        if (! $supplier) {
            $this->error("Supplier #{$supplierId} not found.");
            return self::FAILURE;
        }
        if (! $warehouse) {
            $this->error("Warehouse #{$warehouseId} not found.");
            return self::FAILURE;
        }

        // Validate date
        try {
            $orderDate = \Carbon\Carbon::createFromFormat('Y-m-d', $dateStr);
        } catch (\Exception) {
            $this->error("Invalid date format: {$dateStr}. Use YYYY-MM-DD.");
            return self::FAILURE;
        }

        // ── Build item suggestions for this supplier ───────────────────────
        $stocks = ProductStock::where('warehouse_id', $warehouseId)
            ->where('reorder_point', '>', 0)
            ->get()
            ->keyBy('product_id');

        /** @var list<array{sp: SupplierProduct, qty: float}> $lines */
        $lines = [];

        $supplierProductRows = SupplierProduct::where('supplier_id', $supplierId)
            ->where('active', true)
            ->get();

        foreach ($supplierProductRows as $sp) {
            $stock = $stocks->get($sp->product_id);
            if (! $stock) {
                continue; // product not tracked in this warehouse
            }

            $net = $stock->quantity - $stock->reserved_quantity;
            if ($net >= $stock->reorder_point) {
                continue; // above threshold — skip
            }

            $needed = $stock->reorder_point - $net;
            $lines[] = [
                'sp'  => $sp,
                'qty' => $sp->suggestQty($needed),
            ];
        }

        if (empty($lines)) {
            $this->line("No items below reorder point for supplier {$supplier->name} in warehouse {$warehouse->name}.");
            return self::SUCCESS;
        }

        // ── Create PO in transaction ───────────────────────────────────────
        $po = DB::transaction(function () use ($supplier, $warehouse, $orderDate, $lines): PurchaseOrder {
            // Generate PO number: PO-YYYY-NNNNN
            $year   = $orderDate->year;
            $prefix = "PO-{$year}-";
            $last   = PurchaseOrder::where('po_number', 'like', $prefix . '%')
                ->orderByDesc('po_number')
                ->value('po_number');

            $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
            $poNumber = $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);

            $totalMilli = 0;
            $itemData   = [];

            foreach ($lines as $line) {
                /** @var SupplierProduct $sp */
                $sp       = $line['sp'];
                $qty      = $line['qty'];
                $lineTot  = (int) round($qty * $sp->purchase_price_milli);
                $totalMilli += $lineTot;
                $itemData[] = [
                    'product_id'          => $sp->product_id,
                    'qty'                 => $qty,
                    'unit_purchase_milli' => $sp->purchase_price_milli,
                    'line_total_milli'    => $lineTot,
                    'received_qty'        => null,
                ];
            }

            $po = PurchaseOrder::create([
                'company_id'   => $supplier->company_id,
                'supplier_id'  => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'po_number'    => $poNumber,
                'status'       => PurchaseOrder::STATUS_DRAFT,
                'ordered_at'   => $orderDate->toDateString(),
                'expected_at'  => $orderDate->addDays(
                    $lines[0]['sp']->lead_time_days ?? 3
                )->toDateString(),
                'total_milli'  => $totalMilli,
            ]);

            foreach ($itemData as $item) {
                $item['purchase_order_id'] = $po->id;
                PurchaseOrderItem::create($item);
            }

            return $po;
        });

        // Audit log
        app(AuditLogService::class)->log('po.created', $po, [
            'po_number'    => $po->po_number,
            'supplier'     => $supplier->name,
            'warehouse'    => $warehouse->name,
            'item_count'   => count($lines),
            'total_milli'  => $po->total_milli,
        ]);

        $this->info("✓ Created draft PO <fg=green>{$po->po_number}</> (ID #{$po->id})");
        $this->line("  Supplier:  {$supplier->name}");
        $this->line("  Warehouse: {$warehouse->name}");
        $this->line("  Items:     " . count($lines));
        $this->line("  Total:     " . number_format($po->total_milli / 1_000_000, 2) . " {$supplier->currency}");
        $this->newLine();
        $this->line("  Next step: php artisan kolabri:po:receive --po_id={$po->id}");

        return self::SUCCESS;
    }
}
