<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wareneingänge — eigenständige Tabelle mit eigenem Lebenszyklus.
     *
     * Trennung von purchase_orders ist bewusst: Eine Lieferung kann Teillieferung sein,
     * hat eigene Kontrollstufen, Mitarbeiter und Zeitstempel.
     *
     * Status-Flow:
     *   angekuendigt → in_kontrolle → gebucht | abgebrochen
     *
     * Kontrollstufen (aufsteigend):
     *   nur_angekommen           → Wareneingang direkt als gebucht markieren
     *   summenkontrolle_vpe      → Gesamtzahl VPE prüfen
     *   summenkontrolle_palette  → Palettenanzahl prüfen
     *   positionskontrolle       → Jede Position prüfen
     *   positionskontrolle_mit_mhd → Jede Position + MHD bei relevanten Produkten
     */
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();

            // Bezug zur Bestellung (optional — es kann auch Anlieferungen ohne PO geben)
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('set null');

            $table->unsignedBigInteger('supplier_id');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');

            $table->unsignedBigInteger('warehouse_id');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');

            // Zugehöriger Lieferschein (Dokument)
            $table->unsignedBigInteger('document_id')->nullable();
            $table->foreign('document_id')->references('id')->on('documents')->onDelete('set null');

            // Lieferschein-Nr. des Lieferanten (für schnelle Referenz)
            $table->string('lieferschein_nr', 100)->nullable();

            $table->enum('status', [
                'angekuendigt',    // Bestellung erwartet, noch nicht angekommen
                'in_kontrolle',    // Angekommen, wird geprüft
                'gebucht',         // Wareneingang vollständig gebucht
                'abgebrochen',
            ])->default('angekuendigt');

            // Kontrollstufe (null = Lieferanten-Default verwenden)
            $table->enum('kontrollstufe', [
                'nur_angekommen',
                'summenkontrolle_vpe',
                'summenkontrolle_palette',
                'positionskontrolle',
                'positionskontrolle_mit_mhd',
            ])->default('nur_angekommen');

            // Zeitstempel des Prozesses
            $table->timestamp('arrived_at')->nullable();        // Zeitpunkt Ankunft
            $table->timestamp('kontrolle_beginn_at')->nullable();
            $table->timestamp('gebucht_at')->nullable();

            // Wer hat den Wareneingang gebucht
            $table->unsignedBigInteger('gebucht_by_user_id')->nullable();
            $table->unsignedBigInteger('gebucht_by_employee_id')->nullable();

            // Palettenanzahl (für Summenkontrolle Palette)
            $table->decimal('paletten_anzahl_erwartet', 8, 2)->nullable();
            $table->decimal('paletten_anzahl_geliefert', 8, 2)->nullable();

            $table->text('notiz')->nullable();

            $table->timestamps();

            $table->index('company_id');
            $table->index('supplier_id');
            $table->index('purchase_order_id');
            $table->index('warehouse_id');
            $table->index('status');
            $table->index('arrived_at');
        });

        // Jetzt FK von documents auf goods_receipts nachtragen
        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('erkannter_wareneingang_id')
                ->references('id')->on('goods_receipts')->onDelete('set null');
            $table->index('erkannter_wareneingang_id');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['erkannter_wareneingang_id']);
            $table->dropIndex(['erkannter_wareneingang_id']);
        });
        Schema::dropIfExists('goods_receipts');
    }
};
