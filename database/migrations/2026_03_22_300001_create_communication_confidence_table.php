<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_confidence', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('communication_id');
            $table->foreign('communication_id')->references('id')->on('communications')->cascadeOnDelete();

            $table->unsignedTinyInteger('dim_contact')->default(0);  // Absender als Kontakt erkannt?
            $table->unsignedTinyInteger('dim_org')->default(0);      // Kunde/Lieferant gefunden?
            $table->unsignedTinyInteger('dim_role')->default(0);     // Rolle erkannt?
            $table->unsignedTinyInteger('dim_category')->default(0); // Kategorie erkannt?
            $table->unsignedTinyInteger('dim_document')->default(0); // Dokumenttyp erkannt?
            $table->unsignedTinyInteger('dim_action')->default(0);   // Aktion erkannt?
            $table->unsignedTinyInteger('overall')->default(0);      // Gewichteter Durchschnitt

            $table->json('rule_matches')->nullable(); // [{rule_id, rule_name, matched_field, score_added}]

            $table->timestamp('created_at')->useCurrent(); // append-only
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_confidence');
    }
};
