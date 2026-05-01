<?php

declare(strict_types=1);

namespace App\Services\Bestandsaufnahme;

use App\Models\Bestandsaufnahme\MhdRegel;
use App\Models\Catalog\Product;
use App\Models\Warehouse;

class MhdRegelService
{
    /**
     * Ermittelt den MHD-Modus für einen Artikel in einem Lager.
     *
     * Priorität: Artikel > Lager > Kategorie > Warengruppe > Default
     * Gibt 'nie' zurück, wenn keine Regel gefunden.
     */
    public function resolveModusForProduct(Product $product, Warehouse $warehouse): string
    {
        $checks = [
            ['bezug_typ' => 'artikel',     'bezug_id' => $product->id],
            ['bezug_typ' => 'lager',       'bezug_id' => $warehouse->id],
            ['bezug_typ' => 'kategorie',   'bezug_id' => $product->category_id],
            ['bezug_typ' => 'warengruppe', 'bezug_id' => $product->warengruppe_id],
            ['bezug_typ' => 'default',     'bezug_id' => null],
        ];

        foreach ($checks as $check) {
            if ($check['bezug_id'] === null && $check['bezug_typ'] !== 'default') {
                continue;
            }

            $regel = MhdRegel::where('bezug_typ', $check['bezug_typ'])
                ->where('bezug_id', $check['bezug_id'])
                ->where('aktiv', true)
                ->first();

            if ($regel) {
                return $regel->modus;
            }
        }

        return 'nie';
    }

    /**
     * Gibt Warnschwellenwerte (Tage) für einen Artikel/Lager zurück.
     * Folgt derselben Priorität wie resolveModusForProduct.
     */
    public function resolveThresholds(Product $product, Warehouse $warehouse): array
    {
        $checks = [
            ['bezug_typ' => 'artikel',     'bezug_id' => $product->id],
            ['bezug_typ' => 'lager',       'bezug_id' => $warehouse->id],
            ['bezug_typ' => 'kategorie',   'bezug_id' => $product->category_id],
            ['bezug_typ' => 'warengruppe', 'bezug_id' => $product->warengruppe_id],
            ['bezug_typ' => 'default',     'bezug_id' => null],
        ];

        foreach ($checks as $check) {
            if ($check['bezug_id'] === null && $check['bezug_typ'] !== 'default') {
                continue;
            }

            $regel = MhdRegel::where('bezug_typ', $check['bezug_typ'])
                ->where('bezug_id', $check['bezug_id'])
                ->where('aktiv', true)
                ->first();

            if ($regel) {
                return [
                    'warnung_tage'  => $regel->warnung_tage,
                    'kritisch_tage' => $regel->kritisch_tage,
                ];
            }
        }

        return ['warnung_tage' => 30, 'kritisch_tage' => 14];
    }
}
