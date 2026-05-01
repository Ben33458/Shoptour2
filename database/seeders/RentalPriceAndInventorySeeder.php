<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Rental\RentalInventoryUnit;
use App\Models\Rental\RentalItem;
use App\Models\Rental\RentalPackagingUnit;
use App\Models\Rental\RentalPriceRule;
use App\Models\Rental\RentalTimeModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RentalPriceAndInventorySeeder extends Seeder
{
    public function run(): void
    {
        $veranstaltung = RentalTimeModel::where('rule_type', 'event')
            ->orWhere('default_for_events', true)
            ->first();

        if (! $veranstaltung) {
            $this->command->error('Kein Veranstaltungs-Zeitmodell gefunden.');
            return;
        }

        // ── 1. Preisregeln aus WaWi ───────────────────────────────────────────
        $this->command->info('Importiere Preisregeln aus WaWi...');

        $wawi = DB::table('wawi_artikel')
            ->whereNotNull('fVKNetto')
            ->where('fVKNetto', '>', 0)
            ->pluck('fVKNetto', 'cArtNr');

        // Preise für Artikel ohne WaWi-Nummer (Hardcode)
        $manualPrices = [
            'Liegestühle'               => 8.00,
            'Stehtisch mittel'          => 13.4454,
            'Stehtisch klein'           => 13.4454,
            'Glaskühlschrank mittel'    => 21.0084,
            'Fasskühlier'               => 21.0084,
            'Schmucker Moldau Seidel 0,4 l' => 0.2941,
            'Sackkarre'                 => 8.4034,
        ];

        foreach (RentalItem::with('packagingUnits')->get() as $item) {
            $nettoEur = null;

            // WaWi-Preis per Stück
            if ($item->article_number && isset($wawi[$item->article_number])) {
                $nettoEur = (float) $wawi[$item->article_number];
            }

            // Manueller Preis wenn kein WaWi-Eintrag
            if ($nettoEur === null && isset($manualPrices[$item->name])) {
                $nettoEur = $manualPrices[$item->name];
            }

            if ($nettoEur === null) {
                continue;
            }

            if ($item->inventory_mode === RentalItem::MODE_PACKAGING) {
                // Preis pro Verpackungseinheit = Stückpreis × Stück/VPE
                $activeUnits = $item->packagingUnits->where('active', true);
                foreach ($activeUnits as $pu) {
                    $pricePerPack = $nettoEur * $pu->pieces_per_pack;
                    RentalPriceRule::firstOrCreate(
                        [
                            'rental_item_id'       => $item->id,
                            'rental_time_model_id' => $veranstaltung->id,
                            'packaging_unit_id'    => $pu->id,
                        ],
                        [
                            'price_net_milli' => (int) round($pricePerPack * 1_000_000),
                            'min_quantity'    => 1,
                            'price_type'      => 'per_pack',
                            'active'          => true,
                        ]
                    );
                }
                // Auch Stückpreis als Fallback (für Artikel ohne VPE)
                RentalPriceRule::firstOrCreate(
                    [
                        'rental_item_id'       => $item->id,
                        'rental_time_model_id' => $veranstaltung->id,
                        'packaging_unit_id'    => null,
                    ],
                    [
                        'price_net_milli' => (int) round($nettoEur * 1_000_000),
                        'min_quantity'    => 1,
                        'price_type'      => 'per_item',
                        'active'          => true,
                    ]
                );
            } else {
                // unit_based / quantity_based: Preis pro Stück/Gerät
                RentalPriceRule::firstOrCreate(
                    [
                        'rental_item_id'       => $item->id,
                        'rental_time_model_id' => $veranstaltung->id,
                        'packaging_unit_id'    => null,
                    ],
                    [
                        'price_net_milli' => (int) round($nettoEur * 1_000_000),
                        'min_quantity'    => 1,
                        'price_type'      => 'per_item',
                        'active'          => true,
                    ]
                );
            }
        }

        // ── 2. Bestände setzen ────────────────────────────────────────────────
        $this->command->info('Setze Bestände...');

        // quantity_based: 100 als Standard wenn kein Bestand gepflegt
        RentalItem::where('inventory_mode', RentalItem::MODE_QUANTITY)
            ->whereNull('total_quantity')
            ->update(['total_quantity' => 100]);

        $this->command->line('  quantity_based: Standardbestand 100 gesetzt.');

        // packaging_based: Standard ceil(100 / pieces_per_pack) wenn 0
        foreach (RentalPackagingUnit::where('available_packs', 0)->get() as $pu) {
            $defaultPacks = max(1, (int) ceil(100 / max(1, $pu->pieces_per_pack)));
            $pu->update(['available_packs' => $defaultPacks]);
        }

        $this->command->line('  packaging_based: Standardbestände gesetzt.');

        // unit_based: 1 Inventareinheit anlegen wenn noch keine vorhanden
        $counter = 0;
        foreach (RentalItem::where('inventory_mode', RentalItem::MODE_UNIT)->get() as $item) {
            if ($item->inventoryUnits()->count() > 0) {
                continue;
            }

            // Versuche Anzahl aus Ninox zu ermitteln
            $ninoxCount = $this->ninoxUnitCount($item);

            for ($i = 1; $i <= $ninoxCount; $i++) {
                $invNr = $item->article_number
                    ? sprintf('%s-%03d', $item->article_number, $i)
                    : sprintf('INV-%04d-%03d', $item->id, $i);

                RentalInventoryUnit::create([
                    'rental_item_id'   => $item->id,
                    'inventory_number' => $invNr,
                    'title'            => $ninoxCount > 1 ? $item->name . ' #' . $i : $item->name,
                    'status'           => RentalInventoryUnit::STATUS_AVAILABLE,
                ]);
                $counter++;
            }
        }

        $this->command->line("  unit_based: $counter Inventareinheiten angelegt.");
        $this->command->info('Fertig!');
    }

    /**
     * Ermittle die Anzahl physischer Einheiten für unit_based Artikel aus Ninox.
     * Fallback: 1.
     */
    private function ninoxUnitCount(RentalItem $item): int
    {
        // Bekannte Bestände aus Ninox/Kommentaren
        $known = [
            'Kühlwagen'                           => 1, // 1 Anhänger (besteht aus 4 Unterteilen)
            'Zapfanlage Durchlaufkühler 1-leitig' => 1,
            'Zapfanlage Durchlaufkühler 2-leitig' => 1,
            'Durchlaufkühler Grohe'               => 1,
            'Schankwagen'                         => 1,
            'Festtheke 2,00 m'                    => 1,
            'Sackkarre'                           => 1,
            'Fasskühlier'                         => 1,
        ];

        return $known[$item->name] ?? 1;
    }
}
