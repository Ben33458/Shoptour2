<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Konfigurierbare Parser-/Mapping-Definition für eingehende Lieferantendokumente.
     *
     * Statt hart verdrahteten Parser-Klassen pro Lieferant wird die Mapping-Logik
     * hier als JSON-Konfiguration gespeichert und kann über ein Admin-UI angepasst werden.
     * Beispieldateien können hochgeladen werden, um Feldmappings zu definieren.
     */
    public function up(): void
    {
        Schema::create('supplier_document_parsers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('supplier_id');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');

            $table->string('name', 100);                   // z.B. "Lieferschein-Parser", "Rechnungs-Parser"
            $table->enum('dokument_typ', [
                'lieferschein',
                'rechnung',
                'bestellbestaetigung',
                'sonstig',
            ])->default('lieferschein');

            $table->enum('parser_typ', [
                'csv',
                'xml',
                'pdf_text',
                'pdf_layout',
                'email_body',
            ])->default('csv');

            // Pfad zur hochgeladenen Beispieldatei (Storage-Pfad)
            $table->string('beispiel_datei_pfad', 500)->nullable();
            $table->string('beispiel_datei_typ', 50)->nullable();  // z.B. "text/csv"

            // Feld-Mapping: Quellspalte → interne Bedeutung
            // z.B. {"0": "lieferanten_artnr", "1": "bezeichnung", "3": "menge", "5": "preis_netto"}
            $table->json('feld_mapping')->nullable();

            // Erkennungsregeln: Kriterien, um ein Dokument diesem Parser zuzuordnen
            // z.B. {"dateiname_contains": "LS-", "header_zeile": "Lieferschein-Nr"}
            $table->json('erkennungsregeln')->nullable();

            // CSV-spezifisch
            $table->string('trennzeichen', 5)->nullable();
            $table->boolean('hat_kopfzeile')->default(true);
            $table->unsignedSmallInteger('daten_ab_zeile')->default(2);

            // Konfidenz-Schwelle: Unter diesem Wert → Prüfliste statt Auto-Zuordnung
            $table->decimal('konfidenz_schwelle', 5, 2)->default(0.80);

            $table->boolean('aktiv')->default(true);
            $table->timestamps();

            $table->index(['supplier_id', 'dokument_typ']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_document_parsers');
    }
};
