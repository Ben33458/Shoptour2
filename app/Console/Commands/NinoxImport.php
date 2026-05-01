<?php

namespace App\Console\Commands;

use App\Models\System\SyncRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NinoxImport extends Command
{
    protected $signature = 'ninox:import';
    protected $description = 'Import Ninox data';

    public function handle(): void
    {
        $syncRun = SyncRun::create([
            'source'            => 'ninox',
            'status'            => 'running',
            'records_processed' => 0,
            'triggered_by'      => 'cli',
            'started_at'        => now(),
        ]);

        $totalRecords = 0;

        try {
            $data = json_decode(file_get_contents('/tmp/ninox_all_data.json'), true);
            $this->info('JSON loaded: ' . count($data) . ' tables');

            foreach ($data as $tname => $info) {
                if (empty($info['records'])) continue;

                $tbl = 'ninox_' . $this->sanitize($tname);
                $rows = [];
                foreach ($info['records'] as $rec) {
                    $row = [
                        'ninox_id' => $rec['id'],
                        'ninox_sequence' => $rec['sequence'] ?? null,
                        'ninox_created_at' => isset($rec['createdAt']) ? date('Y-m-d H:i:s', strtotime($rec['createdAt'])) : null,
                        'ninox_updated_at' => isset($rec['modifiedAt']) ? date('Y-m-d H:i:s', strtotime($rec['modifiedAt'])) : null,
                    ];
                    foreach ($rec['fields'] ?? [] as $k => $v) {
                        $col = $this->sanitize($k);
                        if (in_array($col, ['ninox_id','ninox_sequence','ninox_created_at','ninox_updated_at'])) {
                            $col .= '_f';
                        }
                        $row[$col] = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v;
                    }
                    $rows[] = $row;
                }

                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table($tbl)->insertOrIgnore($chunk);
                }

                $totalRecords += count($rows);
                $this->info("  $tbl: " . count($rows) . ' rows');
            }

            $syncRun->update([
                'status'            => 'completed',
                'records_processed' => $totalRecords,
                'finished_at'       => now(),
            ]);

            $this->info('Done!');
        } catch (\Throwable $e) {
            $syncRun->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at'   => now(),
            ]);

            $this->error('Ninox import failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function sanitize(string $name): string
    {
        $name = str_replace(['ä','ö','ü','Ä','Ö','Ü','ß'], ['ae','oe','ue','ae','oe','ue','ss'], $name);
        $name = preg_replace('/[^a-zA-Z0-9]+/', '_', $name);
        return strtolower(trim($name, '_'));
    }
}
