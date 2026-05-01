<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 30);                      // 'wawi' | 'ninox' | 'manual'
            $table->string('entity', 60)->nullable();           // z.B. 'artikel', 'kunden' (nur bei WAWI)
            $table->string('status', 20)->default('completed'); // running|completed|failed|partial
            $table->unsignedInteger('records_processed')->default(0);
            $table->unsignedInteger('records_skipped')->default(0);
            $table->text('error_message')->nullable();
            $table->string('triggered_by', 100)->nullable();   // IP (WAWI) | 'cli' (Ninox)
            $table->datetime('started_at');
            $table->datetime('finished_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['source', 'started_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
    }
};
