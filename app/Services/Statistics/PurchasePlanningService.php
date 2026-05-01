<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Computes stock coverage (Lagerreichweite) per article.
 *
 * Coverage = fVerfuegbar (current available stock) / Ø weekly POS sales
 *
 * Ampel:
 *   red    < 1 week
 *   yellow  1–2 weeks
 *   green  > 2 weeks
 *
 * Queries the pre-aggregated stats_pos_daily table for performance.
 */
class PurchasePlanningService
{
    public function __construct(
        private readonly PosStatisticsService $posService,
    ) {}

    /**
     * Returns the purchase planning dataset.
     * Filters to articles that have stock > 0 OR had POS sales in the last $salesWeeks weeks.
     *
     * @return Collection<int, object{
     *     artnr: string,
     *     name: string,
     *     warengruppe: string,
     *     bestand: float,
     *     avg_4w: float,
     *     avg_8w: float,
     *     reichweite: float|null,
     *     ampel: string
     * }>
     */
    public function dataset(int $salesWeeks = 8): Collection
    {
        $cacheKey = "stats.purchase_planning.w{$salesWeeks}";

        return Cache::remember($cacheKey, 1800, function () use ($salesWeeks) {
            $from4 = now()->startOfWeek()->subWeeks(4)->format('Y-m-d');
            $from8 = now()->startOfWeek()->subWeeks($salesWeeks)->format('Y-m-d');

            // ── POS sales per article (last 4 + 8 weeks) ────────────────────
            $sales4 = $this->salesByArtnr($from4);
            $sales8 = $this->salesByArtnr($from8);

            // ── Current stock from wawi_lagerbestand ─────────────────────────
            $stock = DB::table('wawi_lagerbestand as lb')
                ->join('wawi_artikel as a', 'a.kArtikel', '=', 'lb.kArtikel')
                ->select('a.cArtNr as artnr', 'a.cName as name', 'lb.fVerfuegbar as bestand')
                ->get()
                ->keyBy('artnr');

            // ── Merge ────────────────────────────────────────────────────────
            $artnrs = collect(array_unique(array_merge(
                $stock->keys()->all(),
                array_keys($sales8),
            )));

            return $artnrs->map(function (string $artnr) use ($stock, $sales4, $sales8) {
                $bestand = (float) ($stock[$artnr]?->bestand ?? 0);
                $total4  = (float) ($sales4[$artnr]['menge'] ?? 0);
                $total8  = (float) ($sales8[$artnr]['menge'] ?? 0);

                $avg4 = $total4 / 4;
                $avg8 = $total8 / 8;

                $reichweite = $avg4 > 0 ? ($bestand / $avg4) : null;

                return (object) [
                    'artnr'       => $artnr,
                    'name'        => $stock[$artnr]?->name ?? $sales8[$artnr]['name'] ?? $sales4[$artnr]['name'] ?? $artnr,
                    'warengruppe' => $sales8[$artnr]['warengruppe'] ?? $sales4[$artnr]['warengruppe'] ?? '',
                    'bestand'     => $bestand,
                    'avg_4w'      => round($avg4, 2),
                    'avg_8w'      => round($avg8, 2),
                    'reichweite'  => $reichweite !== null ? round($reichweite, 1) : null,
                    'ampel'       => $this->ampel($reichweite),
                ];
            })
            ->filter(fn ($r) => $r->bestand > 0 || $r->avg_8w > 0)
            ->sortBy(fn ($r) => $r->reichweite ?? PHP_INT_MAX)
            ->values();
        });
    }

    /**
     * Build a StreamedResponse for CSV download.
     */
    public function toCsvRows(Collection $dataset): \Generator
    {
        yield ['Art.-Nr.', 'Artikel', 'Warengruppe', 'Bestand', 'Ø 4W/Woche', 'Ø 8W/Woche', 'Reichweite (Wo.)', 'Ampel'];

        foreach ($dataset as $row) {
            yield [
                $row->artnr,
                $row->name,
                $row->warengruppe,
                number_format($row->bestand, 2, ',', '.'),
                number_format($row->avg_4w, 2, ',', '.'),
                number_format($row->avg_8w, 2, ',', '.'),
                $row->reichweite !== null ? number_format($row->reichweite, 1, ',', '.') : '',
                $row->ampel,
            ];
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Aggregated POS quantity + article meta per article number since $from.
     * Reads from stats_pos_daily — avoids full wawi_ table scan.
     *
     * @return array<string, array{menge:float, name:string, warengruppe:string}>
     */
    private function salesByArtnr(string $from): array
    {
        $rows = DB::table('stats_pos_daily')
            ->selectRaw('artnr, MAX(name) AS name, MAX(warengruppe) AS warengruppe, SUM(menge) AS menge')
            ->where('bon_date', '>=', $from)
            ->where('is_pfand', 0)
            ->where('is_leergut', 0)
            ->where('is_mhd_writeoff', 0)
            ->where('artnr', '!=', '')
            ->groupBy('artnr')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->artnr] = [
                'menge'       => (float) $row->menge,
                'name'        => (string) $row->name,
                'warengruppe' => (string) $row->warengruppe,
            ];
        }

        return $result;
    }

    private function ampel(?float $reichweite): string
    {
        if ($reichweite === null) {
            return 'grau';
        }
        if ($reichweite < 1) {
            return 'rot';
        }
        if ($reichweite < 2) {
            return 'gelb';
        }

        return 'grün';
    }
}
