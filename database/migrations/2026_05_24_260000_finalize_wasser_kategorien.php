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

        // 1. New subcategories
        $geschmackId = DB::table('categories')->insertGetId([
            'name'       => 'Wasser mit Geschmack',
            'parent_id'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $heilwasserId = DB::table('categories')->insertGetId([
            'name'       => 'Heilwasser',
            'parent_id'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Move remaining products from main Wasser (id=1)

        DB::table('products')
            ->where('category_id', 1)
            ->where(function ($q) use ($geschmackId) {
                $q->orWhere('produktname', 'like', '%himbeere%')   // Eli Himbeere x2
                  ->orWhere('produktname', 'like', '%fitzelchen%') // Hassia Fitzelchen
                  ->orWhere('produktname', 'like', '%limette%');   // Rhön Limette
            })
            ->update(['category_id' => $geschmackId]);

        DB::table('products')
            ->where('category_id', 1)
            ->where('produktname', 'like', '%hirschquelle%')
            ->update(['category_id' => $heilwasserId]);

        DB::table('products')
            ->where('category_id', 1)
            ->where(function ($q) {
                $q->orWhere('produktname', 'like', '%pellegrino%')   // San Pellegrino
                  ->orWhere('produktname', 'like', '%sodenthaler%'); // Sodenthaler Gourmet
            })
            ->update(['category_id' => 10]); // Mineralwasser Sprudel

        DB::table('products')
            ->where('category_id', 1)
            ->where(function ($q) {
                $q->orWhere('produktname', 'like', '%black forest%') // Black Forest x2
                  ->orWhere('produktname', 'like', '%leonhard%');    // St.Leonhard x3
            })
            ->update(['category_id' => 12]); // Mineralwasser still

        // 3. Pull aromatised products back out of Sprudel
        DB::table('products')
            ->where('category_id', 10)
            ->where('produktname', 'like', '% plus %') // Odenwald plus Lemon/Orange, Eli plus Pfirsich
            ->update(['category_id' => $geschmackId]);

        // 4. Deactivate discontinued product
        DB::table('products')
            ->where('produktname', 'like', '%kaiser friedrich%')
            ->update(['active' => false]);
    }

    public function down(): void
    {
        foreach (['Wasser mit Geschmack', 'Heilwasser'] as $name) {
            $cat = DB::table('categories')->where('name', $name)->first();
            if ($cat) {
                DB::table('products')->where('category_id', $cat->id)->update(['category_id' => 1]);
                DB::table('categories')->where('id', $cat->id)->delete();
            }
        }
        DB::table('products')
            ->where('produktname', 'like', '%kaiser friedrich%')
            ->update(['active' => true]);
    }
};
