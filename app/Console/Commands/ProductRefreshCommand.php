<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ninox\NinoxApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Refreshes a single product's raw data from Ninox and/or the local WaWi mirror,
 * then re-runs the downstream sync steps (prices, leergut, linking).
 *
 * Usage examples:
 *   php artisan product:refresh 80                  # Ninox-ID 80 aktualisieren
 *   php artisan product:refresh 80 --wawi           # zusätzlich WaWi-Folgeschritte
 *   php artisan product:refresh --ninox-id=80 --wawi-id=15328   # beide getrennt angeben
 */
class ProductRefreshCommand extends Command
{
    protected $signature = 'product:refresh
                            {ninoxId?           : Ninox Record-ID (ninox_marktbestand.ninox_id)}
                            {--ninox-id=        : Alternativ: Ninox-ID als Option}
                            {--wawi-id=         : WaWi kArtikel — WaWi-Folgeschritte für diesen Artikel}
                            {--wawi             : WaWi-Folgeschritte (Preise, Leergut) nach dem Ninox-Refresh}
                            {--dry-run          : Zeige was passieren würde ohne Änderungen zu speichern}';

    protected $description = 'Aktualisiert einen einzelnen Artikel aus Ninox-API und/oder WaWi-Spiegel.';

    // Ninox: Tabellen-ID für "Marktbestand" in der kehr-Datenbank
    private const MARKTBESTAND_TABLE_ID = 'Z';

    public function handle(): int
    {
        $dryRun  = (bool) $this->option('dry-run');
        $ninoxId = $this->argument('ninoxId') ?? $this->option('ninox-id');
        $wawiId  = $this->option('wawi-id');
        $doWawi  = $this->option('wawi') || $wawiId !== null;

        if ($ninoxId === null && $wawiId === null) {
            $this->error('Bitte mindestens eine Ninox-ID oder --wawi-id angeben.');
            $this->line('  Beispiel: php artisan product:refresh 80');
            $this->line('  Beispiel: php artisan product:refresh 80 --wawi');
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('[dry-run] Keine Änderungen werden gespeichert.');
        }

        // ── Schritt 1: Ninox-Datensatz neu laden ─────────────────────────────
        if ($ninoxId !== null) {
            $this->refreshFromNinox((string) $ninoxId, $dryRun);
        }

        // ── Schritt 2: WaWi-Folgeschritte ────────────────────────────────────
        if ($doWawi && ! $dryRun) {
            $this->refreshWawiDownstream($ninoxId, $wawiId);
        }

        return self::SUCCESS;
    }

    // =========================================================================

    private function refreshFromNinox(string $ninoxId, bool $dryRun): void
    {
        $this->info("Lade Ninox-Datensatz #{$ninoxId} ...");

        try {
            $client = NinoxApiClient::make('kehr');
            $record = $client->getRecord(self::MARKTBESTAND_TABLE_ID, $ninoxId);
        } catch (\Throwable $e) {
            $this->error('Ninox API Fehler: ' . $e->getMessage());
            return;
        }

        if (empty($record) || ! isset($record['id'])) {
            $this->error("Datensatz #{$ninoxId} nicht in Ninox gefunden.");
            return;
        }

        // Map API response → DB row (same logic as NinoxImport)
        $row = [
            'ninox_id'         => $record['id'],
            'ninox_sequence'   => $record['sequence'] ?? null,
            'ninox_created_at' => isset($record['createdAt'])
                ? date('Y-m-d H:i:s', strtotime($record['createdAt']))
                : null,
            'ninox_updated_at' => isset($record['modifiedAt'])
                ? date('Y-m-d H:i:s', strtotime($record['modifiedAt']))
                : null,
        ];

        foreach ($record['fields'] ?? [] as $k => $v) {
            $col = $this->sanitize($k);
            if (in_array($col, ['ninox_id', 'ninox_sequence', 'ninox_created_at', 'ninox_updated_at'], true)) {
                $col .= '_f';
            }
            $row[$col] = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v;
        }

        // Show what we got
        $name    = $row['artikelname'] ?? '(kein Name)';
        $kolabri = $row['artnrkolabrikasten'] ?? '—';
        $this->line("  Name:    {$name}");
        $this->line("  Kolabri: {$kolabri}");

        if ($dryRun) {
            $this->line('  [dry-run] Würde ninox_marktbestand upserten.');
            return;
        }

        // Check if the record already exists
        $existing = DB::table('ninox_marktbestand')->where('ninox_id', $record['id'])->first();

        if ($existing) {
            DB::table('ninox_marktbestand')
                ->where('ninox_id', $record['id'])
                ->update($row);
            $this->info("  ninox_marktbestand #{$ninoxId} aktualisiert.");
        } else {
            DB::table('ninox_marktbestand')->insert($row);
            $this->info("  ninox_marktbestand #{$ninoxId} neu angelegt.");
        }
    }

    private function refreshWawiDownstream(?string $ninoxId, ?string $wawiId): void
    {
        // Resolve wawi_artikel_id if not given explicitly
        if ($wawiId === null && $ninoxId !== null) {
            $wawiId = DB::table('products')
                ->where('ninox_artikel_id', $ninoxId)
                ->value('wawi_artikel_id');
        }

        if ($wawiId === null) {
            // Try to re-link first
            $this->info('Kein WaWi-Link bekannt — starte wawi:link-products ...');
            Artisan::call('wawi:link-products', [], $this->output);
            return;
        }

        $wawi = DB::table('wawi_artikel')->where('kArtikel', $wawiId)->first();
        if (! $wawi) {
            $this->warn("wawi_artikel #{$wawiId} nicht in lokalem Spiegel gefunden.");
            $this->line('  Hinweis: WaWi-Daten werden per Push von JTL importiert.');
            return;
        }

        $this->info("WaWi-Artikel gefunden: {$wawi->cName} [artNr={$wawi->cArtNr}]");

        // Re-sync prices for this product
        $product = DB::table('products')->where('wawi_artikel_id', $wawiId)->first();
        if ($product && $wawi->fVKNetto > 0) {
            $taxRate = DB::table('tax_rates')->where('id', $product->tax_rate_id)->value('rate') ?? 19;
            $netto   = (float) $wawi->fVKNetto;
            $brutto  = $netto * (1 + $taxRate / 100);
            DB::table('products')->where('id', $product->id)->update([
                'base_price_net_milli'   => (int) round($netto * 1_000_000),
                'base_price_gross_milli' => (int) round($brutto * 1_000_000),
                'updated_at'             => now(),
            ]);
            $this->info(sprintf(
                '  Preis aktualisiert: %.2f € netto → %.2f € brutto',
                $netto,
                $brutto
            ));
        }
    }

    private function sanitize(string $name): string
    {
        $name = strtr($name, ['ä'=>'ae','ö'=>'oe','ü'=>'ue','Ä'=>'ae','Ö'=>'oe','Ü'=>'ue','ß'=>'ss']);
        $name = preg_replace('/[^a-zA-Z0-9]+/', '_', $name);
        return strtolower(trim($name, '_'));
    }
}
