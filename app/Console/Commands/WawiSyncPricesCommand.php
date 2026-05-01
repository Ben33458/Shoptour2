<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WawiSyncPricesCommand extends Command
{
    protected $signature = 'wawi:sync-prices {--dry-run : Zeige was passieren würde ohne Änderungen zu speichern}';

    protected $description = 'Synchronisiert Verkaufspreise aus wawi_artikel.fVKNetto in die products-Tabelle.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[dry-run] Keine Änderungen werden gespeichert.');
        }

        // Alle Produkte mit WaWi-Verknüpfung und gültigem Preis laden
        $rows = DB::table('products as p')
            ->join('wawi_artikel as wa', 'wa.kArtikel', '=', 'p.wawi_artikel_id')
            ->leftJoin('tax_rates as tr', 'tr.id', '=', 'p.tax_rate_id')
            ->whereNotNull('wa.fVKNetto')
            ->where('wa.fVKNetto', '>', 0)
            ->select(
                'p.id',
                'p.artikelnummer',
                'p.base_price_net_milli as current_net_milli',
                'wa.fVKNetto',
                DB::raw('COALESCE(tr.rate_basis_points, 1900) as tax_bp')
            )
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('Keine Produkte mit wawi_artikel_id + fVKNetto gefunden.');
            return self::SUCCESS;
        }

        $this->info("Produkte mit WaWi-Preis: {$rows->count()}");

        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $netMilli   = (int) round((float) $row->fVKNetto * 1_000_000);
            // Skala tax_rates.rate_basis_points: 10_000 = 100 % (1_900 = 19 %, 700 = 7 %)
            $grossMilli = (int) intdiv($netMilli * (10_000 + (int) $row->tax_bp) + 5_000, 10_000);

            if ($netMilli === (int) $row->current_net_milli) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $currentEur = number_format($row->current_net_milli / 1_000_000, 4, ',', '.');
                $newEur     = number_format($netMilli / 1_000_000, 4, ',', '.');
                $grossEur   = number_format($grossMilli / 1_000_000, 2, ',', '.');
                $this->line("  [{$row->artikelnummer}] netto: {$currentEur} € → {$newEur} € | brutto: {$grossEur} €");
                $updated++;
                continue;
            }

            DB::table('products')
                ->where('id', $row->id)
                ->update([
                    'base_price_net_milli'   => $netMilli,
                    'base_price_gross_milli' => $grossMilli,
                ]);
            $updated++;
        }

        $this->info('');
        $this->info('Ergebnis:');
        $this->table(
            ['', 'Anzahl'],
            [
                ['Preise aktualisiert', $updated],
                ['Bereits aktuell',     $skipped],
            ]
        );

        return self::SUCCESS;
    }
}
