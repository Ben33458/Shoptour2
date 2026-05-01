<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacation_requests', function (Blueprint $table) {
            $table->decimal('days_requested', 5, 1)->default(1)->change();
        });

        Schema::table('vacation_balances', function (Blueprint $table) {
            $table->decimal('total_days', 5, 1)->default(0)->change();
            $table->decimal('used_days', 5, 1)->default(0)->change();
            $table->decimal('carried_over', 5, 1)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('vacation_requests', function (Blueprint $table) {
            $table->unsignedSmallInteger('days_requested')->default(1)->change();
        });

        Schema::table('vacation_balances', function (Blueprint $table) {
            $table->unsignedSmallInteger('total_days')->default(0)->change();
            $table->unsignedSmallInteger('used_days')->default(0)->change();
            $table->smallInteger('carried_over')->default(0)->change();
        });
    }
};
