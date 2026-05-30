<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductLeergut;
use App\Services\Orders\PfandCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin-Seite zur Zuweisung von Leergut-Typen zu Produkten.
 *
 * Befüllt die product_leergut-Tabelle manuell oder automatisch via
 * Pfand-Betrag-Muster: Pfand 5,10 € (510 Ct.) → Leergut-Art.-Nr. "1510"
 */
class AdminLeergutZuweisungController extends Controller
{
    public function __construct(
        private readonly PfandCalculator $pfandCalculator,
    ) {}

    /**
     * Produktliste mit aktuellem Leergut-Typ.
     */
    public function index(): View
    {
        $products = Product::with(['gebinde.pfandSet', 'leergut'])
            ->whereHas('gebinde', fn ($q) => $q->whereNotNull('pfand_set_id'))
            ->where('active', true)
            ->orderBy('artikelnummer')
            ->paginate(50);

        $leergutTypes = DB::table('wawi_artikel')
            ->where('cName', 'LIKE', 'Leergut%')
            ->orderBy('cName')
            ->get(['cArtNr', 'cName', 'fVKNetto']);

        return view('admin.leergut-zuweisungen.index', compact('products', 'leergutTypes'));
    }

    /**
     * Alle Produkte automatisch zuweisen (Pfand-Pattern: "1" + Pfand-Cent).
     */
    public function autoAssign(): RedirectResponse
    {
        $products = Product::with(['gebinde'])
            ->whereHas('gebinde', fn ($q) => $q->whereNotNull('pfand_set_id'))
            ->where('active', true)
            ->get();

        $leergutByArtNr = DB::table('wawi_artikel')
            ->where('cName', 'LIKE', 'Leergut%')
            ->get(['cArtNr', 'cName', 'fVKNetto'])
            ->keyBy('cArtNr');

        $companyId = auth()->user()->company_id;
        $assigned  = 0;

        foreach ($products as $product) {
            if (!$product->gebinde) {
                continue;
            }

            $pfandMilli = $this->pfandCalculator->totalForGebinde($product->gebinde);
            if ($pfandMilli <= 0) {
                continue;
            }

            // 5_100_000 milli-cent → 510 Cent → Art.-Nr. "1510"
            $pfandCents = (int) round($pfandMilli / 10_000);
            $artNr      = '1' . $pfandCents;
            $leergut    = $leergutByArtNr->get($artNr);

            if (!$leergut) {
                continue;
            }

            ProductLeergut::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'company_id'             => $companyId,
                    'leergut_art_nr'         => $leergut->cArtNr,
                    'leergut_name'           => $leergut->cName,
                    'unit_price_net_milli'   => (int) round($leergut->fVKNetto * 1_000_000),
                    'unit_price_gross_milli' => (int) round($leergut->fVKNetto * 1.19 * 1_000_000),
                    'synced_at'              => now(),
                ]
            );
            $assigned++;
        }

        return redirect()->route('admin.leergut-zuweisungen.index')
            ->with('success', "{$assigned} Produkte automatisch zugewiesen.");
    }

    /**
     * Einzelne Zuweisung für ein Produkt aktualisieren.
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'leergut_art_nr' => ['nullable', 'string', 'max:50'],
        ]);

        if (!$validated['leergut_art_nr']) {
            ProductLeergut::where('product_id', $product->id)->delete();

            return redirect()->route('admin.leergut-zuweisungen.index')
                ->with('success', 'Leergut-Zuweisung entfernt.');
        }

        $leergut = DB::table('wawi_artikel')
            ->where('cArtNr', $validated['leergut_art_nr'])
            ->first(['cArtNr', 'cName', 'fVKNetto']);

        if (!$leergut) {
            return redirect()->route('admin.leergut-zuweisungen.index')
                ->with('error', 'Leergut-Artikel nicht gefunden.');
        }

        ProductLeergut::updateOrCreate(
            ['product_id' => $product->id],
            [
                'company_id'             => auth()->user()->company_id,
                'leergut_art_nr'         => $leergut->cArtNr,
                'leergut_name'           => $leergut->cName,
                'unit_price_net_milli'   => (int) round($leergut->fVKNetto * 1_000_000),
                'unit_price_gross_milli' => (int) round($leergut->fVKNetto * 1.19 * 1_000_000),
                'synced_at'              => now(),
            ]
        );

        return redirect()->route('admin.leergut-zuweisungen.index')
            ->with('success', 'Zuweisung gespeichert.');
    }
}
