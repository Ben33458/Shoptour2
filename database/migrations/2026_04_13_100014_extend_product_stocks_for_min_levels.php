<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mindestbestände pro Produkt + Lagerort aufteilen.
     *
     * Ergänzt product_stocks um:
     * - min_bestand_markt   (Mindestbestand für den Markt-Bereich)
     * - min_bestand_lager   (Mindestbestand für das Lager)
     * - min_bestand_gesamt  (Gesamtmindestbestand über alle Lagerorte)
     *
     * Der bisherige reorder_point bleibt erhalten (er kann als Alias
     * für min_bestand_gesamt betrachtet werden).
     */
    public function up(): void
    {
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->decimal('min_bestand_markt', 14, 3)->default(0)->after('reorder_point');
            $table->decimal('min_bestand_lager', 14, 3)->default(0)->after('min_bestand_markt');
            $table->decimal('min_bestand_gesamt', 14, 3)->default(0)->after('min_bestand_lager');
        });
    }

    public function down(): void
    {
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->dropColumn(['min_bestand_markt', 'min_bestand_lager', 'min_bestand_gesamt']);
        });
    }
};
