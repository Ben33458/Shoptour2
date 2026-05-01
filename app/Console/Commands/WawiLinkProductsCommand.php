<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retroactively fills products.wawi_artikel_id for products that were imported
 * from Ninox but never got a WaWi link (because the source_match snapshot had
 * _wawi_id = NULL at import time).
 *
 * Matching priority (same as ProductReconcileService::findWawiMatch):
 *   1. ninox_marktbestand.artnrkolabrikasten == wawi_artikel.cArtNr  (Rule 0)
 *   2. ninox_marktbestand.ean               == wawi_artikel.cBarcode (Rule 1)
 *
 * Also updates the source_match snapshot to persist the _wawi_id for future runs.
 */
class WawiLinkProductsCommand extends Command
{
    protected $signature = 'wawi:link-products
                            {--dry-run : Zeige was passieren würde ohne Änderungen zu speichern}';

    protected $description = 'Befüllt wawi_artikel_id für Produkte, die aus Ninox importiert wurden aber noch kein WaWi-Link haben.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[dry-run] Keine Änderungen werden gespeichert.');
        }

        // Products with ninox link but no wawi link
        $products = DB::table('products as p')
            ->whereNull('p.wawi_artikel_id')
            ->whereNotNull('p.ninox_artikel_id')
            ->select('p.id', 'p.produktname', 'p.ninox_artikel_id')
            ->get();

        $this->info("Unverknüpfte Produkte: {$products->count()}");

        // Build WaWi lookup maps
        $wawiByArtNr = [];
        $wawiByEan   = [];
        DB::table('wawi_artikel')->get()->each(function (object $w) use (&$wawiByArtNr, &$wawiByEan): void {
            if ($w->cArtNr && trim($w->cArtNr) !== '') {
                $wawiByArtNr[trim($w->cArtNr)] = $w;
            }
            if ($w->cBarcode && trim($w->cBarcode) !== '') {
                $wawiByEan[strtolower(trim($w->cBarcode))] = $w;
            }
        });

        $linked   = 0;
        $skipped  = 0;
        $notFound = [];

        foreach ($products as $product) {
            // Load Ninox row
            $ninox = DB::table('ninox_marktbestand')
                ->where('ninox_id', $product->ninox_artikel_id)
                ->first();

            if (! $ninox) {
                $notFound[] = $product->produktname . ' (ninox_id=' . $product->ninox_artikel_id . '): kein ninox_marktbestand Eintrag';
                $skipped++;
                continue;
            }

            $wawiMatch = null;
            $method    = '';

            // Rule 0: artnrkolabrikasten == wawi cArtNr
            $kolabri = trim((string) ($ninox->artnrkolabrikasten ?? ''));
            if ($kolabri !== '' && isset($wawiByArtNr[$kolabri])) {
                $wawiMatch = $wawiByArtNr[$kolabri];
                $method    = 'artnr';
            }

            // Rule 1: EAN exact
            if (! $wawiMatch) {
                $ean = trim((string) ($ninox->ean ?? ''));
                if ($ean !== '' && isset($wawiByEan[strtolower($ean)])) {
                    $wawiMatch = $wawiByEan[strtolower($ean)];
                    $method    = 'ean';
                }
            }

            if (! $wawiMatch) {
                $notFound[] = $product->produktname . ' (ninox_id=' . $product->ninox_artikel_id . ')';
                $skipped++;
                continue;
            }

            $this->line(sprintf(
                '  [%s] %s → %s [%s]',
                $method,
                $product->produktname,
                $wawiMatch->cName,
                $wawiMatch->cArtNr
            ));

            if (! $dryRun) {
                // Update product
                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['wawi_artikel_id' => $wawiMatch->kArtikel]);

                // Update source_match snapshot to persist _wawi_id
                $sm = DB::table('source_matches')
                    ->where('entity_type', 'product')
                    ->where('source', 'ninox')
                    ->where('source_id', (string) $product->ninox_artikel_id)
                    ->first();

                if ($sm) {
                    $snapshot = is_array($sm->source_snapshot)
                        ? $sm->source_snapshot
                        : (json_decode($sm->source_snapshot, true) ?? []);

                    $snapshot['_wawi_id'] = $wawiMatch->kArtikel;

                    DB::table('source_matches')
                        ->where('id', $sm->id)
                        ->update(['source_snapshot' => json_encode($snapshot)]);
                }

                // Ensure a wawi-side source_match exists
                $existingWawiMatch = DB::table('source_matches')
                    ->where('entity_type', 'product')
                    ->where('source', 'wawi')
                    ->where('source_id', (string) $wawiMatch->kArtikel)
                    ->first();

                if (! $existingWawiMatch) {
                    DB::table('source_matches')->insert([
                        'entity_type'     => 'product',
                        'local_id'        => $product->id,
                        'source'          => 'wawi',
                        'source_id'       => (string) $wawiMatch->kArtikel,
                        'status'          => 'confirmed',
                        'confidence'      => 100,
                        'rule'            => $method,
                        'source_snapshot' => json_encode((array) $wawiMatch),
                        'confirmed_at'    => now(),
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                }
            }

            $linked++;
        }

        $this->newLine();
        $this->info("Verknüpft:    {$linked}");
        $this->info("Kein Match:   {$skipped}");

        if (! empty($notFound)) {
            $this->newLine();
            $this->warn('Produkte ohne WaWi-Match:');
            foreach ($notFound as $name) {
                $this->line('  - ' . $name);
            }
        }

        if ($dryRun && $linked > 0) {
            $this->newLine();
            $this->warn('[dry-run] Zum Anwenden: php artisan wawi:link-products');
        }

        return self::SUCCESS;
    }
}
