<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('categories')->where('id', 3)->exists()) {
            return;
        }

        // === BIER ===

        // 1. Fix: Krämer Apfelwein Fässer wrongly in Bier → move to Apfelwein (id=19) first
        DB::table('products')
            ->whereIn('id', [162, 175])
            ->where('category_id', 3)
            ->update(['category_id' => 19]);

        // 2. New subcategory "Bier vom Fass" under Bier (id=3)
        $fassBierId = DB::table('categories')->insertGetId([
            'name'       => 'Bier vom Fass',
            'parent_id'  => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Move all Fass beers from Bier main category
        DB::table('products')
            ->where('category_id', 3)
            ->where('produktname', 'like', '%fass%')
            ->update(['category_id' => $fassBierId]);

        // === SAFT & NEKTAR ===

        // 4. New subcategories under Saft & Nektar (id=16)
        $apfelsaftId = DB::table('categories')->insertGetId([
            'name'       => 'Apfelsaft',
            'parent_id'  => 16,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orangeId = DB::table('categories')->insertGetId([
            'name'       => 'Orange & Citrus',
            'parent_id'  => 16,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fruchtsaefteId = DB::table('categories')->insertGetId([
            'name'       => 'Fruchtsäfte & Nektar',
            'parent_id'  => 16,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. Apfelsaft
        DB::table('products')
            ->where('category_id', 16)
            ->where(function ($q) {
                $q->orWhere('produktname', 'like', '%apfelsaft%')
                  ->orWhere('produktname', 'like', '%winterapfel%')
                  ->orWhere('produktname', 'like', '%boskoop%')
                  ->orWhere('produktname', 'like', '%jonagold%')
                  ->orWhere('produktname', 'like', '%gutenmorgensaft%')
                  ->orWhere('produktname', 'like', '%süßer%')
                  ->orWhere('produktname', 'like', '%bio apfel%')
                  ->orWhere('produktname', 'like', '%bio streuobst%');
            })
            ->update(['category_id' => $apfelsaftId]);

        // 6. Orange & Citrus
        DB::table('products')
            ->where('category_id', 16)
            ->where(function ($q) {
                $q->orWhere('produktname', 'like', '%orangensaft%')
                  ->orWhere('produktname', 'like', '%orange-maracuja%')
                  ->orWhere('produktname', 'like', '%grapefruit%')
                  ->orWhere('produktname', 'like', '%sanft wie seide orange%')
                  ->orWhere('produktname', 'like', '%premium orange%');
            })
            ->update(['category_id' => $orangeId]);

        // 7. All remaining from Saft & Nektar → Fruchtsäfte & Nektar
        DB::table('products')
            ->where('category_id', 16)
            ->update(['category_id' => $fruchtsaefteId]);
    }

    public function down(): void
    {
        // Bier vom Fass
        $fassBier = DB::table('categories')->where('name', 'Bier vom Fass')->where('parent_id', 3)->first();
        if ($fassBier) {
            DB::table('products')->where('category_id', $fassBier->id)->update(['category_id' => 3]);
            DB::table('categories')->where('id', $fassBier->id)->delete();
        }
        // Restore Krämer Apfelwein Fässer to Bier
        DB::table('products')->whereIn('id', [162, 175])->update(['category_id' => 3]);

        // Saft & Nektar subcategories
        foreach (['Apfelsaft', 'Orange & Citrus', 'Fruchtsäfte & Nektar'] as $name) {
            $cat = DB::table('categories')->where('name', $name)->where('parent_id', 16)->first();
            if ($cat) {
                DB::table('products')->where('category_id', $cat->id)->update(['category_id' => 16]);
                DB::table('categories')->where('id', $cat->id)->delete();
            }
        }
    }
};
