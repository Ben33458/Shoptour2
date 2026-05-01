<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds ninox_alt_source_id to employees.
 * Stores the ninox_id from the alt Ninox database (fadrrq8poh9b, table_id='D')
 * which holds comprehensive employee data (email, address, birth_date, IBAN, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('ninox_alt_source_id', 20)->nullable()->after('ninox_source_table');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn('ninox_alt_source_id');
        });
    }
};
