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
        Schema::table('products', function (Blueprint $table) {
            // Füllmenge in ml für die gesetzlich vorgeschriebene Grundpreisangabe (PAngV).
            // Beispiel: 500 für 0,5 L, 1980 für 6x0,33 L Träger.
            $table->unsignedInteger('volume_ml')->nullable()->after('sales_unit_note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('volume_ml');
        });
    }
};
