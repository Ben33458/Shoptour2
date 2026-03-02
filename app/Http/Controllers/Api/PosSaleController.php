<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Admin\Invoice;
use App\Models\Admin\Payment;
use App\Models\Pricing\Customer;
use App\Models\Inventory\Warehouse;
use App\Services\Orders\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * POST /api/pos/sale
 *
 * Create a POS sale (order with immediate payment flag).
 *
 * Request body (JSON):
 * {
 *   "customer_id": 5,          // optional; defaults to walk-in customer if omitted
 *   "warehouse_id": 1,         // optional
 *   "payment_method": "cash",  // cash|card|other (default: cash)
 *   "items": [
 *     { "product_id": 12, "qty": 2 },
 *     { "product_id": 7,  "qty": 1 }
 *   ]
 * }
 *
 * Response:
 * {
 *   "order_id": 42,
 *   "total_gross_milli": 3570000,
 *   "payment_method": "cash",
 *   "message": "Sale recorded."
 * }
 */
class PosSaleController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id'     => ['nullable', 'integer', 'exists:customers,id'],
            'warehouse_id'    => ['nullable', 'integer', 'exists:warehouses,id'],
            'payment_method'  => ['nullable', 'string', 'in:cash,card,transfer,other'],
            'items'           => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty'        => ['required', 'numeric', 'min:0.001'],
        ]);

        $paymentMethod = $validated['payment_method'] ?? 'cash';

        // ── Resolve customer ──────────────────────────────────────────────
        $customer = null;
        if (! empty($validated['customer_id'])) {
            $customer = Customer::with('customerGroup')->find($validated['customer_id']);
        }

        if (! $customer) {
            // Walk-in: use the first active customer from the walk-in group
            // (or simply the first customer as a fallback for the POS MVP)
            $customer = Customer::with('customerGroup')->where('active', true)->first();

            if (! $customer) {
                return response()->json(['error' => 'No walk-in customer configured.'], 422);
            }
        }

        // ── Resolve warehouse ─────────────────────────────────────────────
        $warehouse = null;
        if (! empty($validated['warehouse_id'])) {
            $warehouse = Warehouse::find($validated['warehouse_id']);
        }

        // ── Build items array ─────────────────────────────────────────────
        $orderItems = [];
        foreach ($validated['items'] as $item) {
            $product = Product::find($item['product_id']);
            if (! $product || ! $product->active) {
                return response()->json([
                    'error' => "Product #{$item['product_id']} not found or inactive.",
                ], 422);
            }
            $orderItems[] = [
                'product' => $product,
                'qty'     => (int) $item['qty'],
            ];
        }

        // ── Create order ──────────────────────────────────────────────────
        $order = DB::transaction(function () use ($customer, $orderItems, $warehouse, $paymentMethod): \App\Models\Orders\Order {
            $order = $this->orderService->createOrder(
                customer:     $customer,
                items:        $orderItems,
                deliveryDate: null,
                warehouse:    $warehouse,
            );

            // Mark as POS sale with immediate payment
            $order->update([
                'immediate_payment' => true,
                'is_pos_sale'       => true,
                'status'            => \App\Models\Orders\Order::STATUS_DELIVERED,
            ]);

            return $order;
        });

        return response()->json([
            'order_id'          => $order->id,
            'total_gross_milli' => $order->total_gross_milli,
            'payment_method'    => $paymentMethod,
            'message'           => 'Sale recorded.',
        ], 201);
    }
}
