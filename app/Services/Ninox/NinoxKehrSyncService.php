<?php

declare(strict_types=1);

namespace App\Services\Ninox;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Syncs kehr raw records (ninox_raw_records WHERE db_id = kehr)
 * into the structured ninox_* tables.
 *
 * The mapping between Ninox table_id and ninox_* table names is derived
 * from the table names stored during import (ninox_import_tables.table_name),
 * using the same sanitize() logic as the original NinoxImport artisan command.
 *
 * Kehr DB ID: tpwd0lln7f65
 */
class NinoxKehrSyncService
{
    private string $kehrDbId;

    public function __construct()
    {
        $this->kehrDbId = config('services.ninox.db_id_kehr', 'tpwd0lln7f65');
    }

    /**
     * Sync all kehr tables from ninox_raw_records → ninox_* tables.
     *
     * @return array{synced_tables: int, synced_records: int, skipped_tables: list<string>}
     */
    public function syncAll(): array
    {
        // Get table_id → table_name mapping from the most recent kehr import run
        $tableMap = $this->getTableMap();

        $syncedTables  = 0;
        $syncedRecords = 0;
        $skippedTables = [];

        foreach ($tableMap as $tableId => $tableName) {
            $ninoxTable = 'ninox_' . $this->sanitize($tableName);

            if (! Schema::hasTable($ninoxTable)) {
                $skippedTables[] = "{$tableName} ({$tableId}) → {$ninoxTable} (Tabelle fehlt)";
                continue;
            }

            $count = $this->syncTable($tableId, $ninoxTable);
            $syncedTables++;
            $syncedRecords += $count;
        }

        return [
            'synced_tables'  => $syncedTables,
            'synced_records' => $syncedRecords,
            'skipped_tables' => $skippedTables,
        ];
    }

    /**
     * Sync one kehr table from ninox_raw_records into a ninox_* table.
     * Uses upsert: updates existing rows, inserts new ones.
     */
    public function syncTable(string $tableId, string $ninoxTable): int
    {
        $columns = Schema::getColumnListing($ninoxTable);
        $reserved = ['ninox_id', 'ninox_sequence', 'ninox_created_at', 'ninox_updated_at'];

        $records = DB::table('ninox_raw_records')
            ->where('db_id', $this->kehrDbId)
            ->where('table_id', $tableId)
            ->where('is_latest', true)
            ->get(['ninox_id', 'record_data', 'ninox_created_at', 'ninox_updated_at']);

        $rows = [];
        foreach ($records as $rec) {
            $data   = is_string($rec->record_data) ? json_decode($rec->record_data, true) : $rec->record_data;
            $fields = is_array($data) ? $data : [];

            $row = [
                'ninox_id'         => (int) $rec->ninox_id,
                'ninox_created_at' => $rec->ninox_created_at,
                'ninox_updated_at' => $rec->ninox_updated_at,
            ];

            foreach ($fields as $key => $value) {
                $col = $this->sanitize($key);

                // Avoid overwriting meta columns
                if (in_array($col, $reserved, true)) {
                    $col .= '_f';
                }

                if (! in_array($col, $columns, true)) {
                    continue; // Column not in ninox_* table — skip
                }

                $encoded = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
                // MySQL TEXT columns hold max 65535 bytes — truncate outliers
                if (is_string($encoded) && strlen($encoded) > 65000) {
                    $encoded = mb_substr($encoded, 0, 65000);
                }
                $row[$col] = $encoded;
            }

            $rows[] = $row;
        }

        if (empty($rows)) {
            return 0;
        }

        // Normalise: all rows must have the same keys (fill missing fields with null)
        $allKeys = array_unique(array_merge(...array_map('array_keys', $rows)));
        foreach ($rows as &$row) {
            foreach ($allKeys as $key) {
                if (! array_key_exists($key, $row)) {
                    $row[$key] = null;
                }
            }
            ksort($row);
        }
        unset($row);

        $updateColumns = array_values(array_diff($allKeys, ['ninox_id']));
        $synced        = 0;

        // Try bulk upsert first (fast path); fall back to row-by-row on error
        try {
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table($ninoxTable)->upsert($chunk, ['ninox_id'], $updateColumns);
                $synced += count($chunk);
            }
        } catch (\Illuminate\Database\QueryException) {
            // Slow path: upsert row by row so one bad record doesn't abort the table
            $synced = 0;
            foreach ($rows as $row) {
                try {
                    DB::table($ninoxTable)->upsert([$row], ['ninox_id'], $updateColumns);
                    $synced++;
                } catch (\Illuminate\Database\QueryException) {
                    // Skip this record — likely a type mismatch (DECIMAL overflow, etc.)
                }
            }
        }

        return $synced;
    }

    /**
     * Get the kehr table_id → human name mapping from the most recent import run.
     * Falls back to the hardcoded kehr mapping if no import has been run yet.
     *
     * @return array<string, string>  e.g. ['K' => 'Kunden', 'I' => 'Mitarbeiter', …]
     */
    public function getTableMap(): array
    {
        // Try to load from last kehr import run
        $lastKehrRun = DB::table('ninox_import_runs')
            ->where('db_id', $this->kehrDbId)
            ->where('status', 'completed')
            ->orderByDesc('id')
            ->value('id');

        if ($lastKehrRun) {
            $rows = DB::table('ninox_import_tables')
                ->where('run_id', $lastKehrRun)
                ->pluck('table_name', 'table_id')
                ->all();

            if (! empty($rows)) {
                return $rows;
            }
        }

        // Hardcoded fallback — matches the kehr DB (tpwd0lln7f65) as of 2026-03
        return self::KEHR_TABLE_MAP;
    }

    /**
     * Sanitize a Ninox table/field name to a DB-safe identifier.
     * Must match the logic in App\Console\Commands\NinoxImport::sanitize().
     */
    public function sanitize(string $name): string
    {
        $name = str_replace(
            ['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'],
            ['ae', 'oe', 'ue', 'ae', 'oe', 'ue', 'ss'],
            $name
        );
        $name = preg_replace('/[^a-zA-Z0-9]+/', '_', $name) ?? $name;
        return strtolower(trim($name, '_'));
    }

    // ── Hardcoded kehr table map (fallback) ───────────────────────────────────

    public const KEHR_TABLE_MAP = [
        'A'  => 'Veranstaltung',
        'B'  => 'Veranstaltungstage',
        'C'  => 'Veranstaltungsjahr',
        'D'  => 'Aufgaben',
        'E'  => 'Kontakte',
        'F'  => 'Dokumente',
        'G'  => '77 regelmäßige Aufgaben',
        'H'  => 'Done History',
        'I'  => 'Mitarbeiter',
        'J'  => 'Lieferanten',
        'K'  => 'Kunden',
        'L'  => 'Schlüssel',
        'M'  => 'Fest-Inventar',
        'O'  => 'Dokument',
        'P'  => 'Bestellung',
        'Q'  => 'Kassenbuch',
        'R'  => 'regelmäßige Touren',
        'S'  => 'Bestellannahme',
        'T'  => 'Liefer-Tour',
        'U'  => 'Pfand Rücknahme',
        'W'  => 'Hassia-Rechner',
        'X'  => 'Lieferadressen',
        'Y'  => 'Kassen-Umsatz',
        'Z'  => 'Marktbestand',
        'AB' => 'Benachrichtigungen',
        'BB' => 'Schichtbericht',
        'CB' => 'Abbuchungen',
        'DB' => 'SEPA-Mandat',
        'EB' => 'Warenkorb-Artikel',
        'FB' => 'Belohnung',
        'GB' => 'Wasser',
        'HB' => 'Festbedarf-Warenkorb',
        'IB' => 'Warengruppe',
        'JB' => 'Stammsortiment',
        'KB' => 'Arbeitsmaterial',
        'LB' => 'Pausen',
        'MB' => 'MonatsÜbersicht',
        'NB' => 'Log',
        'OB' => 'Fahrzeug',
        'PB' => 'Zahlungen',
        'QB' => 'Buchhaltungs-Dashboard',
        'RB' => 'Kunden-Historie',
        'SB' => 'Buchhaltungsübersicht',
    ];
}
