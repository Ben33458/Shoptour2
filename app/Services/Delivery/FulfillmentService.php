<?php

declare(strict_types=1);

namespace App\Services\Delivery;

use App\Models\Catalog\Product;
use App\Models\Delivery\FulfillmentEvent;
use App\Models\Delivery\OrderItemFulfillment;
use App\Models\Delivery\Tour;
use App\Models\Delivery\TourStop;
use App\Models\Inventory\Warehouse;
use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use App\Models\Pricing\AppSetting;
use App\Services\Inventory\StockService;
use App\Models\Inventory\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Handles all driver-facing fulfillment actions for a TourStop.
 *
 * Stock booking strategy:
 *   Stock is NOT booked at order creation. It is booked only when the driver
 *   marks a stop as FINISHED (markFinished). At that point:
 *     - For each OrderItemFulfillment row: decrease stock by delivered_qty.
 *     - Warehouse: resolved from app_settings["default_warehouse_id"].
 *       Falls back to the first active Warehouse if the setting is absent.
 *
 * Warehouse resolution is done once per markFinished() call and cached in
 * a private helper to avoid repeated DB round-trips.
 *
 * Event log:
 *   Every public method appends at least one FulfillmentEvent row.
 *   Payload structure for each type is documented on FulfillmentEvent.
 *
 * All writes happen inside DB::transaction(). Stock moves are also inside
 * StockService's own transaction (nested via savepoints on MySQL).
 */
class FulfillmentService
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    // =========================================================================
    // Stop lifecycle
    // =========================================================================

    /**
     * Mark the driver as arrived at this stop.
     *
     * Sets status → arrived, records arrived_at timestamp.
     * Appends a FulfillmentEvent::TYPE_ARRIVED event.
     *
     * @throws \RuntimeException when stop is already finished or skipped
     */
    public function markArrived(TourStop $stop, ?int $userId = null): void
    {
        if (in_array($stop->status, [TourStop::STATUS_FINISHED, TourStop::STATUS_SKIPPED], true)) {
            throw new \RuntimeException(
                "TourStop #{$stop->id} is already {$stop->status} and cannot be marked arrived."
            );
        }

        DB::transaction(function () use ($stop, $userId): void {
            $now = now();

            $stop->update([
                'status'     => TourStop::STATUS_ARRIVED,
                'arrived_at' => $now,
            ]);

            $this->appendEvent($stop, FulfillmentEvent::TYPE_ARRIVED, [
                'arrived_at' => $now->toIso8601String(),
            ], $userId);
        });
    }

    /**
     * Mark the stop as finished and book all delivered stock.
     *
     * Actions:
     *   1. Set status → finished, set finished_at.
     *   2. Resolve the default warehouse.
     *   3. For every OrderItemFulfillment row with delivered_qty > 0:
     *        decreaseStock(product, warehouse, delivered_qty, 'sale_out',
     *                      reference_type='order', reference_id=order.id)
     *   4. Append FulfillmentEvent::TYPE_FINISHED.
     *
     * @throws \RuntimeException when stop has not been marked arrived first
     * @throws \RuntimeException when stop is already finished
     * @throws \RuntimeException when no active warehouse can be resolved
     */
    public function markFinished(TourStop $stop, ?int $userId = null): void
    {
        if ($stop->status === TourStop::STATUS_FINISHED) {
            throw new \RuntimeException(
                "TourStop #{$stop->id} is already finished."
            );
        }

        if ($stop->status === TourStop::STATUS_OPEN) {
            throw new \RuntimeException(
                "TourStop #{$stop->id} must be marked arrived before it can be finished."
            );
        }

        DB::transaction(function () use ($stop, $userId): void {
            $now = now();

            $stop->update([
                'status'      => TourStop::STATUS_FINISHED,
                'finished_at' => $now,
            ]);

            // Resolve warehouse once for all stock bookings in this call
            $warehouse = $this->resolveDefaultWarehouse();

            // Book stock for every item that was delivered
            $fulfillments = $stop->itemFulfillments()->with('orderItem.order')->get();

            foreach ($fulfillments as $fulfillment) {
                if ($fulfillment->delivered_qty <= 0) {
                    continue;
                }

                $orderItem = $fulfillment->orderItem;
                $order     = $orderItem->order;

                // Load the live product for StockService
                $product = Product::findOrFail($orderItem->product_id);

                $this->stockService->decreaseStock(
                    product:       $product,
                    warehouse:     $warehouse,
                    qty:           (float) $fulfillment->delivered_qty,
                    movementType:  StockMovement::TYPE_SALE_OUT,
                    referenceType: 'order',
                    referenceId:   $order->id,
                    note:          "Fulfillment: tour_stop #{$stop->id}",
                    createdByUserId: $userId,
                );
            }

            $this->appendEvent($stop, FulfillmentEvent::TYPE_FINISHED, [
                'finished_at'      => $now->toIso8601String(),
                'warehouse_id'     => $warehouse->id,
                'fulfillment_count'=> $fulfillments->where('delivered_qty', '>', 0)->count(),
            ], $userId);

            // Mark the order as delivered (unless it was already cancelled)
            $order = $stop->order;
            if ($order !== null && $order->status !== Order::STATUS_CANCELLED) {
                $order->update(['status' => Order::STATUS_DELIVERED]);
            }

            // Close the tour when every stop is finished or skipped
            $tour = $stop->tour;
            if ($tour !== null) {
                $hasOpenStops = $tour->stops()
                    ->whereNotIn('status', [TourStop::STATUS_FINISHED, TourStop::STATUS_SKIPPED])
                    ->exists();
                if (! $hasOpenStops) {
                    $tour->update(['status' => Tour::STATUS_DONE]);
                }
            }
        });
    }

    // =========================================================================
    // Item-level delivery recording
    // =========================================================================

    /**
     * Record that a specific quantity of an OrderItem was delivered.
     *
     * Upserts the OrderItemFulfillment row (creates on first call).
     * Delivered qty is ADDED to the existing value (supports multiple partial calls).
     *
     * @param  TourStop   $stop
     * @param  OrderItem  $item
     * @param  int        $deliveredQty  Must be > 0
     * @param  int|null   $userId
     * @throws \InvalidArgumentException when deliveredQty ≤ 0
     */
    public function recordItemDelivery(
        TourStop  $stop,
        OrderItem $item,
        int       $deliveredQty,
        ?int      $userId = null,
    ): void {
        if ($deliveredQty <= 0) {
            throw new \InvalidArgumentException(
                "deliveredQty must be > 0, got {$deliveredQty}."
            );
        }

        DB::transaction(function () use ($stop, $item, $deliveredQty, $userId): void {
            $this->upsertFulfillment($stop, $item, function (OrderItemFulfillment $row) use ($deliveredQty): void {
                $row->delivered_qty += $deliveredQty;
                $row->updated_at     = now();
            });

            $this->appendEvent($stop, FulfillmentEvent::TYPE_ITEM_DELIVERED, [
                'order_item_id' => $item->id,
                'qty'           => $deliveredQty,
            ], $userId);
        });
    }

    /**
     * Record that a specific quantity of an OrderItem could NOT be delivered.
     *
     * Reason codes are free-form strings (e.g. "damaged", "refused", "out_of_stock").
     * The not_delivered_reason on the summary row is overwritten with each call
     * (last call wins). Full history is in FulfillmentEvent.
     *
     * @param  TourStop   $stop
     * @param  OrderItem  $item
     * @param  int        $notDeliveredQty  Must be > 0
     * @param  string     $reason
     * @param  string|null $note
     * @param  int|null   $userId
     * @throws \InvalidArgumentException when notDeliveredQty ≤ 0
     */
    public function recordItemNotDelivered(
        TourStop  $stop,
        OrderItem $item,
        int       $notDeliveredQty,
        string    $reason,
        ?string   $note   = null,
        ?int      $userId = null,
    ): void {
        if ($notDeliveredQty <= 0) {
            throw new \InvalidArgumentException(
                "notDeliveredQty must be > 0, got {$notDeliveredQty}."
            );
        }

        DB::transaction(function () use ($stop, $item, $notDeliveredQty, $reason, $note, $userId): void {
            $this->upsertFulfillment(
                $stop,
                $item,
                function (OrderItemFulfillment $row) use ($notDeliveredQty, $reason, $note): void {
                    $row->not_delivered_qty    += $notDeliveredQty;
                    $row->not_delivered_reason  = $reason;
                    $row->note                  = $note;
                    $row->updated_at            = now();
                }
            );

            $this->appendEvent($stop, FulfillmentEvent::TYPE_ITEM_NOT_DELIVERED, [
                'order_item_id' => $item->id,
                'qty'           => $notDeliveredQty,
                'reason'        => $reason,
                'note'          => $note,
            ], $userId);
        });
    }

    // =========================================================================
    // Empties / breakage adjustments
    // =========================================================================

    /**
     * Record an empties return or breakage stock adjustment for a stop.
     *
     * This is used BEFORE invoicing to post corrections such as:
     *   - Returned empty bottles/crates (qty_delta positive = we received back)
     *   - Broken goods during transport (qty_delta negative = we lost stock)
     *
     * The $eventType parameter selects whether this is empties or breakage:
     *   FulfillmentEvent::TYPE_EMPTIES_ADJUSTED   (default)
     *   FulfillmentEvent::TYPE_BREAKAGE_ADJUSTED
     *
     * Stock is adjusted via StockService with movement_type = 'correction',
     * reference_type = 'tour_stop', reference_id = stop.id.
     *
     * @param  TourStop  $stop
     * @param  Product   $product       The product being returned / written off
     * @param  float     $qtyDelta      Signed: positive = stock in, negative = stock out
     * @param  string    $note
     * @param  string    $eventType     FulfillmentEvent::TYPE_EMPTIES_ADJUSTED or BREAKAGE_ADJUSTED
     * @param  int|null  $userId
     * @throws \InvalidArgumentException when qtyDelta is zero
     * @throws \RuntimeException         when no active warehouse can be resolved
     */
    public function recordEmptiesAdjustment(
        TourStop $stop,
        Product  $product,
        float    $qtyDelta,
        string   $note,
        string   $eventType = FulfillmentEvent::TYPE_EMPTIES_ADJUSTED,
        ?int     $userId = null,
    ): void {
        if ($qtyDelta === 0.0) {
            throw new \InvalidArgumentException('qtyDelta must not be zero.');
        }

        DB::transaction(function () use ($stop, $product, $qtyDelta, $note, $eventType, $userId): void {
            $warehouse = $this->resolveDefaultWarehouse();

            if ($qtyDelta > 0) {
                $this->stockService->increaseStock(
                    product:         $product,
                    warehouse:       $warehouse,
                    qty:             abs($qtyDelta),
                    movementType:    StockMovement::TYPE_CORRECTION,
                    referenceType:   'tour_stop',
                    referenceId:     $stop->id,
                    note:            $note,
                    createdByUserId: $userId,
                );
            } else {
                $this->stockService->decreaseStock(
                    product:         $product,
                    warehouse:       $warehouse,
                    qty:             abs($qtyDelta),
                    movementType:    StockMovement::TYPE_CORRECTION,
                    referenceType:   'tour_stop',
                    referenceId:     $stop->id,
                    note:            $note,
                    createdByUserId: $userId,
                );
            }

            $this->appendEvent($stop, $eventType, [
                'product_id'   => $product->id,
                'qty_delta'    => $qtyDelta,
                'warehouse_id' => $warehouse->id,
                'note'         => $note,
            ], $userId);
        });
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Upsert the OrderItemFulfillment summary row and apply a mutation callback.
     *
     * Uses a SELECT … FOR UPDATE to prevent concurrent writes racing on qty fields.
     */
    private function upsertFulfillment(
        TourStop  $stop,
        OrderItem $item,
        callable  $mutate,
    ): void {
        $row = OrderItemFulfillment::query()
            ->where('tour_stop_id',  $stop->id)
            ->where('order_item_id', $item->id)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            $row = new OrderItemFulfillment([
                'tour_stop_id'         => $stop->id,
                'order_item_id'        => $item->id,
                'delivered_qty'        => 0,
                'not_delivered_qty'    => 0,
                'not_delivered_reason' => null,
                'note'                 => null,
                'updated_at'           => now(),
            ]);
        }

        $mutate($row);
        $row->save();
    }

    /**
     * Append an immutable FulfillmentEvent for the given stop.
     *
     * @param  TourStop     $stop
     * @param  string       $eventType   One of FulfillmentEvent::TYPE_* constants
     * @param  array<mixed> $payload
     * @param  int|null     $userId
     */
    private function appendEvent(
        TourStop $stop,
        string   $eventType,
        array    $payload,
        ?int     $userId,
    ): void {
        FulfillmentEvent::create([
            'tour_stop_id'       => $stop->id,
            'event_type'         => $eventType,
            'payload_json'       => $payload,
            'created_by_user_id' => $userId,
            'created_at'         => now(),
        ]);
    }

    /**
     * Resolve the warehouse to use for stock bookings.
     *
     * Priority:
     *   1. app_settings["default_warehouse_id"] → Warehouse by ID
     *   2. First active Warehouse (by id ascending)
     *
     * @throws \RuntimeException when no active warehouse exists
     */
    private function resolveDefaultWarehouse(): Warehouse
    {
        $warehouseId = AppSetting::get('default_warehouse_id');

        if ($warehouseId !== null) {
            $warehouse = Warehouse::find((int) $warehouseId);

            if ($warehouse !== null) {
                return $warehouse;
            }
        }

        // Fall back to first active warehouse
        $warehouse = Warehouse::where('active', true)->orderBy('id')->first();

        if ($warehouse === null) {
            throw new \RuntimeException(
                'No active warehouse found. '
                . 'Configure app_settings["default_warehouse_id"] or create an active Warehouse.'
            );
        }

        return $warehouse;
    }
}
