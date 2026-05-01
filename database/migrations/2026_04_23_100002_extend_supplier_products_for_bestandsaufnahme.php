<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Erweitert supplier_products um:
     * - ist_standard_lieferant: genau ein Standard-Lieferant je Artikel
     * - verpackungseinheit_id:  optionale VPE-Zuordnung
     * - bestellhinweis:         freitextlicher Hinweis für Bestellung
     */
    public function up(): void
    {
        Schema::table('supplier_products', function (Blueprint $table) {
            $table->boolean('ist_standard_lieferant')->default(false)->after('active');
            $table->unsignedBigInteger('verpackungseinheit_id')->nullable()->after('ist_standard_lieferant');
            $table->foreign('verpackungseinheit_id')
                ->references('id')->on('artikel_verpackungseinheiten')->onDelete('set null');
            $table->text('bestellhinweis')->nullable()->after('verpackungseinheit_id');

            $table->index('ist_standard_lieferant');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_products', function (Blueprint $table) {
            $table->dropForeign(['verpackungseinheit_id']);
            $table->dropIndex(['ist_standard_lieferant']);
            $table->dropColumn(['ist_standard_lieferant', 'verpackungseinheit_id', 'bestellhinweis']);
        });
    }
};
