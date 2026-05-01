<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Bestandsaufnahme;

use App\Http\Controllers\Controller;
use App\Models\Bestandsaufnahme\ArtikelMindestbestand;
use App\Models\Bestandsaufnahme\ArtikelVerpackungseinheit;
use App\Models\Bestandsaufnahme\BestandsaufnahmePosition;
use App\Models\Bestandsaufnahme\BestandsaufnahmeSession;
use App\Models\Catalog\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\StockMovement;
use App\Models\Supplier\Supplier;
use App\Models\Supplier\SupplierProduct;
use App\Models\Warehouse;
use App\Services\Bestandsaufnahme\BestandsaufnahmeService;
use App\Services\Bestandsaufnahme\MhdRegelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminBestandsaufnahmeController extends Controller
{
    public function __construct(
        private readonly BestandsaufnahmeService $service,
        private readonly MhdRegelService $mhdRegelService,
    ) {}

    /** GET /admin/bestandsaufnahme */
    public function index(Request $request): View
    {
        $sessions = BestandsaufnahmeSession::with(['warehouse', 'gestartetVon'])
            ->when($request->warehouse_id, fn($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('gestartet_am')
            ->paginate(25)
            ->withQueryString();

        $warehouses = Warehouse::where('active', true)->orderBy('name')->get();

        return view('admin.bestandsaufnahme.index', compact('sessions', 'warehouses'));
    }

    /** GET /admin/bestandsaufnahme/create */
    public function create(): View
    {
        $warehouses = Warehouse::where('active', true)->orderBy('name')->get();
        return view('admin.bestandsaufnahme.create', compact('warehouses'));
    }

    /** POST /admin/bestandsaufnahme */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'titel'        => ['nullable', 'string', 'max:200'],
        ]);

        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);
        $session   = $this->service->startOrResume($warehouse, $request->user(), $validated['titel'] ?? null);

        return redirect()->route('admin.bestandsaufnahme.show', $session)->with('success', 'Session gestartet.');
    }

    /** GET /admin/bestandsaufnahme/{session} */
    public function show(BestandsaufnahmeSession $bestandsaufnahme, Request $request): View
    {
        $session = $bestandsaufnahme->load('warehouse');

        $warehouseId  = $session->warehouse_id;
        $lieferantId  = $request->integer('lieferant_id') ?: null;
        $filter       = $request->get('filter', 'alle');

        // Produkte mit Bestand in diesem Lager (und optional Mindestbestand)
        $query = Product::with([
            'brand',
            'stocks' => fn($q) => $q->where('warehouse_id', $warehouseId),
            'supplierProducts' => fn($q) => $q->with('supplier')->where('active', true),
            'verpackungseinheiten' => fn($q) => $q->where('aktiv', true)->orderBy('sortierung'),
            'mindestbestaende' => fn($q) => $q->where('warehouse_id', $warehouseId),
        ])
        ->where('active', true)
        ->orderBy('produktname');

        // Lieferant-Filter
        if ($lieferantId) {
            $query->whereHas('supplierProducts', fn($q) => $q->where('supplier_id', $lieferantId));
        }

        // Weitere Filter
        if ($filter === 'fehlbestand') {
            $query->whereExists(function ($q) use ($warehouseId) {
                $q->from('artikel_mindestbestaende as am')
                    ->join('product_stocks as ps', function ($j) use ($warehouseId) {
                        $j->on('ps.product_id', '=', 'am.product_id')
                          ->where('ps.warehouse_id', $warehouseId);
                    })
                    ->whereColumn('am.product_id', 'products.id')
                    ->where('am.warehouse_id', $warehouseId)
                    ->whereRaw('ps.quantity < am.mindestbestand_basiseinheit');
            });
        } elseif ($filter === 'negativ') {
            $query->whereHas('stocks', fn($q) => $q->where('warehouse_id', $warehouseId)->where('quantity', '<', 0));
        }

        $products = $query->paginate(50)->withQueryString();

        // Bereits gezählte Positionen in dieser Session
        $gezaehlt = BestandsaufnahmePosition::where('session_id', $session->id)
            ->pluck('gezaehlt_am', 'product_id');

        $lieferanten = Supplier::whereHas('supplierProducts', fn($q) => $q
                ->where('active', true)
                ->whereHas('product', fn($p) => $p->where('active', true)))
            ->orderBy('name')->get(['id', 'name']);

        $korrekturgründe = BestandsaufnahmePosition::KORREKTURGRÜNDE;

        return view('admin.bestandsaufnahme.show', compact(
            'session', 'products', 'gezaehlt', 'lieferanten', 'lieferantId', 'filter', 'korrekturgründe',
        ));
    }

    /** POST /admin/bestandsaufnahme/{session}/position — AJAX */
    public function savePosition(BestandsaufnahmeSession $bestandsaufnahme, Request $request): JsonResponse
    {
        if ($bestandsaufnahme->isAbgeschlossen()) {
            return response()->json(['error' => 'Session ist abgeschlossen.'], 422);
        }

        $validated = $request->validate([
            'product_id'     => ['required', 'exists:products,id'],
            'korrekturgrund' => ['required', 'in:' . implode(',', array_keys(BestandsaufnahmePosition::KORREKTURGRÜNDE))],
            'kommentar'      => ['nullable', 'string', 'max:1000'],
            'eingaben'       => ['required', 'array', 'min:1'],
            'eingaben.*.verpackungseinheit_id' => ['nullable', 'exists:artikel_verpackungseinheiten,id'],
            'eingaben.*.menge_vpe'             => ['required', 'numeric', 'min:0'],
            'eingaben.*.faktor_basiseinheit'   => ['required', 'numeric', 'min:0.001'],
        ]);

        $product   = Product::findOrFail($validated['product_id']);
        $warehouse = $bestandsaufnahme->warehouse;

        $position = $this->service->savePosition(
            session:        $bestandsaufnahme,
            product:        $product,
            warehouse:      $warehouse,
            eingaben:       $validated['eingaben'],
            korrekturgrund: $validated['korrekturgrund'],
            kommentar:      $validated['kommentar'] ?? null,
            user:           $request->user(),
        );

        return response()->json([
            'success'                          => true,
            'gezaehlter_bestand_basiseinheit'  => $position->gezaehlter_bestand_basiseinheit,
            'differenz_basiseinheit'           => $position->differenz_basiseinheit,
            'gezaehlt_am'                      => $position->gezaehlt_am?->format('d.m.Y H:i'),
        ]);
    }

    /** POST /admin/bestandsaufnahme/{session}/pause */
    public function pause(BestandsaufnahmeSession $bestandsaufnahme): RedirectResponse
    {
        $this->service->pauseSession($bestandsaufnahme);
        return back()->with('success', 'Session pausiert.');
    }

    /** POST /admin/bestandsaufnahme/{session}/close */
    public function close(BestandsaufnahmeSession $bestandsaufnahme): RedirectResponse
    {
        $this->service->closeSession($bestandsaufnahme);
        return redirect()->route('admin.bestandsaufnahme.index')->with('success', 'Session abgeschlossen.');
    }
}
