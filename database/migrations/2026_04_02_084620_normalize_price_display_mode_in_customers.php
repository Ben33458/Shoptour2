<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Normalize English values ('gross'/'net') to German canonical values ('brutto'/'netto')
        DB::table('customers')->where('price_display_mode', 'gross')->update(['price_display_mode' => 'brutto']);
        DB::table('customers')->where('price_display_mode', 'net')->update(['price_display_mode' => 'netto']);

        // Fix column default
        Schema::table('customers', function (Blueprint $table) {
            $table->string('price_display_mode')->default('brutto')->change();
        });
    }

    public function down(): void
    {
        DB::table('customers')->where('price_display_mode', 'brutto')->update(['price_display_mode' => 'gross']);
        DB::table('customers')->where('price_display_mode', 'netto')->update(['price_display_mode' => 'net']);

        Schema::table('customers', function (Blueprint $table) {
            $table->string('price_display_mode')->default('gross')->change();
        });
    }
};
