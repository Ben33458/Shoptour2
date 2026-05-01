<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductBarcode;
use App\Models\SourceMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CatalogOverviewController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));
        $filter = $request->input('filter', 'all');

        // ── 1. Local products ──────────────────────────────────────────────
        $localProducts = Product::with(['category', 'warengruppe'])
            ->select([
                'id', 'artikelnummer', 'produktname', 'base_price_gross_milli',
                'availability_mode', 'show_in_shop', 'category_id', 'warengruppe_id',
                'ninox_artikel_id', 'wawi_artikel_id', 'active',
            ])
            ->get();

        // ── 2. Batch-load ninox rows for local products that have a ninox link
        $localNinoxIntIds = $localProducts
            ->whereNotNull('ninox_artikel_id')
            ->pluck('ninox_artikel_id')
            ->unique()
            ->values()
            ->toArray();

        $ninoxRowsForLocal = count($localNinoxIntIds) > 0
            ? DB::table('ninox_marktbestand')
                ->whereIn('ninox_id', $localNinoxIntIds)
                ->select('ninox_id', 'artnummer', 'artnrkolabrikasten', 'artikelname', 'vk_brutto_markt', 'vk_brutto_kolabri', 'ean')
                ->get()
                ->keyBy('ninox_id')
            : collect();

        // ── 3. Confirmed/auto source_match sets ────────────────────────────
        $ninoxLocalIds = SourceMatch::where('entity_type', SourceMatch::ENTITY_PRODUCT)
            ->where('source', SourceMatch::SOURCE_NINOX)
            ->whereIn('status', [SourceMatch::STATUS_CONFIRMED, SourceMatch::STATUS_AUTO])
            ->whereNotNull('local_id')
            ->pluck('local_id')
            ->flip()
            ->all();

        $wawiLocalIds = SourceMatch::where('entity_type', SourceMatch::ENTITY_PRODUCT)
            ->where('source', 'wawi')
            ->whereIn('status', [SourceMatch::STATUS_CONFIRMED, SourceMatch::STATUS_AUTO])
            ->whereNotNull('local_id')
            ->pluck('local_id')
            ->flip()
            ->all();

        // ── 4. Linked ninox/wawi source_ids (to exclude from unlinked lists)
        $linkedNinoxSourceIds = SourceMatch::where('entity_type', SourceMatch::ENTITY_PRODUCT)
            ->where('source', SourceMatch::SOURCE_NINOX)
            ->whereIn('status', [SourceMatch::STATUS_CONFIRMED, SourceMatch::STATUS_AUTO])
            ->whereNotNull('local_id')
            ->pluck('source_id')
            ->toArray();

        $directNinoxIds = $localProducts
            ->whereNotNull('ninox_artikel_id')
            ->pluck('ninox_artikel_id')
            ->map(fn ($v) => (string) $v)
            ->toArray();

        $allLinkedNinoxIds = array_unique(array_merge($linkedNinoxSourceIds, $directNinoxIds));

        $linkedWawiSourceIds = SourceMatch::where('entity_type', SourceMatch::ENTITY_PRODUCT)
            ->where('source', 'wawi')
            ->whereIn('status', [SourceMatch::STATUS_CONFIRMED, SourceMatch::STATUS_AUTO])
            ->whereNotNull('local_id')
            ->pluck('source_id')
            ->toArray();

        $directWawiIds = $localProducts
            ->whereNotNull('wawi_artikel_id')
            ->pluck('wawi_artikel_id')
            ->map(fn ($v) => (string) $v)
            ->toArray();

        $allLinkedWawiIds = array_unique(array_merge($linkedWawiSourceIds, $directWawiIds));

        // ── 5. Unlinked Ninox records ──────────────────────────────────────
        $unlinkedNinox = DB::table('ninox_marktbestand')
            ->when(
                count($allLinkedNinoxIds) > 0,
                fn ($q) => $q->whereNotIn(DB::raw('CAST(ninox_id AS CHAR)'), $allLinkedNinoxIds)
            )
            ->select('ninox_id', 'artnummer', 'artnrkolabrikasten', 'artikelname', 'vk_brutto_kolabri', 'vk_brutto_markt', 'ean')
            ->get();

        // ── 6. Unlinked WaWi records ───────────────────────────────────────
        $unlinkedWawi = DB::table('wawi_artikel')
            ->when(
                count($allLinkedWawiIds) > 0,
                fn ($q) => $q->whereNotIn(DB::raw('CAST(kArtikel AS CHAR)'), $allLinkedWawiIds)
            )
            ->select('kArtikel', 'cArtNr', 'cName', 'fVKNetto', 'cAktiv')
            ->get();

        // ── 7. Build unified rows ──────────────────────────────────────────
        $rows = collect();

        foreach ($localProducts as $p) {
            $hasNinox = ($p->ninox_artikel_id !== null) || isset($ninoxLocalIds[$p->id]);
            $hasWawi  = ($p->wawi_artikel_id !== null)  || isset($wawiLocalIds[$p->id]);
            $ninoxRow = $p->ninox_artikel_id !== null ? ($ninoxRowsForLocal[$p->ninox_artikel_id] ?? null) : null;

            // Build ninox_data for the modal (fall back to product data if ninox row missing)
            $ninoxData = $p->ninox_artikel_id !== null
                ? ($ninoxRow ? [
                    'artikelname'     => (string) ($ninoxRow->artikelname ?? ''),
                    'artnummer'       => (string) ($ninoxRow->artnrkolabrikasten ?? $ninoxRow->artnummer ?? ''),
                    'ean'             => (string) ($ninoxRow->ean ?? ''),
                    'vk_brutto_markt' => (float) ($ninoxRow->vk_brutto_markt ?? $ninoxRow->vk_brutto_kolabri ?? 0),
                ] : [
                    'artikelname'     => (string) $p->produktname,
                    'artnummer'       => (string) $p->artikelnummer,
                    'ean'             => '',
                    'vk_brutto_markt' => round($p->base_price_gross_milli / 1_000_000, 2),
                ])
                : null;

            $rows->push([
                'source'        => 'local',
                'local_id'      => $p->id,
                'artnr'         => (string) $p->artikelnummer,
                'name'          => (string) $p->produktname,
                'vk_brutto'     => $p->base_price_gross_milli > 0
                    ? round($p->base_price_gross_milli / 1_000_000, 2)
                    : null,
                'availability'  => $p->availability_mode,
                'show_in_shop'  => (bool) $p->show_in_shop,
                'warengruppe'   => $p->warengruppe?->name,
                'kategorie'     => $p->category?->name,
                'has_local'     => true,
                'has_ninox'     => $hasNinox,
                'has_wawi'      => $hasWawi,
                'ninox_id'      => $p->ninox_artikel_id !== null ? (string) $p->ninox_artikel_id : null,
                'wawi_id'       => $p->wawi_artikel_id,
                'ninox_data'    => $ninoxData,
            ]);
        }

        foreach ($unlinkedNinox as $n) {
            $rows->push([
                'source'        => 'ninox',
                'local_id'      => null,
                'artnr'         => (string) ($n->artnrkolabrikasten ?? $n->artnummer ?? ''),
                'name'          => (string) ($n->artikelname ?? ''),
                'vk_brutto'     => isset($n->vk_brutto_kolabri) && $n->vk_brutto_kolabri > 0
                    ? (float) $n->vk_brutto_kolabri
                    : null,
                'availability'  => null,
                'show_in_shop'  => null,
                'warengruppe'   => null,
                'kategorie'     => null,
                'has_local'     => false,
                'has_ninox'     => true,
                'has_wawi'      => false,
                'ninox_id'      => (string) $n->ninox_id,
                'wawi_id'       => null,
                'ninox_data'    => [
                    'artikelname'     => (string) ($n->artikelname ?? ''),
                    'artnummer'       => (string) ($n->artnrkolabrikasten ?? $n->artnummer ?? ''),
                    'ean'             => (string) ($n->ean ?? ''),
                    'vk_brutto_markt' => (float) ($n->vk_brutto_markt ?? $n->vk_brutto_kolabri ?? 0),
                ],
            ]);
        }

        foreach ($unlinkedWawi as $w) {
            $bruttoApprox = isset($w->fVKNetto) ? round((float) $w->fVKNetto * 1.19, 2) : null;

            $rows->push([
                'source'        => 'wawi',
                'local_id'      => null,
                'artnr'         => (string) ($w->cArtNr ?? ''),
                'name'          => (string) ($w->cName ?? ''),
                'vk_brutto'     => $bruttoApprox,
                'availability'  => null,
                'show_in_shop'  => null,
                'warengruppe'   => null,
                'kategorie'     => null,
                'has_local'     => false,
                'has_ninox'     => false,
                'has_wawi'      => true,
                'ninox_id'      => null,
                'wawi_id'       => (int) $w->kArtikel,
                'ninox_data'    => null,
            ]);
        }

        // ── 8. Stats (before any filtering/searching) ──────────────────────
        $totalCount     = $rows->count();
        $unlinkedCount  = $rows->filter(fn ($r) => ! ($r['has_local'] && $r['has_ninox'] && $r['has_wawi']))->count();
        $localOnlyCount = $rows->filter(fn ($r) => $r['has_local'] && ! $r['has_ninox'] && ! $r['has_wawi'])->count();

        $stats = [
            'total'      => $totalCount,
            'local_only' => $localOnlyCount,
            'unlinked'   => $unlinkedCount,
        ];

        // ── 9. Search ──────────────────────────────────────────────────────
        if ($search !== '') {
            $lower = mb_strtolower($search);
            $rows  = $rows->filter(function (array $r) use ($lower): bool {
                return str_contains(mb_strtolower($r['artnr']), $lower)
                    || str_contains(mb_strtolower($r['name']), $lower);
            });
        }

        // ── 10. Filter counts (after search, before filter) ────────────────
        $filterCounts = [
            'all'        => $rows->count(),
            'unlinked'   => $rows->filter(fn ($r) => ! ($r['has_local'] && $r['has_ninox'] && $r['has_wawi']))->count(),
            'local_only' => $rows->filter(fn ($r) => $r['has_local'] && ! $r['has_ninox'] && ! $r['has_wawi'])->count(),
            'ninox_only' => $rows->filter(fn ($r) => $r['source'] === 'ninox')->count(),
            'wawi_only'  => $rows->filter(fn ($r) => $r['source'] === 'wawi')->count(),
        ];

        // ── 11. Apply filter ───────────────────────────────────────────────
        $rows = match ($filter) {
            'unlinked'    => $rows->filter(fn ($r) => ! ($r['has_local'] && $r['has_ninox'] && $r['has_wawi'])),
            'local_only'  => $rows->filter(fn ($r) => $r['has_local'] && ! $r['has_ninox'] && ! $r['has_wawi']),
            'ninox_only'  => $rows->filter(fn ($r) => $r['source'] === 'ninox'),
            'wawi_only'   => $rows->filter(fn ($r) => $r['source'] === 'wawi'),
            default       => $rows,
        };

        // ── 12. Sort ───────────────────────────────────────────────────────
        $rows = $rows->sortBy([
            fn ($a, $b) => ['local' => 0, 'ninox' => 1, 'wawi' => 2][$a['source']]
                         <=> ['local' => 0, 'ninox' => 1, 'wawi' => 2][$b['source']],
            fn ($a, $b) => mb_strtolower($a['name']) <=> mb_strtolower($b['name']),
        ])->values();

        // ── 13. Paginate ───────────────────────────────────────────────────
        $page      = (int) $request->input('page', 1);
        $perPage   = self::PER_PAGE;
        $paginated = new LengthAwarePaginator(
            $rows->slice(($page - 1) * $perPage, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('admin.catalog.overview', compact(
            'paginated', 'stats', 'filterCounts', 'search', 'filter'
        ));
    }

    /** POST /admin/catalog/quick-create — AJAX */
    public function quickCreate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ninox_id' => ['nullable', 'string'],
            'wawi_id'  => ['nullable', 'integer'],
        ]);

        $ninoxId = $data['ninox_id'] ?? null;
        $wawiId  = $data['wawi_id']  ?? null;

        if (!$ninoxId && !$wawiId) {
            return response()->json(['error' => 'ninox_id oder wawi_id erforderlich.'], 422);
        }

        // Default tax rate 19%
        $taxRate = DB::table('tax_rates')->where('rate_basis_points', 1900)->first();
        if (!$taxRate) {
            return response()->json(['error' => 'Steuersatz 19% nicht gefunden.'], 500);
        }

        $produktname   = '';
        $artikelnummer = '';
        $ean           = '';
        $bruttoFloat   = 0.0;
        $ninoxRow      = null;

        if ($ninoxId) {
            $ninoxRow = DB::table('ninox_marktbestand')->where('ninox_id', $ninoxId)->first();
            if (!$ninoxRow) {
                return response()->json(['error' => 'Ninox-Artikel nicht gefunden.'], 404);
            }
            $produktname   = (string) ($ninoxRow->artikelname      ?? '');
            $artikelnummer = (string) ($ninoxRow->artnrkolabrikasten ?? $ninoxRow->artnummer ?? '');
            $ean           = (string) ($ninoxRow->ean               ?? '');
            $bruttoFloat   = (float)  ($ninoxRow->vk_brutto_kolabri ?? $ninoxRow->vk_brutto_markt ?? 0);

            $alreadyLinked = SourceMatch::where('entity_type', SourceMatch::ENTITY_PRODUCT)
                ->where('source', SourceMatch::SOURCE_NINOX)
                ->where('source_id', $ninoxId)
                ->where('status', SourceMatch::STATUS_CONFIRMED)
                ->whereNotNull('local_id')
                ->exists();

            if ($alreadyLinked) {
                return response()->json(['error' => 'Dieser Ninox-Artikel ist bereits verknüpft.'], 422);
            }
        } else {
            $wawiRow = DB::table('wawi_artikel')->where('kArtikel', $wawiId)->first();
            if (!$wawiRow) {
                return response()->json(['error' => 'WaWi-Artikel nicht gefunden.'], 404);
            }
            $produktname   = (string) ($wawiRow->cName  ?? '');
            $artikelnummer = (string) ($wawiRow->cArtNr ?? '');
            $bruttoFloat   = isset($wawiRow->fVKNetto)
                ? round((float) $wawiRow->fVKNetto * 1.19, 2)
                : 0.0;
        }

        if (!$produktname || !$artikelnummer) {
            return response()->json(['error' => 'Produktname oder Artikelnummer fehlen in den Quelldaten.'], 422);
        }

        if (Product::where('artikelnummer', $artikelnummer)->exists()) {
            return response()->json([
                'error' => 'Artikelnummer „' . $artikelnummer . '" ist bereits vergeben.',
            ], 422);
        }

        $taxFactor  = 1 + ($taxRate->rate_basis_points / 10000);
        $netMilli   = (int) round(($bruttoFloat / $taxFactor) * 1_000_000);
        $grossMilli = (int) round($bruttoFloat * 1_000_000);

        $base = Str::slug($produktname) ?: 'produkt';
        $slug = $base;
        $n    = 2;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $n++;
        }

        $product = Product::create([
            'artikelnummer'          => $artikelnummer,
            'slug'                   => $slug,
            'produktname'            => $produktname,
            'tax_rate_id'            => $taxRate->id,
            'base_price_net_milli'   => $netMilli,
            'base_price_gross_milli' => $grossMilli,
            'availability_mode'      => Product::AVAILABILITY_AVAILABLE,
            'active'                 => true,
            'show_in_shop'           => true,
            'is_bundle'              => false,
            'ninox_artikel_id'       => $ninoxId ? (int) $ninoxId : null,
            'wawi_artikel_id'        => $wawiId,
        ]);

        $eanClean = trim($ean);
        if ($eanClean !== '' && $eanClean !== '0' && !ProductBarcode::where('barcode', $eanClean)->exists()) {
            ProductBarcode::create([
                'product_id'   => $product->id,
                'barcode'      => $eanClean,
                'barcode_type' => 'EAN-13',
                'is_primary'   => true,
            ]);
        }

        if ($ninoxId) {
            SourceMatch::updateOrCreate(
                [
                    'entity_type' => SourceMatch::ENTITY_PRODUCT,
                    'source'      => SourceMatch::SOURCE_NINOX,
                    'source_id'   => $ninoxId,
                ],
                [
                    'local_id'        => $product->id,
                    'status'          => SourceMatch::STATUS_CONFIRMED,
                    'matched_by'      => $request->user()->id,
                    'source_snapshot' => $ninoxRow ? (array) $ninoxRow : [],
                    'diff_at_match'   => [],
                    'confirmed_at'    => now(),
                ]
            );
        }

        return response()->json([
            'success'      => true,
            'product_id'   => $product->id,
            'produktname'  => $product->produktname,
            'artikelnummer'=> $product->artikelnummer,
        ]);
    }
}
