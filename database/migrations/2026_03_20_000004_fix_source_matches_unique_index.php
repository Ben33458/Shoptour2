<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixes the source_matches unique index.
 *
 * Original: UNIQUE(entity_type, local_id, source) — prevents one local entity from
 * being matched to two external records of the same source. But this breaks when
 * multiple rows have local_id = NULL (e.g. ignored products before a local product exists).
 *
 * New design:
 *   - UNIQUE(entity_type, source, source_id) — each external record can only be matched once
 *   - INDEX(entity_type, local_id, source) — fast lookup by local entity (non-unique)
 *   - local_id becomes nullable (NULL = not yet linked to a local entity)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_matches', function (Blueprint $table): void {
            // Drop old unique constraint
            $table->dropUnique('source_matches_entity_source_unique');

            // Drop old non-unique index on (source, source_id) — we'll make it unique
            $table->dropIndex('source_matches_source_idx');

            // Make local_id nullable (NULL = pending/no local entity yet)
            $table->unsignedBigInteger('local_id')->nullable()->change();

            // New: UNIQUE per external record
            $table->unique(['entity_type', 'source', 'source_id'], 'source_matches_ext_unique');

            // Keep fast lookup by local entity (non-unique)
            $table->index(['entity_type', 'local_id', 'source'], 'source_matches_local_idx');
        });
    }

    public function down(): void
    {
        Schema::table('source_matches', function (Blueprint $table): void {
            $table->dropUnique('source_matches_ext_unique');
            $table->dropIndex('source_matches_local_idx');

            $table->unsignedBigInteger('local_id')->nullable(false)->default(0)->change();

            $table->unique(['entity_type', 'local_id', 'source'], 'source_matches_entity_source_unique');
            $table->index(['source', 'source_id'], 'source_matches_source_idx');
        });
    }
};
