<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Bestandsaufnahme;

use App\Http\Controllers\Controller;
use App\Models\Bestandsaufnahme\ArtikelVerpackungseinheit;
use App\Models\Catalog\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminArtikelVerpackungseinheitController extends Controller
{
    /** GET /admin/artikel-verpackungseinheiten?product_id=X */
    public function index(Request $request): View
    {
        $productId = $request->integer('product_id') ?: null;

        $vpes = ArtikelVerpackungseinheit::with('product')
            ->when($productId, fn($q) => $q->where('product_id', $productId))
            ->orderBy('product_id')
            ->orderBy('sortierung')
            ->paginate(50)
            ->withQueryString();

        $products = Product::where('active', true)->orderBy('produktname')->get(['id', 'artikelnummer', 'produktname']);

        return view('admin.bestandsaufnahme.verpackungseinheiten.index', compact('vpes', 'products', 'productId'));
    }

    /** POST /admin/artikel-verpackungseinheiten */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id'         => ['required', 'exists:products,id'],
            'bezeichnung'        => ['required', 'string', 'max:100'],
            'faktor_basiseinheit' => ['required', 'numeric', 'min:0.001'],
            'ist_bestellbar'     => ['boolean'],
            'ist_zaehlbar'       => ['boolean'],
            'sortierung'         => ['integer', 'min:0'],
        ]);

        $validated['ist_bestellbar'] = $request->boolean('ist_bestellbar');
        $validated['ist_zaehlbar']   = $request->boolean('ist_zaehlbar');
        $validated['aktiv']          = true;

        ArtikelVerpackungseinheit::create($validated);

        return back()->with('success', 'Verpackungseinheit angelegt.');
    }

    /** PATCH /admin/artikel-verpackungseinheiten/{vpe} */
    public function update(Request $request, ArtikelVerpackungseinheit $artikelVerpackungseinheit): RedirectResponse
    {
        $validated = $request->validate([
            'bezeichnung'        => ['required', 'string', 'max:100'],
            'faktor_basiseinheit' => ['required', 'numeric', 'min:0.001'],
            'ist_bestellbar'     => ['boolean'],
            'ist_zaehlbar'       => ['boolean'],
            'aktiv'              => ['boolean'],
            'sortierung'         => ['integer', 'min:0'],
        ]);

        $validated['ist_bestellbar'] = $request->boolean('ist_bestellbar');
        $validated['ist_zaehlbar']   = $request->boolean('ist_zaehlbar');
        $validated['aktiv']          = $request->boolean('aktiv');

        $artikelVerpackungseinheit->update($validated);

        return back()->with('success', 'Verpackungseinheit aktualisiert.');
    }

    /** DELETE /admin/artikel-verpackungseinheiten/{vpe} */
    public function destroy(ArtikelVerpackungseinheit $artikelVerpackungseinheit): RedirectResponse
    {
        $artikelVerpackungseinheit->delete();
        return back()->with('success', 'Verpackungseinheit gelöscht.');
    }
}
