<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Bestandsaufnahme;

use App\Http\Controllers\Controller;
use App\Models\Bestandsaufnahme\LadenhueterRegel;
use App\Models\Bestandsaufnahme\LadenhueterStatus;
use App\Models\Bestandsaufnahme\MhdRegel;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\Warengruppe;
use App\Models\Warehouse;
use App\Services\Bestandsaufnahme\LadenhueterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminLadenhueterController extends Controller
{
    public function __construct(
        private readonly LadenhueterService $ladenhueterService,
    ) {}

    /** GET /admin/ladenhueter */
    public function index(): View
    {
        $ladenhueter = $this->ladenhueterService->getLadenhueter();
        $regel        = LadenhueterRegel::where('aktiv', true)->first();
        $aktionen     = LadenhueterStatus::AKTIONEN;

        $statusByProduct = LadenhueterStatus::pluck('status', 'product_id');

        return view('admin.bestandsaufnahme.ladenhueter.index', compact('ladenhueter', 'regel', 'aktionen', 'statusByProduct'));
    }

    /** GET /admin/ladenhueter/regeln */
    public function regeln(): View
    {
        $ladenhueterRegel = LadenhueterRegel::first() ?? new LadenhueterRegel();
        $mhdRegeln        = MhdRegel::orderBy('bezug_typ')->orderBy('prioritaet', 'desc')->get();
        $warehouses       = Warehouse::where('active', true)->orderBy('name')->get(['id', 'name']);
        $categories       = Category::orderBy('name')->get(['id', 'name']);
        $warengruppen     = Warengruppe::orderBy('name')->get(['id', 'name']);
        $products         = Product::where('active', true)->orderBy('produktname')->get(['id', 'artikelnummer', 'produktname']);

        return view('admin.bestandsaufnahme.ladenhueter.regeln', compact(
            'ladenhueterRegel', 'mhdRegeln', 'warehouses', 'categories', 'warengruppen', 'products',
        ));
    }

    /** POST /admin/ladenhueter/regeln/ladenhueter */
    public function updateLadenhueterRegel(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tage_ohne_verkauf'           => ['required', 'integer', 'min:1'],
            'max_lagerdauer_tage'         => ['required', 'integer', 'min:1'],
            'max_bestandsreichweite_tage' => ['required', 'integer', 'min:1'],
        ]);

        $regel = LadenhueterRegel::first();
        if ($regel) {
            $regel->update($validated);
        } else {
            LadenhueterRegel::create(array_merge($validated, ['aktiv' => true]));
        }

        return back()->with('success', 'Ladenhüter-Regeln gespeichert.');
    }

    /** POST /admin/ladenhueter/regeln/mhd */
    public function storeMhdRegel(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bezug_typ'     => ['required', 'in:artikel,lager,kategorie,warengruppe,default'],
            'bezug_id'      => ['nullable', 'integer'],
            'modus'         => ['required', 'in:nie,optional,pflichtig'],
            'warnung_tage'  => ['required', 'integer', 'min:0'],
            'kritisch_tage' => ['required', 'integer', 'min:0'],
        ]);

        MhdRegel::updateOrCreate(
            [
                'bezug_typ' => $validated['bezug_typ'],
                'bezug_id'  => $validated['bezug_id'],
            ],
            array_merge($validated, ['aktiv' => true]),
        );

        return back()->with('success', 'MHD-Regel gespeichert.');
    }

    /** DELETE /admin/ladenhueter/regeln/mhd/{mhdRegel} */
    public function destroyMhdRegel(MhdRegel $mhdRegel): RedirectResponse
    {
        if ($mhdRegel->bezug_typ === 'default') {
            return back()->with('error', 'Default-Regel kann nicht gelöscht werden.');
        }
        $mhdRegel->delete();
        return back()->with('success', 'MHD-Regel gelöscht.');
    }

    /** POST /admin/ladenhueter/{product}/status */
    public function setStatus(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'status'      => ['required', 'in:' . implode(',', array_keys(LadenhueterStatus::AKTIONEN))],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'notiz'        => ['nullable', 'string', 'max:1000'],
        ]);

        LadenhueterStatus::updateOrCreate(
            [
                'product_id'  => $product->id,
                'warehouse_id' => $validated['warehouse_id'] ?? null,
            ],
            [
                'status'     => $validated['status'],
                'notiz'      => $validated['notiz'] ?? null,
                'gesetzt_von' => $request->user()->id,
                'gesetzt_am'  => now(),
            ],
        );

        return back()->with('success', 'Ladenhüter-Status gesetzt.');
    }
}
