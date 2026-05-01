<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reconcile_feedback_log', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50);          // 'product', 'customer', 'supplier'
            $table->string('source', 50);               // 'ninox', 'wawi'
            $table->string('source_id', 100);           // ninox_id / external key

            $table->enum('action', ['confirmed', 'ignored', 'bulk_confirmed', 'bulk_ignored']);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Source-side data (snapshot at time of action)
            $table->string('source_name', 500)->nullable();   // artikelname
            $table->string('source_artnr', 100)->nullable();  // artnummer
            $table->string('source_ean', 100)->nullable();    // EAN

            // Match-side data
            $table->string('target_id', 100)->nullable();     // wawi kArtikel
            $table->string('target_name', 500)->nullable();   // wawi cName
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('match_method', 50)->nullable();   // 'ean', 'fuzzy_gebinde', …
            $table->boolean('was_auto_match')->default(false); // previously status=auto?

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index(['entity_type', 'source', 'action']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconcile_feedback_log');
    }
};
