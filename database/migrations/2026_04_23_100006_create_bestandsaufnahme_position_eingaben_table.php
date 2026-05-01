<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Detail-Eingaben je VPE pro Position.
     * Ermöglicht mehrere Gebindetypen pro Artikel in einer Zählung.
     */
    public function up(): void
    {
        Schema::create('bestandsaufnahme_position_eingaben', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();

            $table->unsignedBigInteger('position_id');
            $table->foreign('position_id')
                ->references('id')->on('bestandsaufnahme_positionen')->onDelete('cascade');

            $table->unsignedBigInteger('verpackungseinheit_id')->nullable();
            $table->foreign('verpackungseinheit_id')
                ->references('id')->on('artikel_verpackungseinheiten')->onDelete('set null');

            // Menge wie eingegeben (VPE-Stücke)
            $table->decimal('menge_vpe', 14, 3)->default(0);
            // Umrechnungsfaktor zum Zeitpunkt der Eingabe (snapshot)
            $table->decimal('faktor_basiseinheit', 10, 3)->default(1);
            // Ergebnis in Basiseinheit
            $table->decimal('menge_basiseinheit', 14, 3)->default(0);

            $table->timestamps();

            $table->index('position_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bestandsaufnahme_position_eingaben');
    }
};
