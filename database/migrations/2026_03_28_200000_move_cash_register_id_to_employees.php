<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add cash_register_id to employees
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('cash_register_id')->nullable()->after('user_id');
            $table->foreign('cash_register_id')->references('id')->on('cash_registers')->nullOnDelete();
        });

        // 2. Remove cash_register_id from users
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['cash_register_id']);
            $table->dropColumn('cash_register_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('cash_register_id')->nullable()->after('role');
            $table->foreign('cash_register_id')->references('id')->on('cash_registers')->nullOnDelete();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['cash_register_id']);
            $table->dropColumn('cash_register_id');
        });
    }
};
