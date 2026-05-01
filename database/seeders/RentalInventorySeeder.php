<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Rental\RentalItem;
use App\Models\Rental\RentalItemCategory;
use App\Models\Rental\RentalPackagingUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RentalInventorySeeder extends Seeder
{
    public function run(): void
    {
        // ── Kategorien ────────────────────────────────────────────────────────
        $categoryDefs = [
            ['name' => 'Gläser',                        'slug' => 'glaeser',                    'sort_order' => 10],
            ['name' => 'Kühlgeräte',                    'slug' => 'kuehlgeraete',               'sort_order' => 20],
            ['name' => 'Stehen & Sitzen',               'slug' => 'stehen-sitzen',              'sort_order' => 30],
            ['name' => 'Zapfanlagen & Schankzubehör',   'slug' => 'zapfanlagen-schankzubehoer', 'sort_order' => 40],
            ['name' => 'Schirme',                       'slug' => 'schirme',                    'sort_order' => 50],
            ['name' => 'Sonstiges',                     'slug' => 'sonstiges-leih',             'sort_order' => 60],
        ];

        $cats = [];
        foreach ($categoryDefs as $def) {
            $cats[$def['name']] = RentalItemCategory::firstOrCreate(
                ['slug' => $def['slug']],
                array_merge($def, ['active' => true])
            );
        }

        // ── Leihartikel ───────────────────────────────────────────────────────
        // [article_number|null, name, category, inventory_mode, transport_class, total_quantity|null, unit_label, internal_notes|null]
        $itemDefs = [
            // Gläser — packaging_based (Stiegen)
            ['40019', 'Bierglas 0,4 l',              'Gläser', 'packaging_based', 'small', null, 'Stück', 'Ninox: 24er Stiege; VK 10,08 € netto/Stiege; Schadenswert 2 €/Stück'],
            ['40027', 'Apfelweinglas 0,2 l',          'Gläser', 'packaging_based', 'small', null, 'Stück', 'Ninox: 24er Stiege; VK 10,08 € netto/Stiege; Schadenswert 2 €/Stück'],
            ['40025', 'Kölschglas 0,2 l',             'Gläser', 'packaging_based', 'small', null, 'Stück', 'Ninox: 40er Stiege; Bestand 492 Gläser / 12 Stiegen; Schadenswert 2 €/Stück'],
            ['40022', 'Weinglas 0,2 l',               'Gläser', 'packaging_based', 'small', null, 'Stück', 'Ninox: 40er Stiege; Bestand 96 Gläser / 2 Stiegen'],
            ['40020', 'Weizenbierglas 0,5 l',         'Gläser', 'packaging_based', 'small', null, 'Stück', 'Ninox: 24er Stiege'],
            ['40021', 'Weintonne 0,2 l',              'Gläser', 'packaging_based', 'small', null, 'Stück', 'Ninox: 30er Stiege; VK 6,30 € netto/Stiege'],
            ['40023', 'Sektglas 0,1 l',               'Gläser', 'packaging_based', 'small', null, 'Stück', 'Ninox: 40er Stiege; VK 8,40 € netto/Stiege'],
            ['40024', 'Sektglas 0,05 l',              'Gläser', 'quantity_based',  'small', null, 'Stück', 'Nur in WaWi; Gebindegröße unbekannt'],
            ['40026', 'Schnapsglas 0,1 l',            'Gläser', 'quantity_based',  'small', null, 'Stück', 'Ninox: ninox_id 23; Gebindegröße unbekannt'],
            ['40028', 'Kölschträger',                  'Gläser', 'quantity_based',  'small',   20, 'Stück', 'Ninox: Köln-Kranz; Bestand 20; Schadenswert 25 €/Stück'],
            [null,    'Schmucker Moldau Seidel 0,4 l', 'Gläser', 'packaging_based', 'small', null, 'Stück', 'Ninox: ninox_id 35; 6er Stiege; Bestand 36 Gläser / 6 Stiegen'],

            // Kühlgeräte — unit_based
            ['40000', 'Kühlschrank weiß',              'Kühlgeräte', 'unit_based', 'truck', null, 'Stück', null],
            ['40001', 'Glastürkühlschrank groß',       'Kühlgeräte', 'unit_based', 'truck', null, 'Stück', null],
            ['40002', 'Glastürkühlschrank Pfungstädter','Kühlgeräte', 'unit_based','truck', null, 'Stück', null],
            ['40003', 'Kühltruhe',                     'Kühlgeräte', 'unit_based', 'truck', null, 'Stück', null],
            ['40004', 'Kühlwagen',                     'Kühlgeräte', 'unit_based', 'truck', null, 'Stück', 'Ninox: Kühlanhänger Alt/Hassia/Kehr/Schmucker als Bestandteile (ninox_id 1-4)'],
            [null,    'Glaskühlschrank mittel',        'Kühlgeräte', 'unit_based', 'truck', null, 'Stück', 'Ninox: ninox_id 31'],
            [null,    'Fasskühlier',                   'Kühlgeräte', 'unit_based', 'truck', null, 'Stück', 'Ninox: ninox_id 39; Bestand 1'],

            // Stehen & Sitzen
            ['40005', 'Festzeltgarnitur (1 Tisch, 2 Bänke)', 'Stehen & Sitzen', 'quantity_based', 'truck', 90, 'Stück', 'Ninox: Bestand 90; VK 8,40 € netto; Schadenswert 100 €/Stück'],
            ['40006', 'Festzelttisch',                 'Stehen & Sitzen', 'quantity_based', 'truck', null, 'Stück', null],
            ['40007', 'Festzeltbank',                  'Stehen & Sitzen', 'quantity_based', 'truck', null, 'Stück', null],
            ['40008', 'Stehtisch groß',                'Stehen & Sitzen', 'quantity_based', 'normal', null, 'Stück', null],
            [null,    'Stehtisch mittel',              'Stehen & Sitzen', 'quantity_based', 'normal', null, 'Stück', 'Ninox: ninox_id 30'],
            [null,    'Stehtisch klein',               'Stehen & Sitzen', 'quantity_based', 'normal', null, 'Stück', 'Ninox: ninox_id 29'],
            [null,    'Liegestühle',                   'Stehen & Sitzen', 'quantity_based', 'normal',   7, 'Stück', 'Ninox: Trade-Islands; Bestand 7; VK 8,00 € netto; Schadenswert 60 €/Stück'],
            ['58869', 'Pavillon 3x3m',                 'Stehen & Sitzen', 'unit_based',   'truck', null, 'Stück', null],

            // Zapfanlagen & Schankzubehör — unit_based
            ['40032', 'Zapfanlage Durchlaufkühler 1-leitig', 'Zapfanlagen & Schankzubehör', 'unit_based', 'truck', null, 'Stück', null],
            ['40033', 'Zapfanlage Durchlaufkühler 2-leitig', 'Zapfanlagen & Schankzubehör', 'unit_based', 'truck', null, 'Stück', null],
            ['40034', 'Durchlaufkühler Grohe',         'Zapfanlagen & Schankzubehör', 'unit_based', 'truck', null, 'Stück', null],
            ['40031', 'Schankwagen',                   'Zapfanlagen & Schankzubehör', 'unit_based', 'truck', null, 'Stück', null],

            // Schirme
            ['40013', 'Sonnenschirm groß',             'Schirme', 'unit_based', 'normal', null, 'Stück', null],
            ['40012', 'Sonnenschirm klein',             'Schirme', 'unit_based', 'normal', null, 'Stück', null],

            // Sonstiges
            ['40010', 'Spüle',                         'Sonstiges', 'unit_based',    'truck',  null, 'Stück', null],
            ['40029', 'Flaschöffner Holzgriff',        'Sonstiges', 'quantity_based','small',  null, 'Stück', null],
            ['7005',  'Festtheke 2,00 m',              'Sonstiges', 'unit_based',    'truck',  null, 'Stück', 'Ninox: artnrkehr 7005'],
            [null,    'Sackkarre',                     'Sonstiges', 'unit_based',    'normal', null, 'Stück', 'Mietpreis: 10,00 € pro Mietzeitraum'],
        ];

        $createdItems = [];
        foreach ($itemDefs as [$artNr, $name, $catName, $mode, $transport, $qty, $unitLabel, $notes]) {
            $attributes = $artNr !== null
                ? ['article_number' => $artNr]
                : ['name' => $name];

            // Unique slug (handle potential collisions)
            $existingByAttr = RentalItem::where($attributes)->first();
            if ($existingByAttr) {
                $createdItems[$name] = $existingByAttr;
                continue;
            }

            $slug = $baseSlug = Str::slug($name);
            $i    = 2;
            while (RentalItem::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $i++;
            }

            $createdItems[$name] = RentalItem::create(array_merge($attributes, [
                'name'                 => $name,
                'slug'                 => $slug,
                'category_id'          => $cats[$catName]->id,
                'inventory_mode'       => $mode,
                'transport_class'      => $transport,
                'total_quantity'       => $qty,
                'unit_label'           => $unitLabel,
                'internal_notes'       => $notes,
                'active'               => true,
                'visible_in_shop'      => false,
                'requires_event_order' => true,
                'billing_mode'         => 'per_rental_period',
                'allow_overbooking'    => false,
            ]));
        }

        // ── Verpackungseinheiten (für packaging_based mit bekanntem Gebinde) ──
        // [item_name, label, pieces_per_pack, available_packs]
        $packagingDefs = [
            ['Bierglas 0,4 l',               '24er Stiege', 24,  0],
            ['Apfelweinglas 0,2 l',          '24er Stiege', 24,  0],
            ['Kölschglas 0,2 l',             '40er Stiege', 40, 12],
            ['Weinglas 0,2 l',               '40er Stiege', 40,  2],
            ['Weizenbierglas 0,5 l',         '24er Stiege', 24,  0],
            ['Weintonne 0,2 l',              '30er Stiege', 30,  0],
            ['Sektglas 0,1 l',               '40er Stiege', 40,  0],
            ['Schmucker Moldau Seidel 0,4 l', '6er Stiege',  6,  6],
        ];

        foreach ($packagingDefs as [$itemName, $label, $pieces, $packs]) {
            if (!isset($createdItems[$itemName])) {
                continue;
            }
            RentalPackagingUnit::firstOrCreate(
                ['rental_item_id' => $createdItems[$itemName]->id, 'label' => $label],
                ['pieces_per_pack' => $pieces, 'available_packs' => $packs, 'sort_order' => 0, 'active' => true]
            );
        }
    }
}
