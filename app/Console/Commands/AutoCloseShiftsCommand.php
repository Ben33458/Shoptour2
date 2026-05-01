<?php
namespace App\Console\Commands;

use App\Services\Employee\AutoCloseService;
use Illuminate\Console\Command;

class AutoCloseShiftsCommand extends Command
{
    protected $signature   = 'employee:auto-close';
    protected $description = 'Auto-close stale time entries (12h guard after planned shift end)';

    public function handle(AutoCloseService $service): int
    {
        $count = $service->closeStale();
        $this->info("Closed {$count} stale time entries.");
        return 0;
    }
}
