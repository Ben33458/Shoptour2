<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ninox\NinoxImportService;
use App\Services\Ninox\NinoxPullService;
use Illuminate\Console\Command;

class NinoxFullImportCommand extends Command
{
    protected $signature   = 'ninox:full-import';
    protected $description = 'Full Ninox import: raw data (kehr + alt) → ninox_* tables → tours/tour_stops/orders/goods_receipts';

    public function handle(NinoxImportService $importService, NinoxPullService $pullService): int
    {
        $this->info('Step 1/3: Importing raw Ninox data...');

        $result  = $importService->runAll(null);
        $kehrRun = $result['kehr'];
        $altRun  = $result['alt'];
        $sync    = $result['sync'];

        $this->line("  kehr: {$kehrRun->status} | {$kehrRun->records_imported} records");
        $this->line("  alt:  {$altRun->status} | {$altRun->records_imported} records");

        if (isset($sync['error'])) {
            $this->error("  Sync failed: {$sync['error']}");
        } else {
            $this->line("  Sync: {$sync['synced_tables']} tables, {$sync['synced_records']} rows updated");
        }

        $this->info('Step 2/3: Syncing tours and tour_stops...');
        $toursImported = $pullService->pullTours();
        $this->line("  Tours imported/updated: {$toursImported}");

        $this->info('Step 3/4: Syncing standalone orders (without tour)...');
        $ordersResult = $pullService->pullStandaloneOrders();
        $this->line("  Orders imported: {$ordersResult['imported']}  Failed: {$ordersResult['failed']}");

        $this->info('Step 4/4: Syncing goods receipts (Anlieferungen) from Ninox...');
        $grResult = $pullService->pullGoodsReceipts();
        $this->line("  Anlieferungen: {$grResult['imported']} importiert, {$grResult['skipped']} übersprungen (kein Lieferanten-Match)");

        $ok = $kehrRun->status === 'completed' && $altRun->status === 'completed';
        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
