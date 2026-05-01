<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Supplier\Supplier;
use App\Models\Supplier\SupplierProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WawiSyncSupplierProductsCommand extends Command
{
    protected $signature = 'wawi:sync-supplier-products
                            {--dry-run : Nur anzeigen, nicht schreiben}
                            {--force  : Bestehende Einträge aktualisieren}';

    protected $description = 'Importiert SupplierProducts aus wawi_dbo_tliefartikel';

    // Manuelles Mapping für Fälle wo Name-Matching unzuverlässig ist
    // wawi kLieferant => supplier.id  (null = absichtlich kein Match)
    private const MANUAL_MAP = [
        2  => 447,  // Winkels Getränke Logistik → Winkels Getraenke Logistik GmbH
        4  => null, // SELGROS → kein sinnvoller Lieferant
        6  => null, // Eders Brauerei → nicht in suppliers
        10 => null, // Darmstädter Privatbrauerei → Darmstädter Heiner wäre falsch
        12 => 422,  // August Venten e.K → August Venten Getränke
        14 => 250,  // Maruhn GmbH & Co. KG → Maruhn 13053
        18 => 304,  // Privat-Brauerei Schmucker → Privat-Brauerei Schmucker
        28 => 236,  // Lekkerland Deutschland GmbH → Lekkerland SE
        33 => 294,  // Pfungstädter Brauerei GmbH → Pfungstädter Privatbrauerei
        36 => 174,  // Hassia Mineralquellen → Hassia Mineralbrunnen GmbH
        56 => 433,  // Gabi Gräf Getränke → Getränke, Wein und mehr Gabi Gräf GmbH
        58 => 219,  // Krämer GmbH → Kelterei Krämer GmbH
        82 => null, // Brauerei Faust KG → Brauerei Grohe wäre falsch
        85 => 243,  // Magaloop → Magaloop GmbH
        86 => 399,  // Vivaris Getränke GmbH & Co KG → Vivaris Getränke GmbH & Co. KG
        95 => null, // REWE-FÜR SIE Eigengeschäft → kein Lieferant
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force  = $this->option('force');

        // Schritt 1: wawi_lieferant_id-Mapping aufbauen
        $this->info('Baue Lieferanten-Mapping…');
        $wawiLieferanten = DB::table('wawi_dbo_tlieferant')->get(['kLieferant', 'cFirma']);
        $mapping = $this->buildMapping($wawiLieferanten);
        $this->line('  ' . count($mapping) . ' WaWi-Lieferanten gemappt');

        if (!$dryRun) {
            $this->persistMapping($mapping);
        }

        // Schritt 2: Lieferartikel importieren
        $this->info('Lade wawi_dbo_tliefartikel…');
        $rows = DB::table('wawi_dbo_tliefartikel as la')
            ->join('products as p', 'p.wawi_artikel_id', '=', 'la.tArtikel_kArtikel')
            ->whereNotNull('p.wawi_artikel_id')
            ->select([
                'p.id as product_id',
                'la.tLieferant_kLieferant as wawi_lieferant_id',
                'la.cLiefArtNr as lieferanten_artnr',
                'la.nStandard as ist_standard',
                'la.nMindestAbnahme as min_order_qty',
                'la.nAbnahmeIntervall as pack_size',
                'la.fEKNetto as ek_netto',
                'la.cSonstiges as bestellhinweis',
                'la.nVPEMenge as vpe_menge',
                'la.cVPEEinheit as vpe_einheit',
                'la.nLieferbar as lieferbar',
            ])
            ->get();

        $this->line('  ' . $rows->count() . ' Lieferartikel-Zeilen geladen');

        $created = $updated = $skipped = 0;

        foreach ($rows as $row) {
            $supplierId = $mapping[$row->wawi_lieferant_id] ?? null;
            if (!$supplierId) {
                $skipped++;
                continue;
            }

            $purchasePriceMilli = $row->ek_netto > 0
                ? (int) round($row->ek_netto * 1_000_000)
                : 0;

            $data = [
                'supplier_id'          => $supplierId,
                'product_id'           => $row->product_id,
                'supplier_sku'         => $row->lieferanten_artnr ?: null,
                'ist_standard_lieferant' => (bool) $row->ist_standard,
                'min_order_qty'        => max(1, (int) $row->min_order_qty),
                'pack_size'            => max(1, (int) ($row->pack_size ?: 1)),
                'purchase_price_milli' => $purchasePriceMilli,
                'bestellhinweis'       => $row->bestellhinweis ?: null,
                'active'               => true,
            ];

            if ($dryRun) {
                $created++;
                continue;
            }

            $existing = SupplierProduct::where('supplier_id', $supplierId)
                ->where('product_id', $row->product_id)
                ->first();

            if ($existing) {
                if ($force) {
                    $existing->update($data);
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                SupplierProduct::create($data);
                $created++;
            }
        }

        $this->table(
            ['Erstellt', 'Aktualisiert', 'Übersprungen'],
            [[$created, $updated, $skipped]]
        );

        if ($dryRun) {
            $this->warn('Dry-run: nichts geschrieben. Ohne --dry-run ausführen.');
        }

        return self::SUCCESS;
    }

    private function buildMapping(iterable $wawiLieferanten): array
    {
        $map = [];

        foreach ($wawiLieferanten as $w) {
            $wawiId = (int) $w->kLieferant;

            // Manuelles Mapping hat Vorrang
            if (array_key_exists($wawiId, self::MANUAL_MAP)) {
                if (self::MANUAL_MAP[$wawiId] !== null) {
                    $map[$wawiId] = self::MANUAL_MAP[$wawiId];
                }
                continue;
            }

            // Exakter Name-Match
            $supplier = Supplier::where('name', trim($w->cFirma))->first();

            // Partial-Match (erstes Wort ≥ 5 Zeichen)
            if (!$supplier) {
                $first = explode(' ', trim($w->cFirma))[0] ?? '';
                if (strlen($first) >= 5) {
                    $supplier = Supplier::where('name', 'like', $first . '%')->first();
                }
            }

            if ($supplier) {
                $map[$wawiId] = $supplier->id;
            }
        }

        return $map;
    }

    private function persistMapping(array $mapping): void
    {
        foreach ($mapping as $wawiId => $supplierId) {
            Supplier::where('id', $supplierId)
                ->whereNull('wawi_lieferant_id')
                ->update(['wawi_lieferant_id' => $wawiId]);
        }
    }
}
