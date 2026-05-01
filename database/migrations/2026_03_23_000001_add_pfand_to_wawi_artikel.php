<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wawi_artikel', function (Blueprint $table): void {
            $table->decimal('pfand_betrag_netto', 10, 4)->nullable()->after('fEKNetto');
        });

        // Einmalig befüllen aus dem Attribut-Feld
        DB::statement("
            UPDATE wawi_artikel wa
            JOIN wawi_artikel_attribute waa
              ON waa.kArtikel = wa.kArtikel
             AND waa.cAttributName = 'Pfand'
            SET wa.pfand_betrag_netto = waa.fWertDecimal
            WHERE waa.fWertDecimal IS NOT NULL
              AND waa.fWertDecimal > 0
        ");
    }

    public function down(): void
    {
        Schema::table('wawi_artikel', function (Blueprint $table): void {
            $table->dropColumn('pfand_betrag_netto');
        });
    }
};
