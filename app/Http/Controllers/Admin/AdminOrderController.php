<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Invoice;
use App\Models\Catalog\Product;
use App\Models\Delivery\TourStop;
use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use App\Services\Orders\PfandCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminOrderController extends Controller
{
    /**
     * GET /admin/orders
     * List orders with optional filters.
     */
    public function index(Request $request): View
    {
        $query = Order::with(['customer', 'regularDeliveryTour'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('id', 'LIKE', $term)
                  ->orWhereHas('customer', fn ($cq) => $cq
                      ->where('first_name', 'LIKE', $term)
                      ->orWhere('last_name', 'LIKE', $term)
                      ->orWhere('customer_number', 'LIKE', $term)
                  );
            });
        }

        $orders = $query->paginate(25)->withQueryString();

        $statuses = [
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
            Order::STATUS_CANCELLED,
        ];

        return view('admin.orders.index', compact('orders', 'statuses'));
    }

    /**
     * GET /admin/orders/{order}
     * Show order details with fulfillment info.
     */
    public function show(Order $order): View
    {
        $order->load([
            'customer',
            'items',
            'tourStop.itemFulfillments',
        ]);

        /** @var TourStop|null $stop */
        $stop = $order->tourStop;

        $itemDetails = $order->items->map(function ($item) use ($stop) {
            $fulfillment = $stop?->itemFulfillments->firstWhere('order_item_id', $item->id);

            return [
                'item'              => $item,
                'ordered_qty'       => $item->qty,
                'delivered_qty'     => $fulfillment?->delivered_qty,
                'not_delivered_qty' => $fulfillment?->not_delivered_qty,
                'nd_reason'         => $fulfillment?->not_delivered_reason,
            ];
        });

        $invoice = Invoice::where('order_id', $order->id)->first();

        return view('admin.orders.show', compact('order', 'itemDetails', 'stop', 'invoice'));
    }

    // =========================================================================
    // Edit order (WP-22)
    // =========================================================================

    /**
     * GET /admin/orders/{order}/edit
     * Show order edit form (change qty, remove items, add items).
     */
    public function edit(Order $order): View
    {
        $order->load(['customer', 'items.product']);

        // Active products for "add item" dropdown
        $products = Product::where('active', true)
            ->orderBy('produktname')
            ->get(['id', 'artikelnummer', 'produktname', 'base_price_gross_milli',
                   'base_price_net_milli', 'tax_rate_id', 'gebinde_id']);

        return view('admin.orders.edit', compact('order', 'products'));
    }

    /**
     * POST /admin/orders/{order}/items
     * Update quantities and/or remove items from the order.
     *
     * Expects:
     *   qty[{item_id}] = new quantity  (0 = remove)
     *   remove[]       = item_ids to delete
     */
    public function updateItems(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'qty'      => ['nullable', 'array'],
            'qty.*'    => ['integer', 'min:0', 'max:9999'],
            'remove'   => ['nullable', 'array'],
            'remove.*' => ['integer'],
        ]);

        // Delete explicitly removed items
        if ($request->filled('remove')) {
            OrderItem::whereIn('id', $request->input('remove'))
                ->where('order_id', $order->id)
                ->delete();
        }

        // Apply qty changes (0 = remove)
        foreach ((array) $request->input('qty', []) as $itemId => $qty) {
            $qty = (int) $qty;
            if ($qty <= 0) {
                OrderItem::where('id', $itemId)->where('order_id', $order->id)->delete();
            } else {
                OrderItem::where('id', $itemId)->where('order_id', $order->id)->update(['qty' => $qty]);
            }
        }

        $this->recalcOrderTotals($order);

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', 'Bestellung aktualisiert.');
    }

    /**
     * POST /admin/orders/{order}/items/add
     * Add a new product to the order, or increment qty if already present.
     */
    public function addItem(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'qty'        => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $product = Product::with(['taxRate', 'gebinde'])->findOrFail($request->input('product_id'));
        $qty     = (int) $request->input('qty');

        $pfandMilli = 0;
        if ($product->gebinde) {
            try {
                $pfandMilli = app(PfandCalculator::class)->totalForGebinde($product->gebinde);
            } catch (\Throwable) {
                $pfandMilli = 0;
            }
        }

        // If item already in order — just add to qty
        $existing = OrderItem::where('order_id', $order->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->update(['qty' => $existing->qty + $qty]);
        } else {
            OrderItem::create([
                'order_id'               => $order->id,
                'product_id'             => $product->id,
                'unit_price_net_milli'   => $product->base_price_net_milli,
                'unit_price_gross_milli' => $product->base_price_gross_milli,
                'price_source'           => 'base_plus_adjustment',
                'tax_rate_id'            => $product->tax_rate_id,
                'tax_rate_basis_points'  => $product->taxRate?->rate_basis_points ?? 0,
                'pfand_set_id'           => $product->gebinde?->pfand_set_id,
                'unit_deposit_milli'     => $pfandMilli,
                'qty'                    => $qty,
                'is_backorder'           => false,
                'product_name_snapshot'  => $product->produktname,
                'artikelnummer_snapshot' => $product->artikelnummer,
            ]);
        }

        $this->recalcOrderTotals($order);

        return redirect()
            ->route('admin.orders.edit', $order)
            ->with('success', $product->produktname . ' hinzugefuegt.');
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * Recalculate and persist order totals from current items.
     */
    private function recalcOrderTotals(Order $order): void
    {
        $order->refresh()->load('items');

        $order->total_net_milli          = (int) $order->items->sum(fn ($i) => $i->unit_price_net_milli * $i->qty);
        $order->total_gross_milli        = (int) $order->items->sum(fn ($i) => $i->unit_price_gross_milli * $i->qty);
        $order->total_pfand_brutto_milli = (int) $order->items->sum(fn ($i) => $i->unit_deposit_milli * $i->qty);

        $order->save();
    }
}
