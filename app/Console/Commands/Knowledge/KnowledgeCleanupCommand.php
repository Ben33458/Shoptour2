<?php

declare(strict_types=1);

namespace App\Console\Commands\Knowledge;

use App\Models\Knowledge\KnowledgeSyncRun;
use Illuminate\Console\Command;

class KnowledgeCleanupCommand extends Command
{
    protected $signature = 'knowledge:cleanup {--days=90 : Delete sync runs older than N days}';
    protected $description = 'Clean up old knowledge sync run logs';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $deleted = KnowledgeSyncRun::where('started_at', '<', now()->subDays($days))->delete();
        $this->info("Cleaned up {$deleted} sync run records older than {$days} days.");
        return self::SUCCESS;
    }
}
