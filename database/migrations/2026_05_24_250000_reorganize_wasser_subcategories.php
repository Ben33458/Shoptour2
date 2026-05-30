<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('categories')->where('id', 1)->exists()) {
            return;
        }

        // 1. Create missing "Mineralwasser sanft" subcategory
        $sanftId = DB::table('categories')->insertGetId([
            'name'       => 'Mineralwasser sanft',
            'parent_id'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Sanft FIRST — so "Selters Medium leicht" → sanft (not medium)
        //    Also fixes "Elisabethenquelle Sanft" wrongly placed in Mineralwasser medium (id=2)
        DB::table('products')
            ->whereIn('category_id', [1, 2])
            ->where(function ($q) {
                $q->orWhere('produktname', 'like', '%sanft%')
                  ->orWhere('produktname', 'like', '%leicht%')  // Hassia leicht, Selters Medium leicht
                  ->orWhere('produktname', 'like', '%leise%');  // Viva con Agua leise
            })
            ->update(['category_id' => $sanftId]);

        // 3. Medium
        DB::table('products')
            ->where('category_id', 1)
            ->where('produktname', 'like', '%medium%')
            ->update(['category_id' => 2]);

        // 4. Sprudel / Spritzig / Classic / Klassisch / Original / Laut
        DB::table('products')
            ->where('category_id', 1)
            ->where(function ($q) {
                $q->orWhere('produktname', 'like', '%sprudel%')
                  ->orWhere('produktname', 'like', '%spritzig%')
                  ->orWhere('produktname', 'like', '%klassisch%')
                  ->orWhere('produktname', 'like', '%classic%')
                  ->orWhere('produktname', 'like', '%original%')
                  ->orWhere('produktname', 'like', '% laut%')    // Viva con Agua laut
                  ->orWhere('produktname', 'like', '%sprizz%')   // Eli Bio Sprizz
                  ->orWhere('produktname', 'like', '% plus %');  // Odenwald plus Lemon/Orange
            })
            ->update(['category_id' => 10]);

        // 5. Still — naturell, pur, natur, kleinlaut, and known-still brands
        DB::table('products')
            ->where('category_id', 1)
            ->where(function ($q) {
                $q->orWhere('produktname', 'like', '%still%')
                  ->orWhere('produktname', 'like', '%naturell%')   // incl. naturelle
                  ->orWhere('produktname', 'like', '%pur%')
                  ->orWhere('produktname', 'like', '%natur %')     // Alwa Natur, Gerolsteiner Natur Gastro
                  ->orWhere('produktname', 'like', '%kleinlaut%')  // Viva con Agua kleinlaut = still
                  ->orWhere('produktname', 'like', '%panna%')      // San Pellegrino Acqua Panna
                  ->orWhere('produktname', 'like', '%volvic%')     // Volvic
                  ->orWhere('produktname', 'like', '%evian%');     // Evian
            })
            ->update(['category_id' => 12]);
    }

    public function down(): void
    {
        $sanft = DB::table('categories')->where('name', 'Mineralwasser sanft')->first();
        if ($sanft) {
            DB::table('products')->where('category_id', $sanft->id)->update(['category_id' => 1]);
            DB::table('categories')->where('id', $sanft->id)->delete();
        }
    }
};
