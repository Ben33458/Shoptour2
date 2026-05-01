<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aussortierungen / Bruch / rabattierte MHD-Ware.
     *
     * Mitarbeiter oder Fahrer können Ware aus dem Bestand nehmen und begründen.
     * Der Bezug zum echten Produkt bleibt immer erhalten (kein "sonstiger Verlust").
     *
     * LS POS kann rabattierte MHD-Ware intern nicht sauber abbilden —
     * Shoptour2 führt die saubere Wahrheit intern weiter.
     */
    public function up(): void
    {
        Schema::create('product_write_offs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();

            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');

            $table->unsignedBigInteger('warehouse_id');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');

            // MHD-Charge (wenn bekannt)
            $table->unsignedBigInteger('mhd_batch_id')->nullable();
            $table->foreign('mhd_batch_id')->references('id')->on('product_mhd_batches')->onDelete('set null');

            $table->date('mhd')->nullable();  // Redundant für schnellen Zugriff

            // Menge in kleinster Einheit (Flaschen)
            $table->decimal('menge', 14, 3);

            // Typ der Aussortierung
            $table->enum('typ', [
                'bruch',            // Physisch kaputt
                'abgelaufen',       // MHD überschritten
                'mhd_rabatt',       // Wird rabattiert verkauft (interne Wahrheit)
                'sonstig',
            ]);

            // Status (für mhd_rabatt: noch verfügbar oder verbraucht)
            $table->enum('status', [
                'aktiv',            // Ware ist aussortiert / im Rabatt-Bestand
                'verbraucht',       // Rabattiert verkauft oder entsorgt
            ])->default('aktiv');

            // Ursache
            $table->enum('ursache', [
                'zu_viel_bestellt',
                'stammkunde_abgesprungen',
                'veranstaltung_ausgefallen',
                'fifo_ignoriert',
                'ware_vergessen_falsch_verraeumt',
                'zu_viel_kommissionsrueckgabe',
                'unbekannt',
                'sonstig',
            ])->default('unbekannt');

            // Wer hat aussortiert
            $table->unsignedBigInteger('erfasst_by_employee_id')->nullable();
            $table->unsignedBigInteger('erfasst_by_user_id')->nullable();

            // Erzeugte Lagerbewegung
            $table->unsignedBigInteger('stock_movement_id')->nullable();
            $table->foreign('stock_movement_id')->references('id')->on('stock_movements')->onDelete('set null');

            $table->text('notiz')->nullable();
            $table->timestamps();

            $table->index('company_id');
            $table->index(['product_id', 'typ']);
            $table->index('warehouse_id');
            $table->index('mhd_batch_id');
            $table->index('status');
            $table->index('ursache');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_write_offs');
    }
};
