<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // Bezug zu einer MHD-Charge (nullable — nicht alle Bewegungen sind MHD-pflichtig)
            $table->unsignedBigInteger('mhd_batch_id')->nullable()->after('reference_id');
            $table->foreign('mhd_batch_id')->references('id')->on('product_mhd_batches')->onDelete('set null');

            // Mitarbeiter der die Bewegung verursacht hat (z.B. Einräumen)
            $table->unsignedBigInteger('employee_id')->nullable()->after('created_by_user_id');

            $table->index('mhd_batch_id');
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['mhd_batch_id']);
            $table->dropIndex(['mhd_batch_id']);
            $table->dropIndex(['employee_id']);
            $table->dropColumn(['mhd_batch_id', 'employee_id']);
        });
    }
};
