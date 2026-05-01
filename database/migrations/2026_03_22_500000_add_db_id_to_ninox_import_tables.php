<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add db_id to ninox import tables so records from multiple Ninox databases
 * can coexist in ninox_raw_records.
 *
 * Known databases:
 *   fadrrq8poh9b  — alte DB (WaWi, ProduktDB, Tourenplanung …)
 *   tpwd0lln7f65  — kehr DB (Kunden, Mitarbeiter, Veranstaltung … — aktueller)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ninox_import_runs', function (Blueprint $table) {
            $table->string('db_id', 50)->nullable()->after('id')
                  ->comment('Ninox database ID, e.g. tpwd0lln7f65');
        });

        Schema::table('ninox_import_tables', function (Blueprint $table) {
            $table->string('db_id', 50)->nullable()->after('run_id')
                  ->comment('Ninox database ID');
        });

        Schema::table('ninox_raw_records', function (Blueprint $table) {
            $table->string('db_id', 50)->nullable()->after('run_id')
                  ->comment('Ninox database ID');

            // Better lookup index that includes db_id
            $table->index(['db_id', 'table_id', 'ninox_id', 'is_latest'], 'nrr_db_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('ninox_raw_records', function (Blueprint $table) {
            $table->dropIndex('nrr_db_lookup');
            $table->dropColumn('db_id');
        });

        Schema::table('ninox_import_tables', function (Blueprint $table) {
            $table->dropColumn('db_id');
        });

        Schema::table('ninox_import_runs', function (Blueprint $table) {
            $table->dropColumn('db_id');
        });
    }
};
