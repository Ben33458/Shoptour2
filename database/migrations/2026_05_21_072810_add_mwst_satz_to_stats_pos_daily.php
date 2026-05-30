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
        Schema::table('stats_pos_daily', function (Blueprint $table) {
            $table->decimal('mwst_satz', 5, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('stats_pos_daily', function (Blueprint $table) {
            $table->dropColumn('mwst_satz');
        });
    }
};
