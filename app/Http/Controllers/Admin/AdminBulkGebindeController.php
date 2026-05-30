<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Gebinde;
use App\Models\Catalog\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Bulk editor for gebinde_id on products — lets admin assign Gebinde/Pfand to many products at once.
 */
class AdminBulkGebindeController extends Controller
{
    public function index(Request $request): View
    {
        $filter   = $request->input('filter', 'missing');
        $search   = trim((string) $request->input('search', ''));
        $wgFilter = $request->input('wg', '');

        $query = Product::with(['warengruppe', 'gebinde.pfandSet'])
            ->orderBy('warengruppe_id')
            ->orderBy('produktname');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('produktname', 'like', '%' . $search . '%')
                  ->orWhere('artikelnummer', 'like', '%' . $search . '%');
            });
        }

        if ($wgFilter !== '') {
            $query->where('warengruppe_id', (int) $wgFilter);
        }

        if ($filter === 'missing') {
            $query->whereNull('gebinde_id');
        } elseif ($filter === 'set') {
            $query->whereNotNull('gebinde_id');
        }

        $products = $query->paginate(100)->withQueryString();

        $baseQuery = Product::query();
        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('produktname', 'like', '%' . $search . '%')
                  ->orWhere('artikelnummer', 'like', '%' . $search . '%');
            });
        }
        if ($wgFilter !== '') {
            $baseQuery->where('warengruppe_id', (int) $wgFilter);
        }

        $filterCounts = [
            'all'     => (clone $baseQuery)->count(),
            'missing' => (clone $baseQuery)->whereNull('gebinde_id')->count(),
            'set'     => (clone $baseQuery)->whereNotNull('gebinde_id')->count(),
        ];

        $warengruppen = DB::table('warengruppen')->orderBy('name')->get();

        $gebindeOptions = Gebinde::with('pfandSet')
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $autoPreview = $this->buildAutoPreview();

        return view('admin.products.bulk-gebinde', compact(
            'products', 'filter', 'search', 'wgFilter',
            'filterCounts', 'warengruppen', 'gebindeOptions',
            'autoPreview'
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->input('gebinde', []);
        $updated = 0;

        foreach ($data as $productId => $gebindeId) {
            $productId = (int) $productId;
            if ($productId <= 0) {
                continue;
            }

            if ($gebindeId === '' || $gebindeId === null || $gebindeId === '0') {
                Product::where('id', $productId)->update(['gebinde_id' => null]);
            } else {
                $gId = (int) $gebindeId;
                if (Gebinde::where('id', $gId)->exists()) {
                    Product::where('id', $productId)->update(['gebinde_id' => $gId]);
                    $updated++;
                }
            }
        }

        Cache::flush();

        return redirect()->back()->with('success', $updated . ' Produkte aktualisiert.');
    }

    /**
     * Preview: how many products would be auto-assigned via WaWi Pfandbetrag rule.
     */
    public function autoAssignPreview(): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->buildAutoPreview());
    }

    /**
     * Execute auto-assignment: set gebinde_id on products that have no gebinde_id yet.
     * Two sources:
     *   1. WaWi "Pfandbetrag" fWertDecimal (EUR) → milli-cent = value × 1_000_000
     *   2. WaWi "PfandArtNr" nWertInt (Eurocent) → milli-cent = value × 10_000
     * Only assigns products currently missing a gebinde_id (never overwrites).
     */
    public function autoAssign(Request $request): RedirectResponse
    {
        $dryRun = $request->boolean('dry_run', false);

        $gebindeByMilli = $this->buildGebindeByMilliMap();

        $pfandAttrs = DB::table('wawi_artikel_attribute')
            ->where('cAttributName', 'Pfandbetrag')
            ->whereRaw('fWertDecimal > 0')
            ->select('kArtikel', 'fWertDecimal')
            ->get()
            ->keyBy('kArtikel');

        // Secondary source: PfandArtNr.nWertInt stores Pfand in Eurocent
        $pfandArtNrAttrs = DB::table('wawi_artikel_attribute')
            ->where('cAttributName', 'PfandArtNr')
            ->whereRaw('nWertInt > 0')
            ->select('kArtikel', 'nWertInt')
            ->get()
            ->keyBy('kArtikel');

        $candidates = DB::table('products')
            ->whereNull('gebinde_id')
            ->whereNotNull('wawi_artikel_id')
            ->where('wawi_artikel_id', '>', 0)
            ->select('id', 'wawi_artikel_id')
            ->get();

        $assigned = 0;
        $unmatched = [];

        foreach ($candidates as $p) {
            $milliVal = null;

            $attr = $pfandAttrs[$p->wawi_artikel_id] ?? null;
            if ($attr) {
                $milliVal = (int) round($attr->fWertDecimal * 1_000_000);
            } else {
                $artNr = $pfandArtNrAttrs[$p->wawi_artikel_id] ?? null;
                if ($artNr) {
                    $milliVal = self::PFAND_ARTNR_MILLI_OVERRIDES[(int) $artNr->nWertInt]
                        ?? (int) $artNr->nWertInt * 10_000;
                }
            }

            if ($milliVal === null) {
                continue;
            }

            $gId = $gebindeByMilli[$milliVal] ?? null;

            if (! $gId) {
                $eurKey = number_format($milliVal / 1_000_000, 2);
                $unmatched[$eurKey] = ($unmatched[$eurKey] ?? 0) + 1;
                continue;
            }

            if (! $dryRun) {
                DB::table('products')->where('id', $p->id)->update(['gebinde_id' => $gId]);
            }
            $assigned++;
        }

        if (! $dryRun) {
            Cache::flush();
        }

        $unmatchedInfo = '';
        if (! empty($unmatched)) {
            $parts = array_map(fn ($cnt, $eur) => "{$cnt}× {$eur}€", array_values($unmatched), array_keys($unmatched));
            $unmatchedInfo = ' Kein passendes Gebinde für: ' . implode(', ', $parts) . '.';
        }

        return redirect()->route('admin.products.bulk-gebinde')
            ->with('success', $assigned . ' Produkte automatisch zugewiesen.' . $unmatchedInfo);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * Exceptional mappings: WaWi Pfandbetrag (in Milli-EUR) → Gebinde ID.
     * Used when the WaWi deposit total doesn't equal the Gebinde total
     * (e.g. barrels all share one Fass-Gebinde regardless of keg size).
     */
    private const PFAND_OVERRIDES = [
        30_000_000 => 72, // 30,00 € Fass → Pfand Faß 30,00 €
        50_000_000 => 70, // 50,00 € Fass → Pfand Faß (10,00 €)
         5_700_000 => 57, // 5,70 € (z.B. Viva Gourmet 12×0,7l) → Kasten 12×15ct = 3,30 €
    ];

    /**
     * WaWi PfandArtNr.nWertInt → override milli-cent value.
     *
     * Normally nWertInt is the Pfand amount in Eurocent (×10_000 = milli-cent).
     * Exception: nWertInt=399 is used for Fass products as a workaround because
     * article number 300 (3,00 € Pfand) and 3000 are already taken — so the
     * actual Fass deposit of 30,00 € must be remapped explicitly here.
     */
    private const PFAND_ARTNR_MILLI_OVERRIDES = [
        399 => 30_000_000, // Fass-Sondernummer → 30,00 € Fass
    ];

    private function buildGebindeByMilliMap(): array
    {
        $components = DB::table('pfand_items as pi')
            ->join('pfand_set_components as psc', 'psc.pfand_item_id', '=', 'pi.id')
            ->select('psc.pfand_set_id', 'pi.wert_brutto_milli', 'psc.qty')
            ->get();

        $setTotals = [];
        foreach ($components as $c) {
            $setTotals[$c->pfand_set_id] = ($setTotals[$c->pfand_set_id] ?? 0)
                + ($c->wert_brutto_milli * $c->qty);
        }

        $map = [];
        $gebinde = DB::table('gebinde')
            ->whereNotNull('pfand_set_id')
            ->where('active', true)
            ->select('id', 'pfand_set_id')
            ->get();

        foreach ($gebinde as $g) {
            $milli = $setTotals[$g->pfand_set_id] ?? null;
            if ($milli !== null) {
                $map[(int) $milli] = $g->id;
            }
        }

        // Apply exception overrides (barrel types, special packs, etc.)
        foreach (self::PFAND_OVERRIDES as $milli => $gId) {
            $map[$milli] = $gId;
        }

        return $map;
    }

    private function buildAutoPreview(): array
    {
        $gebindeByMilli = $this->buildGebindeByMilliMap();

        $pfandAttrs = DB::table('wawi_artikel_attribute')
            ->where('cAttributName', 'Pfandbetrag')
            ->whereRaw('fWertDecimal > 0')
            ->select('kArtikel', 'fWertDecimal')
            ->get()
            ->keyBy('kArtikel');

        $pfandArtNrAttrs = DB::table('wawi_artikel_attribute')
            ->where('cAttributName', 'PfandArtNr')
            ->whereRaw('nWertInt > 0')
            ->select('kArtikel', 'nWertInt')
            ->get()
            ->keyBy('kArtikel');

        $candidateRows = DB::table('products')
            ->whereNull('gebinde_id')
            ->whereNotNull('wawi_artikel_id')
            ->where('wawi_artikel_id', '>', 0)
            ->select('id', 'wawi_artikel_id')
            ->get();

        $assignable = 0;
        $unmatched  = [];
        $noPfand    = 0;

        foreach ($candidateRows as $p) {
            $milliVal = null;

            $attr = $pfandAttrs[$p->wawi_artikel_id] ?? null;
            if ($attr) {
                $milliVal = (int) round($attr->fWertDecimal * 1_000_000);
            } else {
                $artNr = $pfandArtNrAttrs[$p->wawi_artikel_id] ?? null;
                if ($artNr) {
                    $milliVal = (int) $artNr->nWertInt * 10_000;
                }
            }

            if ($milliVal === null) {
                $noPfand++;
                continue;
            }

            $gId = $gebindeByMilli[$milliVal] ?? null;
            if ($gId) {
                $assignable++;
            } else {
                $eurKey = number_format($milliVal / 1_000_000, 2) . ' €';
                $unmatched[$eurKey] = ($unmatched[$eurKey] ?? 0) + 1;
            }
        }

        return [
            'total'      => $candidateRows->count(),
            'assignable' => $assignable,
            'no_pfand'   => $noPfand,
            'unmatched'  => $unmatched,
        ];
    }
}
