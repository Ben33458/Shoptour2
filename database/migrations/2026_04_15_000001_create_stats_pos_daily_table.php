<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materialized summary table for POS statistics.
 *
 * Pre-aggregates wawi_dbo_pos_bon + wawi_dbo_pos_bonposition into daily
 * article-level rows with a proper DATE column so all downstream queries
 * use indexed range scans instead of full-table STR_TO_DATE() scans.
 *
 * Populated by: php artisan stats:refresh-pos [--full] [--days=N]
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stats_pos_daily', function (Blueprint $table) {
            $table->id();
            $table->date('bon_date');
            // For articles with empty artnr (e.g. Pfand/Leergut), artnr is
            // set to the article name by the refresh command so it stays unique.
            $table->string('artnr', 200)->default('');
            $table->string('name', 500)->default('');
            $table->string('warengruppe', 200)->default('');
            $table->tinyInteger('is_pfand')->default(0);
            $table->tinyInteger('is_leergut')->default(0);
            $table->double('menge')->default(0);
            $table->double('umsatz')->default(0);   // SUM(fMenge * fEinzelPreis)
            $table->double('unit_price')->default(0); // MAX(fEinzelPreis) for deposit price display
            $table->timestamps();

            $table->unique(['bon_date', 'artnr'], 'uk_date_artnr');
            $table->index('bon_date', 'idx_bon_date');
            $table->index(['warengruppe', 'bon_date'], 'idx_wg_date');
            $table->index(['is_pfand', 'bon_date'], 'idx_pfand_date');
            $table->index(['is_leergut', 'bon_date'], 'idx_leergut_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stats_pos_daily');
    }
};
