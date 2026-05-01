<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add is_mhd_writeoff flag to stats_pos_daily.
 *
 * WaWi customer K3475 (kKunde = 618) is a virtual bookkeeping customer
 * used to dispose of products with expired MHD (best-before date).
 * POS transactions on this customer are NOT real sales and must be
 * excluded from all sales statistics.
 *
 * After migration: run `php artisan stats:refresh-pos --full` to rebuild.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stats_pos_daily', function (Blueprint $table) {
            $table->tinyInteger('is_mhd_writeoff')->default(0)->after('is_leergut');
            $table->index(['is_mhd_writeoff', 'bon_date'], 'idx_mhd_writeoff_date');
        });
    }

    public function down(): void
    {
        Schema::table('stats_pos_daily', function (Blueprint $table) {
            $table->dropIndex('idx_mhd_writeoff_date');
            $table->dropColumn('is_mhd_writeoff');
        });
    }
};
