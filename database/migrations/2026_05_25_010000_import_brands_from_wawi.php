<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wawi_dbo_tartikel') || ! Schema::hasTable('wawi_dbo_thersteller')) {
            return;
        }

        // 1. Collect distinct manufacturers actually used on linked products
        $herstellerRows = DB::table('wawi_dbo_tartikel')
            ->join('products', 'products.wawi_artikel_id', '=', 'wawi_dbo_tartikel.kArtikel')
            ->join('wawi_dbo_thersteller', 'wawi_dbo_thersteller.kHersteller', '=', 'wawi_dbo_tartikel.kHersteller')
            ->where('wawi_dbo_tartikel.kHersteller', '>', 0)
            ->distinct()
            ->get(['wawi_dbo_thersteller.kHersteller', 'wawi_dbo_thersteller.cName'])
            ->sortBy('cName');

        // 2. Insert into brands, keep mapping kHersteller → new brand id
        $herstellerToBrandId = [];
        $now = now();
        foreach ($herstellerRows as $row) {
            $brandId = DB::table('brands')->insertGetId([
                'name'       => $row->cName,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $herstellerToBrandId[(int) $row->kHersteller] = $brandId;
        }

        // 3. Set brand_id on products via wawi join
        foreach ($herstellerToBrandId as $kHersteller => $brandId) {
            $productIds = DB::table('products')
                ->join('wawi_dbo_tartikel', 'wawi_dbo_tartikel.kArtikel', '=', 'products.wawi_artikel_id')
                ->where('wawi_dbo_tartikel.kHersteller', $kHersteller)
                ->pluck('products.id');

            if ($productIds->isEmpty()) {
                continue;
            }

            DB::table('products')
                ->whereIn('id', $productIds)
                ->update(['brand_id' => $brandId]);
        }
    }

    public function down(): void
    {
        DB::table('products')->update(['brand_id' => null]);
        DB::table('brands')->truncate();
    }
};
