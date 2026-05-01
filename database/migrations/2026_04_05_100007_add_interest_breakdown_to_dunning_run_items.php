<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dunning_run_items', function (Blueprint $table) {
            // Per-invoice interest breakdown for transparent display
            $table->json('interest_breakdown')->nullable()->after('voucher_ids');
        });
    }

    public function down(): void
    {
        Schema::table('dunning_run_items', function (Blueprint $table) {
            $table->dropColumn('interest_breakdown');
        });
    }
};
