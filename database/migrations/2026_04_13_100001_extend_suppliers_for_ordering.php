<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // Lieferrhythmus (aus ninox_lieferanten übernehmen)
            $table->string('bestelltag', 30)->nullable()->after('active');
            $table->string('liefertag', 30)->nullable()->after('bestelltag');
            $table->time('bestell_schlusszeit')->nullable()->after('liefertag');
            $table->enum('lieferintervall', ['wöchentlich', '14-tägig', 'nach_bedarf'])->nullable()->after('bestell_schlusszeit');

            // Mindestbestellwert in Netto-EK (Milli-Cent)
            $table->unsignedBigInteger('mindestbestellwert_netto_ek_milli')->default(0)->after('lieferintervall');

            // Standard-Kontrollstufe für Wareneingänge dieses Lieferanten
            $table->enum('kontrollstufe_default', [
                'nur_angekommen',
                'summenkontrolle_vpe',
                'summenkontrolle_palette',
                'positionskontrolle',
                'positionskontrolle_mit_mhd',
            ])->default('nur_angekommen')->after('mindestbestellwert_netto_ek_milli');

            $table->index('kontrollstufe_default');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex(['kontrollstufe_default']);
            $table->dropColumn([
                'bestelltag',
                'liefertag',
                'bestell_schlusszeit',
                'lieferintervall',
                'mindestbestellwert_netto_ek_milli',
                'kontrollstufe_default',
            ]);
        });
    }
};
