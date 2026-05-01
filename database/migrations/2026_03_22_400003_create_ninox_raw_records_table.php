<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ninox_raw_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('ninox_import_runs')->cascadeOnDelete();
            $table->foreignId('import_table_id')->constrained('ninox_import_tables')->cascadeOnDelete();
            $table->string('table_id', 50)->comment('Ninox table ID');
            $table->string('ninox_id', 50)->comment('Record ID in Ninox');
            $table->json('record_data')->comment('Raw JSON from Ninox API');
            $table->boolean('is_latest')->default(true)
                  ->comment('true = most recent import for this ninox_id+table_id');
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->timestamps();

            // For deduplication and lookups
            $table->index(['table_id', 'ninox_id', 'is_latest'], 'nrr_lookup');
            $table->index(['run_id', 'table_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ninox_raw_records');
    }
};
