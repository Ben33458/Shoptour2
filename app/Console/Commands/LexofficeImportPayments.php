<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Integrations\LexofficeImport;
use Illuminate\Console\Command;

class LexofficeImportPayments extends Command
{
    protected $signature = 'lexoffice:import-payments
                            {--batch=100 : Vouchers to process per iteration (0 = all at once)}
                            {--reset : Clear payments_fetched_at so all vouchers are re-fetched}';

    protected $description = 'Import payment history for all Lexoffice vouchers into lexoffice_payments';

    public function handle(LexofficeImport $import): int
    {
        if ($this->option('reset')) {
            \Illuminate\Support\Facades\DB::table('lexoffice_vouchers')
                ->whereNotNull('payments_fetched_at')
                ->update(['payments_fetched_at' => null]);
            $this->info('payments_fetched_at reset on all vouchers.');
        }

        $batch = (int) $this->option('batch');
        $round = 0;

        do {
            $round++;
            $this->info("Round {$round} — fetching up to {$batch} vouchers…");

            $stats = $import->importPayments(null, $batch);

            $this->line(sprintf(
                '  processed=%d  created=%d  updated=%d  skipped=%d  errors=%d  remaining=%d',
                $stats['processed'],
                $stats['created'],
                $stats['updated'],
                $stats['skipped'],
                $stats['errors'],
                $stats['remaining'],
            ));

            if ($stats['processed'] === 0) {
                break;
            }

        } while ($batch > 0 && $stats['remaining'] > 0);

        $this->info('Done.');

        return self::SUCCESS;
    }
}
