<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop FK first, then unique index, then make nullable
        DB::statement('ALTER TABLE shift_reports DROP FOREIGN KEY shift_reports_shift_id_foreign');
        DB::statement('ALTER TABLE shift_reports DROP INDEX shift_reports_shift_id_unique');
        DB::statement('ALTER TABLE shift_reports MODIFY shift_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE shift_reports ADD CONSTRAINT shift_reports_shift_id_foreign FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE CASCADE');

        Schema::table('shift_reports', function (Blueprint $table) {
            // One report per employee per day
            $table->date('report_date')->nullable()->after('employee_id');
        });

        // Backfill report_date from the linked shift's planned_start
        DB::statement("
            UPDATE shift_reports sr
            INNER JOIN shifts s ON s.id = sr.shift_id
            SET sr.report_date = DATE(s.planned_start)
            WHERE sr.report_date IS NULL
        ");

        // Set report_date = today for any orphaned reports without a shift
        DB::statement("
            UPDATE shift_reports SET report_date = CURDATE() WHERE report_date IS NULL
        ");

        Schema::table('shift_reports', function (Blueprint $table) {
            $table->unique(['employee_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::table('shift_reports', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'report_date']);
            $table->dropColumn('report_date');
            $table->foreignId('shift_id')->nullable(false)->change();
            $table->unique(['shift_id']);
        });
    }
};
