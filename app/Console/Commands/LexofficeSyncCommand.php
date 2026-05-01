<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Pricing\AppSetting;
use App\Services\Integrations\LexofficeImport;
use Illuminate\Console\Command;

/**
 * Hourly incremental sync: contacts + vouchers + reference data.
 * Payments are handled separately via lexoffice:import-payments.
 */
class LexofficeSyncCommand extends Command
{
    protected $signature = 'lexoffice:sync
                            {--full : Also sync articles, payment-conditions, categories, layouts, templates, countries}';

    protected $description = 'Sync Lexoffice contacts and vouchers (runs hourly via scheduler)';

    public function handle(LexofficeImport $import): int
    {
        if (AppSetting::get('lexoffice.enabled', '0') !== '1') {
            $this->line('Lexoffice sync disabled — skipping.');
            return self::SUCCESS;
        }

        $this->info('Lexoffice sync started');

        $this->syncStep('Contacts', fn () => $import->importContacts(null, null));
        $this->syncStep('Vouchers', fn () => $import->importVouchers(null));

        if ($this->option('full')) {
            $this->syncStep('Articles',           fn () => $import->importArticles(null));
            $this->syncStep('Payment conditions', fn () => $import->importPaymentConditions(null));
            $this->syncStep('Posting categories', fn () => $import->importPostingCategories(null));
            $this->syncStep('Print layouts',      fn () => $import->importPrintLayouts(null));
            $this->syncStep('Recurring templates',fn () => $import->importRecurringTemplates(null));
            $this->syncStep('Countries',          fn () => $import->importCountries());
        }

        $this->info('Lexoffice sync done');
        return self::SUCCESS;
    }

    private function syncStep(string $label, callable $fn): void
    {
        try {
            $stats = $fn();
            $this->line(sprintf(
                '  %-22s created=%-5d updated=%-5d total=%d',
                $label . ':',
                $stats['created'] ?? 0,
                $stats['updated'] ?? 0,
                $stats['total']   ?? ($stats['created'] ?? 0) + ($stats['updated'] ?? 0),
            ));
        } catch (\Throwable $e) {
            $this->warn("  {$label}: FAILED — " . $e->getMessage());
        }
    }
}
