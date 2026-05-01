<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_products', function (Blueprint $table) {
            // Wie der Lieferant den Artikel intern bezeichnet
            $table->string('lieferanten_bezeichnung', 200)->nullable()->after('supplier_sku');

            // Gebinde- und Palettenfaktoren (auf Flaschenebene denken, Kisten/Paletten ableiten)
            // gebinde_faktor: Flaschen pro VPE/Kiste (z.B. 12)
            // paletten_faktor: VPE/Kisten pro Palette (z.B. 36)
            $table->decimal('gebinde_faktor', 10, 3)->default(1)->after('pack_size');
            $table->decimal('paletten_faktor', 10, 3)->default(1)->after('gebinde_faktor');

            // MHD-Pflicht pro Produkt-Lieferanten-Kombination
            $table->enum('mhd_erfassung', ['nie', 'optional', 'empfohlen', 'pflicht'])
                ->default('optional')->after('paletten_faktor');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_products', function (Blueprint $table) {
            $table->dropColumn([
                'lieferanten_bezeichnung',
                'gebinde_faktor',
                'paletten_faktor',
                'mhd_erfassung',
            ]);
        });
    }
};
