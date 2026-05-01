<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Pfand / Leergut statistics from the materialized stats_pos_daily table.
 *
 * Pairing logic:
 *   "Pfand Flasche 25 Cent"  → key = "flasche 25 cent"  (went OUT, +qty, +price)
 *   "Leergut Flasche 25 Cent"→ key = "flasche 25 cent"  (came BACK, +qty, negative price)
 *
 * Saldo (Stk) = Pfand_out - Leergut_in  (bottles still with customers)
 * Saldo (EUR) = SUM(umsatz) — positive = net outstanding deposit value
 */
class PfandStatisticsService
{
    // ── All-time saldo per article key ────────────────────────────────────────

    /**
     * Returns the all-time deposit balance per article.
     * Sorted by ABS(saldo_eur) descending (biggest balance first).
     *
     * @return Collection<int, object{
     *     artikel_key: string,
     *     pfand_label: string,
     *     leergut_label: string,
     *     pfand_preis: float,
     *     pfand_out: float,
     *     leergut_in: float,
     *     saldo_stk: float,
     *     saldo_eur: float
     * }>
     */
    public function saldoGesamt(): Collection
    {
        return Cache::remember('stats.pfand.saldo_gesamt', 1800, function () {
            $rows = DB::select("
                SELECT
                    CASE
                        WHEN is_pfand   = 1 THEN LOWER(TRIM(SUBSTRING(name, 7)))
                        WHEN is_leergut = 1 THEN LOWER(TRIM(SUBSTRING(name, 9)))
                    END                                                               AS artikel_key,
                    MAX(CASE WHEN is_pfand   = 1 THEN name END)                       AS pfand_label,
                    MAX(CASE WHEN is_leergut = 1 THEN name END)                       AS leergut_label,
                    MAX(CASE WHEN is_pfand   = 1 THEN unit_price ELSE 0 END)          AS pfand_preis,
                    SUM(CASE WHEN is_pfand   = 1 THEN menge ELSE 0 END)               AS pfand_out,
                    SUM(CASE WHEN is_leergut = 1 THEN menge ELSE 0 END)               AS leergut_in,
                    SUM(CASE WHEN is_pfand   = 1 THEN  menge ELSE 0 END)
                  - SUM(CASE WHEN is_leergut = 1 THEN  menge ELSE 0 END)              AS saldo_stk,
                    SUM(umsatz)                                                        AS saldo_eur
                FROM stats_pos_daily
                WHERE is_pfand = 1 OR is_leergut = 1
                GROUP BY artikel_key
                HAVING artikel_key IS NOT NULL AND artikel_key != ''
                ORDER BY ABS(saldo_eur) DESC
            ");

            return collect($rows)->map(fn ($r) => (object) [
                'artikel_key'   => $r->artikel_key,
                'pfand_label'   => $r->pfand_label  ?? ('Pfand '   . ucfirst($r->artikel_key)),
                'leergut_label' => $r->leergut_label ?? ('Leergut ' . ucfirst($r->artikel_key)),
                'pfand_preis'   => (float) $r->pfand_preis,
                'pfand_out'     => (float) $r->pfand_out,
                'leergut_in'    => (float) $r->leergut_in,
                'saldo_stk'     => (float) $r->saldo_stk,
                'saldo_eur'     => (float) $r->saldo_eur,
            ]);
        });
    }

    // ── Weekly in/out/net trend ───────────────────────────────────────────────

    /**
     * Weekly Pfand out / Leergut in / net saldo for the last $weeksBack weeks.
     * Also computes a running cumulative saldo (all-time base + weekly net).
     *
     * @return array{
     *     labels: list<string>,
     *     pfandOut: list<float>,
     *     leergutIn: list<float>,
     *     kwNetto: list<float>,
     *     kwNettoEur: list<float>,
     *     cumStk: list<float>,
     *     cumEur: list<float>
     * }
     */
    public function wochenTrend(int $weeksBack = 24): array
    {
        $cacheKey = "stats.pfand.trend.w{$weeksBack}";

        return Cache::remember($cacheKey, 1800, function () use ($weeksBack) {
            $from = now()->startOfWeek()->subWeeks($weeksBack)->format('Y-m-d');

            // Trend within window
            $rows = DB::select("
                SELECT
                    YEAR(bon_date)        AS yr,
                    WEEK(bon_date, 1)     AS kw,
                    SUM(CASE WHEN is_pfand   = 1 THEN menge ELSE 0 END) AS pfand_out,
                    SUM(CASE WHEN is_leergut = 1 THEN menge ELSE 0 END) AS leergut_in,
                    SUM(CASE WHEN is_pfand   = 1 THEN  menge ELSE 0 END)
                  - SUM(CASE WHEN is_leergut = 1 THEN  menge ELSE 0 END) AS kw_netto,
                    SUM(umsatz) AS kw_netto_eur
                FROM stats_pos_daily
                WHERE (is_pfand = 1 OR is_leergut = 1)
                  AND bon_date >= ?
                GROUP BY yr, kw
                ORDER BY yr, kw
            ", [$from]);

            // All-time saldo BEFORE the window (to anchor running total)
            $baseline = DB::selectOne("
                SELECT
                    COALESCE(
                        SUM(CASE WHEN is_pfand   = 1 THEN  menge ELSE 0 END)
                      - SUM(CASE WHEN is_leergut = 1 THEN  menge ELSE 0 END),
                    0) AS base_stk,
                    COALESCE(SUM(umsatz), 0) AS base_eur
                FROM stats_pos_daily
                WHERE (is_pfand = 1 OR is_leergut = 1)
                  AND bon_date < ?
            ", [$from]);

            $runStk = (float) ($baseline->base_stk ?? 0);
            $runEur = (float) ($baseline->base_eur ?? 0);

            $byWeek = [];
            foreach ($rows as $r) {
                $byWeek[sprintf('%04d-KW%02d', $r->yr, $r->kw)] = $r;
            }

            $labels = $pfandOut = $leergutIn = $kwNetto = $kwNettoEur = $cumStk = $cumEur = [];
            for ($i = $weeksBack - 1; $i >= 0; $i--) {
                $d   = now()->startOfWeek()->subWeeks($i);
                $lbl = sprintf('%04d-KW%02d', (int) $d->format('o'), (int) $d->format('W'));
                $r   = $byWeek[$lbl] ?? null;

                $net    = (float) ($r?->kw_netto     ?? 0);
                $netEur = (float) ($r?->kw_netto_eur ?? 0);
                $runStk += $net;
                $runEur += $netEur;

                $labels[]     = $lbl;
                $pfandOut[]   = (float) ($r?->pfand_out  ?? 0);
                $leergutIn[]  = (float) ($r?->leergut_in ?? 0);
                $kwNetto[]    = $net;
                $kwNettoEur[] = $netEur;
                $cumStk[]     = $runStk;
                $cumEur[]     = $runEur;
            }

            return compact('labels', 'pfandOut', 'leergutIn', 'kwNetto', 'kwNettoEur', 'cumStk', 'cumEur');
        });
    }

    /** Total all-time saldo in Stk and EUR. */
    public function gesamtsaldo(): array
    {
        return Cache::remember('stats.pfand.gesamtsaldo', 1800, function () {
            $r = DB::selectOne("
                SELECT
                    COALESCE(
                        SUM(CASE WHEN is_pfand   = 1 THEN  menge ELSE 0 END)
                      - SUM(CASE WHEN is_leergut = 1 THEN  menge ELSE 0 END),
                    0) AS saldo_stk,
                    COALESCE(SUM(umsatz), 0) AS saldo_eur
                FROM stats_pos_daily
                WHERE is_pfand = 1 OR is_leergut = 1
            ");

            return [
                'saldo_stk' => (float) ($r?->saldo_stk ?? 0),
                'saldo_eur' => (float) ($r?->saldo_eur ?? 0),
            ];
        });
    }
}
