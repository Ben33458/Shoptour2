<?php

declare(strict_types=1);

namespace App\Services\Bestandsaufnahme;

use App\Models\Bestandsaufnahme\LadenhueterRegel;
use App\Models\Catalog\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\StockMovement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LadenhueterService
{
    /**
     * Gibt alle Ladenhüter zurück basierend auf den konfigurierten Regeln.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getLadenhueter(): \Illuminate\Support\Collection
    {
        $regel = LadenhueterRegel::where('aktiv', true)->first();

        if (! $regel) {
            return collect();
        }

        $ohneVerkaufSeit = now()->subDays($regel->tage_ohne_verkauf);
        $maxLagerdauer   = now()->subDays($regel->max_lagerdauer_tage);

        // Artikel ohne Verkauf seit N Tagen
        $ohneVerkauf = DB::table('stock_movements')
            ->where('movement_type', StockMovement::TYPE_SALE_OUT)
            ->where('created_at', '>=', $ohneVerkaufSeit)
            ->pluck('product_id')
            ->unique();

        $stocks = ProductStock::with(['product:id,artikelnummer,produktname,active', 'warehouse:id,name'])
            ->where('quantity', '>', 0)
            ->whereNotIn('product_id', $ohneVerkauf)
            ->get();

        return $stocks->map(function (ProductStock $s) use ($regel) {
            $grund = $this->ermittleGrund($s, $regel);
            if (! $grund) {
                return null;
            }
            return [
                'product_id'   => $s->product_id,
                'warehouse_id' => $s->warehouse_id,
                'product'      => $s->product,
                'warehouse'    => $s->warehouse,
                'bestand'      => $s->quantity,
                'grund'        => $grund,
            ];
        })->filter()->values();
    }

    private function ermittleGrund(ProductStock $stock, LadenhueterRegel $regel): ?string
    {
        $letzterVerkauf = StockMovement::where('product_id', $stock->product_id)
            ->where('warehouse_id', $stock->warehouse_id)
            ->where('movement_type', StockMovement::TYPE_SALE_OUT)
            ->max('created_at');

        if ($letzterVerkauf === null || Carbon::parse($letzterVerkauf)->lt(now()->subDays($regel->tage_ohne_verkauf))) {
            return 'kein_verkauf_seit_' . $regel->tage_ohne_verkauf . '_tagen';
        }

        return null;
    }
}
