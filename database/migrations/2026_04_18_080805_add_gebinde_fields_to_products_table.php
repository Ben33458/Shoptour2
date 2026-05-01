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
            $table->unsignedSmallInteger('gebinde_units')->nullable()->after('volume_ml')
                  ->comment('Anzahl Einheiten im Gebinde (aus produktname geparst, z.B. 20 für 20x0,5 l)');
            $table->unsignedSmallInteger('unit_volume_ml')->nullable()->after('gebinde_units')
                  ->comment('Inhalt pro Einheit in ml (aus produktname geparst, z.B. 500 für 0,5 l)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['gebinde_units', 'unit_volume_ml']);
        });
    }
};
