<?php

declare(strict_types=1);

namespace App\Services\Ninox;

use App\Models\Ninox\NinoxImportRun;
use App\Models\Ninox\NinoxImportTable;
use App\Models\Ninox\NinoxRawRecord;
use App\Services\Employee\SystemLogService;
use Illuminate\Support\Facades\DB;
use Throwable;

class NinoxImportService
{
    private string $kehrDbId;
    private string $altDbId;

    public function __construct(
        private readonly SystemLogService $log,
        private readonly NinoxKehrSyncService $kehrSync,
        private readonly NinoxContactSyncService $contactSync,
    ) {
        $this->kehrDbId = config('services.ninox.db_id_kehr', 'tpwd0lln7f65');
        $this->altDbId  = config('services.ninox.db_id_alt',  'fadrrq8poh9b');
    }

    /**
     * Run a full import of all Ninox databases.
     * Imports kehr first (authoritative), then alt.
     * After kehr import, syncs ninox_raw_records → ninox_* structured tables.
     *
     * @return array{kehr: NinoxImportRun, alt: NinoxImportRun, sync: array}
     */
    public function runAll(?int $userId = null): array
    {
        $kehrRun  = $this->runDatabase($this->kehrDbId, $userId);
        $altRun   = $this->runDatabase($this->altDbId, $userId);
        $syncResult = [];

        // After kehr import, sync into structured ninox_* tables and contacts
        if ($kehrRun->status === 'completed') {
            try {
                $syncResult = $this->kehrSync->syncAll();
                $this->log->log('ninox.sync.completed', $userId, null, 'NinoxImportRun', $kehrRun->id, $syncResult);
            } catch (Throwable $e) {
                $syncResult = ['error' => $e->getMessage()];
                $this->log->log('ninox.sync.failed', $userId, null, 'NinoxImportRun', $kehrRun->id, [
                    'error' => $e->getMessage(),
                ]);
            }

            // Sync contacts (create/update ninox_kontakte → contacts table)
            try {
                $contactResult = $this->contactSync->syncAll();
                $syncResult['contacts'] = $contactResult;
            } catch (Throwable $e) {
                $syncResult['contacts'] = ['error' => $e->getMessage()];
            }
        }

        return [
            'kehr' => $kehrRun->fresh(['tables']),
            'alt'  => $altRun->fresh(['tables']),
            'sync' => $syncResult,
        ];
    }

    /**
     * Run import for a single Ninox database.
     */
    public function run(?int $userId = null): NinoxImportRun
    {
        return $this->runDatabase($this->kehrDbId, $userId);
    }

    /**
     * Import one Ninox database into ninox_raw_records.
     */
    public function runDatabase(string $dbId, ?int $userId = null): NinoxImportRun
    {
        $client = NinoxApiClient::make($dbId);

        $run = NinoxImportRun::create([
            'db_id'       => $dbId,
            'created_by'  => $userId,
            'status'      => 'running',
            'started_at'  => now(),
        ]);

        $this->log->log('ninox.import.started', $userId, null, 'NinoxImportRun', $run->id, [
            'db_id' => $dbId,
        ]);

        try {
            $tables = $client->getTables();
            $run->update(['tables_count' => count($tables)]);

            $totalImported = 0;
            $totalSkipped  = 0;

            foreach ($tables as $tableMeta) {
                [$imported, $skipped] = $this->importTable($run, $tableMeta, $client, $dbId);
                $totalImported += $imported;
                $totalSkipped  += $skipped;
            }

            $run->update([
                'status'           => 'completed',
                'records_imported' => $totalImported,
                'records_skipped'  => $totalSkipped,
                'finished_at'      => now(),
            ]);

            $this->log->log('ninox.import.completed', $userId, null, 'NinoxImportRun', $run->id, [
                'db_id'    => $dbId,
                'tables'   => count($tables),
                'imported' => $totalImported,
                'skipped'  => $totalSkipped,
            ]);
        } catch (Throwable $e) {
            $run->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at'   => now(),
            ]);

            $this->log->log('ninox.import.failed', $userId, null, 'NinoxImportRun', $run->id, [
                'db_id' => $dbId,
                'error' => $e->getMessage(),
            ]);
        }

        return $run->fresh(['tables']);
    }

    /**
     * Import one Ninox table.  Returns [imported, skipped].
     *
     * @return array{0: int, 1: int}
     */
    private function importTable(NinoxImportRun $run, array $tableMeta, NinoxApiClient $client, string $dbId): array
    {
        $tableId   = (string) ($tableMeta['id'] ?? '');
        $tableName = (string) ($tableMeta['name'] ?? $tableId);

        $importTable = NinoxImportTable::create([
            'run_id'     => $run->id,
            'db_id'      => $dbId,
            'table_id'   => $tableId,
            'table_name' => $tableName,
            'status'     => 'importing',
        ]);

        $this->log->log('ninox.import.table.started', null, null, 'NinoxImportTable', $importTable->id, [
            'db_id'      => $dbId,
            'table_id'   => $tableId,
            'table_name' => $tableName,
        ]);

        try {
            $records  = $client->getAllRecords($tableId);
            $imported = 0;
            $skipped  = 0;

            // Mark previous latest records for this db+table as not-latest
            DB::table('ninox_raw_records')
                ->where('db_id', $dbId)
                ->where('table_id', $tableId)
                ->where('is_latest', true)
                ->update(['is_latest' => false]);

            foreach ($records as $record) {
                $ninoxId = (string) ($record['id'] ?? '');
                if (! $ninoxId) {
                    $skipped++;
                    continue;
                }

                $fields = $record['fields'] ?? $record;

                NinoxRawRecord::create([
                    'run_id'           => $run->id,
                    'import_table_id'  => $importTable->id,
                    'db_id'            => $dbId,
                    'table_id'         => $tableId,
                    'ninox_id'         => $ninoxId,
                    'record_data'      => $fields,
                    'is_latest'        => true,
                    'ninox_created_at' => isset($record['createdAt'])
                        ? \Carbon\Carbon::parse($record['createdAt'])
                        : null,
                    'ninox_updated_at' => isset($record['updatedAt'])
                        ? \Carbon\Carbon::parse($record['updatedAt'])
                        : null,
                ]);

                $imported++;
            }

            $importTable->update([
                'status'           => 'completed',
                'records_count'    => count($records),
                'records_imported' => $imported,
                'imported_at'      => now(),
            ]);

            $this->log->log('ninox.import.table.completed', null, null, 'NinoxImportTable', $importTable->id, [
                'imported' => $imported,
                'skipped'  => $skipped,
            ]);

            return [$imported, $skipped];
        } catch (Throwable $e) {
            $importTable->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $this->log->log('ninox.import.table.failed', null, null, 'NinoxImportTable', $importTable->id, [
                'error' => $e->getMessage(),
            ]);

            return [0, 0];
        }
    }

    /**
     * Get the latest raw records for a given Ninox table ID.
     * If db_id is omitted, returns records from kehr (authoritative).
     */
    public function getLatestRecords(string $tableId, ?string $dbId = null): \Illuminate\Support\Collection
    {
        return NinoxRawRecord::where('db_id', $dbId ?? $this->kehrDbId)
            ->where('table_id', $tableId)
            ->where('is_latest', true)
            ->orderBy('ninox_id')
            ->get();
    }

    /**
     * Find an employee match from the raw records of the employee table.
     * Matches by email in the record_data JSON.
     */
    public function findEmployeeByEmail(string $tableId, string $email): ?array
    {
        $records    = $this->getLatestRecords($tableId);
        $emailLower = strtolower(trim($email));

        foreach ($records as $record) {
            $data = $record->record_data;
            foreach ($data as $value) {
                if (is_string($value) && strtolower(trim($value)) === $emailLower
                    && str_contains(strtolower($value), '@')) {
                    return ['record' => $record, 'data' => $data];
                }
            }
        }

        return null;
    }
}
