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
        Schema::table('rental_price_rules', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('requires_drink_order');
        });
    }

    public function down(): void
    {
        Schema::table('rental_price_rules', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }
};
