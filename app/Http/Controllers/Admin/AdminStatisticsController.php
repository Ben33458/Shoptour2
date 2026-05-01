<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Statistics\PfandStatisticsService;
use App\Services\Statistics\PosStatisticsService;
use App\Services\Statistics\PurchasePlanningService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin statistics area — POS sales analysis and purchase planning.
 *
 * Modules:
 *   GET /admin/statistics/pos-top            — Top articles by KW (POS)
 *   GET /admin/statistics/purchase-planning  — Stock coverage / reorder list
 *   GET /admin/statistics/purchase-planning/export — CSV download
 *   GET /admin/statistics/warengruppen       — Category trend (last 12 KW)
 */
class AdminStatisticsController extends Controller
{
    public function __construct(
        private readonly PosStatisticsService      $posService,
        private readonly PurchasePlanningService   $planningService,
        private readonly PfandStatisticsService    $pfandService,
    ) {}

    // ── Modul 1: Top-Artikel je KW ────────────────────────────────────────────

    public function posTop(Request $request): View
    {
        [$currentYear, $currentWeek] = $this->posService->currentYearWeek();

        $year = (int) $request->input('year', $currentYear);
        $week = (int) $request->input('week', $currentWeek);
        $warengruppe = $request->input('warengruppe') ?: null;

        // Clamp week to valid range
        $week = max(1, min(53, $week));

        [$prevYear, $prevWeek]   = $this->posService->previousWeek($year, $week);
        [$lyYear,   $lyWeek]     = $this->posService->sameWeekLastYear($year, $week);

        $current  = $this->posService->topArtikelMapForWeek($year, $week, 50, $warengruppe);
        $previous = $this->posService->topArtikelMapForWeek($prevYear, $prevWeek, 50, $warengruppe);
        $lastYear = $this->posService->topArtikelMapForWeek($lyYear, $lyWeek, 50, $warengruppe);

        // Merge: all artnrs that appear in current week (sorted by current menge)
        $rows = collect($current)
            ->map(function ($row) use ($previous, $lastYear): object {
                $prevMenge = (float) ($previous[$row->artnr]?->menge ?? 0);
                $lyMenge   = (float) ($lastYear[$row->artnr]?->menge ?? 0);
                $diffPrev  = $row->menge - $prevMenge;
                $diffLy    = $row->menge - $lyMenge;
                $pctPrev   = $prevMenge > 0 ? round(($diffPrev / $prevMenge) * 100, 1) : null;
                $pctLy     = $lyMenge   > 0 ? round(($diffLy   / $lyMenge)   * 100, 1) : null;

                return (object) array_merge((array) $row, [
                    'prev_menge'  => $prevMenge,
                    'ly_menge'    => $lyMenge,
                    'diff_prev'   => $diffPrev,
                    'diff_ly'     => $diffLy,
                    'pct_prev'    => $pctPrev,
                    'pct_ly'      => $pctLy,
                ]);
            })
            ->sortByDesc('menge')
            ->values();

        $warengruppen = $this->posService->allWarengruppen();
        [$kwFrom, $kwTo] = $this->posService->kwDateRange($year, $week);

        return view('admin.statistics.pos_top', compact(
            'rows', 'year', 'week', 'warengruppe', 'warengruppen',
            'currentYear', 'currentWeek',
            'prevYear', 'prevWeek',
            'lyYear', 'lyWeek',
            'kwFrom', 'kwTo',
        ));
    }

    // ── Modul 2: Einkaufsplanung ──────────────────────────────────────────────

    public function purchasePlanning(Request $request): View
    {
        $warengruppe = $request->input('warengruppe') ?: null;
        $ampel       = $request->input('ampel') ?: null;

        $dataset = $this->planningService->dataset();

        if ($warengruppe !== null) {
            $dataset = $dataset->filter(fn ($r) => $r->warengruppe === $warengruppe);
        }
        if ($ampel !== null) {
            $dataset = $dataset->filter(fn ($r) => $r->ampel === $ampel);
        }

        $warengruppen = $dataset->pluck('warengruppe')->unique()->sort()->filter()->values()->all();

        return view('admin.statistics.purchase_planning', compact(
            'dataset', 'warengruppe', 'ampel', 'warengruppen',
        ));
    }

    public function exportPurchasePlanning(): StreamedResponse
    {
        $dataset = $this->planningService->dataset();
        $rows    = $this->planningService->toCsvRows($dataset);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM for Excel
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, 'einkaufsplanung-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ── Modul 4: Pfand-Statistik ──────────────────────────────────────────────

    public function pfandStatistik(Request $request): View
    {
        $weeksBack   = (int) $request->input('wochen', 24);
        $weeksBack   = max(4, min(52, $weeksBack));

        $gesamtsaldo = $this->pfandService->gesamtsaldo();
        $saldoItems  = $this->pfandService->saldoGesamt();
        $trend       = $this->pfandService->wochenTrend($weeksBack);

        // Date range shown in trend header
        $trendFrom = now()->startOfWeek()->subWeeks($weeksBack)->format('d.m.Y');
        $trendTo   = now()->endOfWeek()->format('d.m.Y');

        return view('admin.statistics.pfand', compact(
            'gesamtsaldo', 'saldoItems', 'trend', 'weeksBack', 'trendFrom', 'trendTo',
        ));
    }

    // ── Modul 5: Artikel-Detail ───────────────────────────────────────────────

    public function artikelDetail(Request $request, string $artnr): View
    {
        [$currentYear, $currentWeek] = $this->posService->currentYearWeek();

        // Article meta from the materialized table (latest available row)
        $meta = \Illuminate\Support\Facades\DB::table('stats_pos_daily')
            ->where('artnr', $artnr)
            ->orderByDesc('bon_date')
            ->select('name', 'warengruppe')
            ->first();

        if ($meta === null) {
            abort(404, 'Artikel nicht gefunden: ' . $artnr);
        }

        $trend   = $this->posService->artikelWeeklyTrend($artnr, 52);
        $lyMenge = $this->posService->artikelLastYearMenge($artnr, 52);

        // Averages
        $last4  = array_slice($trend, -4);
        $last8  = array_slice($trend, -8);
        $last13 = array_slice($trend, -13);
        $avg4w  = round(array_sum(array_column($last4,  'menge')) / 4,  1);
        $avg8w  = round(array_sum(array_column($last8,  'menge')) / 8,  1);
        $avg13w = round(array_sum(array_column($last13, 'menge')) / 13, 1);

        // Current week numbers
        $currentWeekMenge = collect($trend)->firstWhere('week', $currentWeek)?->menge ?? 0;
        $rank = $meta->warengruppe
            ? $this->posService->artikelRankInWarengruppe($artnr, $meta->warengruppe, $currentYear, $currentWeek)
            : null;

        // Stock
        $stock = \Illuminate\Support\Facades\DB::table('wawi_lagerbestand as lb')
            ->join('wawi_artikel as a', 'a.kArtikel', '=', 'lb.kArtikel')
            ->where('a.cArtNr', $artnr)
            ->select('lb.fVerfuegbar as bestand', 'a.cName as name')
            ->first();

        $bestand    = (float) ($stock?->bestand ?? 0);
        $reichweite = $avg4w > 0 ? round($bestand / $avg4w, 1) : null;

        // Catalog link (if product exists)
        $catalogProduct = \App\Models\Catalog\Product::where('artikelnummer', $artnr)->first();

        // ── Extended sections (only when catalog product is linked) ──────────
        $mhdBatches     = null;
        $stammsortiment = null;
        $recentOrders   = null;

        // MHD write-offs come from POS (customer K3475 / kKunde=618), not shop orders.
        // We query stats_pos_daily directly using the is_mhd_writeoff flag.
        $mhdWriteoffTotal = (float) \Illuminate\Support\Facades\DB::table('stats_pos_daily')
            ->where('artnr', $artnr)
            ->where('is_mhd_writeoff', 1)
            ->sum('menge');

        $mhdWriteoffLast52 = (float) \Illuminate\Support\Facades\DB::table('stats_pos_daily')
            ->where('artnr', $artnr)
            ->where('is_mhd_writeoff', 1)
            ->where('bon_date', '>=', now()->subWeeks(52)->format('Y-m-d'))
            ->sum('menge');

        if ($catalogProduct) {
            $mhdBatches = \App\Models\Procurement\ProductMhdBatch::with(['warehouse:id,name'])
                ->where('product_id', $catalogProduct->id)
                ->where('status', 'aktiv')
                ->orderBy('mhd')
                ->get();

            $stammsortiment = \App\Models\CustomerFavorite::with([
                    'customer:id,company_name,first_name,last_name,customer_number',
                ])
                ->where('product_id', $catalogProduct->id)
                ->orderByDesc('target_stock_units')
                ->get();

            $recentOrders = \App\Models\Orders\OrderItem::with([
                    'order:id,order_number,created_at,customer_id,status',
                    'order.customer:id,company_name,first_name,last_name,customer_number',
                ])
                ->where('product_id', $catalogProduct->id)
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }

        return view('admin.statistics.artikel', compact(
            'artnr', 'meta',
            'trend', 'lyMenge',
            'avg4w', 'avg8w', 'avg13w',
            'currentYear', 'currentWeek', 'currentWeekMenge',
            'rank', 'bestand', 'reichweite',
            'catalogProduct',
            'mhdBatches', 'stammsortiment', 'recentOrders',
            'mhdWriteoffTotal', 'mhdWriteoffLast52',
        ));
    }

    // ── Modul 6: MHD-Abschreibungen ──────────────────────────────────────────

    public function mhdAbschreibungen(Request $request): View
    {
        $weeksBack   = (int) $request->input('wochen', 12);
        $weeksBack   = max(4, min(104, $weeksBack));
        $warengruppe = $request->input('warengruppe') ?: null;

        $from = now()->startOfWeek()->subWeeks($weeksBack)->format('Y-m-d');
        $to   = now()->endOfWeek()->format('Y-m-d');

        // Base query joins wawi_artikel for EK/VK prices.
        // MHD write-offs are booked at €0 in POS — the real loss is menge × fEKNetto.
        $baseQuery = fn () => \Illuminate\Support\Facades\DB::table('stats_pos_daily as s')
            ->leftJoin('wawi_artikel as w', 'w.cArtNr', '=', 's.artnr')
            ->where('s.is_mhd_writeoff', 1)
            ->where('s.bon_date', '>=', $from)
            ->when($warengruppe, fn ($q) => $q->where('s.warengruppe', $warengruppe));

        // ── KW-Trend ─────────────────────────────────────────────────────────
        $trendRows = $baseQuery()
            ->selectRaw('YEAR(s.bon_date) AS yr, WEEK(s.bon_date,1) AS kw, SUM(s.menge) AS menge, SUM(s.menge * COALESCE(w.fEKNetto, 0)) AS ek_warenwert')
            ->groupByRaw('yr, kw')
            ->orderByRaw('yr, kw')
            ->get()
            ->keyBy(fn ($r) => sprintf('%04d-%02d', $r->yr, $r->kw));

        $kwLabels = $this->posService->weekLabels($weeksBack);
        $trend    = array_map(function (string $label) use ($trendRows) {
            [$yr, $kw] = [
                (int) substr($label, 0, 4),
                (int) substr($label, 7),
            ];
            $key = sprintf('%04d-%02d', $yr, $kw);
            $row = $trendRows[$key] ?? null;
            [$from, $to] = $this->posService->kwDateRange($yr, $kw);
            return (object) [
                'label'       => $label,
                'yr'          => $yr,
                'kw'          => $kw,
                'kwFrom'      => $from,
                'kwTo'        => $to,
                'menge'       => (float) ($row?->menge ?? 0),
                'ek_warenwert'=> (float) ($row?->ek_warenwert ?? 0),
            ];
        }, $kwLabels);

        // ── By Article ───────────────────────────────────────────────────────
        $byArtikel = $baseQuery()
            ->selectRaw('s.artnr, MAX(s.name) AS name, MAX(s.warengruppe) AS warengruppe,
                         SUM(s.menge) AS menge,
                         MAX(w.fEKNetto) AS ek_preis, MAX(w.fVKNetto) AS vk_preis,
                         SUM(s.menge * COALESCE(w.fEKNetto, 0)) AS ek_warenwert,
                         MIN(s.bon_date) AS first_date, MAX(s.bon_date) AS last_date')
            ->where('s.artnr', '!=', '')
            ->groupBy('s.artnr')
            ->orderByDesc('menge')
            ->get();

        // ── By Warengruppe ───────────────────────────────────────────────────
        $byWarengruppe = $baseQuery()
            ->selectRaw('s.warengruppe, SUM(s.menge) AS menge,
                         SUM(s.menge * COALESCE(w.fEKNetto, 0)) AS ek_warenwert,
                         COUNT(DISTINCT s.artnr) AS artikel_count')
            ->where('s.warengruppe', '!=', '')
            ->groupBy('s.warengruppe')
            ->orderByDesc('menge')
            ->get();

        // ── All-time totals (for KPI cards) ──────────────────────────────────
        $totalAll = \Illuminate\Support\Facades\DB::table('stats_pos_daily as s')
            ->leftJoin('wawi_artikel as w', 'w.cArtNr', '=', 's.artnr')
            ->where('s.is_mhd_writeoff', 1)
            ->when($warengruppe, fn ($q) => $q->where('s.warengruppe', $warengruppe))
            ->selectRaw('SUM(s.menge) AS menge, SUM(s.menge * COALESCE(w.fEKNetto, 0)) AS ek_warenwert, COUNT(DISTINCT s.artnr) AS artikel_count')
            ->first();

        $totalWindow = $baseQuery()
            ->selectRaw('SUM(s.menge) AS menge, SUM(s.menge * COALESCE(w.fEKNetto, 0)) AS ek_warenwert')
            ->first();

        // ── Warengruppen for filter ───────────────────────────────────────────
        $warengruppen = \Illuminate\Support\Facades\DB::table('stats_pos_daily')
            ->where('is_mhd_writeoff', 1)
            ->where('warengruppe', '!=', '')
            ->distinct()
            ->orderBy('warengruppe')
            ->pluck('warengruppe')
            ->all();

        return view('admin.statistics.mhd_abschreibungen', compact(
            'trend', 'byArtikel', 'byWarengruppe',
            'totalAll', 'totalWindow',
            'weeksBack', 'warengruppe', 'warengruppen',
            'from', 'to',
        ));
    }

    // ── Modul 3: Warengruppen-Trend ───────────────────────────────────────────

    public function warengruppen(): View
    {
        $weekLabels = $this->posService->weekLabels(12);
        $trend      = $this->posService->warengruppenTrend(12);

        // Sort warengruppen by total umsatz descending
        $sorted = $trend->map(fn ($kwData) => array_sum(array_column($kwData, 'umsatz')))
            ->sortDesc()
            ->keys()
            ->all();

        // Date range for first and last displayed week
        [$firstFrom] = $this->posService->kwDateRange(
            (int) substr($weekLabels[0], 0, 4),
            (int) substr($weekLabels[0], 7),
        );
        [, $lastTo] = $this->posService->kwDateRange(
            (int) substr($weekLabels[count($weekLabels) - 1], 0, 4),
            (int) substr($weekLabels[count($weekLabels) - 1], 7),
        );

        return view('admin.statistics.warengruppen', compact(
            'weekLabels', 'trend', 'sorted', 'firstFrom', 'lastTo',
        ));
    }
}
