<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Integrations\LexofficeImport;
use Illuminate\Console\Command;

class LexofficeImportVouchers extends Command
{
    protected $signature = 'lexoffice:import-vouchers
                            {--types=       : Comma-separated voucher types to import (default: all)}
                            {--full-resync  : Ignore the incremental cursor and re-fetch all vouchers}';

    protected $description = 'Import Lexoffice vouchers (incremental by default, --full-resync to reimport all)';

    public function handle(LexofficeImport $import): int
    {
        $typesOption = $this->option('types');
        $typesFilter = $typesOption ? explode(',', $typesOption) : null;
        $fullResync  = (bool) $this->option('full-resync');

        if ($fullResync) {
            $this->warn('Full resync — ignoring incremental cursor, fetching all vouchers…');
        } elseif ($typesFilter) {
            $this->info('Incremental import for types: ' . implode(', ', $typesFilter));
        } else {
            $this->info('Incremental import (all types, changes since last run)…');
        }

        $stats = $import->importVouchers(null, $typesFilter, $fullResync);

        $this->line(sprintf(
            'Done — total=%d  created=%d  updated=%d  errors=%d',
            $stats['total'],
            $stats['created'],
            $stats['updated'],
            $stats['errors'],
        ));

        return self::SUCCESS;
    }
}
