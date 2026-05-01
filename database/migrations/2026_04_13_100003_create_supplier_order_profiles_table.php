<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bestellprofil pro Lieferant.
     *
     * Ein Lieferant kann mehrere Profile haben (z.B. Portal für Normalbestellung,
     * E-Mail-CSV für Sonderbestellung). Genau eines ist als Standard markiert.
     */
    public function up(): void
    {
        Schema::create('supplier_order_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('supplier_id');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');

            $table->string('name', 100);                       // z.B. "Standard Portal", "CSV-Bestellung"
            $table->boolean('ist_standard')->default(false);   // Genau eines pro Lieferant sollte true sein

            // Bestellkanal
            $table->enum('kanal', [
                'portal',
                'email_pdf',
                'email_csv',
                'email_xml',
                'upload_datei',
                'fallback_freitext',
            ])->default('email_pdf');

            // E-Mail-Konfiguration (für email_* Kanäle)
            $table->string('empfaenger_email', 255)->nullable();
            $table->string('cc_email', 255)->nullable();
            $table->string('betreff_vorlage', 500)->nullable();  // Platzhalter: {{datum}}, {{po_nummer}}, {{lieferant}}
            $table->text('text_vorlage')->nullable();

            // Dateiformat-Konfiguration (für CSV/XML)
            $table->string('dateiformat', 20)->nullable();      // z.B. "csv", "xml", "xlsx"
            $table->string('trennzeichen', 5)->nullable();      // z.B. ";", ","
            $table->boolean('mit_kopfzeile')->default(true);
            // Feldreihenfolge und Pflichtfelder als JSON-Array
            // z.B. ["lieferanten_artnr", "bezeichnung", "menge", "einheit", "preis"]
            $table->json('feldreihenfolge')->nullable();
            $table->json('pflichtfelder')->nullable();

            // Portal-URL falls kanal = portal
            $table->string('portal_url', 500)->nullable();

            // Lieferantenspezifische Kundennummer (unsere Kundennummer beim Lieferanten)
            $table->string('kunden_nr_beim_lieferanten', 50)->nullable();

            $table->boolean('aktiv')->default(true);
            $table->timestamps();

            $table->index(['supplier_id', 'ist_standard']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_order_profiles');
    }
};
