<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('notes');
        });

        // Backfill: existing rows keep insertion order
        DB::statement('UPDATE purchase_order_items SET sort_order = id');
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
