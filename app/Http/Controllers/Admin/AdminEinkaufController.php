<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePurchaseOrderRequest;
use App\Models\Catalog\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Supplier\PurchaseOrder;
use App\Models\Supplier\PurchaseOrderItem;
use App\Models\Supplier\Supplier;
use App\Services\Procurement\EinkaufService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

/**
 * PROJ-32: Admin CRUD controller for Purchase Orders (Einkauf).
 */
class AdminEinkaufController extends Controller
{
    public function __construct(
        private readonly EinkaufService $service,
    ) {}

    /**
     * GET /admin/einkauf — Purchase Order list with filters.
     */
    public function index(Request $request): View
    {
        $company = App::make('current_company');

        $query = PurchaseOrder::with(['supplier', 'warehouse'])
            ->where('company_id', $company?->id)
            ->orderByRaw("FIELD(status, 'draft', 'sent', 'confirmed', 'partially_received', 'received', 'cancelled')")
            ->orderBy('expected_at');

        // Filter: Status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter: Supplier
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        // Filter: Date range
        if ($request->filled('from')) {
            $query->where('ordered_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('ordered_at', '<=', $request->input('to'));
        }

        // Search by PO number
        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('po_number', 'LIKE', $term)
                  ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'LIKE', $term));
            });
        }

        $purchaseOrders = $query->paginate(25)->withQueryString();

        $suppliers = Supplier::where('company_id', $company?->id)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $statusCounts = PurchaseOrder::where('company_id', $company?->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return view('admin.einkauf.index', compact('purchaseOrders', 'suppliers', 'statusCounts'));
    }

    /**
     * GET /admin/einkauf/api/product-search — Typeahead endpoint for product search.
     */
    public function productSearch(Request $request): JsonResponse
    {
        $term       = $request->input('q', '');
        $supplierId = $request->input('supplier_id');

        if (strlen($term) < 2) {
            return response()->json([]);
        }

        $like = '%' . $term . '%';

        // Strip leading zeros so "066193" matches stored value "66193" (and vice versa)
        $termNoLeadingZeros = ltrim($term, '0') ?: $term;
        $likeNoZeros        = '%' . $termNoLeadingZeros . '%';

        // Pre-fetch wawi_artikel_ids whose supplier article numbers match the query.
        // Single scan (5 k rows) replaces a correlated EXISTS per product row.
        $matchingWawiIds = \DB::table('wawi_dbo_tliefartikel')
            ->where('cLiefArtNr', 'LIKE', $likeNoZeros)
            ->pluck('tArtikel_kArtikel')
            ->unique()
            ->all();

        $query = Product::query()
            ->where(function ($q) use ($like, $matchingWawiIds): void {
                $q->where('produktname', 'LIKE', $like)
                  ->orWhere('artikelnummer', 'LIKE', $like);
                if ($matchingWawiIds) {
                    $q->orWhereIn('wawi_artikel_id', $matchingWawiIds);
                }
            })
            ->limit(20);

        if ($supplierId) {
            $filterOwn = Supplier::where('id', $supplierId)->value('po_filter_own_products');

            if ($filterOwn) {
                $query->whereHas('supplierProducts', fn ($sq) => $sq->where('supplier_id', $supplierId));
            } else {
                $query->orderByRaw('EXISTS (
                    SELECT 1 FROM supplier_products sp
                    WHERE sp.product_id = products.id AND sp.supplier_id = ?
                ) DESC', [$supplierId]);
            }
        }

        $products = $query->get(['id', 'produktname', 'artikelnummer', 'base_price_gross_milli', 'wawi_artikel_id']);

        // Batch-fetch all supplier prices in one query instead of N+1.
        $supplierPriceMap = [];
        if ($supplierId && $products->isNotEmpty()) {
            \DB::table('supplier_products')
                ->whereIn('product_id', $products->pluck('id'))
                ->where('supplier_id', $supplierId)
                ->orderByDesc('updated_at')
                ->get(['product_id', 'purchase_price_milli'])
                ->each(function ($row) use (&$supplierPriceMap): void {
                    // keep the most-recently-updated price per product
                    $supplierPriceMap[$row->product_id] ??= (int) $row->purchase_price_milli;
                });
        }

        // Build a lookup: wawi_artikel_id → matching Lief-ArtNr strings for label display.
        $wawiIds = $products->pluck('wawi_artikel_id')->filter()->unique()->values()->all();
        $liefArtNrMap = [];
        if ($wawiIds) {
            \DB::table('wawi_dbo_tliefartikel')
                ->whereIn('tArtikel_kArtikel', $wawiIds)
                ->where('cLiefArtNr', 'LIKE', $likeNoZeros)
                ->get(['tArtikel_kArtikel', 'cLiefArtNr'])
                ->each(function ($row) use (&$liefArtNrMap): void {
                    $liefArtNrMap[(int) $row->tArtikel_kArtikel][] = $row->cLiefArtNr;
                });
        }

        $results = $products->map(function (Product $p) use ($liefArtNrMap, $supplierPriceMap): array {
            $label = $p->produktname . ' [' . $p->artikelnummer . ']';
            if (isset($liefArtNrMap[$p->wawi_artikel_id])) {
                $artNrs = implode(', ', array_unique($liefArtNrMap[$p->wawi_artikel_id]));
                $label .= ' · Lief-ArtNr: ' . $artNrs;
            }
            return [
                'id'            => $p->id,
                'label'         => $label,
                'artikelnummer' => $p->artikelnummer,
                'price_milli'   => $supplierPriceMap[$p->id] ?? 0,
                'wawi_id'       => null,
            ];
        })->all();

        // If fewer than 5 shoptour2 results, also search wawi_artikel for unimported products.
        if (count($results) < 5) {
            $wawiRows = \DB::table('wawi_artikel')
                ->where(function ($q) use ($like): void {
                    $q->where('cName', 'LIKE', $like)
                      ->orWhere('cArtNr', 'LIKE', $like);
                })
                ->where('cAktiv', 'Y')
                ->limit(10)
                ->get(['kArtikel', 'cArtNr', 'cName', 'fEKNetto']);

            $existingArtnrs = $products->pluck('artikelnummer')->all();
            foreach ($wawiRows as $w) {
                if (in_array($w->cArtNr, $existingArtnrs, true)) {
                    continue;
                }
                $results[] = [
                    'id'            => null,
                    'label'         => trim($w->cName) . ' [' . $w->cArtNr . ']',
                    'artikelnummer' => $w->cArtNr,
                    'price_milli'   => (int) round((float) ($w->fEKNetto ?? 0) * 1_000_000),
                    'wawi_id'       => $w->kArtikel,
                ];
            }
        }

        return response()->json($results);
    }

    /**
     * POST /admin/einkauf/api/import-wawi — Create a Product from wawi_artikel data.
     *
     * Body: { wawi_id: int }
     * Returns: { id, label, artikelnummer, price_milli }
     */
    public function importWawiProduct(Request $request): JsonResponse
    {
        $wawiId = (int) $request->input('wawi_id');

        $wawi = \DB::table('wawi_artikel')->where('kArtikel', $wawiId)->first();
        if (! $wawi) {
            return response()->json(['error' => 'WaWi-Artikel nicht gefunden.'], 404);
        }

        // Already imported?
        $existing = Product::where('wawi_artikel_id', $wawiId)
            ->orWhere('artikelnummer', $wawi->cArtNr)
            ->first(['id', 'produktname', 'artikelnummer']);

        if ($existing) {
            return response()->json([
                'id'           => $existing->id,
                'label'        => $existing->produktname . ' [' . $existing->artikelnummer . ']',
                'artikelnummer' => $existing->artikelnummer,
                'price_milli'  => 0,
                'imported'     => false,
                'message'      => 'Produkt war bereits vorhanden.',
            ]);
        }

        // Build slug
        $name = trim((string) ($wawi->cName ?? 'Unbekannt'));
        $base = \Illuminate\Support\Str::slug($name) ?: 'produkt';
        $slug = $base;
        $i    = 2;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        // Prices from WaWi (netto → 19% → milli)
        $nettoFloat  = (float) ($wawi->fVKNetto ?? 0);
        $bruttoFloat = $nettoFloat * 1.19;
        $netMilli    = (int) round($nettoFloat * 1_000_000);
        $grossMilli  = (int) round($bruttoFloat * 1_000_000);

        $product = Product::create([
            'artikelnummer'          => trim((string) $wawi->cArtNr),
            'slug'                   => $slug,
            'produktname'            => $name,
            'tax_rate_id'            => 1,
            'base_price_net_milli'   => $netMilli,
            'base_price_gross_milli' => $grossMilli,
            'availability_mode'      => 'always',
            'active'                 => true,
            'show_in_shop'           => false,  // not yet ready for shop
            'is_bundle'              => false,
            'wawi_artikel_id'        => $wawiId,
        ]);

        // Barcode
        $barcode = trim((string) ($wawi->cBarcode ?? ''));
        if ($barcode !== '' && $barcode !== '0') {
            \App\Models\Catalog\ProductBarcode::create([
                'product_id'   => $product->id,
                'barcode'      => $barcode,
                'barcode_type' => 'EAN-13',
                'is_primary'   => true,
            ]);
        }

        $this->auditLog->log('product.imported_from_wawi', $product, [
            'wawi_id'      => $wawiId,
            'artikelnummer' => $product->artikelnummer,
        ]);

        return response()->json([
            'id'            => $product->id,
            'label'         => $product->produktname . ' [' . $product->artikelnummer . ']',
            'artikelnummer' => $product->artikelnummer,
            'price_milli'   => 0,
            'imported'      => true,
            'message'       => 'Produkt wurde importiert.',
        ]);
    }

    /**
     * POST /admin/einkauf/api/supplier-filter — Toggle po_filter_own_products for a supplier.
     */
    public function toggleSupplierFilter(Request $request): JsonResponse
    {
        $supplierId = (int) $request->input('supplier_id');
        $enabled    = (bool) $request->input('enabled');

        $supplier = Supplier::findOrFail($supplierId);
        $supplier->update(['po_filter_own_products' => $enabled]);

        return response()->json(['supplier_id' => $supplierId, 'po_filter_own_products' => $enabled]);
    }

    /**
     * Look up the last purchase price for a product from a given supplier.
     * Falls back to 0 if no supplier_product record exists.
     */

    /**
     * GET /admin/einkauf/create — Form to create a new PO.
     */
    public function create(Request $request): View
    {
        $company = App::make('current_company');

        $suppliers = Supplier::where('company_id', $company?->id)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'po_filter_own_products']);

        $warehouses = Warehouse::where(function ($q) use ($company): void {
                $q->where('company_id', $company?->id)
                  ->orWhereNull('company_id');
            })
            ->where('active', true)
            ->orderBy('name')
            ->get();

        // Pre-selected items (from Bestellvorschläge)
        $prefillItems = [];
        if ($request->filled('supplier_id') && $request->filled('items')) {
            $prefillItems = json_decode($request->input('items'), true) ?? [];
        }

        return view('admin.einkauf.create', compact('suppliers', 'warehouses', 'prefillItems'));
    }

    /**
     * POST /admin/einkauf — Store a new PO.
     */
    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $company = App::make('current_company');
        $data    = $request->validated();

        $po = $this->service->createPurchaseOrder(
            array_merge($data, ['company_id' => $company?->id]),
            $data['items']
        );

        return redirect()
            ->route('admin.einkauf.show', $po)
            ->with('success', "Bestellung {$po->po_number} wurde als Entwurf erstellt.");
    }

    /**
     * GET /admin/einkauf/{purchaseOrder} — PO detail page.
     */
    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['items.product', 'supplier', 'warehouse']);

        $warehouses = Warehouse::where(function ($q) use ($purchaseOrder): void {
                $q->where('company_id', $purchaseOrder->company_id)
                  ->orWhereNull('company_id');
            })
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return view('admin.einkauf.show', compact('purchaseOrder', 'warehouses'));
    }

    /**
     * GET /admin/einkauf/{purchaseOrder}/edit — Edit form (draft only).
     */
    public function edit(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        if (! $purchaseOrder->isDraft()) {
            return redirect()
                ->route('admin.einkauf.show', $purchaseOrder)
                ->with('error', 'Nur Entwürfe können bearbeitet werden.');
        }

        $company = App::make('current_company');

        $purchaseOrder->load(['items.product', 'supplier', 'warehouse']);

        $suppliers = Supplier::where('company_id', $company?->id)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'po_filter_own_products']);

        $warehouses = Warehouse::where(function ($q) use ($company): void {
                $q->where('company_id', $company?->id)
                  ->orWhereNull('company_id');
            })
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return view('admin.einkauf.edit', compact('purchaseOrder', 'suppliers', 'warehouses'));
    }

    /**
     * PUT /admin/einkauf/{purchaseOrder} — Update a draft PO.
     */
    public function update(StorePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        try {
            $data = $request->validated();
            $this->service->updatePurchaseOrder($purchaseOrder, $data, $data['items']);

            return redirect()
                ->route('admin.einkauf.show', $purchaseOrder)
                ->with('success', "Bestellung {$purchaseOrder->po_number} wurde aktualisiert.");
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST /admin/einkauf/{purchaseOrder}/send — Mark as sent (or send email).
     */
    public function send(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        try {
            if ($purchaseOrder->supplier?->email) {
                $this->service->sendToSupplier($purchaseOrder);
                return redirect()
                    ->route('admin.einkauf.show', $purchaseOrder)
                    ->with('success', "Bestellung {$purchaseOrder->po_number} wurde per E-Mail an {$purchaseOrder->supplier->email} gesendet.");
            }

            // No email — just mark as sent
            $this->service->markAsSent($purchaseOrder);
            return redirect()
                ->route('admin.einkauf.show', $purchaseOrder)
                ->with('success', "Bestellung {$purchaseOrder->po_number} wurde als versendet markiert.");
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST /admin/einkauf/{purchaseOrder}/cancel — Cancel a PO.
     */
    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        try {
            $this->service->cancel($purchaseOrder);
            return redirect()
                ->route('admin.einkauf.show', $purchaseOrder)
                ->with('success', "Bestellung {$purchaseOrder->po_number} wurde storniert.");
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * GET /admin/einkauf/{purchaseOrder}/pdf — Download PO as PDF.
     */
    public function pdf(PurchaseOrder $purchaseOrder): Response
    {
        $content = $this->service->getPdfContent($purchaseOrder);

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $purchaseOrder->po_number . '.pdf"',
        ]);
    }

    /**
     * DELETE /admin/einkauf/{purchaseOrder} — Delete a draft PO.
     */
    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! $purchaseOrder->isDraft()) {
            return redirect()->back()->with('error', 'Nur Entwürfe können gelöscht werden.');
        }

        $poNumber = $purchaseOrder->po_number;
        $purchaseOrder->items()->delete();
        $purchaseOrder->delete();

        return redirect()
            ->route('admin.einkauf.index')
            ->with('success', "Bestellung {$poNumber} wurde gelöscht.");
    }

    /**
     * POST /admin/einkauf/{purchaseOrder}/items/reorder
     * Persist drag-and-drop order from the show view.
     */
    public function reorderItems(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $ids = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer'],
        ])['ids'];

        $valid = $purchaseOrder->items()->pluck('id')->all();
        if (count(array_diff($ids, $valid)) > 0) {
            return response()->json(['error' => 'Ungültige Positionen.'], 422);
        }

        foreach ($ids as $position => $itemId) {
            PurchaseOrderItem::where('id', $itemId)->update(['sort_order' => $position]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * PATCH /admin/einkauf/{purchaseOrder}/items/{item}/price
     * Inline price update from the show view.
     */
    public function updateItemPrice(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderItem $item): JsonResponse
    {
        if ($item->purchase_order_id !== $purchaseOrder->id) {
            return response()->json(['error' => 'Ungültige Position.'], 422);
        }

        $validated = $request->validate([
            'unit_purchase_milli' => ['required', 'integer', 'min:0'],
        ]);

        $newMilli    = $validated['unit_purchase_milli'];
        $lineMilli   = (int) round($item->qty * $newMilli);

        $item->update([
            'unit_purchase_milli' => $newMilli,
            'line_total_milli'    => $lineMilli,
        ]);

        $purchaseOrder->recalculateTotal();

        return response()->json([
            'unit_purchase_milli' => $newMilli,
            'line_total_milli'    => $lineMilli,
            'po_total_milli'      => $purchaseOrder->fresh()->total_milli,
        ]);
    }
}
