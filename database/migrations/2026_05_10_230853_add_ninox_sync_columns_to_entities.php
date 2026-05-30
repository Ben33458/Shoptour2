<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('ninox_pushed_at')->nullable()->after('ninox_kunden_id');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->timestamp('ninox_pushed_at')->nullable()->after('ninox_source_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('ninox_pushed_at')->nullable()->after('ninox_artikel_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('ninox_id', 50)->nullable()->after('distance_km');
            $table->timestamp('ninox_pushed_at')->nullable()->after('ninox_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('ninox_pushed_at');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('ninox_pushed_at');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('ninox_pushed_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['ninox_id', 'ninox_pushed_at']);
        });
    }
};
