<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookGoodsReceiptRequest;
use App\Models\Supplier\PurchaseOrder;
use App\Services\Procurement\EinkaufService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * PROJ-32: Goods receipt booking for Purchase Orders.
 */
class AdminEinkaufWareneingangController extends Controller
{
    public function __construct(
        private readonly EinkaufService $service,
    ) {}

    /**
     * POST /admin/einkauf/{purchaseOrder}/wareneingang — Book goods receipt.
     */
    public function store(BookGoodsReceiptRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $data        = $request->validated();
        $warehouseId = (int) $data['warehouse_id'];
        $receivedQtys = [];

        foreach ($data['received'] as $itemId => $qty) {
            $qty = (float) $qty;
            if ($qty > 0) {
                $receivedQtys[(int) $itemId] = $qty;
            }
        }

        if (empty($receivedQtys)) {
            return redirect()->back()->with('error', 'Bitte mindestens eine gelieferte Menge eingeben.');
        }

        try {
            $this->service->bookReceipt(
                $purchaseOrder,
                $receivedQtys,
                $warehouseId,
                auth()->id()
            );

            $purchaseOrder->refresh();
            $statusText = match ($purchaseOrder->status) {
                PurchaseOrder::STATUS_RECEIVED           => 'vollständig eingegangen',
                PurchaseOrder::STATUS_PARTIALLY_RECEIVED => 'teilweise eingegangen',
                default                                  => $purchaseOrder->status,
            };

            return redirect()
                ->route('admin.einkauf.show', $purchaseOrder)
                ->with('success', "Wareneingang gebucht — Bestellung ist jetzt {$statusText}.");
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST /admin/einkauf/{purchaseOrder}/wareneingang/correct — Correct a booked receipt.
     *
     * Accepts absolute received_qty values per item and books the difference
     * as a 'correction' stock movement.
     */
    public function correct(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $request->validate([
            'warehouse_id'                     => ['required', 'integer', 'exists:warehouses,id'],
            'received'                         => ['required', 'array'],
            'received.*'                       => ['required', 'numeric', 'min:0'],
            'new_items'                        => ['nullable', 'array'],
            'new_items.*.product_id'           => ['required_with:new_items', 'integer', 'exists:products,id'],
            'new_items.*.qty'                  => ['required_with:new_items', 'numeric', 'min:0.001'],
            'new_items.*.received_qty'         => ['nullable', 'numeric', 'min:0'],
            'new_items.*.unit_purchase_milli'  => ['nullable', 'integer', 'min:0'],
        ]);

        $warehouseId = (int) $request->input('warehouse_id');
        $newQtys     = [];

        foreach ($request->input('received', []) as $itemId => $qty) {
            $newQtys[(int) $itemId] = (float) $qty;
        }

        $newItems = array_filter(
            $request->input('new_items', []),
            fn ($ni) => ! empty($ni['product_id'])
        );

        try {
            $this->service->correctReceipt(
                $purchaseOrder,
                $newQtys,
                $warehouseId,
                auth()->id(),
                array_values($newItems),
            );

            return redirect()
                ->route('admin.einkauf.show', $purchaseOrder)
                ->with('success', "Wareneingang für {$purchaseOrder->po_number} wurde korrigiert.");
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
