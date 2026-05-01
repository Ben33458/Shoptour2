<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wawi_sync_log', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 100);          // z.B. "dbo.tZahlung"
            $table->string('table_name', 64);        // z.B. "wawi_dbo_tzahlung"
            $table->unsignedInteger('records_received')->default(0);
            $table->unsignedInteger('records_upserted')->default(0);
            $table->string('ip', 45)->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['entity', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wawi_sync_log');
    }
};
