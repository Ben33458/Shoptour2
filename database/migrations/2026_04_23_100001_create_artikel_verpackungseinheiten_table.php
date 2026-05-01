<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Verpackungseinheiten (VPE) je Basisartikel.
     *
     * Jede VPE definiert einen Umrechnungsfaktor zur Basiseinheit (Einzelflasche = 1).
     * Beispiel: "24er Kasten 0,33l" → faktor_basiseinheit = 24.
     *
     * Getrennt von `gebinde`, das pfand-/depositbezogen ist.
     */
    public function up(): void
    {
        Schema::create('artikel_verpackungseinheiten', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');

            $table->string('bezeichnung', 100);              // "24er Kasten 0,33l"
            $table->decimal('faktor_basiseinheit', 10, 3);  // 24.0 → Basiseinheit
            $table->boolean('ist_bestellbar')->default(true);
            $table->boolean('ist_zaehlbar')->default(true);
            $table->boolean('aktiv')->default(true);
            $table->unsignedSmallInteger('sortierung')->default(0);

            $table->timestamps();

            $table->index(['product_id', 'aktiv']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artikel_verpackungseinheiten');
    }
};
