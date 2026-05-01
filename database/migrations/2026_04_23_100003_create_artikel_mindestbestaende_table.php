<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lagerbezogene Mindestbestände je Artikel.
     *
     * Separater Wert von product_stocks.reorder_point / min_bestand_*,
     * da hier Import-Herkunft und Konflikte protokolliert werden.
     *
     * Speicherung immer in Basiseinheit.
     */
    public function up(): void
    {
        Schema::create('artikel_mindestbestaende', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();

            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');

            $table->unsignedBigInteger('warehouse_id');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');

            $table->decimal('mindestbestand_basiseinheit', 14, 3)->default(0);

            // Herkunft des Wertes
            $table->enum('quelle', ['manuell', 'import'])->default('manuell');
            $table->string('quelle_datei', 255)->nullable();
            $table->string('quelle_tabellenblatt', 100)->nullable();

            // Konflikt ODS vs. DB
            $table->boolean('konflikt_flag')->default(false);
            $table->json('konflikt_details')->nullable();

            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id']);
            $table->index('konflikt_flag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artikel_mindestbestaende');
    }
};
