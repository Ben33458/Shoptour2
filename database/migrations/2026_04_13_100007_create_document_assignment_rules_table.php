<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Regelbasierte automatische Dokumentzuordnung.
     *
     * Regeln werden nach `prioritaet` (aufsteigend) abgearbeitet.
     * Bei match wird das Dokument dem Ziel zugeordnet (falls Konfidenz >= Schwelle).
     * Unterschwellige Treffer landen in der Prüfliste.
     *
     * Bedingungen als JSON, z.B.:
     * {
     *   "absender_email_contains": "@hassia.de",
     *   "dateiname_starts_with": "LS-",
     *   "ocr_text_contains": "Lieferschein"
     * }
     *
     * Ziel-Typen:
     * - supplier   → erkannter_lieferant_id setzen
     * - bestellung → erkannte_bestellung_id setzen (über PO-Nummer aus Text)
     * - wareneingang → erkannter_wareneingang_id setzen
     */
    public function up(): void
    {
        Schema::create('document_assignment_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();

            $table->string('name', 200);
            $table->unsignedSmallInteger('prioritaet')->default(100);  // Kleiner = höhere Priorität

            // Bedingungen als JSON-Objekt (AND-verknüpft)
            $table->json('bedingungen');

            // Was wird zugeordnet wenn die Regel matcht
            $table->enum('ziel_typ', ['supplier', 'bestellung', 'wareneingang'])->default('supplier');

            // Fixer Zielwert (z.B. immer supplier_id = 5 wenn Absender = "@hassia.de")
            $table->unsignedBigInteger('ziel_supplier_id')->nullable();
            $table->foreign('ziel_supplier_id')->references('id')->on('suppliers')->onDelete('set null');

            // Konfidenz-Gewicht dieser Regel (0.0–1.0)
            // Mehrere passende Regeln werden gewichtet kombiniert
            $table->decimal('konfidenz_gewicht', 5, 2)->default(1.00);

            $table->boolean('aktiv')->default(true);
            $table->text('notiz')->nullable();

            $table->timestamps();

            $table->index('company_id');
            $table->index(['aktiv', 'prioritaet']);
            $table->index('ziel_typ');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_assignment_rules');
    }
};
