<?php

declare(strict_types=1);

namespace App\Services\Events;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Discovers whether JTL offer tables are available in the connected database.
 *
 * Used to generate an informational report for the import admin UI.
 */
class JtlOfferDiscoveryService
{
    private const KNOWN_JTL_TABLES = [
        'wawi_verkauf_tauftrag',
        'wawi_verkauf_tauftragposition',
        'wawi_kunden',
        'wawi_artikel',
    ];

    /**
     * Run the discovery and return a structured report.
     *
     * @return array{
     *   has_offer_table: bool,
     *   tables_found: array,
     *   tables_missing: array,
     *   offer_count: int,
     *   note: string,
     *   wawi_auftraege_count: int,
     * }
     */
    public function discover(): array
    {
        $tablesFound   = [];
        $tablesMissing = [];

        foreach (self::KNOWN_JTL_TABLES as $table) {
            if (Schema::hasTable($table)) {
                $tablesFound[] = $table;
            } else {
                $tablesMissing[] = $table;
            }
        }

        $hasOfferTable = Schema::hasTable('wawi_verkauf_tauftrag');
        $offerCount    = 0;

        if ($hasOfferTable) {
            $offerCount = (int) DB::table('wawi_verkauf_tauftrag')->where('nType', 0)->count();
        }

        $wawiAuftraege = 0;
        if ($hasOfferTable) {
            $wawiAuftraege = (int) DB::table('wawi_verkauf_tauftrag')->where('nType', 1)->count();
        }

        $note = $hasOfferTable
            ? "JTL-Angebote gefunden (wawi_verkauf_tauftrag, nType=0): {$offerCount} Angebote."
            : 'JTL WAWI nicht verbunden (wawi_verkauf_tauftrag fehlt).';

        return [
            'has_offer_table'      => $hasOfferTable,
            'tables_found'         => $tablesFound,
            'tables_missing'       => $tablesMissing,
            'offer_count'          => $offerCount,
            'note'                 => $note,
            'wawi_auftraege_count' => $wawiAuftraege,
        ];
    }
}
