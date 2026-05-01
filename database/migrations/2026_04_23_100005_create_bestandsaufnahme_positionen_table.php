<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bestandsaufnahme_positionen', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();

            $table->unsignedBigInteger('session_id');
            $table->foreign('session_id')->references('id')->on('bestandsaufnahme_sessions')->onDelete('cascade');

            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');

            $table->unsignedBigInteger('warehouse_id');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');

            // Bestand vor der Zählung (Snapshot beim ersten Speichern)
            $table->decimal('letzter_bestand_basiseinheit', 14, 3)->nullable();
            // Gezählter Bestand (Summe aller VPE-Eingaben)
            $table->decimal('gezaehlter_bestand_basiseinheit', 14, 3)->nullable();
            // Differenz = gezaehlt - letzter
            $table->decimal('differenz_basiseinheit', 14, 3)->nullable();

            // MHD-Modus der für diesen Artikel/Lager gilt (snapshot aus mhd_regeln)
            $table->enum('mhd_modus', ['nie', 'optional', 'pflichtig'])->default('nie');

            $table->unsignedBigInteger('gezaehlt_von')->nullable();
            $table->foreign('gezaehlt_von')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('gezaehlt_am')->nullable();

            // Korrekturgrund (null = noch nicht gespeichert / kein Grund nötig)
            $table->string('korrekturgrund', 50)->nullable();
            $table->text('kommentar')->nullable();

            $table->timestamps();

            $table->index(['session_id', 'product_id']);
            $table->index(['product_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bestandsaufnahme_positionen');
    }
};
