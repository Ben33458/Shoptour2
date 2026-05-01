<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates POS (Kassenpositionen) statistics from the materialized
 * stats_pos_daily table (populated by `php artisan stats:refresh-pos`).
 *
 * Querying the pre-aggregated table avoids full-table STR_TO_DATE() scans
 * on the 1M-row wawi_dbo_pos_bonposition table.
 */
class PosStatisticsService
{
    // ── Top articles ─────────────────────────────────────────────────────────

    /**
     * Returns top-N articles for a given ISO week.
     * Cached 30 minutes per KW.
     *
     * @return Collection<int, object{artnr:string, name:string, warengruppe:string, menge:float, umsatz:float}>
     */
    public function topArtikelForWeek(
        int     $year,
        int     $week,
        int     $limit       = 50,
        ?string $warengruppe = null,
    ): Collection {
        $cacheKey = "stats.pos_top.{$year}w{$week}.wg" . ($warengruppe ?? 'all') . ".n{$limit}";

        return Cache::remember($cacheKey, 1800, function () use ($year, $week, $limit, $warengruppe) {
            $monday = $this->mondayOfYearWeek($year, $week)->format('Y-m-d');
            $sunday = $this->mondayOfYearWeek($year, $week)->addDays(6)->format('Y-m-d');

            $query = DB::table('stats_pos_daily')
                ->selectRaw('artnr, MAX(name) AS name, MAX(warengruppe) AS warengruppe, SUM(menge) AS menge, SUM(umsatz) AS umsatz')
                ->whereBetween('bon_date', [$monday, $sunday])
                ->where('is_pfand', 0)
                ->where('is_leergut', 0)
                ->where('is_mhd_writeoff', 0)
                ->where('artnr', '!=', '')
                ->groupBy('artnr')
                ->orderByDesc('menge')
                ->limit($limit);

            if ($warengruppe !== null) {
                $query->where('warengruppe', $warengruppe);
            }

            return collect($query->get());
        });
    }

    /**
     * Returns top articles for a KW as an artnr → row map (for diffing).
     *
     * @return array<string, object>
     */
    public function topArtikelMapForWeek(int $year, int $week, int $limit = 50, ?string $warengruppe = null): array
    {
        return $this->topArtikelForWeek($year, $week, $limit, $warengruppe)
            ->keyBy('artnr')
            ->all();
    }

    // ── Warengruppen ─────────────────────────────────────────────────────────

    /**
     * Umsatz + Menge je Warengruppe for the last N weeks.
     * Returns: [ 'warengruppe' => ['YYYY-KWww' => [menge, umsatz], ...], ... ]
     *
     * @return Collection<string, array<string, array{menge:float,umsatz:float}>>
     */
    public function warengruppenTrend(int $weeksBack = 12): Collection
    {
        $cacheKey = "stats.warengruppen.w{$weeksBack}";

        return Cache::remember($cacheKey, 1800, function () use ($weeksBack) {
            $from = now()->startOfWeek()->subWeeks($weeksBack)->format('Y-m-d');

            $rows = DB::table('stats_pos_daily')
                ->selectRaw("warengruppe, YEAR(bon_date) AS yr, WEEK(bon_date, 1) AS kw, SUM(menge) AS menge, SUM(umsatz) AS umsatz")
                ->where('bon_date', '>=', $from)
                ->where('is_pfand', 0)
                ->where('is_leergut', 0)
                ->where('is_mhd_writeoff', 0)
                ->where('warengruppe', '!=', '')
                ->groupByRaw('warengruppe, yr, kw')
                ->orderByRaw('warengruppe, yr, kw')
                ->get();

            $result = [];
            foreach ($rows as $row) {
                $kw  = sprintf('%04d-KW%02d', $row->yr, $row->kw);
                $result[$row->warengruppe][$kw] = [
                    'menge'  => (float) $row->menge,
                    'umsatz' => (float) $row->umsatz,
                ];
            }

            return collect($result);
        });
    }

    /**
     * Build a sorted list of the last N ISO week labels (e.g. "2025-KW15").
     *
     * @return list<string>
     */
    public function weekLabels(int $weeksBack = 12): array
    {
        $labels = [];
        for ($i = $weeksBack - 1; $i >= 0; $i--) {
            $d       = now()->startOfWeek()->subWeeks($i);
            $labels[] = sprintf('%04d-KW%02d', (int) $d->format('o'), (int) $d->format('W'));
        }

        return $labels;
    }

    // ── Artikel detail ───────────────────────────────────────────────────────

    /**
     * Weekly sales history for a single article (last $weeksBack weeks).
     * Each row: year, week, menge, umsatz, kwLabel, mondayDate.
     *
     * @return list<object{year:int, week:int, kwLabel:string, mondayDate:string, menge:float, umsatz:float}>
     */
    public function artikelWeeklyTrend(string $artnr, int $weeksBack = 52): array
    {
        $cacheKey = "stats.artikel.{$artnr}.w{$weeksBack}";

        return Cache::remember($cacheKey, 1800, function () use ($artnr, $weeksBack) {
            $from = now()->startOfWeek()->subWeeks($weeksBack)->format('Y-m-d');

            $rows = DB::table('stats_pos_daily')
                ->selectRaw('YEAR(bon_date) AS yr, WEEK(bon_date,1) AS kw, SUM(menge) AS menge, SUM(umsatz) AS umsatz')
                ->where('artnr', $artnr)
                ->where('bon_date', '>=', $from)
                ->where('is_mhd_writeoff', 0)
                ->groupByRaw('yr, kw')
                ->orderByRaw('yr, kw')
                ->get()
                ->keyBy(fn ($r) => sprintf('%04d-%02d', $r->yr, $r->kw));

            $result = [];
            for ($i = $weeksBack - 1; $i >= 0; $i--) {
                $monday = now()->startOfWeek()->subWeeks($i);
                $yr     = (int) $monday->format('o');
                $kw     = (int) $monday->format('W');
                $key    = sprintf('%04d-%02d', $yr, $kw);
                $row    = $rows[$key] ?? null;

                $result[] = (object) [
                    'year'       => $yr,
                    'week'       => $kw,
                    'kwLabel'    => sprintf('%04d-KW%02d', $yr, $kw),
                    'mondayDate' => $monday->format('d.m.Y'),
                    'sundayDate' => $monday->copy()->addDays(6)->format('d.m.Y'),
                    'menge'      => (float) ($row?->menge ?? 0),
                    'umsatz'     => (float) ($row?->umsatz ?? 0),
                ];
            }

            return $result;
        });
    }

    /**
     * Same-week-last-year menge for each week in the trend window.
     * Returns array keyed by 'YYYY-WW' (current year/week) → menge.
     *
     * @return array<string, float>
     */
    public function artikelLastYearMenge(string $artnr, int $weeksBack = 52): array
    {
        $cacheKey = "stats.artikel_ly.{$artnr}.w{$weeksBack}";

        return Cache::remember($cacheKey, 1800, function () use ($artnr, $weeksBack) {
            $fromLy = now()->startOfWeek()->subWeeks($weeksBack)->subYear()->format('Y-m-d');
            $toLy   = now()->startOfWeek()->subYear()->format('Y-m-d');

            $rows = DB::table('stats_pos_daily')
                ->selectRaw('WEEK(bon_date,1) AS kw, SUM(menge) AS menge')
                ->where('artnr', $artnr)
                ->whereBetween('bon_date', [$fromLy, $toLy])
                ->where('is_mhd_writeoff', 0)
                ->groupByRaw('kw')
                ->get()
                ->keyBy('kw');

            $result = [];
            for ($i = $weeksBack - 1; $i >= 0; $i--) {
                $monday = now()->startOfWeek()->subWeeks($i);
                $yr     = (int) $monday->format('o');
                $kw     = (int) $monday->format('W');
                $key    = sprintf('%04d-%02d', $yr, $kw);
                $result[$key] = (float) ($rows[$kw]?->menge ?? 0);
            }

            return $result;
        });
    }

    /**
     * Ranking of an article within its Warengruppe for the current week.
     * Returns ['rank' => int, 'total' => int] or null if article not in top list.
     */
    public function artikelRankInWarengruppe(string $artnr, string $warengruppe, int $year, int $week): ?array
    {
        $monday = $this->mondayOfYearWeek($year, $week)->format('Y-m-d');
        $sunday = $this->mondayOfYearWeek($year, $week)->addDays(6)->format('Y-m-d');

        $rows = DB::table('stats_pos_daily')
            ->selectRaw('artnr, SUM(menge) AS menge')
            ->where('warengruppe', $warengruppe)
            ->whereBetween('bon_date', [$monday, $sunday])
            ->where('is_pfand', 0)
            ->where('is_leergut', 0)
            ->where('is_mhd_writeoff', 0)
            ->groupBy('artnr')
            ->orderByDesc('menge')
            ->pluck('menge', 'artnr');

        $total = $rows->count();
        $rank  = array_search($artnr, array_keys($rows->all()));

        return $rank !== false ? ['rank' => $rank + 1, 'total' => $total] : null;
    }

    // ── Distinct Warengruppen ─────────────────────────────────────────────────

    /** @return list<string> */
    public function allWarengruppen(): array
    {
        return Cache::remember('stats.pos.warengruppen_list', 3600, function () {
            return DB::table('stats_pos_daily')
                ->where('warengruppe', '!=', '')
                ->where('is_pfand', 0)
                ->where('is_leergut', 0)
                ->where('is_mhd_writeoff', 0)
                ->distinct()
                ->orderBy('warengruppe')
                ->pluck('warengruppe')
                ->all();
        });
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /** Returns the Monday of ISO week $week in year $year. */
    public function mondayOfYearWeek(int $year, int $week): \Carbon\Carbon
    {
        return \Carbon\Carbon::now()
            ->setISODate($year, $week, 1)
            ->startOfDay();
    }

    /** Current ISO year + week numbers. */
    public function currentYearWeek(): array
    {
        $now = now();
        return [(int) $now->format('o'), (int) $now->format('W')];
    }

    /** Previous ISO week [year, week]. */
    public function previousWeek(int $year, int $week): array
    {
        $d = $this->mondayOfYearWeek($year, $week)->subWeek();
        return [(int) $d->format('o'), (int) $d->format('W')];
    }

    /** Same week one year ago [year, week]. */
    public function sameWeekLastYear(int $year, int $week): array
    {
        return [$year - 1, $week];
    }

    /**
     * Returns the Monday and Sunday of a given ISO week as formatted strings.
     * e.g. ['07.04.2026', '13.04.2026']
     */
    public function kwDateRange(int $year, int $week): array
    {
        $monday = $this->mondayOfYearWeek($year, $week);
        $sunday = $monday->copy()->addDays(6);
        return [$monday->format('d.m.Y'), $sunday->format('d.m.Y')];
    }
}
