<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Catalog\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use App\Models\Orders\OrderItemComponent;
use App\Models\Pricing\Customer;
use App\Services\Inventory\StockService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Core order creation service for Kolabri Getränke.
 *
 * Responsibilities of createOrder():
 *   1. Compute the complete per-line pricing snapshot via OrderPricingService:
 *        - net price, gross price, price source
 *        - tax_rate_id, tax_rate_basis_points
 *        - pfand_set_id, unit_deposit_milli
 *   2. Flag backorder when availability_mode = 'stock_based' and available
 *      quantity is insufficient.
 *   3. Explode bundle products and save one OrderItemComponent per resolved leaf.
 *   4. Compute order-level totals (net, gross, pfand).
 *   5. Wrap everything in a DB transaction.
 *
 * Backorder model:
 *   The order is ALWAYS created. Insufficient stock only sets is_backorder = true
 *   on the order item (and has_backorder = true on the order). Stock movements
 *   are NOT written by this service — that is the responsibility of a downstream
 *   fulfilment step.
 *
 * Snapshot strategy:
 *   All price, tax, and deposit fields on order_items are frozen at creation time
 *   by OrderPricingService. Subsequent changes to products, pricing rules, or
 *   tax-rate tables have no effect on existing orders.
 *
 * Monetary convention: all *_milli values are milli-cents (int).
 *   1 EUR = 1_000_000 milli-cents.
 *
 * @see OrderPricingService  for price + tax + deposit snapshot computation
 * @see PfandCalculator      for deposit-tree recursion (used inside OrderPricingService)
 * @see StockService         for availability queries
 */
class OrderService
{
    public function __construct(
        private readonly OrderPricingService $orderPricingService,
        private readonly StockService        $stockService,
    ) {}

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Create a new order for a customer.
     *
     * @param Customer                                     $customer
     * @param array<int, array{product: Product, qty: int}> $items
     *        Each element must contain:
     *          'product' => Product instance (must have gebinde relation loadable)
     *          'qty'     => positive integer quantity
     * @param Carbon|null    $deliveryDate  Optional requested delivery date
     * @param Warehouse|null $warehouse     Optional warehouse for stock checks and fulfilment
     * @return Order                        The persisted order with all items and components
     *
     * @throws \InvalidArgumentException when $items is empty or qty ≤ 0
     * @throws \RuntimeException         on DB failure (transaction rolls back)
     */
    public function createOrder(
        Customer   $customer,
        array      $items,
        ?Carbon    $deliveryDate = null,
        ?Warehouse $warehouse    = null,
    ): Order {
        if (empty($items)) {
            throw new \InvalidArgumentException('Cannot create an order with no items.');
        }

        foreach ($items as $idx => $item) {
            if (! isset($item['product'], $item['qty'])) {
                throw new \InvalidArgumentException(
                    "Item at index {$idx} must have 'product' and 'qty' keys."
                );
            }
            if ($item['qty'] <= 0) {
                throw new \InvalidArgumentException(
                    "Item qty must be > 0, got {$item['qty']} at index {$idx}."
                );
            }
        }

        // Load the customer's group once — needed for deposit-exempt check and
        // customer_group_id_snapshot on the order header.
        $customer->loadMissing('customerGroup');
        $group           = $customer->customerGroup;
        $isDepositExempt = $group->is_deposit_exempt;

        return DB::transaction(function () use (
            $customer, $group, $items, $deliveryDate, $warehouse, $isDepositExempt
        ): Order {

            // ------------------------------------------------------------------
            // 1. Create the order header (totals filled in at the end)
            // ------------------------------------------------------------------
            $order = Order::create([
                'company_id'                 => $customer->company_id,  // BUG-13 fix
                'customer_id'                => $customer->id,
                'customer_group_id_snapshot' => $group->id,
                'status'                     => Order::STATUS_PENDING,
                'delivery_date'              => $deliveryDate,
                'warehouse_id'               => $warehouse?->id,
                'has_backorder'              => false,
                'total_net_milli'            => 0,
                'total_gross_milli'          => 0,
                'total_pfand_brutto_milli'   => 0,
            ]);

            $totalNetMilli   = 0;
            $totalGrossMilli = 0;
            $totalPfandMilli = 0;
            $hasBackorder    = false;

            // ------------------------------------------------------------------
            // 2. Process each line item
            // ------------------------------------------------------------------
            foreach ($items as $itemData) {
                /** @var Product $product */
                $product = $itemData['product'];
                $qty     = (int) $itemData['qty'];

                // 2a. Compute full pricing snapshot via OrderPricingService
                $snapshot = $this->orderPricingService->priceOrderItem(
                    product:         $product,
                    customer:        $customer,
                    isDepositExempt: $isDepositExempt,
                );

                // 2b. Check stock / backorder flag
                $isBackorder = false;
                if (
                    $warehouse !== null
                    && $product->availability_mode === Product::AVAILABILITY_STOCK_BASED
                ) {
                    $available = $this->stockService->getAvailableQuantity($product, $warehouse);
                    if ($available < $qty) {
                        $isBackorder  = true;
                        $hasBackorder = true;
                    }
                }

                // 2c. Create order_item with all snapshot fields spread in
                $orderItem = OrderItem::create(array_merge(
                    $snapshot->toOrderItemArray(),
                    [
                        'order_id'               => $order->id,
                        'product_id'             => $product->id,
                        'qty'                    => $qty,
                        'is_backorder'           => $isBackorder,
                        'product_name_snapshot'  => $product->produktname,
                        'artikelnummer_snapshot' => $product->artikelnummer,
                    ]
                ));

                // 2d. Accumulate order totals
                $totalNetMilli   += $snapshot->netMilli         * $qty;
                $totalGrossMilli += $snapshot->grossMilli       * $qty;
                $totalPfandMilli += $snapshot->unitDepositMilli * $qty;

                // 2e. Explode bundle — snapshot components
                if ($product->isBundle()) {
                    $this->snapshotBundleComponents($orderItem, $product, $qty);
                }
            }

            // ------------------------------------------------------------------
            // 3. Update order header with computed totals
            // ------------------------------------------------------------------
            $order->update([
                'has_backorder'            => $hasBackorder,
                'total_net_milli'          => $totalNetMilli,
                'total_gross_milli'        => $totalGrossMilli,
                'total_pfand_brutto_milli' => $totalPfandMilli,
            ]);

            return $order;
        });
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * Snapshot the resolved bundle component tree into order_item_components rows.
     *
     * Calls Product::resolveBundleComponentsRecursive() which handles:
     *   - Multi-level nesting with quantity multiplication
     *   - Cycle protection via $visited set
     *
     * One row is written per unique leaf product, storing the fully-multiplied
     * qty_per_bundle and the total qty across all ordered bundle units.
     *
     * @param OrderItem $orderItem   The parent bundle order item
     * @param Product   $bundle      The bundle product
     * @param int       $bundleQty   How many bundles were ordered
     */
    private function snapshotBundleComponents(
        OrderItem $orderItem,
        Product   $bundle,
        int       $bundleQty,
    ): void {
        $leafComponents = $bundle->resolveBundleComponentsRecursive();

        foreach ($leafComponents as ['product' => $leafProduct, 'qty' => $qtyPerBundle]) {
            OrderItemComponent::create([
                'order_item_id'                    => $orderItem->id,
                'component_product_id'             => $leafProduct->id,
                'component_product_name_snapshot'  => $leafProduct->produktname,
                'component_artikelnummer_snapshot' => $leafProduct->artikelnummer,
                'qty_per_bundle'                   => $qtyPerBundle,
                'qty_total'                        => $qtyPerBundle * $bundleQty,
            ]);
        }
    }
}
