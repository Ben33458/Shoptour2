<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Refreshes the stats_pos_daily materialized summary table.
 *
 * Full rebuild:     php artisan stats:refresh-pos --full
 * Incremental:      php artisan stats:refresh-pos          (last 3 days)
 * Custom window:    php artisan stats:refresh-pos --days=7
 */
class StatsPosRefreshCommand extends Command
{
    protected $signature = 'stats:refresh-pos
                            {--full    : Full rebuild from 2019-01-01}
                            {--days=3  : Incremental: re-aggregate last N days (default 3)}';

    protected $description = 'Refresh stats_pos_daily materialized table from wawi POS data';

    public function handle(): int
    {
        $full = (bool) $this->option('full');
        $days = (int) $this->option('days');

        if ($full) {
            $this->info('Full rebuild — truncating stats_pos_daily …');
            DB::statement('TRUNCATE TABLE stats_pos_daily');
            $fromDate = '2019-01-01';
        } else {
            $fromDate = now()->subDays($days)->format('Y-m-d');
            $this->info("Incremental refresh — deleting rows from {$fromDate} onwards …");
            DB::table('stats_pos_daily')->where('bon_date', '>=', $fromDate)->delete();
        }

        $this->info("Inserting aggregated rows from {$fromDate} …");

        // One INSERT … SELECT that re-aggregates from the raw wawi tables.
        // COALESCE(NULLIF(artnr,''), name) ensures uniqueness even for empty artnr
        // (e.g. Pfand/Leergut articles often have no artnr in JTL).
        //
        // is_mhd_writeoff: WaWi customer K3475 (kKunde = 618) is a virtual
        // bookkeeping customer used exclusively for MHD write-offs (expired goods
        // disposal). Transactions on this customer are NOT real sales and are
        // flagged here so all statistics queries can exclude them.
        DB::statement("
            INSERT INTO stats_pos_daily
                (bon_date, artnr, name, warengruppe, is_pfand, is_leergut, is_mhd_writeoff, menge, umsatz, unit_price, created_at, updated_at)
            SELECT
                DATE(STR_TO_DATE(SUBSTRING(b.dDatum,1,19),'%Y-%m-%d %H:%i:%s'))  AS bon_date,
                COALESCE(NULLIF(bp.tArtikel_cArtNr,''), bp.tArtikel_cName)        AS artnr,
                MAX(bp.tArtikel_cName)                                             AS name,
                MAX(COALESCE(bp.tArtikel_cWarengruppe,''))                         AS warengruppe,
                MAX(CASE WHEN bp.tArtikel_cName LIKE 'Pfand %'   THEN 1 ELSE 0 END) AS is_pfand,
                MAX(CASE WHEN bp.tArtikel_cName LIKE 'Leergut %' THEN 1 ELSE 0 END) AS is_leergut,
                MAX(CASE WHEN b.kKunde = 618                      THEN 1 ELSE 0 END) AS is_mhd_writeoff,
                SUM(bp.fMenge)                                                     AS menge,
                SUM(bp.fMenge * bp.fEinzelPreis)                                   AS umsatz,
                MAX(bp.fEinzelPreis)                                                AS unit_price,
                NOW(), NOW()
            FROM wawi_dbo_pos_bonposition bp
            JOIN wawi_dbo_pos_bon b ON b.kBon = bp.kBon
            WHERE bp.kBonPositionStorno = 0
              AND b.kBonStorno = 0
              AND DATE(STR_TO_DATE(SUBSTRING(b.dDatum,1,19),'%Y-%m-%d %H:%i:%s')) >= ?
            GROUP BY bon_date, COALESCE(NULLIF(bp.tArtikel_cArtNr,''), bp.tArtikel_cName)
            ON DUPLICATE KEY UPDATE
                name            = VALUES(name),
                warengruppe     = VALUES(warengruppe),
                is_pfand        = VALUES(is_pfand),
                is_leergut      = VALUES(is_leergut),
                is_mhd_writeoff = VALUES(is_mhd_writeoff),
                menge           = VALUES(menge),
                umsatz          = VALUES(umsatz),
                unit_price      = VALUES(unit_price),
                updated_at      = NOW()
        ", [$fromDate]);

        $count = DB::table('stats_pos_daily')->count();
        $this->info("Done. stats_pos_daily now has {$count} rows.");

        // Flush statistics caches so next page load uses fresh data
        Cache::flush();
        $this->info('Cache flushed.');

        return self::SUCCESS;
    }
}
