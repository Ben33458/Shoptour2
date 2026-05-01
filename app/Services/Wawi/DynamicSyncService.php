<?php

declare(strict_types=1);

namespace App\Services\Wawi;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DynamicSyncService
{
    /**
     * Convert JTL entity name to MySQL table name.
     *   "dbo.tZahlung"     → "wawi_dbo_tzahlung"
     *   "Verkauf.tAuftrag" → "wawi_verkauf_tauftrag"
     *   "dbo.POS_Bon"      → "wawi_dbo_pos_bon"
     */
    public function tableNameFor(string $entity): string
    {
        $name = 'wawi_' . strtolower($entity);
        $name = preg_replace('/[^a-z0-9]+/', '_', $name);   // dots, spaces, dashes → _
        $name = trim($name, '_');
        return substr($name, 0, 64);                         // MySQL max 64 chars
    }

    /**
     * Detect primary key from record keys using JTL naming conventions.
     * First column starting with 'k', fallback: first column.
     */
    public function detectPrimaryKey(array $record): string
    {
        foreach (array_keys($record) as $col) {
            if (str_starts_with($col, 'k')) {
                return $col;
            }
        }
        return (string) array_key_first($record);
    }

    /**
     * Upsert a batch of records into the dynamically managed wawi_* table.
     * Returns the number of records processed.
     */
    public function upsert(string $entity, array $records): int
    {
        if (empty($records)) {
            return 0;
        }

        $tableName   = $this->tableNameFor($entity);
        $firstRecord = (array) $records[0];
        $pk          = $this->detectPrimaryKey($firstRecord);
        $allCols     = array_keys($firstRecord);

        $this->ensureTable($tableName, $firstRecord, $pk);

        $now  = now()->toDateTimeString();
        $rows = [];

        foreach ($records as $record) {
            $record = (array) $record;

            // Skip records without a primary key value
            if (!isset($record[$pk]) || $record[$pk] === '' || $record[$pk] === null) {
                continue;
            }

            $row = [];
            foreach ($allCols as $col) {
                $row[$col] = isset($record[$col]) ? $record[$col] : null;
            }
            $row['created_at'] = $now;
            $row['updated_at'] = $now;

            $rows[] = $row;
        }

        if (empty($rows)) {
            return 0;
        }

        $updateColumns = array_values(array_filter(
            array_keys($rows[0]),
            fn (string $col) => $col !== $pk && $col !== 'created_at'
        ));

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($tableName)->upsert($chunk, [$pk], $updateColumns);
        }

        return count($rows);
    }

    /**
     * Ensure the target table exists and contains all required columns.
     */
    private function ensureTable(string $tableName, array $sampleRecord, string $pk): void
    {
        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) use ($sampleRecord, $pk) {
                $table->unsignedBigInteger($pk)->primary();

                foreach (array_keys($sampleRecord) as $col) {
                    if ($col === $pk) {
                        continue;
                    }
                    $this->addColumn($table, $col);
                }

                $table->timestamps();
            });

            Log::info('DynamicSync: created table', ['table' => $tableName, 'pk' => $pk]);
            return;
        }

        // Add any columns present in the incoming data but missing from the table
        $existing = Schema::getColumnListing($tableName);
        $missing  = array_diff(array_keys($sampleRecord), $existing);

        if (!empty($missing)) {
            Schema::table($tableName, function (Blueprint $table) use ($missing, $pk) {
                foreach ($missing as $col) {
                    if ($col === $pk) {
                        continue;
                    }
                    $this->addColumn($table, $col);
                }
            });

            Log::info('DynamicSync: added columns', ['table' => $tableName, 'columns' => $missing]);
        }
    }

    /**
     * Add a column to a table blueprint using JTL naming-convention type hints.
     *   k* → unsignedBigInteger (foreign keys)
     *   f* → double             (floats)
     *   n* → integer            (integers/booleans)
     *   d* → string(50)         (date strings — JTL uses mixed formats)
     *   *  → text               (everything else: cName, cBeschreibung, etc.)
     */
    private function addColumn(Blueprint $table, string $col): void
    {
        $prefix = substr($col, 0, 1);

        match ($prefix) {
            'k'     => $table->unsignedBigInteger($col)->nullable(),
            'f'     => $table->double($col)->nullable(),
            'n'     => $table->integer($col)->nullable(),
            'd'     => $table->string($col, 50)->nullable(),
            default => $table->text($col)->nullable(),
        };
    }
}
