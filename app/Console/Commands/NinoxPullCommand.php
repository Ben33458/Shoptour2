<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\System\SyncRun;
use App\Services\Ninox\NinoxPullService;
use Illuminate\Console\Command;

class NinoxPullCommand extends Command
{
    protected $signature = 'ninox:pull';

    protected $description = 'Pull changes from Ninox into shoptour2 (2-hour polling)';

    public function handle(): int
    {
        $this->info('Ninox pull starting…');

        $run = SyncRun::create([
            'source'     => 'ninox',
            'entity'     => 'all',
            'status'     => 'running',
            'started_at' => now(),
        ]);

        try {
            $stats = app(NinoxPullService::class)->pullAll();

            $run->update([
                'status'            => 'completed',
                'finished_at'       => now(),
                'records_processed' => ($stats['push_done'] ?? 0) + ($stats['push_failed'] ?? 0),
            ]);

            $this->info('Ninox pull completed: ' . json_encode($stats));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $run->update([
                'status'        => 'failed',
                'finished_at'   => now(),
                'error_message' => $e->getMessage(),
            ]);

            $this->error('Ninox pull failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
