<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Zentrale Dokumententabelle für alle Dokumenttypen.
     *
     * Speichert Lieferscheine, Rechnungen, Bestell-PDFs, E-Mail-Anhänge,
     * Fotos von handschriftlichen Lieferscheinen, Palettenfotos und sonstige Belege.
     *
     * Duplikate werden über datei_hash erkannt, aber nicht automatisch gelöscht.
     * Die Zuordnung zu Lieferanten / Bestellungen / Wareneingängen ist nullable —
     * unsichere Treffer landen in einer Prüfliste (zuordnungs_status = 'pruefliste').
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();

            // Dokumenttyp
            $table->enum('typ', [
                'lieferschein',
                'rechnung',
                'bestell_pdf',
                'bestell_csv',
                'email_anhang',
                'foto_lieferschein',
                'foto_palette',
                'foto_sonstig',
                'sonstig',
            ])->default('sonstig');

            // Quelle
            $table->enum('quelle', [
                'email_eingang',
                'manuell_upload',
                'system_generiert',
                'fahrer_foto',
                'scanner',
            ])->default('manuell_upload');

            // Datei
            $table->string('dateiname', 255)->nullable();
            $table->string('pfad', 1000)->nullable();          // Storage-Pfad
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('datei_groesse')->nullable();   // Bytes
            $table->char('datei_hash', 64)->nullable();        // SHA-256

            // OCR / Erkennungstext (für spätere Suche und Parser)
            $table->longText('ocr_text')->nullable();

            // Erkannte Zuordnung (nullable — wird durch Regelwerk oder manuell gesetzt)
            $table->unsignedBigInteger('erkannter_lieferant_id')->nullable();
            $table->foreign('erkannter_lieferant_id')->references('id')->on('suppliers')->onDelete('set null');

            $table->unsignedBigInteger('erkannte_bestellung_id')->nullable();
            $table->foreign('erkannte_bestellung_id')->references('id')->on('purchase_orders')->onDelete('set null');

            $table->unsignedBigInteger('erkannter_wareneingang_id')->nullable();
            // FK auf goods_receipts wird nach Anlage der Tabelle via separater Migration gesetzt

            // Lieferant-externe Belegnummer (z.B. Lieferschein-Nr. des Lieferanten)
            $table->string('externe_belegnummer', 100)->nullable();
            $table->date('belegdatum')->nullable();

            // Konfidenz des Erkennungsalgorithmus (0.0–1.0)
            $table->decimal('erkennungs_konfidenz', 5, 2)->nullable();

            // Verwendeter Parser
            $table->unsignedBigInteger('verwendeter_parser_id')->nullable();
            $table->foreign('verwendeter_parser_id')->references('id')->on('supplier_document_parsers')->onDelete('set null');

            // Dubletten-Status
            $table->enum('dubletten_status', [
                'unbekannt',
                'original',
                'duplikat',
                'unklar',
            ])->default('unbekannt');
            $table->unsignedBigInteger('duplikat_von_document_id')->nullable();

            // Zuordnungs-Status
            $table->enum('zuordnungs_status', [
                'nicht_zugeordnet',
                'auto_zugeordnet',
                'manuell_zugeordnet',
                'pruefliste',       // Zuordnung unsicher, muss manuell geprüft werden
                'ignoriert',
            ])->default('nicht_zugeordnet');

            // Wer hat hochgeladen / zugeordnet
            $table->unsignedBigInteger('hochgeladen_by_user_id')->nullable();
            $table->unsignedBigInteger('zugeordnet_by_user_id')->nullable();
            $table->timestamp('zugeordnet_at')->nullable();

            // Zusätzliche Metadaten (E-Mail-Absender, Subject, etc.)
            $table->json('metadaten')->nullable();

            $table->timestamps();

            $table->index('company_id');
            $table->index('typ');
            $table->index('quelle');
            $table->index('zuordnungs_status');
            $table->index('dubletten_status');
            $table->index('datei_hash');
            $table->index('belegdatum');
            $table->index('erkannter_lieferant_id');
            $table->index('erkannte_bestellung_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
