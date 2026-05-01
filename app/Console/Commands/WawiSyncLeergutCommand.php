<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Catalog\ProductLeergut;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WawiSyncLeergutCommand extends Command
{
    protected $signature = 'wawi:sync-leergut {--dry-run : Zeige was passieren würde ohne Änderungen zu speichern}';

    protected $description = 'Synchronisiert Leergut-Zuordnungen aus WaWi-Attributen (PfandARtNr) in die interne product_leergut-Tabelle.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[dry-run] Keine Änderungen werden gespeichert.');
        }

        // Alle Produkte mit WaWi-Artikel und PfandARtNr-Attribut laden
        $rows = DB::table('products as p')
            ->join('wawi_artikel as wa', 'wa.cArtNr', '=', 'p.artikelnummer')
            ->join('wawi_artikel_attribute as waa', function ($j) {
                $j->on('waa.kArtikel', '=', 'wa.kArtikel')
                  ->where('waa.cAttributName', '=', 'PfandARtNr');
            })
            ->whereNotNull('waa.cWertVarchar')
            ->select('p.id as product_id', 'waa.cWertVarchar as leergut_art_nr')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('Keine Produkte mit PfandARtNr-Attribut gefunden. Abbruch.');
            return self::SUCCESS;
        }

        $this->info("Produkte mit PfandARtNr-Attribut: {$rows->count()}");

        // Leergut-Artikel aus wawi_artikel laden (cName LIKE 'Leergut%')
        $leergutArtNrs = $rows->pluck('leergut_art_nr')->unique()->values()->all();

        $leergutArtikel = DB::table('wawi_artikel')
            ->whereIn('cArtNr', $leergutArtNrs)
            ->where('cName', 'like', 'Leergut%')
            ->get(['cArtNr', 'cName', 'fVKNetto'])
            ->keyBy('cArtNr');

        $upserted = 0;
        $skipped  = 0;
        $now      = now();

        foreach ($rows as $row) {
            $leergut = $leergutArtikel->get($row->leergut_art_nr);

            if (! $leergut) {
                $skipped++;
                continue;
            }

            $netMilli   = (int) round((float) $leergut->fVKNetto * 1_000_000);
            $grossMilli = (int) intdiv($netMilli * 11_900 + 5_000, 10_000); // 19% integer-sicher

            if ($dryRun) {
                $this->line("  product_id={$row->product_id} → {$leergut->cArtNr} \"{$leergut->cName}\" ({$netMilli} milli-cent netto)");
                $upserted++;
                continue;
            }

            ProductLeergut::updateOrCreate(
                ['product_id' => $row->product_id],
                [
                    'leergut_art_nr'          => $leergut->cArtNr,
                    'leergut_name'            => $leergut->cName,
                    'unit_price_net_milli'    => $netMilli,
                    'unit_price_gross_milli'  => $grossMilli,
                    'synced_at'               => $now,
                ]
            );
            $upserted++;
        }

        $this->info('');
        $this->info('Ergebnis:');
        $this->table(
            ['', 'Anzahl'],
            [
                ['Leergut-Zuordnungen gespeichert', $upserted],
                ['Übersprungen (kein Leergut-Artikel)', $skipped],
            ]
        );

        return self::SUCCESS;
    }
}
