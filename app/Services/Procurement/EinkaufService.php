<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\Company;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\StockMovement;
use App\Models\Supplier\PurchaseOrder;
use App\Models\Supplier\PurchaseOrderItem;
use App\Services\Admin\AuditLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * PROJ-32: Central service for Purchase Order operations.
 *
 * Responsibilities:
 *   - PO number generation (race-condition-free via po_sequences)
 *   - PDF generation
 *   - Email dispatch to supplier
 *   - Goods receipt booking (with stock movements)
 *   - Status transitions
 */
class EinkaufService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    // =========================================================================
    // PO Number Generation
    // =========================================================================

    /**
     * Generate the next PO number using po_sequences table.
     * Falls back to scanning purchase_orders if no sequence row exists yet.
     *
     * Format: EK-YYYY-NNNNN  (e.g. EK-2026-00001)
     */
    public function nextPoNumber(?int $companyId = null): string
    {
        return DB::transaction(function () use ($companyId): string {
            $year   = now()->year;
            $prefix = 'EK';

            // Lock + increment sequence row
            $seq = DB::table('po_sequences')
                ->where('company_id', $companyId)
                ->where('prefix', $prefix)
                ->lockForUpdate()
                ->first();

            if ($seq) {
                $next = $seq->last_number + 1;
                DB::table('po_sequences')
                    ->where('id', $seq->id)
                    ->update(['last_number' => $next, 'updated_at' => now()]);
            } else {
                // First time — check existing POs to avoid collisions
                $pattern = "{$prefix}-{$year}-";
                $last    = PurchaseOrder::where('po_number', 'like', $pattern . '%')
                    ->orderByDesc('po_number')
                    ->value('po_number');

                $next = $last ? ((int) substr($last, strlen($pattern))) + 1 : 1;

                DB::table('po_sequences')->insert([
                    'company_id'  => $companyId,
                    'prefix'      => $prefix,
                    'last_number' => $next,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            return sprintf('%s-%d-%05d', $prefix, $year, $next);
        });
    }

    // =========================================================================
    // Create PO
    // =========================================================================

    /**
     * Create a new draft PurchaseOrder with items.
     *
     * @param  array<string, mixed>  $data       PO header data
     * @param  list<array{product_id: int, qty: float, unit_purchase_milli: int, notes?: string}>  $items
     */
    public function createPurchaseOrder(array $data, array $items): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $items): PurchaseOrder {
            $companyId = $data['company_id'] ?? null;
            $poNumber  = $this->nextPoNumber($companyId);

            $totalMilli = 0;
            foreach ($items as $item) {
                $totalMilli += (int) round($item['qty'] * $item['unit_purchase_milli']);
            }

            $po = PurchaseOrder::create([
                'company_id'   => $companyId,
                'supplier_id'  => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'po_number'    => $poNumber,
                'status'       => PurchaseOrder::STATUS_DRAFT,
                'ordered_at'   => $data['ordered_at'] ?? now()->toDateString(),
                'expected_at'  => $data['expected_at'] ?? null,
                'total_milli'  => $totalMilli,
                'notes'        => $data['notes'] ?? null,
            ]);

            foreach ($items as $itemData) {
                $lineTot = (int) round($itemData['qty'] * $itemData['unit_purchase_milli']);
                PurchaseOrderItem::create([
                    'purchase_order_id'  => $po->id,
                    'product_id'         => $itemData['product_id'],
                    'qty'                => $itemData['qty'],
                    'unit_purchase_milli' => $itemData['unit_purchase_milli'],
                    'line_total_milli'   => $lineTot,
                    'received_qty'       => null,
                    'notes'              => $itemData['notes'] ?? null,
                ]);
            }

            $this->auditLog->log('po.created', $po, [
                'po_number'  => $po->po_number,
                'supplier'   => $po->supplier_id,
                'item_count' => count($items),
                'total_milli' => $totalMilli,
            ]);

            return $po;
        });
    }

    // =========================================================================
    // Update PO (draft only)
    // =========================================================================

    /**
     * Update a draft PO header and replace items.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{product_id: int, qty: float, unit_purchase_milli: int, notes?: string}>  $items
     */
    public function updatePurchaseOrder(PurchaseOrder $po, array $data, array $items): PurchaseOrder
    {
        if (! $po->isDraft()) {
            throw new RuntimeException("Bestellung {$po->po_number} kann nicht mehr bearbeitet werden (Status: {$po->status}).");
        }

        return DB::transaction(function () use ($po, $data, $items): PurchaseOrder {
            $po->update([
                'supplier_id'  => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'ordered_at'   => $data['ordered_at'] ?? $po->ordered_at,
                'expected_at'  => $data['expected_at'] ?? $po->expected_at,
                'notes'        => $data['notes'] ?? $po->notes,
            ]);

            // Replace items
            $po->items()->delete();

            $totalMilli = 0;
            foreach ($items as $itemData) {
                $lineTot = (int) round($itemData['qty'] * $itemData['unit_purchase_milli']);
                $totalMilli += $lineTot;
                PurchaseOrderItem::create([
                    'purchase_order_id'  => $po->id,
                    'product_id'         => $itemData['product_id'],
                    'qty'                => $itemData['qty'],
                    'unit_purchase_milli' => $itemData['unit_purchase_milli'],
                    'line_total_milli'   => $lineTot,
                    'received_qty'       => null,
                    'notes'              => $itemData['notes'] ?? null,
                ]);
            }

            $po->update(['total_milli' => $totalMilli]);
            $po->refresh();

            $this->auditLog->log('po.updated', $po, [
                'po_number'  => $po->po_number,
                'item_count' => count($items),
                'total_milli' => $totalMilli,
            ]);

            return $po;
        });
    }

    // =========================================================================
    // Status Transitions
    // =========================================================================

    /**
     * Mark PO as sent (transitions from draft → sent).
     */
    public function markAsSent(PurchaseOrder $po): void
    {
        if (! $po->canSend()) {
            throw new RuntimeException("Bestellung {$po->po_number} kann nicht versendet werden (Status: {$po->status}).");
        }

        $po->update([
            'status'     => PurchaseOrder::STATUS_SENT,
            'ordered_at' => $po->ordered_at ?? now()->toDateString(),
        ]);

        $this->auditLog->log('po.sent', $po, ['po_number' => $po->po_number]);
    }

    /**
     * Cancel a PO. Only allowed if no goods have been received.
     */
    public function cancel(PurchaseOrder $po): void
    {
        if (! $po->canCancel()) {
            throw new RuntimeException("Bestellung {$po->po_number} kann nicht storniert werden.");
        }

        $po->update(['status' => PurchaseOrder::STATUS_CANCELLED]);

        $this->auditLog->log('po.cancelled', $po, ['po_number' => $po->po_number]);
    }

    // =========================================================================
    // Goods Receipt
    // =========================================================================

    /**
     * Book goods receipt for specific items.
     *
     * @param  PurchaseOrder  $po
     * @param  array<int, float>  $receivedQtys  item_id => received_qty
     * @param  int  $warehouseId  Target warehouse (usually po->warehouse_id)
     * @param  int|null  $userId  User performing the receipt
     */
    public function bookReceipt(PurchaseOrder $po, array $receivedQtys, int $warehouseId, ?int $userId = null): void
    {
        if (! $po->canReceive() && ! $po->isDraft()) {
            throw new RuntimeException("Wareneingang für {$po->po_number} nicht möglich (Status: {$po->status}).");
        }

        DB::transaction(function () use ($po, $receivedQtys, $warehouseId, $userId): void {
            $po->load('items');

            foreach ($po->items as $item) {
                $receivedQty = $receivedQtys[$item->id] ?? null;
                if ($receivedQty === null || $receivedQty <= 0) {
                    continue;
                }

                // Update received qty (cumulative)
                $newReceived = ($item->received_qty ?? 0) + $receivedQty;
                $item->update(['received_qty' => $newReceived]);

                // Create stock movement
                StockMovement::create([
                    'product_id'       => $item->product_id,
                    'warehouse_id'     => $warehouseId,
                    'movement_type'    => StockMovement::TYPE_PURCHASE_IN,
                    'quantity_delta'   => $receivedQty,
                    'reference_type'   => 'purchase_order',
                    'reference_id'     => $po->id,
                    'note'             => "Wareneingang {$po->po_number}",
                    'created_by_user_id' => $userId,
                ]);

                // Upsert product_stocks
                $stock = ProductStock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();

                if ($stock) {
                    $stock->quantity += $receivedQty;
                    $stock->save();
                } else {
                    ProductStock::create([
                        'product_id'        => $item->product_id,
                        'warehouse_id'      => $warehouseId,
                        'quantity'          => $receivedQty,
                        'reserved_quantity' => 0,
                    ]);
                }
            }

            // Reload items to check status
            $po->load('items');

            if ($po->allItemsReceived()) {
                $po->update(['status' => PurchaseOrder::STATUS_RECEIVED]);
            } elseif ($po->hasAnyReceipt()) {
                $po->update(['status' => PurchaseOrder::STATUS_PARTIALLY_RECEIVED]);
            }

            // Mark as sent if was draft (auto-transition when receiving directly)
            if ($po->isDraft()) {
                $po->update(['status' => $po->allItemsReceived()
                    ? PurchaseOrder::STATUS_RECEIVED
                    : PurchaseOrder::STATUS_PARTIALLY_RECEIVED]);
            }
        });

        $this->auditLog->log('po.receipt_booked', $po, [
            'po_number'   => $po->po_number,
            'items_count' => count(array_filter($receivedQtys, fn ($q) => $q > 0)),
            'warehouse'   => $warehouseId,
        ]);
    }

    /**
     * Correct a previously booked goods receipt.
     *
     * - Existing items: sets received_qty to the given absolute value,
     *   books the difference as a TYPE_CORRECTION stock movement.
     * - New items: creates a PurchaseOrderItem and books a TYPE_PURCHASE_IN movement.
     *
     * @param  array<int, float>   $newQtys    item_id => new absolute received_qty
     * @param  array<int, array{product_id:int, qty:float, received_qty:float, unit_purchase_milli:int}>  $newItems
     */
    public function correctReceipt(
        PurchaseOrder $po,
        array $newQtys,
        int $warehouseId,
        ?int $userId = null,
        array $newItems = [],
    ): void {
        DB::transaction(function () use ($po, $newQtys, $warehouseId, $userId, $newItems): void {
            $po->load('items');

            // ── 1. Adjust existing items ──────────────────────────────────────
            foreach ($po->items as $item) {
                if (! array_key_exists($item->id, $newQtys)) {
                    continue;
                }

                $newQty = max(0, (float) $newQtys[$item->id]);
                $oldQty = (float) ($item->received_qty ?? 0);
                $delta  = $newQty - $oldQty;

                if (abs($delta) < 0.0001) {
                    continue;
                }

                $item->update(['received_qty' => $newQty]);

                StockMovement::create([
                    'product_id'         => $item->product_id,
                    'warehouse_id'       => $warehouseId,
                    'movement_type'      => StockMovement::TYPE_CORRECTION,
                    'quantity_delta'     => $delta,
                    'reference_type'     => 'purchase_order',
                    'reference_id'       => $po->id,
                    'note'               => "Korrektur Wareneingang {$po->po_number} (war: {$oldQty}, neu: {$newQty})",
                    'created_by_user_id' => $userId,
                ]);

                $this->upsertStock($item->product_id, $warehouseId, $delta);
            }

            // ── 2. Add new items ──────────────────────────────────────────────
            foreach ($newItems as $ni) {
                $productId   = (int) $ni['product_id'];
                $qty         = max(0.001, (float) $ni['qty']);
                $receivedQty = max(0, (float) $ni['received_qty']);
                $priceMilli  = (int) ($ni['unit_purchase_milli'] ?? 0);
                $lineMilli   = (int) round($qty * $priceMilli);

                $item = PurchaseOrderItem::create([
                    'purchase_order_id'   => $po->id,
                    'product_id'          => $productId,
                    'qty'                 => $qty,
                    'unit_purchase_milli' => $priceMilli,
                    'line_total_milli'    => $lineMilli,
                    'received_qty'        => $receivedQty > 0 ? $receivedQty : null,
                ]);

                if ($receivedQty > 0) {
                    StockMovement::create([
                        'product_id'         => $productId,
                        'warehouse_id'       => $warehouseId,
                        'movement_type'      => StockMovement::TYPE_PURCHASE_IN,
                        'quantity_delta'     => $receivedQty,
                        'reference_type'     => 'purchase_order',
                        'reference_id'       => $po->id,
                        'note'               => "Wareneingang (nacherfasst) {$po->po_number}",
                        'created_by_user_id' => $userId,
                    ]);

                    $this->upsertStock($productId, $warehouseId, $receivedQty);
                }
            }

            // ── 3. Recalculate PO status ──────────────────────────────────────
            $po->load('items');
            if ($po->allItemsReceived()) {
                $po->update(['status' => PurchaseOrder::STATUS_RECEIVED]);
            } elseif ($po->hasAnyReceipt()) {
                $po->update(['status' => PurchaseOrder::STATUS_PARTIALLY_RECEIVED]);
            } else {
                $po->update(['status' => PurchaseOrder::STATUS_SENT]);
            }
        });

        $this->auditLog->log('po.receipt_corrected', $po, [
            'po_number'    => $po->po_number,
            'warehouse'    => $warehouseId,
            'new_items'    => count($newItems),
        ]);
    }

    private function upsertStock(int $productId, int $warehouseId, float $delta): void
    {
        $stock = ProductStock::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if ($stock) {
            $stock->quantity = max(0, $stock->quantity + $delta);
            $stock->save();
        } elseif ($delta > 0) {
            ProductStock::create([
                'product_id'        => $productId,
                'warehouse_id'      => $warehouseId,
                'quantity'          => $delta,
                'reserved_quantity' => 0,
            ]);
        }
    }

    // =========================================================================
    // PDF Generation
    // =========================================================================

    /**
     * Generate a PDF for the purchase order and return the path.
     */
    public function generatePdf(PurchaseOrder $po): string
    {
        $po->load(['items.product', 'supplier', 'warehouse']);
        $company = Company::find($po->company_id);

        $pdfContent = Pdf::loadView('pdf.purchase-order', [
            'po'      => $po,
            'company' => $company,
        ])
            ->setPaper('a4', 'portrait')
            ->output();

        $pdfPath = 'purchase-orders/' . $po->po_number . '.pdf';
        Storage::disk('local')->put($pdfPath, $pdfContent);

        return $pdfPath;
    }

    /**
     * Get the PDF content as a string (for download/preview).
     */
    public function getPdfContent(PurchaseOrder $po): string
    {
        $po->load(['items.product', 'supplier', 'warehouse']);
        $company = Company::find($po->company_id);

        return Pdf::loadView('pdf.purchase-order', [
            'po'      => $po,
            'company' => $company,
        ])
            ->setPaper('a4', 'portrait')
            ->output();
    }

    // =========================================================================
    // Email to Supplier
    // =========================================================================

    /**
     * Send the PO as PDF attachment to the supplier's email.
     *
     * @return bool true if sent successfully
     */
    public function sendToSupplier(PurchaseOrder $po): bool
    {
        $po->load('supplier');

        if (! $po->supplier?->email) {
            throw new RuntimeException("Lieferant {$po->supplier?->name} hat keine E-Mail-Adresse.");
        }

        try {
            $pdfPath    = $this->generatePdf($po);
            $pdfContent = Storage::disk('local')->get($pdfPath);

            Mail::to($po->supplier->email)
                ->send(new \App\Mail\PurchaseOrderMail($po, $pdfContent));

            // Auto-transition draft → sent
            if ($po->isDraft()) {
                $this->markAsSent($po);
            }

            $this->auditLog->log('po.email_sent', $po, [
                'po_number' => $po->po_number,
                'recipient' => $po->supplier->email,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error("Failed to send PO {$po->po_number} email: " . $e->getMessage());

            $this->auditLog->log('po.email_failed', $po, [
                'po_number' => $po->po_number,
                'error'     => $e->getMessage(),
            ], level: 'error');

            throw $e;
        }
    }
}
