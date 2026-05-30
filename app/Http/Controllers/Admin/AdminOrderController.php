<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Invoice;
use App\Models\Catalog\PfandSet;
use App\Models\Catalog\Product;
use App\Models\Delivery\TourStop;
use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use App\Models\Company;
use App\Services\Catalog\JugendschutzService;
use App\Services\Ninox\NinoxPullService;
use App\Services\Orders\PfandCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
            'items.product.gebinde',
            'tourStop.itemFulfillments',
            'lieferscheinUpload',
        ]);

        /** @var TourStop|null $stop */
        $stop = $order->tourStop;

        $itemDetails = $order->items->map(function ($item) use ($stop) {
            $fulfillment = $stop?->itemFulfillments->firstWhere('order_item_id', $item->id);

            return [
                'item'              => $item,
                'ordered_qty'       => $item->qty,
                'gebinde_name'      => $item->product?->gebinde?->name ?? '',
                'delivered_qty'     => $fulfillment?->delivered_qty,
                'not_delivered_qty' => $fulfillment?->not_delivered_qty,
                'nd_reason'         => $fulfillment?->not_delivered_reason,
            ];
        });

        $invoice = Invoice::where('order_id', $order->id)->first();
        $minAge  = JugendschutzService::orderMinAge($order);

        return view('admin.orders.show', compact('order', 'itemDetails', 'stop', 'invoice', 'minAge'));
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
        $order->load(['customer', 'items.product.gebinde']);

        return view('admin.orders.edit', compact('order'));
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
            'notes'    => ['nullable', 'string', 'max:5000'],
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

        $order->update(['notes' => $request->input('notes')]);

        $this->recalcOrderTotals($order);

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', 'Bestellung aktualisiert.');
    }

    /**
     * POST /admin/orders/{order}/items/add
     * Add a new product to the order, or increment qty if already present.
     * Returns JSON when called with Accept: application/json.
     */
    public function addItem(Request $request, Order $order): RedirectResponse|JsonResponse
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

        if ($request->expectsJson()) {
            $savedItem = $existing
                ? $existing->fresh(['product.gebinde'])
                : OrderItem::where('order_id', $order->id)
                    ->where('product_id', $product->id)
                    ->with('product.gebinde')
                    ->latest('id')
                    ->first();

            $order->refresh();

            return response()->json([
                'success' => true,
                'item'    => $this->formatItemForJs($savedItem),
                'totals'  => [
                    'gross_milli' => $order->total_gross_milli,
                    'pfand_milli' => $order->total_pfand_brutto_milli,
                ],
            ]);
        }

        return redirect()
            ->route('admin.orders.edit', $order)
            ->with('success', $product->produktname . ' hinzugefuegt.');
    }

    private function formatItemForJs(OrderItem $item): array
    {
        return [
            'id'                     => $item->id,
            'artikelnummer_snapshot' => $item->artikelnummer_snapshot,
            'product_name_snapshot'  => $item->product_name_snapshot,
            'qty'                    => $item->qty,
            'unit_price_gross_milli' => $item->unit_price_gross_milli,
            'unit_deposit_milli'     => $item->unit_deposit_milli,
            'gebinde_name'           => $item->product?->gebinde?->name ?? '',
        ];
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * Recalculate and persist order totals from current items.
     */
    public function deleteItem(Order $order, OrderItem $item): JsonResponse
    {
        abort_if($item->order_id !== $order->id, 404);

        $item->delete();
        $this->recalcOrderTotals($order);

        return response()->json([
            'success' => true,
            'totals'  => [
                'gross_milli' => $order->total_gross_milli,
                'pfand_milli' => $order->total_pfand_brutto_milli,
            ],
        ]);
    }

    private function recalcOrderTotals(Order $order): void
    {
        $order->refresh()->load('items');

        $order->total_net_milli          = (int) $order->items->sum(fn ($i) => $i->unit_price_net_milli * $i->qty);
        $order->total_gross_milli        = (int) $order->items->sum(fn ($i) => $i->unit_price_gross_milli * $i->qty);
        $order->total_pfand_brutto_milli = (int) $order->items->sum(fn ($i) => $i->unit_deposit_milli * $i->qty);

        $order->save();
    }

    // =========================================================================
    // WAWI-Auftrag → OrderItems
    // =========================================================================

    /**
     * POST /admin/orders/{order}/wawi-import
     * Übernimmt Positionen aus einem JTL-WAWI-Auftrag als OrderItems.
     */
    public function importFromWawi(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'wawi_auftragsnr' => ['required', 'string', 'max:50'],
        ]);

        $auftragsnr = trim($request->input('wawi_auftragsnr'));

        $wawiOrder = DB::table('wawi_auftraege')
            ->where('cAuftragsNr', $auftragsnr)
            ->first();

        if (! $wawiOrder) {
            return back()->with('error',
                "WAWI-Auftrag \"{$auftragsnr}\" nicht gefunden. " .
                'Bitte WAWI-Sync durchführen, damit aktuelle Aufträge verfügbar sind.'
            );
        }

        $positions = DB::table('wawi_auftragspositionen')
            ->where('kAuftrag', $wawiOrder->kAuftrag)
            ->get();

        if ($positions->isEmpty()) {
            return back()->with('error', "WAWI-Auftrag \"{$auftragsnr}\" hat keine Positionen.");
        }

        $imported = 0;

        foreach ($positions as $pos) {
            $product = null;
            if ($pos->cArtNr) {
                $product = Product::with(['taxRate', 'gebinde'])
                    ->where('artikelnummer', $pos->cArtNr)
                    ->first();
            }
            if (! $product && $pos->kArtikel) {
                $product = Product::with(['taxRate', 'gebinde'])
                    ->where('wawi_artikel_id', $pos->kArtikel)
                    ->first();
            }

            $qty = max(1, (int) round((float) ($pos->fAnzahl ?? 1)));

            if ($product) {
                $netMilli   = $product->base_price_net_milli;
                $grossMilli = $product->base_price_gross_milli;
                $taxRateId  = $product->tax_rate_id;
                $taxBp      = $product->taxRate?->rate_basis_points ?? 0;
            } else {
                $netMilli   = (int) round((float) ($pos->fVkNetto ?? 0) * 1_000_000);
                $grossMilli = (int) round($netMilli * 1.19);
                $taxRateId  = null;
                $taxBp      = 1_900;
            }

            $pfandMilli = 0;
            if ($product?->gebinde) {
                try {
                    $pfandMilli = app(PfandCalculator::class)->totalForGebinde($product->gebinde);
                } catch (\Throwable) {
                    $pfandMilli = 0;
                }
            }

            $existing = OrderItem::where('order_id', $order->id)
                ->when($product, fn ($q) => $q->where('product_id', $product->id))
                ->when(! $product, fn ($q) => $q->where('artikelnummer_snapshot', $pos->cArtNr))
                ->first();

            if ($existing) {
                $existing->update(['qty' => $existing->qty + $qty]);
            } else {
                OrderItem::create([
                    'order_id'               => $order->id,
                    'product_id'             => $product?->id,
                    'unit_price_net_milli'   => $netMilli,
                    'unit_price_gross_milli' => $grossMilli,
                    'price_source'           => 'wawi_import',
                    'tax_rate_id'            => $taxRateId,
                    'tax_rate_basis_points'  => $taxBp,
                    'pfand_set_id'           => $product?->gebinde?->pfand_set_id,
                    'unit_deposit_milli'     => $pfandMilli,
                    'qty'                    => $qty,
                    'is_backorder'           => false,
                    'product_name_snapshot'  => $product?->produktname ?? $pos->cName ?? '',
                    'artikelnummer_snapshot' => $product?->artikelnummer ?? $pos->cArtNr ?? '',
                ]);
            }

            $imported++;
        }

        $this->recalcOrderTotals($order);

        return redirect()
            ->route('admin.orders.edit', $order)
            ->with('success', "{$imported} Positionen aus WAWI-Auftrag \"{$auftragsnr}\" übernommen.");
    }

    // =========================================================================
    // Ninox standalone order sync
    // =========================================================================

    /**
     * POST /admin/orders/ninox-sync
     * Import Ninox orders that have no tour assignment and no local Order yet.
     */
    public function ninoxSync(NinoxPullService $service): RedirectResponse
    {
        try {
            $result = $service->pullStandaloneOrders();
            $msg = "Ninox-Sync: {$result['imported']} Bestellung(en) importiert";
            if ($result['failed']) {
                $msg .= ", {$result['failed']} fehlgeschlagen";
            }
            if ($result['remaining'] > 0) {
                $msg .= " — noch {$result['remaining']} ausstehend, erneut klicken";
            }
            return redirect()->route('admin.orders.index')->with('success', $msg . '.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.orders.index')
                ->with('error', 'Ninox-Sync fehlgeschlagen: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // Rückgabeformular (PDF)
    // =========================================================================

    /**
     * GET /admin/orders/{order}/return-form
     * Download Rückgabeformular als PDF (bestellungsbezogen).
     */
    public function returnForm(Order $order): Response
    {
        $order->load([
            'customer',
            'items',
            'rentalBookingItems.rentalItem',
        ]);

        $leergut = $this->aggregateLeergut($order);
        $company = Company::first();

        $pdf = Pdf::loadView('pdf.return-form', compact('order', 'leergut', 'company'))
            ->setPaper('a4', 'portrait');

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="rueckgabe-' . $order->id . '.pdf"',
        ]);
    }

    /**
     * GET /admin/return-form/blank
     * Download Blanko-Rückgabeformular (ohne Bestellbezug).
     */
    public function returnFormBlank(): Response
    {
        $company = Company::first();
        $order   = null;
        $leergut = [];

        $pdf = Pdf::loadView('pdf.return-form', compact('order', 'leergut', 'company'))
            ->setPaper('a4', 'portrait');

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="rueckgabe-blanko.pdf"',
        ]);
    }

    /**
     * Aggregiert alle Pfand-Leaf-Items einer Bestellung nach Bezeichnung.
     * Expandiert PfandSets maximal 2 Ebenen tief.
     *
     * OrderItem hat keine pfandSet()-Relation — wir laden die Sets direkt per pfand_set_id.
     *
     * @return array<int, array{bezeichnung: string, menge: int}>
     */
    private function aggregateLeergut(Order $order): array
    {
        $pfandSetIds = $order->items
            ->filter(fn ($item) => $item->pfand_set_id !== null)
            ->pluck('pfand_set_id')
            ->unique();

        if ($pfandSetIds->isEmpty()) {
            return [];
        }

        $pfandSets = PfandSet::with([
            'components.pfandItem',
            'components.childPfandSet.components.pfandItem',
        ])->whereIn('id', $pfandSetIds)->get()->keyBy('id');

        $totals = [];

        foreach ($order->items as $item) {
            if (! $item->pfand_set_id) {
                continue;
            }
            $pfandSet = $pfandSets->get($item->pfand_set_id);
            if (! $pfandSet) {
                continue;
            }
            $this->expandPfandSet($pfandSet, $item->qty, 1, $totals);
        }

        $result = [];
        foreach ($totals as $bezeichnung => $menge) {
            $result[] = ['bezeichnung' => $bezeichnung, 'menge' => $menge];
        }

        return $result;
    }

    /**
     * Rekursiv einen PfandSet in Leaf-PfandItems auflösen (max. Tiefe 2).
     *
     * @param array<string, int> $totals Akkumulator (by reference)
     */
    private function expandPfandSet(PfandSet $set, int $orderQty, int $depth, array &$totals): void
    {
        if ($depth > 2) {
            return;
        }

        foreach ($set->components as $component) {
            $componentQty = $orderQty * $component->qty;

            if ($component->isLeaf() && $component->pfandItem) {
                $label = $component->pfandItem->bezeichnung;
                $totals[$label] = ($totals[$label] ?? 0) + $componentQty;
            } elseif ($component->isNestedSet() && $component->childPfandSet) {
                $this->expandPfandSet($component->childPfandSet, $componentQty, $depth + 1, $totals);
            }
        }
    }
}
