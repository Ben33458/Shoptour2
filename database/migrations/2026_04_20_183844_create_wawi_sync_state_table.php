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
        Schema::create('wawi_sync_state', function (Blueprint $table) {
            $table->string('entity', 100)->primary();  // e.g. "dbo.tArtikel"
            $table->dateTime('last_ts')->nullable();    // MAX(updated_at) of last batch
            $table->integer('last_count')->nullable(); // record count of last batch
            $table->dateTime('updated_at');            // when this row was last written
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wawi_sync_state');
    }
};
