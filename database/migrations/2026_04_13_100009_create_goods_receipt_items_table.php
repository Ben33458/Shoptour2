<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wareneingangspositionen.
     *
     * Pro Position wird die bestellte vs. gelieferte Menge festgehalten.
     * Bei Abweichungen wird ein Grund angegeben.
     * MHD wird hier erfasst (nicht bei Leergutrücknahme!).
     */
    public function up(): void
    {
        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('goods_receipt_id');
            $table->foreign('goods_receipt_id')->references('id')->on('goods_receipts')->onDelete('cascade');

            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');

            // Bezug zur Bestellposition (optional)
            $table->unsignedBigInteger('purchase_order_item_id')->nullable();
            $table->foreign('purchase_order_item_id')->references('id')->on('purchase_order_items')->onDelete('set null');

            // Mengen (auf kleinster Einheit: Flasche)
            $table->decimal('bestellte_menge', 14, 3)->default(0);
            $table->decimal('gelieferte_menge', 14, 3)->default(0);

            // Abweichungsgrund (wenn gelieferte_menge != bestellte_menge)
            $table->enum('abweichungs_grund', [
                'kein',
                'fehlmenge',        // Weniger geliefert
                'übermenge',        // Mehr geliefert
                'falscher_artikel', // Anderer Artikel geliefert
                'beschaedigt',      // Ware beschädigt
                'sonstig',
            ])->default('kein');
            $table->text('abweichungs_notiz')->nullable();

            // MHD-Erfassung (Pflicht-Level kommt aus supplier_products.mhd_erfassung)
            $table->enum('mhd_erfassung_modus', ['nie', 'optional', 'empfohlen', 'pflicht'])->default('optional');
            // Wird genau ein MHD erfasst → direktes Feld; mehrere MHDs → über product_mhd_batches
            $table->date('mhd')->nullable();

            // Ob der Bestand durch diesen Item bereits eingebucht wurde
            $table->boolean('eingebucht')->default(false);
            $table->timestamp('eingebucht_at')->nullable();

            $table->timestamps();

            $table->index('goods_receipt_id');
            $table->index('product_id');
            $table->index('eingebucht');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
    }
};
