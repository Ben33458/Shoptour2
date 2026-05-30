<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_import_links', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('entity_type', 100); // App\Models\Events\EventOccurrence etc.
            $table->unsignedBigInteger('entity_id');
            $table->string('source_system', 50); // ninox, jtl, gmail, manual
            $table->string('source_table', 100)->nullable();
            $table->string('source_record_id', 100);
            $table->string('external_number', 100)->nullable();
            $table->string('raw_payload_hash', 64)->nullable();
            $table->timestamp('imported_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->unique(['entity_type', 'entity_id', 'source_system', 'source_record_id'], 'import_link_unique');
            $table->index(['source_system', 'source_record_id'], 'import_link_source');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_import_links');
    }
};
