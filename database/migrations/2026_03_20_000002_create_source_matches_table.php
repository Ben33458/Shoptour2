<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the source_matches table.
 *
 * Stores every confirmed match between a local entity (customer / supplier / product)
 * and its counterpart in an external system (Ninox, JTL-WaWi, Lexoffice).
 *
 * A snapshot of the external record at the time of matching is stored in
 * source_snapshot so that later diffs can detect when external data has changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_matches', function (Blueprint $table): void {
            $table->bigIncrements('id');

            // What kind of local entity this match belongs to
            $table->string('entity_type');   // 'customer' | 'supplier' | 'product'
            $table->unsignedBigInteger('local_id'); // customers.id / suppliers.id / products.id

            // Which external system and which record therein
            $table->string('source');        // 'ninox' | 'wawi' | 'lexoffice'
            $table->string('source_id');     // ninox_id / kKunde / kArtikel / lexoffice_contact_id

            // Match lifecycle
            $table->string('status')->default('auto'); // 'auto' | 'confirmed' | 'ignored'
            $table->unsignedBigInteger('matched_by')->nullable(); // users.id; NULL = auto-matched
            $table->foreign('matched_by')->references('id')->on('users')->nullOnDelete();

            // Snapshot of external data at the time of matching
            $table->json('source_snapshot')->nullable();
            // Fields that differed between local and external at match time
            $table->json('diff_at_match')->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            // Prevent duplicate matches for the same entity+source combination
            $table->unique(['entity_type', 'local_id', 'source'], 'source_matches_entity_source_unique');

            // Fast lookup by entity
            $table->index(['entity_type', 'local_id'], 'source_matches_entity_local_idx');

            // Fast lookup by source record
            $table->index(['source', 'source_id'], 'source_matches_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_matches');
    }
};
