<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Move all Schorle products from Limonade (id=9) to Schorle (id=8)
        DB::table('products')
            ->where('category_id', 9)
            ->where('produktname', 'like', '%schorl%')
            ->update(['category_id' => 8]);
    }

    public function down(): void
    {
        // Only move back what came from Limonade (heuristic: all Schorle products in id=8)
        // Note: original 6 Schorle products were already there before this migration
        DB::table('products')
            ->where('category_id', 8)
            ->where('produktname', 'like', '%schorl%')
            ->update(['category_id' => 9]);
    }
};
