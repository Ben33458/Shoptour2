<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Leergutrücknahmen.
     *
     * Basiert auf der Lernbasis aus ninoxalt_-Daten (Kolabri-Ninox).
     * Logik wird hier modernisiert und operativ geführt.
     *
     * Erfassung:
     * - Paletten
     * - Standard-Kästen (Normkästen)
     * - Seltene/Sonderkästen (mit Beschreibung)
     * - Kontrollzählung (zweite Person prüft)
     * - Zwei Fotos (Vorher/Nachher oder Übersicht/Detail)
     *
     * MHD-Erfassung gehört NICHT zur Leergutrücknahme.
     */
    public function up(): void
    {
        Schema::create('leergut_returns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();

            // Woher kommt das Leergut (Kunde, Lieferant, Tour)
            $table->string('rueckgabe_von_typ', 50)->nullable();   // 'kunde', 'fahrer', 'sonstig'
            $table->unsignedBigInteger('rueckgabe_von_id')->nullable(); // Morphe Referenz (customer_id etc.)
            $table->string('rueckgabe_von_bezeichnung', 200)->nullable(); // Freitext-Fallback

            // Zugehörige Tour (optional)
            $table->unsignedBigInteger('tour_id')->nullable();
            $table->foreign('tour_id')->references('id')->on('tours')->onDelete('set null');

            // Lagerort des Empfangs
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('set null');

            $table->enum('status', [
                'erfasst',
                'kontrolliert',  // Kontrollzählung durchgeführt
                'gebucht',
                'storniert',
            ])->default('erfasst');

            // Paletten
            $table->decimal('paletten_anzahl', 8, 2)->default(0);

            // Gesamtanzahl Kästen (alle Typen summiert, für Schnellerfassung)
            $table->integer('kaesten_gesamt')->default(0);

            // Fotos (zwei Fotos für Dokumentation)
            $table->string('foto_1_pfad', 500)->nullable();
            $table->string('foto_2_pfad', 500)->nullable();

            // Kontrollzählung
            $table->boolean('kontrollzaehlung_durchgefuehrt')->default(false);
            $table->unsignedBigInteger('kontrolliert_by_employee_id')->nullable();
            $table->timestamp('kontrolliert_at')->nullable();

            // Wer hat erfasst
            $table->unsignedBigInteger('erfasst_by_employee_id')->nullable();
            $table->unsignedBigInteger('erfasst_by_user_id')->nullable();

            $table->text('notiz')->nullable();
            $table->timestamps();

            $table->index('company_id');
            $table->index(['rueckgabe_von_typ', 'rueckgabe_von_id']);
            $table->index('tour_id');
            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('leergut_return_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('leergut_return_id');
            $table->foreign('leergut_return_id')->references('id')->on('leergut_returns')->onDelete('cascade');

            // Pfand-Artikel (product_leergut ist die operative Leergut-Tabelle)
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');

            // Ob es sich um einen seltenen/Sonder-Kasten handelt
            $table->boolean('ist_sonderkasten')->default(false);

            $table->string('bezeichnung', 200)->nullable();  // z.B. "Schmucker Kasten 20×0,5l"
            $table->string('kastentyp', 100)->nullable();    // Frei oder aus Stammdaten

            $table->integer('anzahl')->default(0);

            // Kontrollzählung-Abweichung
            $table->integer('anzahl_kontrollzaehlung')->nullable();
            $table->text('abweichungs_notiz')->nullable();

            $table->timestamps();

            $table->index('leergut_return_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leergut_return_items');
        Schema::dropIfExists('leergut_returns');
    }
};
