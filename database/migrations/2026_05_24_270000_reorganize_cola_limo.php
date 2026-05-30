<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Requires parent category id=7 ("Cola / Limo / Schorle") to exist — skip in empty test DB
        if (! DB::table('categories')->where('id', 7)->exists()) {
            return;
        }

        // 1. New subcategories under "Cola / Limo / Schorle" (id=7)
        $colaId = DB::table('categories')->insertGetId([
            'name'       => 'Cola',
            'parent_id'  => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $energyId = DB::table('categories')->insertGetId([
            'name'       => 'Energy & Isotonic',
            'parent_id'  => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tonicId = DB::table('categories')->insertGetId([
            'name'       => 'Tonic & Mixer',
            'parent_id'  => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $craftId = DB::table('categories')->insertGetId([
            'name'       => 'Craft & Spezial',
            'parent_id'  => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Move from Limonade (id=9) — ORDER MATTERS

        // Craft & Spezial first (catches Club Mate, Mio Mio Mate, Thomas Henry Mate before Cola step)
        DB::table('products')
            ->where('category_id', 9)
            ->where(function ($q) {
                $q->orWhere('produktname', 'like', '%wostok%')
                  ->orWhere('produktname', 'like', '%bionade%')
                  ->orWhere('produktname', 'like', '%lemonaid%')
                  ->orWhere('produktname', 'like', '%charitea%')
                  ->orWhere('produktname', 'like', '%trade islands%')
                  ->orWhere('produktname', 'like', '%kräuterbraut%')
                  ->orWhere('produktname', 'like', '%elephant bay%')
                  ->orWhere('produktname', 'like', '%balis%')
                  ->orWhere('produktname', 'like', '%club mate%')
                  ->orWhere('produktname', 'like', '%mio mio mate%')
                  ->orWhere('produktname', 'like', '%thomas henry mate%')
                  ->orWhere('produktname', 'like', '%piranja eistee%')
                  ->orWhere('produktname', 'like', '%fuze%')
                  ->orWhere('produktname', 'like', '%holi limonade%')
                  ->orWhere('produktname', 'like', '%burkhardt%')
                  ->orWhere('produktname', 'like', '%now fresh%');
            })
            ->update(['category_id' => $craftId]);

        // Cola (Mate variants already moved)
        DB::table('products')
            ->where('category_id', 9)
            ->where(function ($q) {
                $q->orWhere('produktname', 'like', '%cola%')
                  ->orWhere('produktname', 'like', '%kola%')
                  ->orWhere('produktname', 'like', '%mezzo mix%')
                  ->orWhere('produktname', 'like', '%spezi%')
                  ->orWhere('produktname', 'like', '%pepsi%');
            })
            ->update(['category_id' => $colaId]);

        // Energy & Isotonic
        DB::table('products')
            ->where('category_id', 9)
            ->where(function ($q) {
                $q->orWhere('produktname', 'like', '%red bull%')
                  ->orWhere('produktname', 'like', '%flora power%')
                  ->orWhere('produktname', 'like', '%isofit%')
                  ->orWhere('produktname', 'like', '%odenwald quelle iso%')
                  ->orWhere('produktname', 'like', '%vivaris sport%')
                  ->orWhere('produktname', 'like', '%sport-fit%')
                  ->orWhere('produktname', 'like', '%bernadett ace%')
                  ->orWhere('produktname', 'like', '%vitaminquelle%')
                  ->orWhere('produktname', 'like', '%koffein%')
                  ->orWhere('produktname', 'like', '%guarana%');
            })
            ->update(['category_id' => $energyId]);

        // Tonic & Mixer (Thomas Henry Mate already moved to Craft)
        DB::table('products')
            ->where('category_id', 9)
            ->where(function ($q) {
                $q->orWhere('produktname', 'like', '%schweppes%')
                  ->orWhere('produktname', 'like', '%fever tree%')
                  ->orWhere('produktname', 'like', '%thomas henry%')
                  ->orWhere('produktname', 'like', '%bundaberg%');
            })
            ->update(['category_id' => $tonicId]);
    }

    public function down(): void
    {
        foreach (['Cola', 'Energy & Isotonic', 'Tonic & Mixer', 'Craft & Spezial'] as $name) {
            $cat = DB::table('categories')->where('name', $name)->where('parent_id', 7)->first();
            if ($cat) {
                DB::table('products')->where('category_id', $cat->id)->update(['category_id' => 9]);
                DB::table('categories')->where('id', $cat->id)->delete();
            }
        }
    }
};
