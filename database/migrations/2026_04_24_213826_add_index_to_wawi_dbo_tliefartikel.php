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
        DB::statement('ALTER TABLE wawi_dbo_tliefartikel ADD INDEX wawi_tliefartikel_artikel_idx (tArtikel_kArtikel(32))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE wawi_dbo_tliefartikel DROP INDEX wawi_tliefartikel_artikel_idx');
    }
};
