<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MHD-Chargen-Tracking pro Produkt, Lagerort und MHD-Datum.
     *
     * Kernprinzip:
     * - Ein Artikel kann gleichzeitig mehrere MHDs haben (verschiedene Chargen)
     * - Das ältere MHD soll zuerst abverkauft werden (FIFO)
     * - Beim Wiederauftauchen eines vermeintlich abverkauften MHDs ist der
     *   komplette Herkunftsweg nachvollziehbar:
     *   → wann angeliefert (goods_receipt_item_id)
     *   → von welchem Lieferanten (über goods_receipt → supplier)
     *   → welcher Mitarbeiter hat verräumt (eingeraeumt_by_employee_id)
     *   → Umlagerungen / Korrekturen (stock_movements.mhd_batch_id)
     *
     * menge gibt den aktuell noch verfügbaren Bestand dieser Charge an.
     * Bestandsbewegungen werden über stock_movements mit mhd_batch_id referenziert.
     */
    public function up(): void
    {
        Schema::create('product_mhd_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();

            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');

            $table->unsignedBigInteger('warehouse_id');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');

            $table->date('mhd');

            // Aktueller Bestand dieser Charge (Flaschenmenge)
            $table->decimal('menge', 14, 3)->default(0);

            // Herkunft
            $table->unsignedBigInteger('goods_receipt_item_id')->nullable();
            $table->foreign('goods_receipt_item_id')->references('id')->on('goods_receipt_items')->onDelete('set null');

            // Wer hat diese Ware verräumt
            $table->unsignedBigInteger('eingeraeumt_by_employee_id')->nullable();
            $table->timestamp('eingeraeumt_at')->nullable();

            // Lagerplatz-Angabe (freitext, für spätere Lagerhaltungsverfeinerung)
            $table->string('lagerplatz', 100)->nullable();

            // Segment: Markt oder Lager (wichtig für Warnlisten)
            $table->enum('segment', ['markt', 'lager', 'sonstig'])->default('lager');

            // Status
            $table->enum('status', [
                'aktiv',
                'abverkauft',   // Menge = 0, aber historisch erhalten
                'ausgebucht',   // Durch Aussortierung / Bruch vollständig entfernt
            ])->default('aktiv');

            // MHD-Risikomarkierung (wird durch Job gesetzt)
            $table->boolean('mhd_warnung_aktiv')->default(false);
            $table->enum('mhd_risiko', ['ok', 'bald_ablaufend', 'abgelaufen', 'kritisch'])->default('ok');

            $table->text('notiz')->nullable();
            $table->timestamps();

            $table->index('company_id');
            $table->index(['product_id', 'warehouse_id', 'mhd']);
            $table->index(['product_id', 'status']);
            $table->index('mhd');
            $table->index('mhd_risiko');
            $table->index('goods_receipt_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_mhd_batches');
    }
};
