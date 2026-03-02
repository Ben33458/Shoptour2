<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Inventory\ProductStock;
use App\Models\Inventory\StockMovement;
use App\Models\Supplier\PurchaseOrder;
use App\Models\Supplier\PurchaseOrderItem;
use App\Services\Admin\AuditLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Record goods receipt for a purchase order.
 *
 * For each item on the PO the command:
 *  1. Prompts (or uses --auto) for the received qty.
 *  2. Creates a stock_movement of type 'purchase_receipt'.
 *  3. Updates (upserts) the product_stocks snapshot.
 *  4. Sets received_qty on the purchase_order_item.
 *  5. Marks the PO as received (even if partially received).
 *
 * Supports partial receipt: received_qty < ordered qty is fine.
 *
 * Usage:
 *   php artisan kolabri:po:receive --po_id=42
 *   php artisan kolabri:po:receive --po_id=42 --auto   (accept all as fully received)
 */
class PoReceiveCommand extends Command
{
    protected $signature = 'kolabri:po:receive
                            {--po_id= : ID of the purchase order to receive (required)}
                            {--auto   : Accept full ordered quantities without prompting}';

    protected $description = 'Record goods receipt for a purchase order, creating stock movements';

    public function handle(): int
    {
        $poId = (int) $this->option('po_id');

        if (! $poId) {
            $this->error('--po_id is required.');
            return self::FAILURE;
        }

        /** @var PurchaseOrder|null $po */
        $po = PurchaseOrder::with(['items.product', 'supplier', 'warehouse'])->find($poId);

        if (! $po) {
            $this->error("Purchase order #{$poId} not found.");
            return self::FAILURE;
        }

        if ($po->isReceived()) {
            $this->warn("PO #{$poId} ({$po->po_number}) is already marked as received.");
            return self::SUCCESS;
        }

        if ($po->isCancelled()) {
            $this->error("PO #{$poId} ({$po->po_number}) is cancelled — cannot receive.");
            return self::FAILURE;
        }

        $this->info("Goods receipt for PO: <fg=yellow>{$po->po_number}</> ({$po->supplier->name})");
        $this->line("Warehouse: {$po->warehouse->name}");
        $this->newLine();

        $auto = (bool) $this->option('auto');

        // ── Collect received quantities ────────────────────────────────────
        /** @var array<int, float> $receivedQtys  item_id → received_qty */
        $receivedQtys = [];

        foreach ($po->items as $item) {
            $productName = $item->product->artikelnummer . ' ' . $item->product->produktname;
            $this->line("  [{$item->id}] {$productName}  — ordered: {$item->qty}");

            if ($auto) {
                $receivedQtys[$item->id] = $item->qty;
                $this->line("        → auto-accept: {$item->qty}");
            } else {
                $default  = number_format($item->qty, 3, '.', '');
                $received = $this->ask("    Received qty (default: {$default})", $default);
                $receivedQtys[$item->id] = (float) $received;
            }
        }

        $this->newLine();

        // ── Apply in a transaction ─────────────────────────────────────────
        DB::transaction(function () use ($po, $receivedQtys): void {
            foreach ($po->items as $item) {
                $receivedQty = $receivedQtys[$item->id] ?? 0.0;

                if ($receivedQty <= 0) {
                    $this->line("  Skipping item #{$item->id} (0 received).");
                    continue;
                }

                // 1. Record stock movement
                StockMovement::create([
                    'product_id'      => $item->product_id,
                    'warehouse_id'    => $po->warehouse_id,
                    'movement_type'   => 'purchase_receipt',
                    'quantity_delta'  => $receivedQty,
                    'reference_type'  => 'purchase_order',
                    'reference_id'    => $po->id,
                    'note'            => "Goods receipt for {$po->po_number}",
                    'created_by_user_id' => null,
                ]);

                // 2. Upsert product_stocks snapshot
                $existing = ProductStock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $po->warehouse_id)
                    ->first();

                if ($existing) {
                    $existing->quantity += $receivedQty;
                    $existing->save();
                } else {
                    ProductStock::create([
                        'product_id'        => $item->product_id,
                        'warehouse_id'      => $po->warehouse_id,
                        'quantity'          => $receivedQty,
                        'reserved_quantity' => 0,
                    ]);
                }

                // 3. Mark item as received
                $item->update(['received_qty' => $receivedQty]);
            }

            // 4. Mark PO as received
            $po->update(['status' => PurchaseOrder::STATUS_RECEIVED]);
        });

        // Audit log
        app(AuditLogService::class)->log('po.received', $po, [
            'po_number' => $po->po_number,
            'warehouse' => $po->warehouse->name ?? $po->warehouse_id,
        ]);

        $this->info("✓ PO {$po->po_number} marked as received. Stock movements created.");

        return self::SUCCESS;
    }
}
